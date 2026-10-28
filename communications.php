<?php
$page_title = 'Communications Agent';
require_once __DIR__ . '/includes/header.php';
$db = get_db();
$user = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'create') {
        $db->prepare('INSERT INTO communications (source, subject, from_address, from_name, body, date, status, is_pending) VALUES (?,?,?,?,?,?,?,?)')
            ->execute([$_POST['source'] ?? 'email', $_POST['subject'] ?? null, $_POST['from_address'] ?? null,
                $_POST['from_name'] ?? null, $_POST['body'] ?? null, $_POST['date'] ?: date('Y-m-d H:i:s'),
                'unread', isset($_POST['is_pending']) ? 1 : 0]);
        $id = $db->lastInsertId();
        log_action('create', 'communication', $id);
        toast_redirect('Message logged', 'communications.php?id=' . $id);
    } elseif ($action === 'update') {
        $id = (int)$_POST['id'];
        $db->prepare('UPDATE communications SET ai_summary=?, ai_draft_reply=?, status=?, is_pending=?, linked_item_type=?, linked_item_id=?, follow_up_date=? WHERE id=?')
            ->execute([$_POST['ai_summary'] ?? null, $_POST['ai_draft_reply'] ?? null, $_POST['status'] ?? 'read',
                isset($_POST['is_pending']) ? 1 : 0, $_POST['linked_item_type'] ?? null, $_POST['linked_item_id'] ?: null,
                $_POST['follow_up_date'] ?: null, $id]);
        log_action('update', 'communication', $id);
        toast_redirect('Message updated', 'communications.php?id=' . $id);
    } elseif ($action === 'delete') {
        $id = (int)$_POST['id'];
        $db->prepare('DELETE FROM communications WHERE id=?')->execute([$id]);
        toast_redirect('Message deleted', 'communications.php');
    }
}

$detailId = (int)($_GET['id'] ?? 0);
$filter = $_GET['filter'] ?? 'all';
$where = '';
if ($filter === 'pending') $where = 'WHERE is_pending=1';
elseif ($filter === 'email') $where = "WHERE source='email'";
elseif ($filter === 'whatsapp') $where = "WHERE source='whatsapp'";

$messages = $db->query("SELECT * FROM communications $where ORDER BY date DESC, id DESC")->fetchAll();
$csrf = csrf_token();

$detail = null;
if ($detailId) {
    $stmt = $db->prepare('SELECT * FROM communications WHERE id=?');
    $stmt->execute([$detailId]);
    $detail = $stmt->fetch();
}
?>

<div class="section-header">
    <div>
        <h2 class="section-title">Executive Communications Agent</h2>
        <p class="section-sub">Consolidating pending matters from email and WhatsApp, drafting replies, tracking follow-ups and linking to registers</p>
    </div>
    <button class="btn btn-primary" onclick="openModal('modalComms')">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Log Message
    </button>
</div>

<div class="alert alert-info">
    <strong>Scope:</strong> Reading, summarising and drafting. Automatic sending or creating binding commitments is outside the initial scope.
    The agent supports the Director's <strong>gitau.john@rvibs.ac.ke</strong> email and authorised WhatsApp conversations.
    Emma retains full day-to-day management of <strong>director@rvibs.ac.ke</strong>.
</div>

<div class="grid cols-2-1">
    <!-- Message List -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Inbox</h3>
            <div class="flex gap-2">
                <a href="communications.php" class="badge <?= $filter==='all'?'badge-primary':'badge-neutral' ?>">All</a>
                <a href="communications.php?filter=pending" class="badge <?= $filter==='pending'?'badge-accent':'badge-neutral' ?>">Pending</a>
                <a href="communications.php?filter=email" class="badge <?= $filter==='email'?'badge-info':'badge-neutral' ?>">Email</a>
                <a href="communications.php?filter=whatsapp" class="badge <?= $filter==='whatsapp'?'badge-success':'badge-neutral' ?>">WhatsApp</a>
            </div>
        </div>
        <div class="card-body" style="padding:0.5rem;">
            <?php if (empty($messages)): ?>
                <div class="empty-state">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                    <h3>No messages</h3>
                    <p>Messages from the Director's email and authorised WhatsApp will appear here. Log a message manually or connect an integration.</p>
                </div>
            <?php else: ?>
                <?php foreach ($messages as $m): ?>
                    <div class="card mb-2 cursor-pointer <?= $detailId === (int)$m['id'] ? '' : '' ?>" style="<?= $detailId === (int)$m['id'] ? 'border-color: var(--c-accent); border-width:2px;' : '' ?>" onclick="location.href='communications.php?id=<?= $m['id'] ?>'">
                        <div class="card-body" style="padding:0.875rem;">
                            <div class="flex items-center justify-between mb-1">
                                <div class="flex items-center gap-2">
                                    <span class="badge <?= $m['source']==='email'?'badge-info':'badge-success' ?>"><?= ucfirst($m['source']) ?></span>
                                    <?php if ($m['is_pending']): ?><span class="badge badge-accent">Pending</span><?php endif; ?>
                                </div>
                                <span class="text-xs text-3"><?= e(fmt_date($m['date'])) ?></span>
                            </div>
                            <div class="fw-600 text-sm"><?= e($m['subject'] ?? '(no subject)') ?></div>
                            <div class="text-xs text-2 mt-1">From: <?= e($m['from_name'] ?? '') ?> <?= e($m['from_address'] ? '&lt;'.$m['from_address'].'&gt;' : '') ?></div>
                            <div class="text-xs text-3 mt-1 truncate"><?= e(substr($m['body'] ?? '', 0, 80)) ?>...</div>
                            <?php if ($m['linked_item_type']): ?><span class="badge badge-neutral mt-1">Linked: <?= e($m['linked_item_type']) ?></span><?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Detail / Action Panel -->
    <div class="card">
        <?php if ($detail): ?>
            <div class="card-header">
                <h3 class="card-title">Message Detail</h3>
                <span class="badge <?= status_badge($detail['status']) ?>"><?= status_label($detail['status']) ?></span>
            </div>
            <div class="card-body">
                <div class="text-sm fw-600 mb-1"><?= e($detail['subject'] ?? '(no subject)') ?></div>
                <div class="text-xs text-2 mb-3">From: <?= e($detail['from_name'] ?? '') ?> <?= e($detail['from_address'] ?? '') ?></div>
                <div class="text-sm text-2 mb-4" style="border-left: 3px solid var(--c-border); padding-left: 0.75rem;"><?= nl2br(e($detail['body'] ?? '')) ?></div>

                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="id" value="<?= $detail['id'] ?>">
                    <div class="form-group">
                        <label class="form-label">AI Summary</label>
                        <textarea name="ai_summary" class="form-control" rows="3" placeholder="Auto-generated summary of the message"><?= e($detail['ai_summary'] ?? '') ?></textarea>
                        <div class="form-hint">Summarises pending matters, action items and key points</div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Draft Reply</label>
                        <textarea name="ai_draft_reply" class="form-control" rows="4" placeholder="Suggested reply (review before sending)"><?= e($detail['ai_draft_reply'] ?? '') ?></textarea>
                        <div class="form-hint">Draft only — review and send manually. No automatic sending.</div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-control">
                                <option value="unread" <?= $detail['status']==='unread'?'selected':'' ?>>Unread</option>
                                <option value="read" <?= $detail['status']==='read'?'selected':'' ?>>Read</option>
                                <option value="summarized" <?= $detail['status']==='summarized'?'selected':'' ?>>Summarized</option>
                                <option value="drafted" <?= $detail['status']==='drafted'?'selected':'' ?>>Drafted</option>
                                <option value="actioned" <?= $detail['status']==='actioned'?'selected':'' ?>>Actioned</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Follow-up Date</label>
                            <input type="date" name="follow_up_date" class="form-control" value="<?= e($detail['follow_up_date'] ?? '') ?>">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="flex gap-2 items-center cursor-pointer">
                            <input type="checkbox" name="is_pending" value="1" <?= $detail['is_pending'] ? 'checked' : '' ?>>
                            <span class="form-label" style="margin:0">Mark as pending matter</span>
                        </label>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Link to Register</label>
                            <select name="linked_item_type" class="form-control">
                                <option value="">None</option>
                                <option value="commitment" <?= $detail['linked_item_type']==='commitment'?'selected':'' ?>>Commitment</option>
                                <option value="outcome" <?= $detail['linked_item_type']==='outcome'?'selected':'' ?>>Outcome</option>
                                <option value="funding_opportunity" <?= $detail['linked_item_type']==='funding_opportunity'?'selected':'' ?>>Funding Opportunity</option>
                                <option value="escalation" <?= $detail['linked_item_type']==='escalation'?'selected':'' ?>>Escalation</option>
                                <option value="decision" <?= $detail['linked_item_type']==='decision'?'selected':'' ?>>Decision</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Linked Item ID</label>
                            <input type="number" name="linked_item_id" class="form-control" value="<?= e($detail['linked_item_id'] ?? '') ?>">
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm">Update</button>
                    <form method="POST" style="display:inline" onsubmit="return confirm('Delete?')">
                        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= $detail['id'] ?>">
                        <button class="btn-ghost btn-sm">Delete</button>
                    </form>
                </form>
            </div>
        <?php else: ?>
            <div class="card-body">
                <div class="empty-state">
                    <p>Select a message to view details, add a summary, draft a reply, and link it to the Executive Office or funding registers.</p>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Log Message Modal -->
<div class="modal-overlay" id="modalComms">
    <div class="modal modal-lg">
        <div class="modal-header"><h2 class="modal-title">Log Message</h2><button class="modal-close" onclick="closeModal('modalComms')">&times;</button></div>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <input type="hidden" name="action" value="create">
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group"><label class="form-label">Source</label><select name="source" class="form-control">
                        <option value="email">Email</option><option value="whatsapp">WhatsApp</option>
                    </select></div>
                    <div class="form-group"><label class="form-label">Date</label><input type="datetime-local" name="date" class="form-control" value="<?= date('Y-m-d\TH:i') ?>"></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label class="form-label">From Name</label><input type="text" name="from_name" class="form-control"></div>
                    <div class="form-group"><label class="form-label">From Address/Number</label><input type="text" name="from_address" class="form-control" placeholder="email or WhatsApp number"></div>
                </div>
                <div class="form-group"><label class="form-label">Subject / Preview</label><input type="text" name="subject" class="form-control"></div>
                <div class="form-group"><label class="form-label">Message Body</label><textarea name="body" class="form-control" rows="4"></textarea></div>
                <div class="form-group"><label class="flex gap-2 items-center cursor-pointer">
                    <input type="checkbox" name="is_pending" value="1" checked>
                    <span class="form-label" style="margin:0">Mark as pending matter</span>
                </label></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-outline" onclick="closeModal('modalComms')">Cancel</button><button type="submit" class="btn btn-primary">Log</button></div>
        </form>
    </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
