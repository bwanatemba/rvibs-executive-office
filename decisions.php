<?php
$page_title = 'Delegated Authority';
require_once __DIR__ . '/includes/auth.php';
require_login();
$db = get_db();
$user = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'create') {
        $stmt = $db->prepare('INSERT INTO decisions (title, description, owner_id, authority_level, delegation_limit, status, requires_director_approval) VALUES (?,?,?,?,?,?,?)');
        $stmt->execute([
            $_POST['title'], $_POST['description'] ?? null, $_POST['owner_id'] ?: null,
            $_POST['authority_level'] ?? null, $_POST['delegation_limit'] ?? null,
            'pending', isset($_POST['requires_director_approval']) ? 1 : 0
        ]);
        $id = $db->lastInsertId();
        log_action('create', 'decision', $id, null, $_POST);
        toast_redirect('Decision record created', 'decisions.php');
    } elseif ($action === 'update') {
        $id = (int)$_POST['id'];
        $stmt = $db->prepare('UPDATE decisions SET title=?, description=?, owner_id=?, authority_level=?, delegation_limit=?, requires_director_approval=? WHERE id=?');
        $stmt->execute([
            $_POST['title'], $_POST['description'] ?? null, $_POST['owner_id'] ?: null,
            $_POST['authority_level'] ?? null, $_POST['delegation_limit'] ?? null,
            isset($_POST['requires_director_approval']) ? 1 : 0, $id
        ]);
        log_action('update', 'decision', $id);
        toast_redirect('Decision updated', 'decisions.php');
    } elseif ($action === 'decide') {
        $id = (int)$_POST['id'];
        $status = $_POST['status'];
        $stmt = $db->prepare('UPDATE decisions SET status=?, decided_by=?, decided_at=NOW(), decision_notes=? WHERE id=?');
        $stmt->execute([$status, $user['id'], $_POST['decision_notes'] ?? null, $id]);
        log_action('decide', 'decision', $id, null, ['status' => $status]);
        if ($status === 'escalated') {
            // Create an escalation
            $dec = $db->prepare('SELECT title, description FROM decisions WHERE id=?');
            $dec->execute([$id]);
            $decData = $dec->fetch();
            $db->prepare('INSERT INTO escalations (title, description, raised_by, priority, linked_item_type, linked_item_id) VALUES (?,?,?, "high","decision",?)')
                ->execute([$decData['title'], $decData['description'], $user['id'], $id]);
        }
        toast_redirect('Decision recorded', 'decisions.php');
    } elseif ($action === 'delete') {
        $id = (int)$_POST['id'];
        $db->prepare('DELETE FROM decisions WHERE id=?')->execute([$id]);
        log_action('delete', 'decision', $id);
        toast_redirect('Decision deleted', 'decisions.php');
    }
}

$filter = $_GET['status'] ?? 'all';
$where = '';
if ($filter && $filter !== 'all') { $where = 'WHERE d.status = ' . $db->quote($filter); }
$decisions = $db->query("
    SELECT d.*, u.name as owner_name, decision_maker.name as decided_by_name
    FROM decisions d LEFT JOIN users u ON d.owner_id = u.id LEFT JOIN users decision_maker ON d.decided_by = decision_maker.id
    $where ORDER BY FIELD(d.status,'pending','escalated','approved','declined'), d.created_at DESC
")->fetchAll();
$users_list = get_users_list();
$csrf = csrf_token();
$canDecide = can('can_approve') || has_role('system_admin', 'chairman_ceo');
require_once __DIR__ . '/includes/header.php';
?>

<div class="section-header">
    <div>
        <h2 class="section-title">Delegated Authority</h2>
        <p class="section-sub">Managers have clear authority to make routine decisions. Material exceptions escalate to the Director.</p>
    </div>
    <button class="btn btn-primary" onclick="openModal('modalDecision')">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Record Decision
    </button>
</div>

<div class="filter-bar">
    <a href="decisions.php" class="btn btn-sm <?= $filter==='all'?'btn-primary':'btn-outline' ?>">All</a>
    <?php foreach (['pending','approved','declined','escalated'] as $s): ?>
        <a href="decisions.php?status=<?= $s ?>" class="badge <?= $filter===$s?status_badge($s):'badge-neutral' ?>"><?= status_label($s) ?></a>
    <?php endforeach; ?>
</div>

<div class="card">
    <div class="table-wrap" style="border:none;">
        <?php if (empty($decisions)): ?>
            <div class="empty-state">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M3 3l7.07 16.97 2.51-7.39 7.39-2.51z"/></svg>
                <h3>No decisions recorded</h3>
                <p>Record decisions with their authority level and delegation limits. Routine decisions stay with managers; material exceptions escalate to the Director.</p>
            </div>
        <?php else: ?>
            <table class="data-table">
                <thead><tr><th>Decision</th><th>Owner</th><th>Authority</th><th>Delegation Limit</th><th>Status</th><th>Decided By</th><th>Actions</th></tr></thead>
                <tbody>
                    <?php foreach ($decisions as $d): ?>
                    <tr>
                        <td>
                            <div class="cell-title"><?= e($d['title']) ?></div>
                            <?php if ($d['description']): ?><div class="cell-sub"><?= e(substr($d['description'], 0, 80)) ?>...</div><?php endif; ?>
                            <?php if ($d['requires_director_approval']): ?><span class="badge badge-accent mt-1">Requires Director</span><?php endif; ?>
                        </td>
                        <td class="text-sm"><?= e($d['owner_name'] ?? 'Unassigned') ?></td>
                        <td class="text-sm"><?= e($d['authority_level'] ?? '—') ?></td>
                        <td class="text-sm"><?= e($d['delegation_limit'] ?? '—') ?></td>
                        <td><span class="badge <?= status_badge($d['status']) ?>"><?= status_label($d['status']) ?></span></td>
                        <td class="text-sm"><?= e($d['decided_by_name'] ?? '—') ?></td>
                        <td>
                            <div class="flex gap-1">
                                <?php if ($canDecide && $d['status'] === 'pending'): ?>
                                    <button class="btn btn-primary btn-sm" onclick="decideDecision(<?= $d['id'] ?>, '<?= e($d['title']) ?>')">Decide</button>
                                <?php endif; ?>
                                <button class="btn-ghost btn-sm" onclick="editDecision(<?= htmlspecialchars(json_encode($d), ENT_QUOTES) ?>)">Edit</button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<!-- Create/Edit Modal -->
<div class="modal-overlay" id="modalDecision">
    <div class="modal modal-lg">
        <div class="modal-header">
            <h2 class="modal-title" id="decisionModalTitle">Record Decision</h2>
            <button class="modal-close" onclick="closeModal('modalDecision')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <input type="hidden" name="action" id="decisionAction" value="create">
            <input type="hidden" name="id" id="decisionId">
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
                        <label class="form-label">Authority Level</label>
                        <input type="text" name="authority_level" class="form-control" placeholder="e.g. Principal, Finance Lead">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Delegation Limit</label>
                    <input type="text" name="delegation_limit" class="form-control" placeholder="e.g. Up to KES 500,000, routine procurement">
                </div>
                <div class="form-group">
                    <label class="flex gap-2 items-center cursor-pointer">
                        <input type="checkbox" name="requires_director_approval" value="1">
                        <span class="form-label" style="margin:0">Requires Director approval (reserved decision)</span>
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('modalDecision')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save</button>
            </div>
        </form>
    </div>
</div>

<!-- Decide Modal -->
<div class="modal-overlay" id="modalDecide">
    <div class="modal">
        <div class="modal-header">
            <h2 class="modal-title">Make Decision</h2>
            <button class="modal-close" onclick="closeModal('modalDecide')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <input type="hidden" name="action" value="decide">
            <input type="hidden" name="id" id="decideId">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Decision for: <span id="decideTitle" class="fw-600"></span></label>
                </div>
                <div class="form-group">
                    <label class="form-label">Outcome</label>
                    <select name="status" class="form-control">
                        <option value="approved">Approve</option>
                        <option value="declined">Decline</option>
                        <option value="escalated">Escalate to Director</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Decision Notes</label>
                    <textarea name="decision_notes" class="form-control" rows="3" placeholder="Reasoning for the decision"></textarea>
                </div>
                <div class="alert alert-info">
                    Escalating will create an escalation record for the Director to review.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('modalDecide')">Cancel</button>
                <button type="submit" class="btn btn-primary">Record Decision</button>
            </div>
        </form>
    </div>
</div>

<script>
function editDecision(data) {
    document.getElementById('decisionModalTitle').textContent = 'Edit Decision';
    document.getElementById('decisionAction').value = 'update';
    document.getElementById('decisionId').value = data.id;
    const form = document.querySelector('#modalDecision form');
    form.querySelector('[name="title"]').value = data.title || '';
    form.querySelector('[name="description"]').value = data.description || '';
    form.querySelector('[name="owner_id"]').value = data.owner_id || '';
    form.querySelector('[name="authority_level"]').value = data.authority_level || '';
    form.querySelector('[name="delegation_limit"]').value = data.delegation_limit || '';
    form.querySelector('[name="requires_director_approval"]').checked = data.requires_director_approval == 1;
    openModal('modalDecision');
}
function decideDecision(id, title) {
    document.getElementById('decideId').value = id;
    document.getElementById('decideTitle').textContent = title;
    openModal('modalDecide');
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
