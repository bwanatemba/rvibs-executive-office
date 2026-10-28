<?php
$page_title = 'Ideas Pipeline';
require_once __DIR__ . '/includes/header.php';
$db = get_db();
$user = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'create') {
        $stmt = $db->prepare('INSERT INTO ideas (title, description, proposed_by, status) VALUES (?,?,?,?)');
        $stmt->execute([$_POST['title'], $_POST['description'] ?? null, $user['id'], 'new']);
        $id = $db->lastInsertId();
        log_action('create', 'idea', $id, null, $_POST);
        toast_redirect('Idea submitted', 'ideas.php');
    } elseif ($action === 'update') {
        $id = (int)$_POST['id'];
        $stmt = $db->prepare('UPDATE ideas SET title=?, description=? WHERE id=?');
        $stmt->execute([$_POST['title'], $_POST['description'] ?? null, $id]);
        log_action('update', 'idea', $id);
        toast_redirect('Idea updated', 'ideas.php');
    } elseif ($action === 'review') {
        $id = (int)$_POST['id'];
        $status = $_POST['status'];
        $stmt = $db->prepare('UPDATE ideas SET status=?, capacity_note=?, resource_required=?, decision_note=?, decided_by=?, decided_at=NOW() WHERE id=?');
        $stmt->execute([$status, $_POST['capacity_note'] ?? null, $_POST['resource_required'] ?? null, $_POST['decision_note'] ?? null, $user['id'], $id]);
        log_action('review', 'idea', $id, null, ['status' => $status]);
        toast_redirect('Idea ' . status_label($status), 'ideas.php');
    } elseif ($action === 'convert') {
        $id = (int)$_POST['id'];
        // Get the idea
        $idea = $db->prepare('SELECT * FROM ideas WHERE id=?');
        $idea->execute([$id]);
        $ideaData = $idea->fetch();
        if ($ideaData) {
            // Create commitment from idea
            $stmt = $db->prepare('INSERT INTO commitments (title, description, owner_id, status, priority, created_by) VALUES (?,?,?,?,?,?)');
            $stmt->execute([$ideaData['title'], $ideaData['description'], $_POST['owner_id'] ?: null, 'open', 'medium', $user['id']]);
            $commitmentId = $db->lastInsertId();
            $db->prepare('UPDATE ideas SET status="converted", converted_commitment_id=?, decided_by=?, decided_at=NOW() WHERE id=?')->execute([$commitmentId, $user['id'], $id]);
            log_action('convert', 'idea', $id, null, ['commitment_id' => $commitmentId]);
            toast_redirect('Idea converted to commitment', 'commitments.php?id=' . $commitmentId);
        }
    } elseif ($action === 'delete') {
        $id = (int)$_POST['id'];
        $db->prepare('DELETE FROM ideas WHERE id=?')->execute([$id]);
        log_action('delete', 'idea', $id);
        toast_redirect('Idea deleted', 'ideas.php');
    }
}

// Kanban-style pipeline columns
$columns = ['new' => 'New Ideas', 'under_review' => 'Under Review', 'approved' => 'Approved (Capacity Assigned)', 'declined' => 'Declined'];
$ideas = $db->query('SELECT i.*, u.name as proposer_name FROM ideas i LEFT JOIN users u ON i.proposed_by = u.id ORDER BY i.created_at DESC')->fetchAll();
$ideasByStatus = [];
foreach ($columns as $key => $label) {
    $ideasByStatus[$key] = array_filter($ideas, fn($i) => $i['status'] === $key);
}
$converted = array_filter($ideas, fn($i) => $i['status'] === 'converted');
$users_list = get_users_list();
$csrf = csrf_token();
$canApprove = can('can_approve') || has_role('system_admin');
?>

<div class="section-header">
    <div>
        <h2 class="section-title">Ideas Pipeline</h2>
        <p class="section-sub">New ideas are kept separate from approved assignments. New work must have an explicit resource and capacity decision.</p>
    </div>
    <button class="btn btn-primary" onclick="openModal('modalIdea')">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Submit Idea
    </button>
</div>

<div class="pipeline">
    <?php foreach ($columns as $status => $label): ?>
        <div class="pipeline-col">
            <div class="pipeline-col-header">
                <span class="pipeline-col-title"><?= $label ?></span>
                <span class="pipeline-col-count"><?= count($ideasByStatus[$status]) ?></span>
            </div>
            <?php if (empty($ideasByStatus[$status])): ?>
                <div class="text-center text-3 text-sm" style="padding: 2rem 1rem; border: 1px dashed var(--c-border); border-radius: var(--radius);">No items</div>
            <?php else: ?>
                <?php foreach ($ideasByStatus[$status] as $idea): ?>
                    <div class="pipeline-card">
                        <div class="flex items-center justify-between mb-2">
                            <span class="badge <?= status_badge($idea['status']) ?>"><?= status_label($idea['status']) ?></span>
                            <div class="flex gap-1">
                                <?php if ($canApprove && $idea['status'] === 'new'): ?>
                                    <button class="btn-ghost btn-sm" onclick="reviewIdea(<?= htmlspecialchars(json_encode($idea), ENT_QUOTES) ?>)" title="Review">Review</button>
                                <?php endif; ?>
                                <?php if ($canApprove && $idea['status'] === 'approved'): ?>
                                    <button class="btn-ghost btn-sm" onclick="convertIdea(<?= $idea['id'] ?>)" title="Convert to commitment">Convert</button>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="fw-600 text-sm mb-1"><?= e($idea['title']) ?></div>
                        <?php if ($idea['description']): ?>
                            <p class="text-xs text-2 mb-2"><?= e(substr($idea['description'], 0, 120)) ?><?= strlen($idea['description']) > 120 ? '...' : '' ?></p>
                        <?php endif; ?>
                        <?php if ($idea['capacity_note']): ?>
                            <div class="alert alert-info mt-2" style="padding:0.5rem 0.625rem; font-size:0.75rem;">
                                <strong>Capacity:</strong> <?= e($idea['capacity_note']) ?>
                            </div>
                        <?php endif; ?>
                        <?php if ($idea['resource_required']): ?>
                            <div class="text-xs text-2 mt-1"><strong>Resources:</strong> <?= e($idea['resource_required']) ?></div>
                        <?php endif; ?>
                        <?php if ($idea['decision_note']): ?>
                            <div class="text-xs text-2 mt-1"><strong>Decision:</strong> <?= e($idea['decision_note']) ?></div>
                        <?php endif; ?>
                        <div class="text-xs text-3 mt-2">Proposed by <?= e($idea['proposer_name'] ?? 'Unknown') ?> · <?= e(fmt_date($idea['created_at'])) ?></div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>

<?php if (!empty($converted)): ?>
<div class="card mt-6">
    <div class="card-header">
        <h3 class="card-title">Converted to Commitments</h3>
        <span class="badge badge-success"><?= count($converted) ?> converted</span>
    </div>
    <div class="table-wrap" style="border:none;">
        <table class="data-table">
            <thead><tr><th>Title</th><th>Converted To</th><th>By</th><th>Date</th></tr></thead>
            <tbody>
                <?php foreach ($converted as $c): ?>
                <tr>
                    <td class="cell-title"><?= e($c['title']) ?></td>
                    <td><a href="commitments.php?id=<?= $c['converted_commitment_id'] ?>" class="cell-link">Commitment #<?= $c['converted_commitment_id'] ?></a></td>
                    <td class="text-sm"><?= e(get_user_name($c['decided_by'])) ?></td>
                    <td class="text-sm text-3"><?= e(fmt_date($c['decided_at'])) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- New Idea Modal -->
<div class="modal-overlay" id="modalIdea">
    <div class="modal">
        <div class="modal-header">
            <h2 class="modal-title">Submit New Idea</h2>
            <button class="modal-close" onclick="closeModal('modalIdea')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <input type="hidden" name="action" value="create">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Title <span class="req">*</span></label>
                    <input type="text" name="title" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="3" placeholder="What is the idea? What problem does it solve?"></textarea>
                </div>
                <div class="alert alert-info">
                    New ideas are kept separate from approved work. An explicit resource and capacity decision is required before an idea becomes an assignment.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('modalIdea')">Cancel</button>
                <button type="submit" class="btn btn-primary">Submit</button>
            </div>
        </form>
    </div>
</div>

<!-- Review Modal -->
<div class="modal-overlay" id="modalReview">
    <div class="modal modal-lg">
        <div class="modal-header">
            <h2 class="modal-title">Review Idea</h2>
            <button class="modal-close" onclick="closeModal('modalReview')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <input type="hidden" name="action" value="review">
            <input type="hidden" name="id" id="reviewId">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Decision</label>
                    <select name="status" class="form-control" id="reviewStatus">
                        <option value="under_review">Under Review</option>
                        <option value="approved">Approve (assign capacity)</option>
                        <option value="declined">Decline</option>
                    </select>
                </div>
                <div class="form-group" id="capacityFields" style="display:none;">
                    <label class="form-label">Capacity Note</label>
                    <textarea name="capacity_note" class="form-control" rows="2" placeholder="Is there capacity to take this on? Whose capacity?"></textarea>
                </div>
                <div class="form-group" id="resourceFields" style="display:none;">
                    <label class="form-label">Resources Required</label>
                    <textarea name="resource_required" class="form-control" rows="2" placeholder="What resources are needed?"></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">Decision Notes</label>
                    <textarea name="decision_note" class="form-control" rows="2" placeholder="Reason for decision"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('modalReview')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Decision</button>
            </div>
        </form>
    </div>
</div>

<!-- Convert Modal -->
<div class="modal-overlay" id="modalConvert">
    <div class="modal">
        <div class="modal-header">
            <h2 class="modal-title">Convert to Commitment</h2>
            <button class="modal-close" onclick="closeModal('modalConvert')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <input type="hidden" name="action" value="convert">
            <input type="hidden" name="id" id="convertId">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Assign Owner</label>
                    <select name="owner_id" class="form-control">
                        <option value="">Unassigned</option>
                        <?php foreach ($users_list as $u): ?>
                            <option value="<?= $u['id'] ?>"><?= e($u['name']) ?> (<?= e($u['email']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="alert alert-info">This will create a new commitment from the approved idea.</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('modalConvert')">Cancel</button>
                <button type="submit" class="btn btn-primary">Convert</button>
            </div>
        </form>
    </div>
</div>

<script>
function reviewIdea(data) {
    document.getElementById('reviewId').value = data.id;
    openModal('modalReview');
}
function convertIdea(id) {
    document.getElementById('convertId').value = id;
    openModal('modalConvert');
}
document.getElementById('reviewStatus').addEventListener('change', function() {
    const show = this.value === 'approved';
    document.getElementById('capacityFields').style.display = show ? 'block' : 'none';
    document.getElementById('resourceFields').style.display = show ? 'block' : 'none';
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
