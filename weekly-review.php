<?php
$page_title = 'Weekly Review';
require_once __DIR__ . '/includes/auth.php';
require_login();
$db = get_db();
$user = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'create') {
        $stmt = $db->prepare('INSERT INTO weekly_reviews (week_of, summary, prepared_by, status) VALUES (?,?,?,?)');
        $stmt->execute([$_POST['week_of'], $_POST['summary'] ?? null, $user['id'], 'draft']);
        $id = $db->lastInsertId();
        // Add items
        if (!empty($_POST['items'])) {
            foreach ($_POST['items'] as $item) {
                if (!empty($item['discussion']) || !empty($item['action'])) {
                    $db->prepare('INSERT INTO weekly_review_items (review_id, item_type, item_ref_id, discussion, action, owner_id) VALUES (?,?,?,?,?,?)')
                        ->execute([$id, $item['item_type'] ?? 'commitment', $item['item_ref_id'] ?: null, $item['discussion'] ?? null, $item['action'] ?? null, $item['owner_id'] ?: null]);
                }
            }
        }
        log_action('create', 'weekly_review', $id);
        toast_redirect('Weekly review created', 'weekly-review.php');
    } elseif ($action === 'publish') {
        $id = (int)$_POST['id'];
        $db->prepare('UPDATE weekly_reviews SET status="published" WHERE id=?')->execute([$id]);
        log_action('publish', 'weekly_review', $id);
        toast_redirect('Weekly review published', 'weekly-review.php');
    } elseif ($action === 'delete') {
        $id = (int)$_POST['id'];
        $db->prepare('DELETE FROM weekly_reviews WHERE id=?')->execute([$id]);
        log_action('delete', 'weekly_review', $id);
        toast_redirect('Weekly review deleted', 'weekly-review.php');
    }
}

$reviews = $db->query('SELECT wr.*, u.name as prepared_by_name FROM weekly_reviews wr LEFT JOIN users u ON wr.prepared_by = u.id ORDER BY wr.week_of DESC')->fetchAll();
$csrf = csrf_token();
$canCoordinate = can('can_coordinate') || has_role('system_admin', 'chairman_ceo');
$users_list = get_users_list();

// Get detail
$detailId = (int)($_GET['id'] ?? 0);
$detailReview = null;
$detailItems = [];
if ($detailId) {
    $stmt = $db->prepare('SELECT wr.*, u.name as prepared_by_name FROM weekly_reviews wr LEFT JOIN users u ON wr.prepared_by = u.id WHERE wr.id=?');
    $stmt->execute([$detailId]);
    $detailReview = $stmt->fetch();
    if ($detailReview) {
        $stmt = $db->prepare('SELECT wri.*, u.name as owner_name FROM weekly_review_items wri LEFT JOIN users u ON wri.owner_id = u.id WHERE wri.review_id=?');
        $stmt->execute([$detailId]);
        $detailItems = $stmt->fetchAll();
    }
}
require_once __DIR__ . '/includes/header.php';
?>

<div class="section-header">
    <div>
        <h2 class="section-title">Weekly Review</h2>
        <p class="section-sub">Focused weekly review coordinated by the Executive Coordinator</p>
    </div>
    <?php if ($canCoordinate): ?>
    <button class="btn btn-primary" onclick="openModal('modalReview')">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        New Review
    </button>
    <?php endif; ?>
</div>

<?php if ($detailReview): ?>
<div class="card mb-6">
    <div class="card-header">
        <h3 class="card-title">Week of <?= e(fmt_date($detailReview['week_of'])) ?></h3>
        <div class="flex gap-2">
            <span class="badge <?= status_badge($detailReview['status']) ?>"><?= status_label($detailReview['status']) ?></span>
            <a href="weekly-review.php" class="btn btn-sm btn-outline">← Back</a>
        </div>
    </div>
    <div class="card-body">
        <?php if ($detailReview['summary']): ?>
            <div class="mb-4"><strong>Summary:</strong><p class="text-sm text-2 mt-1"><?= nl2br(e($detailReview['summary'])) ?></p></div>
        <?php endif; ?>
        <h4 class="text-sm fw-600 mb-3">Review Items</h4>
        <?php if (empty($detailItems)): ?>
            <p class="text-sm text-3">No items in this review.</p>
        <?php else: ?>
            <div class="table-wrap" style="border:none;">
                <table class="data-table">
                    <thead><tr><th>Type</th><th>Discussion</th><th>Action</th><th>Owner</th></tr></thead>
                    <tbody>
                        <?php foreach ($detailItems as $item): ?>
                        <tr>
                            <td><span class="badge badge-primary"><?= status_label($item['item_type']) ?></span></td>
                            <td class="text-sm"><?= e($item['discussion'] ?? '—') ?></td>
                            <td class="text-sm"><?= e($item['action'] ?? '—') ?></td>
                            <td class="text-sm"><?= e($item['owner_name'] ?? '—') ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
        <div class="text-xs text-3 mt-3">Prepared by <?= e($detailReview['prepared_by_name'] ?? 'Unknown') ?> · <?= e(fmt_datetime($detailReview['created_at'])) ?></div>
        <?php if ($detailReview['status'] === 'draft' && $canCoordinate): ?>
            <form method="POST" style="margin-top: 1rem;">
                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                <input type="hidden" name="action" value="publish">
                <input type="hidden" name="id" value="<?= $detailReview['id'] ?>">
                <button class="btn btn-primary btn-sm">Publish Review</button>
            </form>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-body" style="padding: 0.5rem;">
        <?php if (empty($reviews)): ?>
            <div class="empty-state">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                <h3>No weekly reviews yet</h3>
                <p>Weekly reviews help maintain focus and accountability. The Executive Coordinator prepares a review pack for the Director each week.</p>
            </div>
        <?php else: ?>
            <?php foreach ($reviews as $r): ?>
                <div class="card mb-2">
                    <div class="card-body">
                        <div class="flex items-center justify-between flex-wrap gap-2">
                            <div class="flex items-center gap-2">
                                <a href="weekly-review.php?id=<?= $r['id'] ?>" class="fw-600 text-sm">Week of <?= e(fmt_date($r['week_of'])) ?></a>
                                <span class="badge <?= status_badge($r['status']) ?>"><?= status_label($r['status']) ?></span>
                            </div>
                            <div class="flex gap-2">
                                <?php if ($canCoordinate): ?>
                                <form method="POST" style="display:inline" data-delete-confirm="Delete?" onsubmit="return confirmDeleteForm(this, event)">
                                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $r['id'] ?>">
                                    <button class="btn-ghost btn-sm"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/></svg></button>
                                </form>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php if ($r['summary']): ?><p class="text-sm text-2 mt-1"><?= e(substr($r['summary'], 0, 150)) ?>...</p><?php endif; ?>
                        <div class="text-xs text-3 mt-1">Prepared by <?= e($r['prepared_by_name'] ?? 'Unknown') ?></div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- New Review Modal -->
<div class="modal-overlay" id="modalReview">
    <div class="modal modal-lg">
        <div class="modal-header">
            <h2 class="modal-title">New Weekly Review</h2>
            <button class="modal-close" onclick="closeModal('modalReview')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <input type="hidden" name="action" value="create">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Week Of <span class="req">*</span></label>
                    <input type="date" name="week_of" class="form-control" required value="<?= date('Y-m-d', strtotime('monday this week')) ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Summary</label>
                    <textarea name="summary" class="form-control" rows="3" placeholder="Overall summary of the week"></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">Review Items</label>
                    <div id="reviewItems">
                        <div class="review-item-row" style="border: 1px solid var(--c-border); border-radius: var(--radius); padding: 0.75rem; margin-bottom: 0.5rem;">
                            <div class="form-row-3" style="margin-bottom:0.5rem;">
                                <select name="items[0][item_type]" class="form-control">
                                    <option value="commitment">Commitment</option>
                                    <option value="outcome">Outcome</option>
                                    <option value="escalation">Escalation</option>
                                    <option value="funding">Funding</option>
                                </select>
                                <select name="items[0][item_ref_id]" class="form-control">
                                    <option value="">Link ID (optional)</option>
                                </select>
                                <select name="items[0][owner_id]" class="form-control">
                                    <option value="">Assign to</option>
                                    <?php foreach ($users_list as $u): ?>
                                        <option value="<?= $u['id'] ?>"><?= e($u['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <input type="text" name="items[0][discussion]" class="form-control mb-2" placeholder="Discussion point">
                            <input type="text" name="items[0][action]" class="form-control" placeholder="Action / decision">
                        </div>
                    </div>
                    <button type="button" class="btn btn-outline btn-sm mt-2" onclick="addReviewItem()">+ Add Item</button>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('modalReview')">Cancel</button>
                <button type="submit" class="btn btn-primary">Create (Draft)</button>
            </div>
        </form>
    </div>
</div>

<script>
let reviewItemIndex = 1;
function addReviewItem() {
    const container = document.getElementById('reviewItems');
    const div = document.createElement('div');
    div.className = 'review-item-row';
    div.style.cssText = 'border: 1px solid var(--c-border); border-radius: var(--radius); padding: 0.75rem; margin-bottom: 0.5rem;';
    div.innerHTML = `
        <div class="form-row-3" style="margin-bottom:0.5rem;">
            <select name="items[${reviewItemIndex}][item_type]" class="form-control">
                <option value="commitment">Commitment</option>
                <option value="outcome">Outcome</option>
                <option value="escalation">Escalation</option>
                <option value="funding">Funding</option>
            </select>
            <select name="items[${reviewItemIndex}][item_ref_id]" class="form-control">
                <option value="">Link ID (optional)</option>
            </select>
            <select name="items[${reviewItemIndex}][owner_id]" class="form-control">
                <option value="">Assign to</option>
                <?php foreach ($users_list as $u): ?>
                    <option value="<?= $u['id'] ?>"><?= e($u['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <input type="text" name="items[${reviewItemIndex}][discussion]" class="form-control mb-2" placeholder="Discussion point">
        <input type="text" name="items[${reviewItemIndex}][action]" class="form-control" placeholder="Action / decision">
    `;
    container.appendChild(div);
    reviewItemIndex++;
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
