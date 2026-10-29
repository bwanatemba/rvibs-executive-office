<?php
$page_title = '90-Day Outcomes';
require_once __DIR__ . '/includes/auth.php';
require_login();
$db = get_db();
$user = current_user();

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'create') {
        $stmt = $db->prepare('INSERT INTO outcomes (title, description, owner_id, measure_of_success, due_date, progress, status, pillar, quarter_label, created_by) VALUES (?,?,?,?,?,?,?,?,?,?)');
        $stmt->execute([
            $_POST['title'], $_POST['description'] ?? null, $_POST['owner_id'] ?: null,
            $_POST['measure_of_success'] ?? null, $_POST['due_date'] ?: null,
            (int)($_POST['progress'] ?? 0), $_POST['status'] ?? 'on_track',
            $_POST['pillar'] ?? null, $_POST['quarter_label'] ?? null, $user['id']
        ]);
        $id = $db->lastInsertId();
        log_action('create', 'outcome', $id, null, $_POST);
        toast_redirect('Outcome created', 'outcomes.php');
    } elseif ($action === 'update') {
        $id = (int)$_POST['id'];
        $stmt = $db->prepare('UPDATE outcomes SET title=?, description=?, owner_id=?, measure_of_success=?, due_date=?, progress=?, status=?, pillar=?, quarter_label=? WHERE id=?');
        $stmt->execute([
            $_POST['title'], $_POST['description'] ?? null, $_POST['owner_id'] ?: null,
            $_POST['measure_of_success'] ?? null, $_POST['due_date'] ?: null,
            (int)($_POST['progress'] ?? 0), $_POST['status'] ?? 'on_track',
            $_POST['pillar'] ?? null, $_POST['quarter_label'] ?? null, $id
        ]);
        log_action('update', 'outcome', $id, null, $_POST);
        toast_redirect('Outcome updated', 'outcomes.php');
    } elseif ($action === 'delete') {
        $id = (int)$_POST['id'];
        $db->prepare('DELETE FROM outcomes WHERE id=?')->execute([$id]);
        log_action('delete', 'outcome', $id);
        toast_redirect('Outcome deleted', 'outcomes.php');
    }
}

$outcomes = $db->query('
    SELECT o.*, u.name as owner_name
    FROM outcomes o LEFT JOIN users u ON o.owner_id = u.id
    ORDER BY o.status, o.due_date
')->fetchAll();
$users_list = get_users_list();
$csrf = csrf_token();
require_once __DIR__ . '/includes/header.php';
?>

<div class="section-header">
    <div>
        <h2 class="section-title">90-Day Outcomes</h2>
        <p class="section-sub">Three agreed outcomes with accountable owners and measurable progress</p>
    </div>
    <button class="btn btn-primary" onclick="openModal('modalOutcome')">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        New Outcome
    </button>
</div>

<?php if (empty($outcomes)): ?>
    <div class="card">
        <div class="card-body">
            <div class="empty-state">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="6"/><circle cx="12" cy="12" r="2"/></svg>
                <h3>No outcomes defined</h3>
                <p>Define your three agreed 90-day outcomes. Each should have an accountable owner, a measure of success, and a due date.</p>
                <button class="btn btn-primary mt-4" onclick="openModal('modalOutcome')">Add First Outcome</button>
            </div>
        </div>
    </div>
<?php else: ?>
    <div class="grid grid-3">
        <?php foreach ($outcomes as $o):
            $days = days_until($o['due_date']);
        ?>
            <div class="card">
                <div class="card-body">
                    <div class="flex items-center justify-between mb-3">
                        <span class="badge <?= status_badge($o['status']) ?>"><?= status_label($o['status']) ?></span>
                        <div class="flex gap-1">
                            <button class="btn-ghost btn-sm" onclick="editOutcome(<?= htmlspecialchars(json_encode($o), ENT_QUOTES) ?>)" title="Edit">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                            </button>
                            <form method="POST" style="display:inline" data-delete-confirm="Delete this outcome?" onsubmit="return confirmDeleteForm(this, event)">
                                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= $o['id'] ?>">
                                <button class="btn-ghost btn-sm" title="Delete">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                </button>
                            </form>
                        </div>
                    </div>
                    <h3 class="fw-600 text-sm mb-3" style="font-family: var(--font-heading)"><?= e($o['title']) ?></h3>
                    <?php if ($o['description']): ?>
                        <p class="text-xs text-2 mb-3"><?= e($o['description']) ?></p>
                    <?php endif; ?>
                    <div class="flex items-center gap-2 mb-3">
                        <div class="progress flex-1">
                            <div class="progress-bar <?= $o['progress'] >= 100 ? 'success' : ($o['status'] === 'at_risk' ? 'warning' : ($o['status'] === 'off_track' ? 'danger' : '')) ?>" style="width: <?= $o['progress'] ?>%"></div>
                        </div>
                        <span class="text-xs fw-600"><?= $o['progress'] ?>%</span>
                    </div>
                    <div class="text-xs text-2 mb-2"><strong>Owner:</strong> <?= e($o['owner_name'] ?? 'Unassigned') ?></div>
                    <div class="text-xs text-2 mb-2"><strong>Success measure:</strong> <?= e($o['measure_of_success'] ?? '—') ?></div>
                    <div class="text-xs text-3">
                        <strong>Due:</strong> <?= e(fmt_date($o['due_date'])) ?>
                        <?php if ($days !== null && $o['status'] !== 'complete'): ?>
                            <?php if ($days < 0): ?> · <span style="color: var(--c-danger)"><?= abs($days) ?> days overdue</span>
                            <?php elseif ($days <= 7): ?> · <span style="color: var(--c-warning)"><?= $days ?> days left</span>
                            <?php else: ?> · <?= $days ?> days left
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- Add/Edit Modal -->
<div class="modal-overlay" id="modalOutcome">
    <div class="modal modal-lg">
        <div class="modal-header">
            <h2 class="modal-title" id="outcomeModalTitle">New 90-Day Outcome</h2>
            <button class="modal-close" onclick="closeModal('modalOutcome')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <input type="hidden" name="action" id="outcomeAction" value="create">
            <input type="hidden" name="id" id="outcomeId">
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
                <div class="form-group">
                    <label class="form-label">Measure of Success</label>
                    <textarea name="measure_of_success" class="form-control" rows="2" placeholder="How will we know this is achieved?"></textarea>
                </div>
                <div class="form-row-3">
                    <div class="form-group">
                        <label class="form-label">Progress (%)</label>
                        <input type="number" name="progress" class="form-control" min="0" max="100" value="0">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-control">
                            <option value="on_track">On Track</option>
                            <option value="at_risk">At Risk</option>
                            <option value="off_track">Off Track</option>
                            <option value="complete">Complete</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Pillar</label>
                        <input type="text" name="pillar" class="form-control" placeholder="e.g. Academic">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Quarter Label</label>
                    <input type="text" name="quarter_label" class="form-control" placeholder="e.g. Q1 2026-2027" value="<?= e(get_setting('current_quarter')) ?>">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('modalOutcome')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Outcome</button>
            </div>
        </form>
    </div>
</div>

<script>
function editOutcome(data) {
    document.getElementById('outcomeModalTitle').textContent = 'Edit Outcome';
    document.getElementById('outcomeAction').value = 'update';
    document.getElementById('outcomeId').value = data.id;
    const form = document.querySelector('#modalOutcome form');
    form.querySelector('[name="title"]').value = data.title || '';
    form.querySelector('[name="description"]').value = data.description || '';
    form.querySelector('[name="owner_id"]').value = data.owner_id || '';
    form.querySelector('[name="due_date"]').value = (data.due_date || '').split(' ')[0];
    form.querySelector('[name="measure_of_success"]').value = data.measure_of_success || '';
    form.querySelector('[name="progress"]').value = data.progress || 0;
    form.querySelector('[name="status"]').value = data.status || 'on_track';
    form.querySelector('[name="pillar"]').value = data.pillar || '';
    form.querySelector('[name="quarter_label"]').value = data.quarter_label || '';
    openModal('modalOutcome');
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
