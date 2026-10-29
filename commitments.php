<?php
$page_title = 'Commitments';
require_once __DIR__ . '/includes/auth.php';
require_login();
$db = get_db();
$user = current_user();
$userId = $user['id'];

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'create') {
        $due = $_POST['due_date'] ?: null;
        $stmt = $db->prepare('INSERT INTO commitments (title, description, owner_id, due_date, original_due_date, status, blocker, evidence, priority, linked_outcome_id, created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?)');
        $stmt->execute([
            $_POST['title'], $_POST['description'] ?? null, $_POST['owner_id'] ?: null,
            $due, $due, $_POST['status'] ?? 'open', $_POST['blocker'] ?? null,
            $_POST['evidence'] ?? null, $_POST['priority'] ?? 'medium',
            $_POST['linked_outcome_id'] ?: null, $user['id']
        ]);
        $id = $db->lastInsertId();
        log_action('create', 'commitment', $id, null, $_POST);
        toast_redirect('Commitment created', 'commitments.php');
    } elseif ($action === 'update') {
        $id = (int)$_POST['id'];
        // Check if deadline changed
        $old = $db->prepare('SELECT due_date FROM commitments WHERE id=?');
        $old->execute([$id]);
        $oldData = $old->fetch();
        $newDue = $_POST['due_date'] ?: null;
        if ($oldData && $oldData['due_date'] != $newDue && !empty($_POST['deadline_change_reason'])) {
            $stmt = $db->prepare('INSERT INTO commitment_deadline_changes (commitment_id, old_deadline, new_deadline, reason, changed_by) VALUES (?,?,?,?,?)');
            $stmt->execute([$id, $oldData['due_date'], $newDue, $_POST['deadline_change_reason'], $user['id']]);
        }
        $stmt = $db->prepare('UPDATE commitments SET title=?, description=?, owner_id=?, due_date=?, status=?, blocker=?, evidence=?, priority=?, linked_outcome_id=? WHERE id=?');
        $stmt->execute([
            $_POST['title'], $_POST['description'] ?? null, $_POST['owner_id'] ?: null,
            $newDue, $_POST['status'] ?? 'open', $_POST['blocker'] ?? null,
            $_POST['evidence'] ?? null, $_POST['priority'] ?? 'medium',
            $_POST['linked_outcome_id'] ?: null, $id
        ]);
        if ($_POST['status'] === 'complete') {
            $db->prepare('UPDATE commitments SET completed_at=NOW() WHERE id=? AND completed_at IS NULL')->execute([$id]);
        }
        log_action('update', 'commitment', $id, null, $_POST);
        toast_redirect('Commitment updated', 'commitments.php');
    } elseif ($action === 'status') {
        $id = (int)$_POST['id'];
        $status = $_POST['status'];
        $stmt = $db->prepare('UPDATE commitments SET status=? WHERE id=?');
        $stmt->execute([$status, $id]);
        if ($status === 'complete') {
            $db->prepare('UPDATE commitments SET completed_at=NOW() WHERE id=? AND completed_at IS NULL')->execute([$id]);
        }
        log_action('status_change', 'commitment', $id, null, ['status' => $status]);
        toast_redirect('Status updated', 'commitments.php');
    } elseif ($action === 'delete') {
        $id = (int)$_POST['id'];
        $db->prepare('DELETE FROM commitments WHERE id=?')->execute([$id]);
        log_action('delete', 'commitment', $id);
        toast_redirect('Commitment deleted', 'commitments.php');
    }
}

// Filters
$filterMine = ($_GET['filter'] ?? '') === 'mine';
$filterStatus = $_GET['status'] ?? '';

$where = '1=1';
$params = [];
if ($filterMine) { $where .= ' AND c.owner_id = ?'; $params[] = $userId; }
if ($filterStatus) { $where .= ' AND c.status = ?'; $params[] = $filterStatus; }

$stmt = $db->prepare("
    SELECT c.*, u.name as owner_name, o.title as outcome_title
    FROM commitments c
    LEFT JOIN users u ON c.owner_id = u.id
    LEFT JOIN outcomes o ON c.linked_outcome_id = o.id
    WHERE $where
    ORDER BY FIELD(c.status,'overdue','blocked','open','in_progress','complete'), c.due_date ASC
");
$stmt->execute($params);
$commitments = $stmt->fetchAll();

$outcomes = $db->query('SELECT id, title FROM outcomes ORDER BY title')->fetchAll();
$users_list = get_users_list();
$csrf = csrf_token();

// Get deadline changes for detail view
$detailId = (int)($_GET['id'] ?? 0);
$detailCommitment = null;
$deadlineChanges = [];
if ($detailId) {
    $stmt = $db->prepare('SELECT c.*, u.name as owner_name FROM commitments c LEFT JOIN users u ON c.owner_id = u.id WHERE c.id=?');
    $stmt->execute([$detailId]);
    $detailCommitment = $stmt->fetch();
    if ($detailCommitment) {
        $stmt = $db->prepare('SELECT dc.*, u.name as changed_by_name FROM commitment_deadline_changes dc LEFT JOIN users u ON dc.changed_by = u.id WHERE dc.commitment_id=? ORDER BY dc.changed_at DESC');
        $stmt->execute([$detailId]);
        $deadlineChanges = $stmt->fetchAll();
    }
}
require_once __DIR__ . '/includes/header.php';
?>

<div class="section-header">
    <div>
        <h2 class="section-title">Commitments</h2>
        <p class="section-sub">Record commitments with owner, deadline, status, blocker and evidence of completion</p>
    </div>
    <button class="btn btn-primary" onclick="openModal('modalCommitment')">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        New Commitment
    </button>
</div>

<div class="filter-bar">
    <a href="commitments.php" class="btn btn-sm <?= !$filterMine && !$filterStatus ? 'btn-primary' : 'btn-outline' ?>">All</a>
    <a href="commitments.php?filter=mine" class="btn btn-sm <?= $filterMine ? 'btn-primary' : 'btn-outline' ?>">Mine</a>
    <span class="filter-spacer"></span>
    <?php foreach (['open','in_progress','blocked','overdue','complete'] as $s): ?>
        <a href="commitments.php?status=<?= $s ?>" class="badge <?= $filterStatus === $s ? status_badge($s) : 'badge-neutral' ?>"><?= status_label($s) ?></a>
    <?php endforeach; ?>
</div>

<?php if ($detailCommitment): ?>
<!-- Detail View -->
<div class="card mb-6">
    <div class="card-header">
        <h3 class="card-title">Commitment Detail</h3>
        <a href="commitments.php" class="btn btn-sm btn-outline">← Back to list</a>
    </div>
    <div class="card-body">
        <div class="grid cols-2-1 mb-4">
            <div>
                <h3 class="fw-600" style="font-family:var(--font-heading); margin-bottom:0.5rem;"><?= e($detailCommitment['title']) ?></h3>
                <?php if ($detailCommitment['description']): ?>
                    <p class="text-sm text-2 mb-3"><?= e($detailCommitment['description']) ?></p>
                <?php endif; ?>
                <div class="grid grid-2 gap-2">
                    <div><span class="text-xs text-3">Owner:</span> <span class="text-sm fw-600"><?= e($detailCommitment['owner_name'] ?? 'Unassigned') ?></span></div>
                    <div><span class="text-xs text-3">Priority:</span> <span class="badge <?= status_badge($detailCommitment['priority']) ?>"><?= status_label($detailCommitment['priority']) ?></span></div>
                    <div><span class="text-xs text-3">Due date:</span> <span class="text-sm fw-600"><?= e(fmt_date($detailCommitment['due_date'])) ?></span></div>
                    <div><span class="text-xs text-3">Original deadline:</span> <span class="text-sm fw-600"><?= e(fmt_date($detailCommitment['original_due_date'])) ?></span></div>
                    <div><span class="text-xs text-3">Status:</span> <span class="badge <?= status_badge($detailCommitment['status']) ?>"><?= status_label($detailCommitment['status']) ?></span></div>
                    <div><span class="text-xs text-3">Completed:</span> <span class="text-sm fw-600"><?= e(fmt_datetime($detailCommitment['completed_at'])) ?></span></div>
                </div>
                <?php if ($detailCommitment['blocker']): ?>
                    <div class="alert alert-danger mt-3"><strong>Blocker:</strong> <?= e($detailCommitment['blocker']) ?></div>
                <?php endif; ?>
                <?php if ($detailCommitment['evidence']): ?>
                    <div class="alert alert-success mt-3"><strong>Evidence of completion:</strong> <?= e($detailCommitment['evidence']) ?></div>
                <?php endif; ?>
            </div>
            <div>
                <div class="flex gap-2 mb-3">
                    <button class="btn btn-outline btn-sm" onclick="editCommitment(<?= htmlspecialchars(json_encode($detailCommitment), ENT_QUOTES) ?>)">Edit</button>
                    <form method="POST" style="display:inline" data-delete-confirm="Delete this commitment?" onsubmit="return confirmDeleteForm(this, event)">
                        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= $detailCommitment['id'] ?>">
                        <button class="btn btn-danger btn-sm">Delete</button>
                    </form>
                </div>
                <h4 class="text-sm fw-600 mb-2">Quick Status Update</h4>
                <form method="POST" class="flex gap-2">
                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                    <input type="hidden" name="action" value="status">
                    <input type="hidden" name="id" value="<?= $detailCommitment['id'] ?>">
                    <select name="status" class="form-control">
                        <option value="open" <?= $detailCommitment['status']==='open'?'selected':'' ?>>Open</option>
                        <option value="in_progress" <?= $detailCommitment['status']==='in_progress'?'selected':'' ?>>In Progress</option>
                        <option value="blocked" <?= $detailCommitment['status']==='blocked'?'selected':'' ?>>Blocked</option>
                        <option value="complete" <?= $detailCommitment['status']==='complete'?'selected':'' ?>>Complete</option>
                    </select>
                    <button class="btn btn-primary btn-sm">Update</button>
                </form>
            </div>
        </div>

        <?php if (!empty($deadlineChanges)): ?>
        <div class="mt-4">
            <h4 class="text-sm fw-600 mb-3">Deadline Change History</h4>
            <div class="timeline">
                <?php foreach ($deadlineChanges as $dc): ?>
                    <div class="timeline-item">
                        <div class="timeline-dot <?= $dc === reset($deadlineChanges) ? 'accent' : '' ?>"></div>
                        <div class="timeline-content">
                            <div class="timeline-title"><?= e(fmt_date($dc['old_deadline'])) ?> → <?= e(fmt_date($dc['new_deadline'])) ?></div>
                            <div class="timeline-date">Changed by <?= e($dc['changed_by_name'] ?? 'Unknown') ?> on <?= e(fmt_datetime($dc['changed_at'])) ?></div>
                            <div class="timeline-body">Reason: <?= e($dc['reason']) ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<!-- Commitments Table -->
<div class="card">
    <div class="table-wrap" style="border: none;">
        <?php if (empty($commitments)): ?>
            <div class="empty-state">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
                <h3>No commitments yet</h3>
                <p>Record commitments with owners, deadlines, and evidence of completion. The Director's commitments are subject to the same accountability as everyone else's.</p>
            </div>
        <?php else: ?>
            <table class="data-table">
                <thead>
                    <tr><th>Commitment</th><th>Owner</th><th>Due</th><th>Priority</th><th>Status</th><th>Blocker</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($commitments as $c):
                        $days = days_until($c['due_date']);
                    ?>
                    <tr>
                        <td>
                            <a href="commitments.php?id=<?= $c['id'] ?>" class="cell-link"><?= e($c['title']) ?></a>
                            <?php if ($c['outcome_title']): ?><div class="cell-sub">↳ <?= e($c['outcome_title']) ?></div><?php endif; ?>
                        </td>
                        <td class="text-sm"><?= e($c['owner_name'] ?? 'Unassigned') ?></td>
                        <td class="text-sm">
                            <?= e(fmt_date($c['due_date'])) ?>
                            <?php if ($c['due_date'] && $c['status'] !== 'complete'):
                                if ($days !== null && $days < 0): echo '<div style="color:var(--c-danger)">' . abs($days) . 'd overdue</div>';
                                elseif ($days !== null && $days <= 3): echo '<div style="color:var(--c-warning)">' . $days . 'd left</div>'; endif;
                            endif; ?>
                            <?php if ($c['original_due_date'] != $c['due_date']): ?>
                                <div class="cell-sub" title="Original deadline">was <?= e(fmt_date($c['original_due_date'])) ?></div>
                            <?php endif; ?>
                        </td>
                        <td><span class="badge <?= status_badge($c['priority']) ?>"><?= status_label($c['priority']) ?></span></td>
                        <td><span class="badge <?= status_badge($c['status']) ?>"><?= status_label($c['status']) ?></span></td>
                        <td class="text-sm text-2 truncate" style="max-width:180px"><?= e($c['blocker'] ?? '—') ?></td>
                        <td>
                            <div class="flex gap-1">
                                <button class="btn-ghost btn-sm" onclick="editCommitment(<?= htmlspecialchars(json_encode($c), ENT_QUOTES) ?>)" title="Edit">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                </button>
                                <form method="POST" style="display:inline" data-delete-confirm="Delete?" onsubmit="return confirmDeleteForm(this, event)">
                                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $c['id'] ?>">
                                    <button class="btn-ghost btn-sm"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<!-- Add/Edit Modal -->
<div class="modal-overlay" id="modalCommitment">
    <div class="modal modal-lg">
        <div class="modal-header">
            <h2 class="modal-title" id="commitmentModalTitle">New Commitment</h2>
            <button class="modal-close" onclick="closeModal('modalCommitment')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <input type="hidden" name="action" id="commitmentAction" value="create">
            <input type="hidden" name="id" id="commitmentId">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Title <span class="req">*</span></label>
                    <input type="text" name="title" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="2"></textarea>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Owner</label>
                        <select name="owner_id" class="form-control">
                            <option value="">Unassigned</option>
                            <?php foreach ($users_list as $u): ?>
                                <option value="<?= $u['id'] ?>"><?= e($u['name']) ?> (<?= e($u['email']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Due Date</label>
                        <input type="date" name="due_date" class="form-control">
                    </div>
                </div>
                <div class="form-row-3">
                    <div class="form-group">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-control">
                            <option value="open">Open</option>
                            <option value="in_progress">In Progress</option>
                            <option value="blocked">Blocked</option>
                            <option value="complete">Complete</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Priority</label>
                        <select name="priority" class="form-control">
                            <option value="low">Low</option>
                            <option value="medium" selected>Medium</option>
                            <option value="high">High</option>
                            <option value="critical">Critical</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Linked Outcome</label>
                        <select name="linked_outcome_id" class="form-control">
                            <option value="">None</option>
                            <?php foreach ($outcomes as $o): ?>
                                <option value="<?= $o['id'] ?>"><?= e($o['title']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-group" id="blockerGroup">
                    <label class="form-label">Blocker (if blocked)</label>
                    <input type="text" name="blocker" class="form-control" placeholder="What is blocking progress?">
                </div>
                <div class="form-group" id="evidenceGroup">
                    <label class="form-label">Evidence of Completion</label>
                    <textarea name="evidence" class="form-control" rows="2" placeholder="What demonstrates this is complete?"></textarea>
                </div>
                <div class="form-group" id="deadlineReasonGroup" style="display:none;">
                    <label class="form-label">Deadline Change Reason</label>
                    <textarea name="deadline_change_reason" class="form-control" rows="2" placeholder="Why is the deadline being changed? (recorded in audit trail)"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('modalCommitment')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save</button>
            </div>
        </form>
    </div>
</div>

<script>
function editCommitment(data) {
    document.getElementById('commitmentModalTitle').textContent = 'Edit Commitment';
    document.getElementById('commitmentAction').value = 'update';
    document.getElementById('commitmentId').value = data.id;
    const form = document.querySelector('#modalCommitment form');
    form.querySelector('[name="title"]').value = data.title || '';
    form.querySelector('[name="description"]').value = data.description || '';
    form.querySelector('[name="owner_id"]').value = data.owner_id || '';
    form.querySelector('[name="due_date"]').value = (data.due_date || '').split(' ')[0];
    form.querySelector('[name="status"]').value = data.status || 'open';
    form.querySelector('[name="priority"]').value = data.priority || 'medium';
    form.querySelector('[name="linked_outcome_id"]').value = data.linked_outcome_id || '';
    form.querySelector('[name="blocker"]').value = data.blocker || '';
    form.querySelector('[name="evidence"]').value = data.evidence || '';
    document.getElementById('deadlineReasonGroup').style.display = 'block';
    openModal('modalCommitment');
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
