<?php
$page_title = 'Funding Opportunities';
require_once __DIR__ . '/includes/auth.php';
require_login();
$db = get_db();
$user = current_user();

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'create') {
        $stmt = $db->prepare('INSERT INTO funding_opportunities (title, funder, url, source, amount, currency, deadline, description, type, status, discovered_by) VALUES (?,?,?,?,?,?,?,?,?,?,?)');
        $stmt->execute([$_POST['title'], $_POST['funder'] ?? null, $_POST['url'] ?? null, $_POST['source'] ?? null,
            $_POST['amount'] ?: null, $_POST['currency'] ?? 'KES', $_POST['deadline'] ?: null,
            $_POST['description'] ?? null, $_POST['type'] ?? 'grant', 'discovered', $user['id']]);
        $id = $db->lastInsertId();
        log_action('create', 'opportunity', $id, null, $_POST);
        toast_redirect('Opportunity discovered', 'funding-opportunities.php?id=' . $id);
    } elseif ($action === 'update') {
        $id = (int)$_POST['id'];
        $stmt = $db->prepare('UPDATE funding_opportunities SET title=?, funder=?, url=?, source=?, amount=?, currency=?, deadline=?, description=?, type=? WHERE id=?');
        $stmt->execute([$_POST['title'], $_POST['funder'] ?? null, $_POST['url'] ?? null, $_POST['source'] ?? null,
            $_POST['amount'] ?: null, $_POST['currency'] ?? 'KES', $_POST['deadline'] ?: null,
            $_POST['description'] ?? null, $_POST['type'] ?? 'grant', $id]);
        log_action('update', 'opportunity', $id);
        toast_redirect('Opportunity updated', 'funding-opportunities.php?id=' . $id);
    } elseif ($action === 'status') {
        $id = (int)$_POST['id'];
        $status = $_POST['status'];
        $db->prepare('UPDATE funding_opportunities SET status=? WHERE id=?')->execute([$status, $id]);
        if ($status === 'verified') { $db->prepare('UPDATE funding_opportunities SET verified_by=?, verified_at=NOW() WHERE id=?')->execute([$user['id'], $id]); }
        log_action('status_change', 'opportunity', $id, null, ['status' => $status]);
        toast_redirect('Status updated', 'funding-opportunities.php?id=' . $id);
    } elseif ($action === 'eligibility_check') {
        $id = (int)$_POST['opportunity_id'];
        $db->prepare('INSERT INTO funding_eligibility_checks (opportunity_id, check_type, result, notes, checked_by) VALUES (?,?,?,?,?)')
            ->execute([$id, $_POST['check_type'], $_POST['result'], $_POST['notes'] ?? null, $user['id']]);
        log_action('eligibility_check', 'opportunity', $id, null, $_POST);
        toast_redirect('Eligibility check added', 'funding-opportunities.php?id=' . $id);
    } elseif ($action === 'preparation') {
        $id = (int)$_POST['opportunity_id'];
        $exists = $db->prepare('SELECT id FROM funding_preparations WHERE opportunity_id=?');
        $exists->execute([$id]);
        if ($exists->fetch()) {
            $db->prepare('UPDATE funding_preparations SET owner_id=?, resources_allocated=?, capacity_note=?, next_action=?, next_action_date=?, status=? WHERE opportunity_id=?')
                ->execute([$_POST['owner_id'] ?: null, $_POST['resources_allocated'] ?? null, $_POST['capacity_note'] ?? null, $_POST['next_action'] ?? null, $_POST['next_action_date'] ?: null, $_POST['prep_status'] ?? 'in_progress', $id]);
        } else {
            $db->prepare('INSERT INTO funding_preparations (opportunity_id, owner_id, resources_allocated, capacity_note, next_action, next_action_date, status) VALUES (?,?,?,?,?,?,?)')
                ->execute([$id, $_POST['owner_id'] ?: null, $_POST['resources_allocated'] ?? null, $_POST['capacity_note'] ?? null, $_POST['next_action'] ?? null, $_POST['next_action_date'] ?: null, $_POST['prep_status'] ?? 'in_progress']);
        }
        log_action('preparation', 'opportunity', $id);
        toast_redirect('Preparation updated', 'funding-opportunities.php?id=' . $id);
    } elseif ($action === 'delete') {
        $id = (int)$_POST['id'];
        $db->prepare('DELETE FROM funding_opportunities WHERE id=?')->execute([$id]);
        log_action('delete', 'opportunity', $id);
        toast_redirect('Opportunity deleted', 'funding-opportunities.php');
    }
}

$view = $_GET['view'] ?? 'pipeline';
$detailId = (int)($_GET['id'] ?? 0);

// Pipeline data
$pipelineStages = ['discovered' => 'Discovered', 'verified' => 'Verified', 'eligible' => 'Eligible', 'pursuing' => 'Pursuing', 'preparing' => 'Preparing', 'submitted' => 'Submitted', 'monitoring' => 'Monitoring', 'awarded' => 'Awarded', 'declined' => 'Declined', 'closed' => 'Closed'];
$opportunities = $db->query('SELECT fo.*, u.name as discovered_by_name FROM funding_opportunities fo LEFT JOIN users u ON fo.discovered_by = u.id ORDER BY fo.created_at DESC')->fetchAll();

// Detail view
$detail = null;
$eligibilityChecks = [];
$preparation = null;
if ($detailId) {
    $stmt = $db->prepare('SELECT fo.*, u.name as discovered_by_name, v.name as verified_by_name FROM funding_opportunities fo LEFT JOIN users u ON fo.discovered_by = u.id LEFT JOIN users v ON fo.verified_by = v.id WHERE fo.id=?');
    $stmt->execute([$detailId]);
    $detail = $stmt->fetch();
    if ($detail) {
        $stmt = $db->prepare('SELECT ec.*, u.name as checked_by_name FROM funding_eligibility_checks ec LEFT JOIN users u ON ec.checked_by = u.id WHERE ec.opportunity_id=? ORDER BY ec.checked_at DESC');
        $stmt->execute([$detailId]);
        $eligibilityChecks = $stmt->fetchAll();
        $stmt = $db->prepare('SELECT fp.*, u.name as owner_name FROM funding_preparations fp LEFT JOIN users u ON fp.owner_id = u.id WHERE fp.opportunity_id=?');
        $stmt->execute([$detailId]);
        $preparation = $stmt->fetch();
    }
}
$users_list = get_users_list();
$csrf = csrf_token();
$readiness = $db->query('SELECT * FROM funding_readiness WHERE status="ready" OR status="in_progress" ORDER BY institution')->fetchAll();
require_once __DIR__ . '/includes/header.php';
?>

<div class="section-header">
    <div>
        <h2 class="section-title">Funding Opportunities</h2>
        <p class="section-sub">Discovery, verification, eligibility checks, and pursue/monitor/decline decisions</p>
    </div>
    <div class="flex gap-2">
        <button class="btn btn-outline btn-sm <?= $view==='pipeline'?'':'hidden' ?>" onclick="location.href='funding-opportunities.php'">Pipeline View</button>
        <button class="btn btn-primary" onclick="openModal('modalOpportunity')">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            New Opportunity
        </button>
    </div>
</div>

<?php if ($detail): ?>
<!-- DETAIL VIEW -->
<div class="card mb-6">
    <div class="card-header">
        <div class="flex items-center gap-2">
            <h3 class="card-title"><?= e($detail['title']) ?></h3>
            <span class="badge <?= status_badge($detail['status']) ?>"><?= status_label($detail['status']) ?></span>
        </div>
        <a href="funding-opportunities.php" class="btn btn-sm btn-outline">← Back to pipeline</a>
    </div>
    <div class="card-body">
        <div class="grid cols-2-1 mb-4">
            <div>
                <div class="grid grid-2 gap-2 mb-3">
                    <div><span class="text-xs text-3">Funder:</span><div class="text-sm fw-600"><?= e($detail['funder'] ?? '—') ?></div></div>
                    <div><span class="text-xs text-3">Type:</span><div class="text-sm fw-600"><?= status_label($detail['type']) ?></div></div>
                    <div><span class="text-xs text-3">Amount:</span><div class="text-sm fw-600"><?= $detail['amount'] ? e(number_format($detail['amount'])) . ' ' . e($detail['currency']) : '—' ?></div></div>
                    <div><span class="text-xs text-3">Deadline:</span><div class="text-sm fw-600"><?= e(fmt_date($detail['deadline'])) ?> <?php if($detail['deadline']): $d=days_until($detail['deadline']); if($d!==null&&$d<14) echo '<span style="color:var(--c-warning)">('.$d.'d)</span>'; endif; ?></div></div>
                </div>
                <?php if ($detail['url']): ?><div class="mb-2"><a href="<?= e($detail['url']) ?>" target="_blank" class="cell-link text-sm">View source ↗</a></div><?php endif; ?>
                <?php if ($detail['description']): ?><div class="text-sm text-2 mt-2"><?= nl2br(e($detail['description'])) ?></div><?php endif; ?>
                <div class="text-xs text-3 mt-2">Discovered by <?= e($detail['discovered_by_name'] ?? 'Unknown') ?><?php if($detail['verified_by_name']): ?> · Verified by <?= e($detail['verified_by_name']) ?><?php endif; ?></div>
            </div>
            <div>
                <h4 class="text-sm fw-600 mb-2">Status</h4>
                <form method="POST" class="flex gap-2">
                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                    <input type="hidden" name="action" value="status">
                    <input type="hidden" name="id" value="<?= $detail['id'] ?>">
                    <select name="status" class="form-control">
                        <?php foreach ($pipelineStages as $key => $label): ?>
                            <option value="<?= $key ?>" <?= $detail['status']===$key?'selected':'' ?>><?= $label ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button class="btn btn-primary btn-sm">Update</button>
                </form>
                <div class="flex gap-2 mt-2">
                    <button class="btn btn-outline btn-sm" onclick="editOpportunity(<?= htmlspecialchars(json_encode($detail), ENT_QUOTES) ?>)">Edit Details</button>
                    <form method="POST" style="display:inline" data-delete-confirm="Delete?" onsubmit="return confirmDeleteForm(this, event)">
                        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= $detail['id'] ?>">
                        <button class="btn-ghost btn-sm">Delete</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Eligibility Checks -->
        <div class="card mb-4" style="border-color: var(--c-border);">
            <div class="card-header">
                <h4 class="card-title">Eligibility Checks</h4>
                <button class="btn btn-outline btn-sm" onclick="openModal('modalEligibility')">Add Check</button>
            </div>
            <div class="card-body">
                <?php if (empty($eligibilityChecks)): ?>
                    <p class="text-sm text-3">No eligibility checks yet. Verify against official sources before pursuing.</p>
                <?php else: ?>
                    <div class="grid grid-2">
                        <?php foreach ($eligibilityChecks as $ec): ?>
                            <div class="list-item">
                                <span class="badge <?= status_badge($ec['result']) ?>"><?= status_label($ec['result']) ?></span>
                                <div class="list-item-body">
                                    <div class="list-item-title text-sm"><?= status_label($ec['check_type']) ?></div>
                                    <?php if ($ec['notes']): ?><div class="text-xs text-2 mt-1"><?= e($ec['notes']) ?></div><?php endif; ?>
                                    <div class="text-xs text-3 mt-1">Checked by <?= e($ec['checked_by_name'] ?? 'Unknown') ?> · <?= e(fmt_date($ec['checked_at'])) ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Preparation Capacity -->
        <div class="card" style="border-color: var(--c-border);">
            <div class="card-header">
                <h4 class="card-title">Preparation Capacity</h4>
                <button class="btn btn-outline btn-sm" onclick="openModal('modalPreparation')"><?= $preparation ? 'Edit' : 'Set Up' ?></button>
            </div>
            <div class="card-body">
                <?php if (!$preparation): ?>
                    <p class="text-sm text-3">No preparation plan yet. Assign an owner, allocate resources, and set the next action before pursuing.</p>
                <?php else: ?>
                    <div class="grid grid-2 gap-2">
                        <div><span class="text-xs text-3">Owner:</span><div class="text-sm fw-600"><?= e($preparation['owner_name'] ?? 'Unassigned') ?></div></div>
                        <div><span class="text-xs text-3">Status:</span><span class="badge <?= status_badge($preparation['status']) ?>"><?= status_label($preparation['status']) ?></span></div>
                        <div><span class="text-xs text-3">Resources Allocated:</span><div class="text-sm"><?= e($preparation['resources_allocated'] ?? '—') ?></div></div>
                        <div><span class="text-xs text-3">Capacity Note:</span><div class="text-sm"><?= e($preparation['capacity_note'] ?? '—') ?></div></div>
                        <div><span class="text-xs text-3">Next Action:</span><div class="text-sm fw-600"><?= e($preparation['next_action'] ?? '—') ?></div></div>
                        <div><span class="text-xs text-3">Next Action Date:</span><div class="text-sm fw-600"><?= e(fmt_date($preparation['next_action_date'])) ?></div></div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php else: ?>
<!-- PIPELINE VIEW -->
<div class="pipeline">
    <?php
    $stages = ['discovered' => 'Discovered', 'verified' => 'Verified', 'eligible' => 'Eligible', 'pursuing' => 'Pursuing', 'preparing' => 'Preparing', 'submitted' => 'Submitted', 'monitoring' => 'Monitoring', 'awarded' => 'Awarded', 'declined' => 'Declined'];
    foreach ($stages as $key => $label):
        $stageItems = array_filter($opportunities, fn($o) => $o['status'] === $key);
    ?>
    <div class="pipeline-col">
        <div class="pipeline-col-header">
            <span class="pipeline-col-title"><?= $label ?></span>
            <span class="pipeline-col-count"><?= count($stageItems) ?></span>
        </div>
        <?php if (empty($stageItems)): ?>
            <div class="text-center text-3 text-sm" style="padding: 2rem 1rem; border: 1px dashed var(--c-border); border-radius: var(--radius);">—</div>
        <?php else: ?>
            <?php foreach ($stageItems as $o): ?>
                <div class="pipeline-card cursor-pointer" onclick="location.href='funding-opportunities.php?id=<?= $o['id'] ?>'">
                    <div class="fw-600 text-sm mb-1"><?= e($o['title']) ?></div>
                    <div class="text-xs text-3 mb-1"><?= e($o['funder'] ?? 'Unknown funder') ?></div>
                    <div class="flex items-center justify-between mt-2">
                        <span class="badge badge-neutral"><?= status_label($o['type']) ?></span>
                        <?php if ($o['amount']): ?><span class="text-xs fw-600"><?= e(number_format($o['amount'], 0)) ?> <?= e($o['currency']) ?></span><?php endif; ?>
                    </div>
                    <?php if ($o['deadline']): $d = days_until($o['deadline']); ?>
                        <div class="text-xs mt-1" style="color: <?= $d!==null&&$d<0?'var(--c-danger)':($d!==null&&$d<14?'var(--c-warning)':'var(--c-text-3)') ?>">Due <?= e(fmt_date($o['deadline'])) ?></div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
</div>

<?php if (empty($opportunities)): ?>
<div class="card mt-4">
    <div class="card-body">
        <div class="empty-state">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <h3>No opportunities yet</h3>
            <p>Discover funding opportunities and verify them against official sources. Each opportunity goes through eligibility checks before a pursue, monitor or decline decision.</p>
            <button class="btn btn-primary mt-4" onclick="openModal('modalOpportunity')">Add First Opportunity</button>
        </div>
    </div>
</div>
<?php endif; ?>
<?php endif; ?>

<!-- Modals -->
<div class="modal-overlay" id="modalOpportunity">
    <div class="modal modal-lg">
        <div class="modal-header"><h2 class="modal-title" id="oppModalTitle">New Opportunity</h2><button class="modal-close" onclick="closeModal('modalOpportunity')">&times;</button></div>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <input type="hidden" name="action" id="oppAction" value="create">
            <input type="hidden" name="id" id="oppId">
            <div class="modal-body">
                <div class="form-group"><label class="form-label">Title <span class="req">*</span></label><input type="text" name="title" class="form-control" required></div>
                <div class="form-row">
                    <div class="form-group"><label class="form-label">Funder</label><input type="text" name="funder" class="form-control"></div>
                    <div class="form-group"><label class="form-label">Source</label><input type="text" name="source" class="form-control" placeholder="Where did you find this?"></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label class="form-label">Amount</label><input type="number" name="amount" class="form-control" step="0.01"></div>
                    <div class="form-group"><label class="form-label">Currency</label><select name="currency" class="form-control"><option>KES</option><option>USD</option><option>EUR</option><option>GBP</option></select></div>
                    <div class="form-group"><label class="form-label">Deadline</label><input type="date" name="deadline" class="form-control"></div>
                </div>
                <div class="form-group"><label class="form-label">URL</label><input type="url" name="url" class="form-control" placeholder="Official source link"></div>
                <div class="form-group"><label class="form-label">Type</label><select name="type" class="form-control">
                    <option value="grant">Grant</option><option value="equipment_donation">Equipment Donation</option>
                    <option value="staff_exchange">Staff Exchange</option><option value="student_exchange">Student Exchange</option><option value="partnership">Partnership</option>
                </select></div>
                <div class="form-group"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="3"></textarea></div>
                <div class="alert alert-info">
                    Donated equipment must be assessed for transport, installation, training and maintenance costs — not treated as "free" merely because it is donated.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('modalOpportunity')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save</button>
            </div>
        </form>
    </div>
</div>

<div class="modal-overlay" id="modalEligibility">
    <div class="modal">
        <div class="modal-header"><h2 class="modal-title">Add Eligibility Check</h2><button class="modal-close" onclick="closeModal('modalEligibility')">&times;</button></div>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <input type="hidden" name="action" value="eligibility_check">
            <input type="hidden" name="opportunity_id" value="<?= $detailId ?>">
            <div class="modal-body">
                <div class="form-group"><label class="form-label">Check Type</label><select name="check_type" class="form-control">
                    <option value="eligibility">Eligibility</option><option value="strategic_fit">Strategic Fit</option>
                    <option value="readiness">Readiness</option><option value="evidence_confidence">Evidence Confidence</option>
                </select></div>
                <div class="form-group"><label class="form-label">Result</label><select name="result" class="form-control">
                    <option value="pass">Pass</option><option value="fail">Fail</option><option value="conditional">Conditional</option>
                </select></div>
                <div class="form-group"><label class="form-label">Notes</label><textarea name="notes" class="form-control" rows="3" placeholder="What was checked? What was found?"></textarea></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-outline" onclick="closeModal('modalEligibility')">Cancel</button><button type="submit" class="btn btn-primary">Save</button></div>
        </form>
    </div>
</div>

<div class="modal-overlay" id="modalPreparation">
    <div class="modal modal-lg">
        <div class="modal-header"><h2 class="modal-title">Preparation Capacity</h2><button class="modal-close" onclick="closeModal('modalPreparation')">&times;</button></div>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <input type="hidden" name="action" value="preparation">
            <input type="hidden" name="opportunity_id" value="<?= $detailId ?>">
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group"><label class="form-label">Named Owner</label><select name="owner_id" class="form-control">
                        <option value="">Unassigned</option>
                        <?php foreach ($users_list as $u): ?><option value="<?= $u['id'] ?>" <?= $preparation&&$preparation['owner_id']==$u['id']?'selected':'' ?>><?= e($u['name']) ?></option><?php endforeach; ?>
                    </select></div>
                    <div class="form-group"><label class="form-label">Status</label><select name="prep_status" class="form-control">
                        <option value="not_started" <?= $preparation&&$preparation['status']=='not_started'?'selected':'' ?>>Not Started</option>
                        <option value="in_progress" <?= $preparation&&$preparation['status']=='in_progress'?'selected':'' ?>>In Progress</option>
                        <option value="ready" <?= $preparation&&$preparation['status']=='ready'?'selected':'' ?>>Ready</option>
                        <option value="blocked" <?= $preparation&&$preparation['status']=='blocked'?'selected':'' ?>>Blocked</option>
                    </select></div>
                </div>
                <div class="form-group"><label class="form-label">Resources Allocated</label><textarea name="resources_allocated" class="form-control" rows="2"><?= e($preparation['resources_allocated'] ?? '') ?></textarea></div>
                <div class="form-group"><label class="form-label">Capacity Note</label><textarea name="capacity_note" class="form-control" rows="2" placeholder="Is there capacity to prepare this application?"><?= e($preparation['capacity_note'] ?? '') ?></textarea></div>
                <div class="form-row">
                    <div class="form-group"><label class="form-label">Next Action</label><input type="text" name="next_action" class="form-control" value="<?= e($preparation['next_action'] ?? '') ?>"></div>
                    <div class="form-group"><label class="form-label">Next Action Date</label><input type="date" name="next_action_date" class="form-control" value="<?= e(($preparation['next_action_date'] ?? '')) ?>"></div>
                </div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-outline" onclick="closeModal('modalPreparation')">Cancel</button><button type="submit" class="btn btn-primary">Save</button></div>
        </form>
    </div>
</div>

<script>
function editOpportunity(data) {
    document.getElementById('oppModalTitle').textContent = 'Edit Opportunity';
    document.getElementById('oppAction').value = 'update';
    document.getElementById('oppId').value = data.id;
    const form = document.querySelector('#modalOpportunity form');
    form.querySelector('[name="title"]').value = data.title || '';
    form.querySelector('[name="funder"]').value = data.funder || '';
    form.querySelector('[name="source"]').value = data.source || '';
    form.querySelector('[name="amount"]').value = data.amount || '';
    form.querySelector('[name="currency"]').value = data.currency || 'KES';
    form.querySelector('[name="deadline"]').value = (data.deadline || '').split(' ')[0];
    form.querySelector('[name="url"]').value = data.url || '';
    form.querySelector('[name="type"]').value = data.type || 'grant';
    form.querySelector('[name="description"]').value = data.description || '';
    openModal('modalOpportunity');
}
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
