<?php
$page_title = 'Awards & Disbursements';
require_once __DIR__ . '/includes/auth.php';
require_login();
$db = get_db();
$user = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'update_award') {
        $id = (int)$_POST['id'];
        $accepted = isset($_POST['accepted']) ? 1 : 0;
        $acceptedBy = $accepted ? $user['id'] : null;
        $acceptedAt = $accepted ? 'NOW()' : 'NULL';
        $db->prepare("UPDATE funding_awards SET conditions=?, financial_obligations=?, notes=?, accepted=?, accepted_by=$acceptedBy, accepted_at=" . ($accepted ? 'NOW()' : 'NULL') . " WHERE id=?")
            ->execute([$_POST['conditions'] ?? null, $_POST['financial_obligations'] ?? null, $_POST['notes'] ?? null, $accepted, $id]);
        log_action('update', 'award', $id);
        toast_redirect('Award updated', 'funding-awards.php?id=' . $id);
    } elseif ($action === 'add_disbursement') {
        $id = (int)$_POST['award_id'];
        $db->prepare('INSERT INTO funding_disbursements (award_id, type, description, amount, value, date_received, recipient_costs, notes) VALUES (?,?,?,?,?,?,?,?)')
            ->execute([$id, $_POST['type'], $_POST['description'] ?? null, $_POST['amount'] ?: null, $_POST['value'] ?: null, $_POST['date_received'] ?: null, $_POST['recipient_costs'] ?? null, $_POST['notes'] ?? null]);
        log_action('create', 'disbursement', null, null, $_POST);
        toast_redirect('Disbursement recorded', 'funding-awards.php?id=' . $id);
    } elseif ($action === 'validate_disbursement') {
        $id = (int)$_POST['id'];
        $db->prepare('UPDATE funding_disbursements SET validated_by=?, validated_at=NOW() WHERE id=?')->execute([$user['id'], $id]);
        log_action('validate', 'disbursement', $id);
        toast_redirect('Disbursement validated', 'funding-awards.php?id=' . $_POST['award_id']);
    } elseif ($action === 'delete_disbursement') {
        $id = (int)$_POST['id'];
        $db->prepare('DELETE FROM funding_disbursements WHERE id=?')->execute([$id]);
        toast_redirect('Disbursement deleted', 'funding-awards.php?id=' . $_POST['award_id']);
    }
}

$detailId = (int)($_GET['id'] ?? 0);
$awards = $db->query("
    SELECT a.*, fo.title as opp_title, fo.funder, s.id as submission_id,
           acc.name as accepted_by_name
    FROM funding_awards a
    LEFT JOIN funding_opportunities fo ON a.opportunity_id = fo.id
    LEFT JOIN funding_submissions s ON a.submission_id = s.id
    LEFT JOIN users acc ON a.accepted_by = acc.id
    ORDER BY a.created_at DESC
")->fetchAll();

$detail = null;
$disbursements = [];
$amounts = ['cash' => 0, 'in_kind' => 0];
if ($detailId) {
    $stmt = $db->prepare("SELECT a.*, fo.title as opp_title, fo.funder, fo.type as opp_type,
           acc.name as accepted_by_name
           FROM funding_awards a
           LEFT JOIN funding_opportunities fo ON a.opportunity_id = fo.id
           LEFT JOIN users acc ON a.accepted_by = acc.id
           WHERE a.id=?");
    $stmt->execute([$detailId]);
    $detail = $stmt->fetch();
    if ($detail) {
        $stmt = $db->prepare('SELECT d.*, v.name as validated_by_name FROM funding_disbursements d LEFT JOIN users v ON d.validated_by=v.id WHERE d.award_id=? ORDER BY d.date_received DESC');
        $stmt->execute([$detailId]);
        $disbursements = $stmt->fetchAll();
        foreach ($disbursements as $d) {
            if ($d['type'] === 'cash') $amounts['cash'] += $d['amount'] ?? 0;
            else $amounts['in_kind'] += $d['value'] ?? 0;
        }
    }
}
$csrf = csrf_token();
$canFinance = can('can_manage_finance');
require_once __DIR__ . '/includes/header.php';
?>

<div class="section-header">
    <div>
        <h2 class="section-title">Awards & Disbursements</h2>
        <p class="section-sub">Award conditions, acceptance, financial obligations. Cash and in-kind support recorded separately.</p>
    </div>
</div>

<?php if ($detail): ?>
<!-- DETAIL VIEW -->
<div class="card mb-6">
    <div class="card-header">
        <h3 class="card-title">Award: <?= e($detail['opp_title'] ?? 'Unknown') ?></h3>
        <div class="flex gap-2">
            <span class="badge <?= $detail['accepted'] ? 'badge-success' : 'badge-warning' ?>"><?= $detail['accepted'] ? 'Accepted' : 'Pending Acceptance' ?></span>
            <a href="funding-awards.php" class="btn btn-sm btn-outline">← Back</a>
        </div>
    </div>
    <div class="card-body">
        <div class="grid grid-4 mb-4">
            <div class="text-center p-3" style="border-radius:var(--radius); background:var(--c-surface-2);">
                <div class="text-xs text-3 mb-1">Award Amount</div>
                <div class="fw-600"><?= $detail['amount'] ? e(number_format($detail['amount'], 0)) . ' ' . e($detail['currency']) : '—' ?></div>
            </div>
            <div class="text-center p-3" style="border-radius:var(--radius); background:var(--c-surface-2);">
                <div class="text-xs text-3 mb-1">Cash Received</div>
                <div class="fw-600" style="color:var(--c-success)"><?= e(number_format($amounts['cash'], 0)) ?> <?= e($detail['currency']) ?></div>
            </div>
            <div class="text-center p-3" style="border-radius:var(--radius); background:var(--c-surface-2);">
                <div class="text-xs text-3 mb-1">In-Kind Value</div>
                <div class="fw-600" style="color:var(--c-info)"><?= e(number_format($amounts['in_kind'], 0)) ?> <?= e($detail['currency']) ?></div>
            </div>
            <div class="text-center p-3" style="border-radius:var(--radius); background:var(--c-surface-2);">
                <div class="text-xs text-3 mb-1">Funder</div>
                <div class="fw-600 text-sm"><?= e($detail['funder'] ?? '—') ?></div>
            </div>
        </div>

        <div class="grid cols-2-1 mb-4">
            <div>
                <?php if ($detail['conditions']): ?>
                <div class="mb-3"><strong>Conditions:</strong><p class="text-sm text-2 mt-1"><?= nl2br(e($detail['conditions'])) ?></p></div>
                <?php endif; ?>
                <?php if ($detail['financial_obligations']): ?>
                <div class="mb-3"><strong>Financial Obligations:</strong><p class="text-sm text-2 mt-1"><?= nl2br(e($detail['financial_obligations'])) ?></p></div>
                <?php endif; ?>
                <?php if ($detail['notes']): ?>
                <div class="mb-3"><strong>Notes:</strong><p class="text-sm text-2 mt-1"><?= nl2br(e($detail['notes'])) ?></p></div>
                <?php endif; ?>
                <?php if ($detail['accepted_by_name']): ?>
                <div class="text-xs text-3">Accepted by <?= e($detail['accepted_by_name']) ?> on <?= e(fmt_datetime($detail['accepted_at'])) ?></div>
                <?php endif; ?>
            </div>
            <div>
                <button class="btn btn-outline btn-sm w-full mb-2" onclick="openModal('modalEditAward')">Edit Award Details</button>
                <a href="funding-closeout.php?award_id=<?= $detail['id'] ?>" class="btn btn-primary btn-sm w-full">View Delivery & Closeout</a>
            </div>
        </div>

        <h4 class="text-sm fw-600 mb-3">Disbursements</h4>
        <?php if (empty($disbursements)): ?>
            <p class="text-sm text-3">No disbursements recorded. Cash and in-kind support are recorded separately.</p>
        <?php else: ?>
            <div class="table-wrap" style="border:none;">
                <table class="data-table">
                    <thead><tr><th>Type</th><th>Description</th><th>Amount/Value</th><th>Date Received</th><th>Recipient Costs</th><th>Validated</th><th>Actions</th></tr></thead>
                    <tbody>
                        <?php foreach ($disbursements as $d): ?>
                        <tr>
                            <td><span class="badge <?= status_badge($d['type']) ?>"><?= status_label($d['type']) ?></span></td>
                            <td class="text-sm"><?= e($d['description'] ?? '—') ?></td>
                            <td class="text-sm fw-600"><?= $d['type'] === 'cash' ? ($d['amount'] ? number_format($d['amount']) : '—') : ($d['value'] ? number_format($d['value']) : '—') ?> <?= e($detail['currency']) ?></td>
                            <td class="text-sm"><?= e(fmt_date($d['date_received'])) ?></td>
                            <td class="text-sm"><?= e($d['recipient_costs'] ?? '—') ?></td>
                            <td><?php if ($d['validated_by_name']): ?><span class="badge badge-success">✓ <?= e($d['validated_by_name']) ?></span><?php else: ?><span class="badge badge-neutral">Pending</span><?php endif; ?></td>
                            <td>
                                <div class="flex gap-1">
                                    <?php if (!$d['validated_by_name'] && $canFinance): ?>
                                    <form method="POST" style="display:inline">
                                        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                        <input type="hidden" name="action" value="validate_disbursement">
                                        <input type="hidden" name="id" value="<?= $d['id'] ?>">
                                        <input type="hidden" name="award_id" value="<?= $detailId ?>">
                                        <button class="btn btn-primary btn-sm">Validate</button>
                                    </form>
                                    <?php endif; ?>
                                    <form method="POST" style="display:inline" data-delete-confirm="Delete?" onsubmit="return confirmDeleteForm(this, event)">
                                        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                        <input type="hidden" name="action" value="delete_disbursement">
                                        <input type="hidden" name="id" value="<?= $d['id'] ?>">
                                        <input type="hidden" name="award_id" value="<?= $detailId ?>">
                                        <button class="btn-ghost btn-sm"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><polyline points="3 6 5 6 21 6"/></svg></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
        <button class="btn btn-primary btn-sm mt-3" onclick="openModal('modalDisbursement')">Add Disbursement</button>
    </div>
</div>

<!-- Edit Award Modal -->
<div class="modal-overlay" id="modalEditAward">
    <div class="modal modal-lg">
        <div class="modal-header"><h2 class="modal-title">Edit Award Details</h2><button class="modal-close" onclick="closeModal('modalEditAward')">&times;</button></div>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <input type="hidden" name="action" value="update_award">
            <input type="hidden" name="id" value="<?= $detailId ?>">
            <div class="modal-body">
                <div class="form-group"><label class="form-label">Award Conditions</label><textarea name="conditions" class="form-control" rows="3"><?= e($detail['conditions'] ?? '') ?></textarea></div>
                <div class="form-group"><label class="form-label">Financial Obligations</label><textarea name="financial_obligations" class="form-control" rows="3"><?= e($detail['financial_obligations'] ?? '') ?></textarea></div>
                <div class="form-group"><label class="flex gap-2 items-center cursor-pointer">
                    <input type="checkbox" name="accepted" value="1" <?= $detail['accepted'] ? 'checked' : '' ?>>
                    <span class="form-label" style="margin:0">Award Accepted</span>
                </label></div>
                <div class="form-group"><label class="form-label">Notes</label><textarea name="notes" class="form-control" rows="2"><?= e($detail['notes'] ?? '') ?></textarea></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-outline" onclick="closeModal('modalEditAward')">Cancel</button><button type="submit" class="btn btn-primary">Save</button></div>
        </form>
    </div>
</div>

<!-- Add Disbursement Modal -->
<div class="modal-overlay" id="modalDisbursement">
    <div class="modal modal-lg">
        <div class="modal-header"><h2 class="modal-title">Add Disbursement</h2><button class="modal-close" onclick="closeModal('modalDisbursement')">&times;</button></div>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <input type="hidden" name="action" value="add_disbursement">
            <input type="hidden" name="award_id" value="<?= $detailId ?>">
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group"><label class="form-label">Type</label><select name="type" class="form-control">
                        <option value="cash">Cash</option><option value="in_kind">In-Kind</option>
                    </select></div>
                    <div class="form-group"><label class="form-label">Date Received</label><input type="date" name="date_received" class="form-control" value="<?= date('Y-m-d') ?>"></div>
                </div>
                <div class="form-group"><label class="form-label">Description</label><input type="text" name="description" class="form-control" placeholder="e.g. First tranche payment / Laptops donation"></div>
                <div class="form-row">
                    <div class="form-group"><label class="form-label">Cash Amount</label><input type="number" name="amount" class="form-control" step="0.01"></div>
                    <div class="form-group"><label class="form-label">In-Kind Value</label><input type="number" name="value" class="form-control" step="0.01"></div>
                </div>
                <div class="form-group"><label class="form-label">Recipient Costs</label><textarea name="recipient_costs" class="form-control" rows="2" placeholder="Transport, installation, training, maintenance costs for in-kind support"></textarea></div>
                <div class="alert alert-info">Donated equipment must be assessed for transport, installation, training and maintenance costs — not treated as "free" because it is donated.</div>
                <div class="form-group"><label class="form-label">Notes</label><textarea name="notes" class="form-control" rows="2"></textarea></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-outline" onclick="closeModal('modalDisbursement')">Cancel</button><button type="submit" class="btn btn-primary">Record</button></div>
        </form>
    </div>
</div>

<?php else: ?>
<!-- LIST VIEW -->
<div class="card">
    <div class="table-wrap" style="border:none;">
        <?php if (empty($awards)): ?>
            <div class="empty-state">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="8" r="7"/><polyline points="8.21 13.89 7 23 12 20 17 23 15.79 13.88"/></svg>
                <h3>No awards yet</h3>
                <p>Awards are created automatically when a funding application outcome is marked as "Awarded". Track award conditions, acceptance, and disbursements here.</p>
            </div>
        <?php else: ?>
            <table class="data-table">
                <thead><tr><th>Opportunity</th><th>Funder</th><th>Amount</th><th>Accepted</th><th>Actions</th></tr></thead>
                <tbody>
                    <?php foreach ($awards as $a): ?>
                    <tr>
                        <td class="cell-title"><a href="funding-awards.php?id=<?= $a['id'] ?>" class="cell-link"><?= e($a['opp_title'] ?? 'Unknown') ?></a></td>
                        <td class="text-sm"><?= e($a['funder'] ?? '—') ?></td>
                        <td class="text-sm fw-600"><?= $a['amount'] ? e(number_format($a['amount'], 0)) . ' ' . e($a['currency']) : '—' ?></td>
                        <td><span class="badge <?= $a['accepted'] ? 'badge-success' : 'badge-warning' ?>"><?= $a['accepted'] ? 'Accepted' : 'Pending' ?></span></td>
                        <td><a href="funding-awards.php?id=<?= $a['id'] ?>" class="btn btn-outline btn-sm">View</a></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
