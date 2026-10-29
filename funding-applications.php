<?php
$page_title = 'Applications';
require_once __DIR__ . '/includes/auth.php';
require_login();
$db = get_db();
$user = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'create') {
        $stmt = $db->prepare('INSERT INTO funding_applications (opportunity_id, title, notes) VALUES (?,?,?)');
        $stmt->execute([$_POST['opportunity_id'], $_POST['title'] ?? null, $_POST['notes'] ?? null]);
        $id = $db->lastInsertId();
        // Update opportunity status
        $db->prepare('UPDATE funding_opportunities SET status="submitted" WHERE id=?')->execute([$_POST['opportunity_id']]);
        log_action('create', 'application', $id);
        toast_redirect('Application created', 'funding-applications.php?id=' . $id);
    } elseif ($action === 'review') {
        $id = (int)$_POST['id'];
        $reviewType = $_POST['review_type'];
        $status = $_POST['review_status'];
        $notes = $_POST['review_notes'] ?? null;
        $reviewerCol = $reviewType === 'technical' ? 'technical_review_by' : 'financial_review_by';
        $statusCol = $reviewType === 'technical' ? 'technical_review_status' : 'financial_review_status';
        $notesCol = $reviewType === 'technical' ? 'technical_review_notes' : 'financial_review_notes';
        $atCol = $reviewType === 'technical' ? 'technical_review_at' : 'financial_review_at';
        $db->prepare("UPDATE funding_applications SET $statusCol=?, $reviewerCol=?, $atCol=NOW(), $notesCol=? WHERE id=?")
            ->execute([$status, $user['id'], $notes, $id]);
        log_action($reviewType.'_review', 'application', $id);
        toast_redirect(ucfirst($reviewType) . ' review saved', 'funding-applications.php?id=' . $id);
    } elseif ($action === 'approve') {
        $id = (int)$_POST['id'];
        $db->prepare('UPDATE funding_applications SET approval_status="approved", approved_by=?, approved_at=NOW(), submission_package=? WHERE id=?')
            ->execute([$user['id'], $_POST['submission_package'] ?? null, $id]);
        log_action('approve', 'application', $id);
        toast_redirect('Application approved for submission', 'funding-applications.php?id=' . $id);
    } elseif ($action === 'submit') {
        $id = (int)$_POST['id'];
        // Get application to find opportunity
        $app = $db->prepare('SELECT * FROM funding_applications WHERE id=?');
        $app->execute([$id]);
        $appData = $app->fetch();
        $stmt = $db->prepare('INSERT INTO funding_submissions (application_id, submitted_by, receipt_reference, receipt_date, follow_up_date, outcome) VALUES (?,?,?,?,?,?)');
        $stmt->execute([$id, $user['id'], $_POST['receipt_reference'] ?? null, $_POST['receipt_date'] ?: null, $_POST['follow_up_date'] ?: null, 'pending']);
        $db->prepare('UPDATE funding_opportunities SET status="monitoring" WHERE id=?')->execute([$appData['opportunity_id']]);
        log_action('submit', 'application', $id);
        toast_redirect('Submission recorded', 'funding-applications.php?id=' . $id);
    } elseif ($action === 'outcome') {
        $subId = (int)$_POST['submission_id'];
        $outcome = $_POST['outcome'];
        $db->prepare('UPDATE funding_submissions SET outcome=?, outcome_date=?, outcome_notes=? WHERE id=?')
            ->execute([$outcome, $_POST['outcome_date'] ?: null, $_POST['outcome_notes'] ?? null, $subId]);
        // If awarded, create award record
        if ($outcome === 'awarded') {
            $sub = $db->prepare('SELECT s.*, a.opportunity_id, fo.amount, fo.currency FROM funding_submissions s JOIN funding_applications a ON s.application_id=a.id JOIN funding_opportunities fo ON a.opportunity_id=fo.id WHERE s.id=?');
            $sub->execute([$subId]);
            $subData = $sub->fetch();
            if ($subData) {
                $db->prepare('INSERT INTO funding_awards (submission_id, opportunity_id, amount, currency) VALUES (?,?,?,?)')
                    ->execute([$subId, $subData['opportunity_id'], $subData['amount'], $subData['currency']]);
                $db->prepare('UPDATE funding_opportunities SET status="awarded" WHERE id=?')->execute([$subData['opportunity_id']]);
            }
        } elseif ($outcome === 'declined') {
            $sub = $db->prepare('SELECT a.opportunity_id FROM funding_submissions s JOIN funding_applications a ON s.application_id=a.id WHERE s.id=?');
            $sub->execute([$subId]);
            $subData = $sub->fetch();
            if ($subData) { $db->prepare('UPDATE funding_opportunities SET status="declined" WHERE id=?')->execute([$subData['opportunity_id']]); }
        }
        log_action('outcome', 'submission', $subId);
        toast_redirect('Outcome recorded', 'funding-applications.php?id=' . (int)$_POST['app_id']);
    } elseif ($action === 'delete') {
        $id = (int)$_POST['id'];
        $db->prepare('DELETE FROM funding_applications WHERE id=?')->execute([$id]);
        log_action('delete', 'application', $id);
        toast_redirect('Application deleted', 'funding-applications.php');
    }
}

$detailId = (int)($_GET['id'] ?? 0);
$applications = $db->query("
    SELECT fa.*, fo.title as opp_title, fo.funder, fo.amount as opp_amount, fo.currency,
           tr.name as tech_reviewer, fr.name as fin_reviewer, ap.name as approver
    FROM funding_applications fa
    LEFT JOIN funding_opportunities fo ON fa.opportunity_id = fo.id
    LEFT JOIN users tr ON fa.technical_review_by = tr.id
    LEFT JOIN users fr ON fa.financial_review_by = fr.id
    LEFT JOIN users ap ON fa.approved_by = ap.id
    ORDER BY fa.created_at DESC
")->fetchAll();

$availableOpps = $db->query("SELECT id, title, funder FROM funding_opportunities WHERE status IN ('pursuing','preparing','eligible') ORDER BY title")->fetchAll();
$csrf = csrf_token();
$canFinance = can('can_manage_finance');
$canApprove = can('can_approve') || has_role('system_admin', 'chairman_ceo');

$detail = null;
$submissions = [];
if ($detailId) {
    $stmt = $db->prepare("SELECT fa.*, fo.title as opp_title, fo.funder, fo.amount as opp_amount, fo.currency,
           tr.name as tech_reviewer, fr.name as fin_reviewer, ap.name as approver
           FROM funding_applications fa
           LEFT JOIN funding_opportunities fo ON fa.opportunity_id = fo.id
           LEFT JOIN users tr ON fa.technical_review_by = tr.id
           LEFT JOIN users fr ON fa.financial_review_by = fr.id
           LEFT JOIN users ap ON fa.approved_by = ap.id
           WHERE fa.id=?");
    $stmt->execute([$detailId]);
    $detail = $stmt->fetch();
    if ($detail) {
        $stmt = $db->prepare("SELECT s.*, sb.name as submitted_by_name FROM funding_submissions s LEFT JOIN users sb ON s.submitted_by=sb.id WHERE s.application_id=? ORDER BY s.submitted_at DESC");
        $stmt->execute([$detailId]);
        $submissions = $stmt->fetchAll();
    }
}
require_once __DIR__ . '/includes/header.php';
?>

<div class="section-header">
    <div>
        <h2 class="section-title">Applications</h2>
        <p class="section-sub">Application preparation, technical review, financial review, approval and submission</p>
    </div>
    <?php if (!empty($availableOpps)): ?>
    <button class="btn btn-primary" onclick="openModal('modalApp')">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        New Application
    </button>
    <?php endif; ?>
</div>

<?php if ($detail): ?>
<!-- DETAIL VIEW -->
<div class="card mb-6">
    <div class="card-header">
        <div class="flex items-center gap-2">
            <h3 class="card-title"><?= e($detail['title'] ?: 'Application for ' . ($detail['opp_title'] ?? '')) ?></h3>
        </div>
        <a href="funding-applications.php" class="btn btn-sm btn-outline">← Back</a>
    </div>
    <div class="card-body">
        <div class="grid grid-4 mb-4">
            <div class="text-center p-3" style="border-radius:var(--radius); background:var(--c-surface-2);">
                <div class="text-xs text-3 mb-1">Technical Review</div>
                <span class="badge <?= status_badge($detail['technical_review_status']) ?>"><?= status_label($detail['technical_review_status']) ?></span>
                <?php if ($detail['tech_reviewer']): ?><div class="text-xs text-3 mt-1"><?= e($detail['tech_reviewer']) ?></div><?php endif; ?>
            </div>
            <div class="text-center p-3" style="border-radius:var(--radius); background:var(--c-surface-2);">
                <div class="text-xs text-3 mb-1">Financial Review</div>
                <span class="badge <?= status_badge($detail['financial_review_status']) ?>"><?= status_label($detail['financial_review_status']) ?></span>
                <?php if ($detail['fin_reviewer']): ?><div class="text-xs text-3 mt-1"><?= e($detail['fin_reviewer']) ?></div><?php endif; ?>
            </div>
            <div class="text-center p-3" style="border-radius:var(--radius); background:var(--c-surface-2);">
                <div class="text-xs text-3 mb-1">Approval</div>
                <span class="badge <?= status_badge($detail['approval_status']) ?>"><?= status_label($detail['approval_status']) ?></span>
                <?php if ($detail['approver']): ?><div class="text-xs text-3 mt-1"><?= e($detail['approver']) ?></div><?php endif; ?>
            </div>
            <div class="text-center p-3" style="border-radius:var(--radius); background:var(--c-surface-2);">
                <div class="text-xs text-3 mb-1">Opportunity</div>
                <a href="funding-opportunities.php?id=<?= $detail['opportunity_id'] ?>" class="text-sm cell-link"><?= e($detail['opp_title'] ?? '') ?></a>
                <?php if ($detail['opp_amount']): ?><div class="text-xs text-3 mt-1"><?= e(number_format($detail['opp_amount'], 0)) ?> <?= e($detail['currency']) ?></div><?php endif; ?>
            </div>
        </div>

        <div class="grid cols-2-1">
            <div>
                <!-- Technical Review -->
                <div class="card mb-3" style="border-color:var(--c-border);">
                    <div class="card-header"><h4 class="card-title">Technical Review</h4></div>
                    <div class="card-body">
                        <?php if ($detail['technical_review_notes']): ?><p class="text-sm text-2 mb-2"><?= nl2br(e($detail['technical_review_notes'])) ?></p><?php endif; ?>
                        <button class="btn btn-outline btn-sm" onclick="openModal('modalTechReview')">Update Technical Review</button>
                    </div>
                </div>
                <!-- Financial Review -->
                <div class="card mb-3" style="border-color:var(--c-border);">
                    <div class="card-header"><h4 class="card-title">Financial Review</h4></div>
                    <div class="card-body">
                        <?php if ($detail['financial_review_notes']): ?><p class="text-sm text-2 mb-2"><?= nl2br(e($detail['financial_review_notes'])) ?></p><?php endif; ?>
                        <?php if ($canFinance || $canApprove): ?>
                        <button class="btn btn-outline btn-sm" onclick="openModal('modalFinReview')">Update Financial Review</button>
                        <?php else: ?>
                        <p class="text-xs text-3">Financial review is restricted to Finance and Director roles.</p>
                        <?php endif; ?>
                    </div>
                </div>
                <!-- Approval -->
                <div class="card" style="border-color:var(--c-border);">
                    <div class="card-header"><h4 class="card-title">Approval & Submission Package</h4></div>
                    <div class="card-body">
                        <?php if ($detail['submission_package']): ?>
                            <div class="alert alert-success mb-2"><strong>Approved package:</strong> <?= e($detail['submission_package']) ?></div>
                        <?php endif; ?>
                        <?php if ($detail['approval_status'] !== 'approved' && $canApprove && $detail['technical_review_status'] === 'approved' && $detail['financial_review_status'] === 'approved'): ?>
                            <button class="btn btn-primary btn-sm" onclick="openModal('modalApprove')">Approve for Submission</button>
                        <?php elseif ($detail['approval_status'] !== 'approved' && $canApprove): ?>
                            <p class="text-xs text-3">Both technical and financial reviews must be approved before submission approval.</p>
                        <?php elseif ($detail['approval_status'] === 'approved' && empty($submissions)): ?>
                            <button class="btn btn-primary btn-sm" onclick="openModal('modalSubmit')">Record Submission</button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div>
                <h4 class="text-sm fw-600 mb-2">Submissions & Outcomes</h4>
                <?php if (empty($submissions)): ?>
                    <p class="text-sm text-3">No submissions recorded yet.</p>
                <?php else: ?>
                    <?php foreach ($submissions as $s): ?>
                        <div class="card mb-2" style="border-color:var(--c-border);">
                            <div class="card-body" style="padding:0.875rem;">
                                <div class="flex items-center justify-between mb-1">
                                    <span class="badge <?= status_badge($s['outcome']) ?>"><?= status_label($s['outcome']) ?></span>
                                    <span class="text-xs text-3"><?= e(fmt_date($s['receipt_date'] ?? $s['submitted_at'])) ?></span>
                                </div>
                                <?php if ($s['receipt_reference']): ?><div class="text-xs">Ref: <?= e($s['receipt_reference']) ?></div><?php endif; ?>
                                <?php if ($s['outcome_notes']): ?><div class="text-xs text-2 mt-1"><?= e($s['outcome_notes']) ?></div><?php endif; ?>
                                <?php if ($s['outcome'] === 'pending' && $canApprove): ?>
                                <button class="btn btn-outline btn-sm mt-2" onclick="recordOutcome(<?= $s['id'] ?>, <?= $detail['id'] ?>)">Record Outcome</button>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php else: ?>
<!-- LIST VIEW -->
<div class="card">
    <div class="table-wrap" style="border:none;">
        <?php if (empty($applications)): ?>
            <div class="empty-state">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                <h3>No applications yet</h3>
                <p>Applications are created from opportunities that are being pursued. Each application goes through technical review, financial review, and approval before submission.</p>
            </div>
        <?php else: ?>
            <table class="data-table">
                <thead><tr><th>Application</th><th>Opportunity</th><th>Amount</th><th>Technical</th><th>Financial</th><th>Approval</th><th>Actions</th></tr></thead>
                <tbody>
                    <?php foreach ($applications as $a): ?>
                    <tr>
                        <td class="cell-title"><a href="funding-applications.php?id=<?= $a['id'] ?>" class="cell-link"><?= e($a['title'] ?: 'Application') ?></a></td>
                        <td class="text-sm"><?= e($a['opp_title'] ?? '—') ?> <?= e($a['funder'] ? '(' . $a['funder'] . ')' : '') ?></td>
                        <td class="text-sm"><?= $a['opp_amount'] ? e(number_format($a['opp_amount'], 0)) . ' ' . e($a['currency']) : '—' ?></td>
                        <td><span class="badge <?= status_badge($a['technical_review_status']) ?>"><?= status_label($a['technical_review_status']) ?></span></td>
                        <td><span class="badge <?= status_badge($a['financial_review_status']) ?>"><?= status_label($a['financial_review_status']) ?></span></td>
                        <td><span class="badge <?= status_badge($a['approval_status']) ?>"><?= status_label($a['approval_status']) ?></span></td>
                        <td><a href="funding-applications.php?id=<?= $a['id'] ?>" class="btn btn-outline btn-sm">View</a></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<!-- Modals -->
<div class="modal-overlay" id="modalApp">
    <div class="modal">
        <div class="modal-header"><h2 class="modal-title">New Application</h2><button class="modal-close" onclick="closeModal('modalApp')">&times;</button></div>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <input type="hidden" name="action" value="create">
            <div class="modal-body">
                <div class="form-group"><label class="form-label">Opportunity <span class="req">*</span></label>
                    <select name="opportunity_id" class="form-control" required>
                        <?php foreach ($availableOpps as $o): ?><option value="<?= $o['id'] ?>"><?= e($o['title']) ?> (<?= e($o['funder'] ?? 'Unknown') ?>)</option><?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group"><label class="form-label">Application Title</label><input type="text" name="title" class="form-control" placeholder="Optional custom title"></div>
                <div class="form-group"><label class="form-label">Notes</label><textarea name="notes" class="form-control" rows="2"></textarea></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-outline" onclick="closeModal('modalApp')">Cancel</button><button type="submit" class="btn btn-primary">Create</button></div>
        </form>
    </div>
</div>

<div class="modal-overlay" id="modalTechReview">
    <div class="modal">
        <div class="modal-header"><h2 class="modal-title">Technical Review</h2><button class="modal-close" onclick="closeModal('modalTechReview')">&times;</button></div>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <input type="hidden" name="action" value="review">
            <input type="hidden" name="id" value="<?= $detailId ?>">
            <input type="hidden" name="review_type" value="technical">
            <div class="modal-body">
                <div class="form-group"><label class="form-label">Status</label><select name="review_status" class="form-control">
                    <option value="pending">Pending</option><option value="in_review">In Review</option>
                    <option value="approved">Approved</option><option value="changes_required">Changes Required</option>
                </select></div>
                <div class="form-group"><label class="form-label">Review Notes</label><textarea name="review_notes" class="form-control" rows="4" placeholder="Technical assessment of the application"><?= e($detail['technical_review_notes'] ?? '') ?></textarea></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-outline" onclick="closeModal('modalTechReview')">Cancel</button><button type="submit" class="btn btn-primary">Save Review</button></div>
        </form>
    </div>
</div>

<div class="modal-overlay" id="modalFinReview">
    <div class="modal">
        <div class="modal-header"><h2 class="modal-title">Financial Review</h2><button class="modal-close" onclick="closeModal('modalFinReview')">&times;</button></div>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <input type="hidden" name="action" value="review">
            <input type="hidden" name="id" value="<?= $detailId ?>">
            <input type="hidden" name="review_type" value="financial">
            <div class="modal-body">
                <div class="form-group"><label class="form-label">Status</label><select name="review_status" class="form-control">
                    <option value="pending">Pending</option><option value="in_review">In Review</option>
                    <option value="approved">Approved</option><option value="changes_required">Changes Required</option>
                </select></div>
                <div class="form-group"><label class="form-label">Review Notes</label><textarea name="review_notes" class="form-control" rows="4" placeholder="Financial assessment of the budget"><?= e($detail['financial_review_notes'] ?? '') ?></textarea></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-outline" onclick="closeModal('modalFinReview')">Cancel</button><button type="submit" class="btn btn-primary">Save Review</button></div>
        </form>
    </div>
</div>

<div class="modal-overlay" id="modalApprove">
    <div class="modal">
        <div class="modal-header"><h2 class="modal-title">Approve for Submission</h2><button class="modal-close" onclick="closeModal('modalApprove')">&times;</button></div>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <input type="hidden" name="action" value="approve">
            <input type="hidden" name="id" value="<?= $detailId ?>">
            <div class="modal-body">
                <div class="form-group"><label class="form-label">Submission Package Description</label><textarea name="submission_package" class="form-control" rows="3" placeholder="Describe the exact submission package being approved (documents, attachments, budget version, etc.)"></textarea></div>
                <div class="alert alert-info">This approves the exact submission package. Finance must have approved the financial review.</div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-outline" onclick="closeModal('modalApprove')">Cancel</button><button type="submit" class="btn btn-primary">Approve</button></div>
        </form>
    </div>
</div>

<div class="modal-overlay" id="modalSubmit">
    <div class="modal">
        <div class="modal-header"><h2 class="modal-title">Record Submission</h2><button class="modal-close" onclick="closeModal('modalSubmit')">&times;</button></div>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <input type="hidden" name="action" value="submit">
            <input type="hidden" name="id" value="<?= $detailId ?>">
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group"><label class="form-label">Receipt Reference</label><input type="text" name="receipt_reference" class="form-control" placeholder="Confirmation/reference number"></div>
                    <div class="form-group"><label class="form-label">Receipt Date</label><input type="date" name="receipt_date" class="form-control" value="<?= date('Y-m-d') ?>"></div>
                </div>
                <div class="form-group"><label class="form-label">Follow-up Date</label><input type="date" name="follow_up_date" class="form-control"></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-outline" onclick="closeModal('modalSubmit')">Cancel</button><button type="submit" class="btn btn-primary">Record</button></div>
        </form>
    </div>
</div>

<div class="modal-overlay" id="modalOutcome">
    <div class="modal">
        <div class="modal-header"><h2 class="modal-title">Record Outcome</h2><button class="modal-close" onclick="closeModal('modalOutcome')">&times;</button></div>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <input type="hidden" name="action" value="outcome">
            <input type="hidden" name="submission_id" id="outcomeSubId">
            <input type="hidden" name="app_id" id="outcomeAppId">
            <div class="modal-body">
                <div class="form-group"><label class="form-label">Outcome</label><select name="outcome" class="form-control">
                    <option value="pending">Pending</option><option value="awarded">Awarded</option>
                    <option value="declined">Declined</option><option value="withdrawn">Withdrawn</option>
                </select></div>
                <div class="form-group"><label class="form-label">Outcome Date</label><input type="date" name="outcome_date" class="form-control" value="<?= date('Y-m-d') ?>"></div>
                <div class="form-group"><label class="form-label">Outcome Notes</label><textarea name="outcome_notes" class="form-control" rows="3"></textarea></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-outline" onclick="closeModal('modalOutcome')">Cancel</button><button type="submit" class="btn btn-primary">Record</button></div>
        </form>
    </div>
</div>

<script>
function recordOutcome(subId, appId) {
    document.getElementById('outcomeSubId').value = subId;
    document.getElementById('outcomeAppId').value = appId;
    openModal('modalOutcome');
}
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
