<?php
$page_title = 'Daily Brief';
require_once __DIR__ . '/includes/header.php';
$db = get_db();
$user = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'create') {
        $stmt = $db->prepare('INSERT INTO daily_briefs (brief_date, summary, prepared_by, status) VALUES (?,?,?,?)');
        $stmt->execute([$_POST['brief_date'], $_POST['summary'] ?? null, $user['id'], 'draft']);
        $id = $db->lastInsertId();
        if (!empty($_POST['items'])) {
            foreach ($_POST['items'] as $item) {
                if (!empty($item['note'])) {
                    $db->prepare('INSERT INTO daily_brief_items (brief_id, item_type, item_ref_id, note, priority) VALUES (?,?,?,?,?)')
                        ->execute([$id, $item['item_type'] ?? 'commitment', $item['item_ref_id'] ?: null, $item['note'], $item['priority'] ?? 'medium']);
                }
            }
        }
        log_action('create', 'daily_brief', $id);
        toast_redirect('Daily brief created', 'daily-brief.php');
    } elseif ($action === 'publish') {
        $id = (int)$_POST['id'];
        $db->prepare('UPDATE daily_briefs SET status="published" WHERE id=?')->execute([$id]);
        log_action('publish', 'daily_brief', $id);
        toast_redirect('Daily brief published', 'daily-brief.php');
    } elseif ($action === 'delete') {
        $id = (int)$_POST['id'];
        $db->prepare('DELETE FROM daily_briefs WHERE id=?')->execute([$id]);
        log_action('delete', 'daily_brief', $id);
        toast_redirect('Daily brief deleted', 'daily-brief.php');
    }
}

$briefs = $db->query('SELECT db.*, u.name as prepared_by_name FROM daily_briefs db LEFT JOIN users u ON db.prepared_by = u.id ORDER BY db.brief_date DESC')->fetchAll();
$csrf = csrf_token();
$canCoordinate = can('can_coordinate') || has_role('system_admin', 'chairman_ceo');

// Auto-gather today's items
$today = date('Y-m-d');
$urgentCommitments = $db->query("SELECT id, title, due_date, owner_id FROM commitments WHERE status NOT IN ('complete') AND due_date <= DATE_ADD(CURDATE(), INTERVAL 3 DAY) ORDER BY due_date ASC")->fetchAll();
$openEscalations = $db->query("SELECT id, title, priority FROM escalations WHERE status='open' ORDER BY priority DESC")->fetchAll();
$pendingDecisions = $db->query("SELECT id, title FROM decisions WHERE status='pending' ORDER BY created_at DESC")->fetchAll();

// Detail
$detailId = (int)($_GET['id'] ?? 0);
$detailBrief = null;
$detailItems = [];
if ($detailId) {
    $stmt = $db->prepare('SELECT db.*, u.name as prepared_by_name FROM daily_briefs db LEFT JOIN users u ON db.prepared_by = u.id WHERE db.id=?');
    $stmt->execute([$detailId]);
    $detailBrief = $stmt->fetch();
    if ($detailBrief) {
        $stmt = $db->prepare('SELECT * FROM daily_brief_items WHERE brief_id=? ORDER BY priority DESC');
        $stmt->execute([$detailId]);
        $detailItems = $stmt->fetchAll();
    }
}
?>

<div class="section-header">
    <div>
        <h2 class="section-title">Daily Brief</h2>
        <p class="section-sub">Focused daily brief coordinated by the Executive Coordinator</p>
    </div>
    <?php if ($canCoordinate): ?>
    <button class="btn btn-primary" onclick="openModal('modalBrief')">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        New Brief
    </button>
    <?php endif; ?>
</div>

<?php if ($detailBrief): ?>
<div class="card mb-6">
    <div class="card-header">
        <h3 class="card-title">Brief for <?= e(fmt_date($detailBrief['brief_date'])) ?></h3>
        <div class="flex gap-2">
            <span class="badge <?= status_badge($detailBrief['status']) ?>"><?= status_label($detailBrief['status']) ?></span>
            <a href="daily-brief.php" class="btn btn-sm btn-outline">← Back</a>
        </div>
    </div>
    <div class="card-body">
        <?php if ($detailBrief['summary']): ?>
            <div class="mb-4"><strong>Summary:</strong><p class="text-sm text-2 mt-1"><?= nl2br(e($detailBrief['summary'])) ?></p></div>
        <?php endif; ?>
        <h4 class="text-sm fw-600 mb-3">Brief Items</h4>
        <?php if (empty($detailItems)): ?>
            <p class="text-sm text-3">No items.</p>
        <?php else: ?>
            <?php foreach ($detailItems as $item): ?>
                <div class="list-item">
                    <span class="badge <?= status_badge($item['priority']) ?>" style="align-self:flex-start; margin-top:2px;"><?= status_label($item['priority']) ?></span>
                    <div class="list-item-body">
                        <div class="text-sm"><?= e($item['note']) ?></div>
                        <div class="text-xs text-3 mt-1"><?= status_label($item['item_type']) ?><?= $item['item_ref_id'] ? ' #' . $item['item_ref_id'] : '' ?></div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
        <div class="text-xs text-3 mt-3">Prepared by <?= e($detailBrief['prepared_by_name'] ?? 'Unknown') ?> · <?= e(fmt_datetime($detailBrief['created_at'])) ?></div>
        <?php if ($detailBrief['status'] === 'draft' && $canCoordinate): ?>
            <form method="POST" style="margin-top:1rem;">
                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                <input type="hidden" name="action" value="publish">
                <input type="hidden" name="id" value="<?= $detailBrief['id'] ?>">
                <button class="btn btn-primary btn-sm">Publish Brief</button>
            </form>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<!-- Auto-generated today items -->
<?php if (!$detailBrief): ?>
<div class="grid cols-1-2 mb-6">
    <div class="card">
        <div class="card-header"><h3 class="card-title">Items requiring attention today</h3></div>
        <div class="card-body">
            <?php if (empty($urgentCommitments) && empty($openEscalations) && empty($pendingDecisions)): ?>
                <div class="empty-state" style="padding:1.5rem;"><p>Nothing urgent right now.</p></div>
            <?php else: ?>
                <?php foreach ($urgentCommitments as $c):
                    $days = days_until($c['due_date']);
                ?>
                <div class="list-item">
                    <div class="list-item-icon <?= $days !== null && $days < 0 ? '' : '' ?>">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
                    </div>
                    <div class="list-item-body">
                        <div class="list-item-title"><a href="commitments.php?id=<?= $c['id'] ?>" class="cell-link"><?= e($c['title']) ?></a></div>
                        <div class="list-item-sub">Due <?= e(fmt_date($c['due_date'])) ?> · <?= e(get_user_name($c['owner_id'])) ?> <?php if ($days !== null && $days < 0) echo '<span style="color:var(--c-danger)">' . abs($days) . 'd overdue</span>'; ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php foreach ($openEscalations as $e): ?>
                <div class="list-item">
                    <div class="list-item-icon" style="background: var(--c-danger-bg); color: var(--c-danger);">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/></svg>
                    </div>
                    <div class="list-item-body">
                        <div class="list-item-title"><a href="escalations.php" class="cell-link"><?= e($e['title']) ?></a></div>
                        <div class="list-item-sub"><?= status_label($e['priority']) ?> priority escalation</div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php foreach ($pendingDecisions as $d): ?>
                <div class="list-item">
                    <div class="list-item-icon" style="background: var(--c-warning-bg); color: var(--c-warning);">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><path d="M3 3l7.07 16.97 2.51-7.39 7.39-2.51z"/></svg>
                    </div>
                    <div class="list-item-body">
                        <div class="list-item-title"><a href="decisions.php" class="cell-link"><?= e($d['title']) ?></a></div>
                        <div class="list-item-sub">Pending decision</div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    <div class="card">
        <div class="card-header"><h3 class="card-title">Recent briefs</h3></div>
        <div class="card-body" style="padding:0.5rem;">
            <?php if (empty($briefs)): ?>
                <div class="empty-state" style="padding:1.5rem;"><p>No briefs yet.</p></div>
            <?php else: ?>
                <?php foreach (array_slice($briefs, 0, 5) as $b): ?>
                <div class="card mb-2">
                    <div class="card-body" style="padding:0.875rem;">
                        <div class="flex items-center justify-between">
                            <a href="daily-brief.php?id=<?= $b['id'] ?>" class="fw-600 text-sm"><?= e(fmt_date($b['brief_date'])) ?></a>
                            <span class="badge <?= status_badge($b['status']) ?>"><?= status_label($b['status']) ?></span>
                        </div>
                        <?php if ($b['summary']): ?><p class="text-xs text-2 mt-1"><?= e(substr($b['summary'], 0, 80)) ?>...</p><?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- New Brief Modal -->
<div class="modal-overlay" id="modalBrief">
    <div class="modal modal-lg">
        <div class="modal-header">
            <h2 class="modal-title">New Daily Brief</h2>
            <button class="modal-close" onclick="closeModal('modalBrief')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <input type="hidden" name="action" value="create">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Date <span class="req">*</span></label>
                    <input type="date" name="brief_date" class="form-control" required value="<?= $today ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Summary</label>
                    <textarea name="summary" class="form-control" rows="2" placeholder="Overall summary of today's priorities"></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">Brief Items</label>
                    <div id="briefItems">
                        <div class="brief-item-row" style="border: 1px solid var(--c-border); border-radius: var(--radius); padding: 0.75rem; margin-bottom: 0.5rem;">
                            <div class="flex gap-2" style="margin-bottom:0.5rem;">
                                <select name="items[0][item_type]" class="form-control">
                                    <option value="commitment">Commitment</option>
                                    <option value="escalation">Escalation</option>
                                    <option value="funding">Funding</option>
                                    <option value="decision">Decision</option>
                                    <option value="communication">Communication</option>
                                </select>
                                <select name="items[0][priority]" class="form-control">
                                    <option value="low">Low</option>
                                    <option value="medium" selected>Medium</option>
                                    <option value="high">High</option>
                                    <option value="critical">Critical</option>
                                </select>
                            </div>
                            <input type="text" name="items[0][note]" class="form-control" placeholder="What needs attention?">
                        </div>
                    </div>
                    <button type="button" class="btn btn-outline btn-sm mt-2" onclick="addBriefItem()">+ Add Item</button>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('modalBrief')">Cancel</button>
                <button type="submit" class="btn btn-primary">Create (Draft)</button>
            </div>
        </form>
    </div>
</div>

<script>
let briefItemIndex = 1;
function addBriefItem() {
    const container = document.getElementById('briefItems');
    const div = document.createElement('div');
    div.className = 'brief-item-row';
    div.style.cssText = 'border: 1px solid var(--c-border); border-radius: var(--radius); padding: 0.75rem; margin-bottom: 0.5rem;';
    div.innerHTML = `
        <div class="flex gap-2" style="margin-bottom:0.5rem;">
            <select name="items[${briefItemIndex}][item_type]" class="form-control">
                <option value="commitment">Commitment</option>
                <option value="escalation">Escalation</option>
                <option value="funding">Funding</option>
                <option value="decision">Decision</option>
                <option value="communication">Communication</option>
            </select>
            <select name="items[${briefItemIndex}][priority]" class="form-control">
                <option value="low">Low</option>
                <option value="medium" selected>Medium</option>
                <option value="high">High</option>
                <option value="critical">Critical</option>
            </select>
        </div>
        <input type="text" name="items[${briefItemIndex}][note]" class="form-control" placeholder="What needs attention?">
    `;
    container.appendChild(div);
    briefItemIndex++;
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
