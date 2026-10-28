<?php
$page_title = 'Escalations';
require_once __DIR__ . '/includes/header.php';
$db = get_db();
$user = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'create') {
        $stmt = $db->prepare('INSERT INTO escalations (title, description, raised_by, priority, linked_item_type, linked_item_id) VALUES (?,?,?,?,?,?)');
        $stmt->execute([$_POST['title'], $_POST['description'] ?? null, $user['id'], $_POST['priority'] ?? 'high', $_POST['linked_item_type'] ?? null, $_POST['linked_item_id'] ?: null]);
        $id = $db->lastInsertId();
        log_action('create', 'escalation', $id, null, $_POST);
        toast_redirect('Escalation raised', 'escalations.php');
    } elseif ($action === 'resolve') {
        $id = (int)$_POST['id'];
        $stmt = $db->prepare('UPDATE escalations SET status="resolved", resolution=?, resolved_by=?, resolved_at=NOW() WHERE id=?');
        $stmt->execute([$_POST['resolution'] ?? null, $user['id'], $id]);
        log_action('resolve', 'escalation', $id);
        toast_redirect('Escalation resolved', 'escalations.php');
    } elseif ($action === 'reopen') {
        $id = (int)$_POST['id'];
        $db->prepare('UPDATE escalations SET status="open", resolution=NULL, resolved_by=NULL, resolved_at=NULL WHERE id=?')->execute([$id]);
        log_action('reopen', 'escalation', $id);
        toast_redirect('Escalation reopened', 'escalations.php');
    } elseif ($action === 'delete') {
        $id = (int)$_POST['id'];
        $db->prepare('DELETE FROM escalations WHERE id=?')->execute([$id]);
        log_action('delete', 'escalation', $id);
        toast_redirect('Escalation deleted', 'escalations.php');
    }
}

$filter = $_GET['status'] ?? 'open';
$where = $filter === 'all' ? '' : 'WHERE e.status=' . $db->quote($filter);
$escalations = $db->query("
    SELECT e.*, u.name as raised_by_name, res.name as resolved_by_name
    FROM escalations e LEFT JOIN users u ON e.raised_by = u.id LEFT JOIN users res ON e.resolved_by = res.id
    $where ORDER BY FIELD(e.priority,'critical','high','medium'), e.raised_at DESC
")->fetchAll();
$csrf = csrf_token();
?>

<div class="section-header">
    <div>
        <h2 class="section-title">Escalations</h2>
        <p class="section-sub">Material exceptions requiring Director intervention: decisions beyond delegated limits, critical deadline risks, unfunded obligations</p>
    </div>
    <button class="btn btn-accent" onclick="openModal('modalEscalation')">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Raise Escalation
    </button>
</div>

<div class="filter-bar">
    <a href="escalations.php?status=open" class="btn btn-sm <?= $filter==='open'?'btn-danger':'btn-outline' ?>">Open</a>
    <a href="escalations.php?status=resolved" class="btn btn-sm <?= $filter==='resolved'?'btn-primary':'btn-outline' ?>">Resolved</a>
    <a href="escalations.php?status=all" class="btn btn-sm <?= $filter==='all'?'btn-outline':'btn-outline' ?>">All</a>
</div>

<div class="card">
    <div class="card-body" style="padding: 0.5rem;">
        <?php if (empty($escalations)): ?>
            <div class="empty-state">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/></svg>
                <h3>No escalations</h3>
                <p>No material exceptions at this time. Escalate when decisions exceed delegated limits, deadlines are at critical risk, or obligations are unfunded.</p>
            </div>
        <?php else: ?>
            <?php foreach ($escalations as $e): ?>
                <div class="card mb-2" style="border-left: 4px solid <?= $e['priority']==='critical'?'var(--c-danger)':($e['priority']==='high'?'var(--c-warning)':'var(--c-info)') ?>">
                    <div class="card-body">
                        <div class="flex items-center justify-between mb-2 flex-wrap gap-2">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="badge <?= status_badge($e['status']) ?>"><?= status_label($e['status']) ?></span>
                                <span class="badge <?= status_badge($e['priority']) ?>"><?= status_label($e['priority']) ?> priority</span>
                                <?php if ($e['linked_item_type']): ?>
                                    <span class="badge badge-neutral"><?= $e['linked_item_type'] ?> #<?= $e['linked_item_id'] ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="flex gap-2">
                                <?php if ($e['status'] === 'open'): ?>
                                    <button class="btn btn-primary btn-sm" onclick="resolveEscalation(<?= $e['id'] ?>)">Resolve</button>
                                <?php else: ?>
                                    <form method="POST" style="display:inline">
                                        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                        <input type="hidden" name="action" value="reopen">
                                        <input type="hidden" name="id" value="<?= $e['id'] ?>">
                                        <button class="btn btn-outline btn-sm">Reopen</button>
                                    </form>
                                <?php endif; ?>
                                <form method="POST" style="display:inline" onsubmit="return confirm('Delete?')">
                                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $e['id'] ?>">
                                    <button class="btn-ghost btn-sm"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/></svg></button>
                                </form>
                            </div>
                        </div>
                        <h3 class="fw-600 text-sm" style="font-family:var(--font-heading)"><?= e($e['title']) ?></h3>
                        <?php if ($e['description']): ?><p class="text-sm text-2 mt-1"><?= e($e['description']) ?></p><?php endif; ?>
                        <?php if ($e['status'] === 'resolved' && $e['resolution']): ?>
                            <div class="alert alert-success mt-2" style="padding:0.5rem 0.75rem;">
                                <strong>Resolution:</strong> <?= e($e['resolution']) ?>
                            </div>
                        <?php endif; ?>
                        <div class="text-xs text-3 mt-2">
                            Raised by <?= e($e['raised_by_name'] ?? 'Unknown') ?> on <?= e(fmt_datetime($e['raised_at'])) ?>
                            <?php if ($e['resolved_by_name']): ?> · Resolved by <?= e($e['resolved_by_name']) ?> on <?= e(fmt_datetime($e['resolved_at'])) ?><?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Raise Escalation Modal -->
<div class="modal-overlay" id="modalEscalation">
    <div class="modal modal-lg">
        <div class="modal-header">
            <h2 class="modal-title">Raise Escalation</h2>
            <button class="modal-close" onclick="closeModal('modalEscalation')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <input type="hidden" name="action" value="create">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Title <span class="req">*</span></label>
                    <input type="text" name="title" class="form-control" required placeholder="Brief summary of the material exception">
                </div>
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="3" placeholder="What is the exception? Why does it require Director intervention?"></textarea>
                </div>
                <div class="form-row-3">
                    <div class="form-group">
                        <label class="form-label">Priority</label>
                        <select name="priority" class="form-control">
                            <option value="medium">Medium</option>
                            <option value="high" selected>High</option>
                            <option value="critical">Critical</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Linked Item Type</label>
                        <select name="linked_item_type" class="form-control">
                            <option value="">None</option>
                            <option value="commitment">Commitment</option>
                            <option value="decision">Decision</option>
                            <option value="funding_opportunity">Funding Opportunity</option>
                            <option value="outcome">Outcome</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Linked Item ID</label>
                        <input type="number" name="linked_item_id" class="form-control" placeholder="Optional">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('modalEscalation')">Cancel</button>
                <button type="submit" class="btn btn-accent">Raise</button>
            </div>
        </form>
    </div>
</div>

<!-- Resolve Modal -->
<div class="modal-overlay" id="modalResolve">
    <div class="modal">
        <div class="modal-header">
            <h2 class="modal-title">Resolve Escalation</h2>
            <button class="modal-close" onclick="closeModal('modalResolve')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <input type="hidden" name="action" value="resolve">
            <input type="hidden" name="id" id="resolveId">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Resolution <span class="req">*</span></label>
                    <textarea name="resolution" class="form-control" rows="4" required placeholder="How was this resolved? What action was taken?"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('modalResolve')">Cancel</button>
                <button type="submit" class="btn btn-primary">Mark Resolved</button>
            </div>
        </form>
    </div>
</div>

<script>
function resolveEscalation(id) {
    document.getElementById('resolveId').value = id;
    openModal('modalResolve');
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
