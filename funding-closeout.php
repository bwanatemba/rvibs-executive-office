<?php
$page_title = 'Delivery & Closeout';
require_once __DIR__ . '/includes/header.php';
$db = get_db();
$user = current_user();

$awardId = (int)($_GET['award_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'add_milestone') {
        $db->prepare('INSERT INTO funding_milestones (award_id, title, description, due_date, reporting_required, notes) VALUES (?,?,?,?,?,?)')
            ->execute([$_POST['award_id'], $_POST['title'], $_POST['description'] ?? null, $_POST['due_date'] ?: null, isset($_POST['reporting_required']) ? 1 : 0, $_POST['notes'] ?? null]);
        log_action('create', 'milestone', null, null, $_POST);
        toast_redirect('Milestone added', 'funding-closeout.php?award_id=' . $_POST['award_id']);
    } elseif ($action === 'update_milestone') {
        $id = (int)$_POST['id'];
        $status = $_POST['status'];
        $completed = $status === 'complete' ? 'NOW()' : 'NULL';
        $report = isset($_POST['report_submitted']) ? 1 : 0;
        $db->prepare("UPDATE funding_milestones SET status=?, report_submitted=?, notes=?, completed_at=$completed WHERE id=?")
            ->execute([$status, $report, $_POST['notes'] ?? null, $id]);
        log_action('update', 'milestone', $id);
        toast_redirect('Milestone updated', 'funding-closeout.php?award_id=' . $_POST['award_id']);
    } elseif ($action === 'delete_milestone') {
        $id = (int)$_POST['id'];
        $db->prepare('DELETE FROM funding_milestones WHERE id=?')->execute([$id]);
        toast_redirect('Milestone deleted', 'funding-closeout.php?award_id=' . $_POST['award_id']);
    } elseif ($action === 'update_closeout') {
        $awardId = (int)$_POST['award_id'];
        $exists = $db->prepare('SELECT id FROM funding_closeouts WHERE award_id=?');
        $exists->execute([$awardId]);
        if ($exists->fetch()) {
            $db->prepare('UPDATE funding_closeouts SET reconciliation_status=?, final_report_status=?, final_report_date=?, closeout_date=?, notes=? WHERE award_id=?')
                ->execute([$_POST['reconciliation_status'], $_POST['final_report_status'], $_POST['final_report_date'] ?: null, $_POST['closeout_date'] ?: null, $_POST['notes'] ?? null, $awardId]);
        } else {
            $db->prepare('INSERT INTO funding_closeouts (award_id, reconciliation_status, final_report_status, final_report_date, closeout_date, notes, completed_by) VALUES (?,?,?,?,?,?,?)')
                ->execute([$awardId, $_POST['reconciliation_status'], $_POST['final_report_status'], $_POST['final_report_date'] ?: null, $_POST['closeout_date'] ?: null, $_POST['notes'] ?? null, $user['id']]);
        }
        if ($_POST['closeout_date']) { $db->prepare('UPDATE funding_opportunities SET status="closed" WHERE id=(SELECT opportunity_id FROM funding_awards WHERE id=?)')->execute([$awardId]); }
        log_action('update', 'closeout', null, null, $_POST);
        toast_redirect('Closeout updated', 'funding-closeout.php?award_id=' . $awardId);
    }
}

// If no award_id, show list of all awards with closeout status
if (!$awardId) {
    $awards = $db->query("
        SELECT a.*, fo.title as opp_title, fo.funder,
               (SELECT COUNT(*) FROM funding_milestones WHERE award_id=a.id) as milestone_count,
               (SELECT COUNT(*) FROM funding_milestones WHERE award_id=a.id AND status='complete') as milestones_done,
               fc.reconciliation_status, fc.final_report_status, fc.closeout_date
        FROM funding_awards a
        LEFT JOIN funding_opportunities fo ON a.opportunity_id = fo.id
        LEFT JOIN funding_closeouts fc ON a.id = fc.award_id
        ORDER BY a.created_at DESC
    ")->fetchAll();
    $csrf = csrf_token();
    ?>
<div class="section-header">
    <div>
        <h2 class="section-title">Delivery & Closeout</h2>
        <p class="section-sub">Delivery milestones, reporting, reconciliation and closeout for awarded funding</p>
    </div>
</div>
<div class="card">
    <div class="table-wrap" style="border:none;">
        <?php if (empty($awards)): ?>
            <div class="empty-state">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                <h3>No awards to track</h3>
                <p>Delivery milestones, reporting, reconciliation and closeout are tracked once funding is awarded.</p>
            </div>
        <?php else: ?>
            <table class="data-table">
                <thead><tr><th>Award</th><th>Funder</th><th>Milestones</th><th>Reconciliation</th><th>Final Report</th><th>Closeout</th><th>Actions</th></tr></thead>
                <tbody>
                    <?php foreach ($awards as $a): ?>
                    <tr>
                        <td class="cell-title"><?= e($a['opp_title'] ?? 'Unknown') ?></td>
                        <td class="text-sm"><?= e($a['funder'] ?? '—') ?></td>
                        <td><span class="text-sm"><?= $a['milestones_done'] ?>/<?= $a['milestone_count'] ?></span> <?php if($a['milestone_count']>0): $pct=round($a['milestones_done']/$a['milestone_count']*100); ?><div class="progress mt-1"><div class="progress-bar <?= $pct>=100?'success':'' ?>" style="width:<?= $pct ?>%"></div></div><?php endif; ?></td>
                        <td><span class="badge <?= status_badge($a['reconciliation_status'] ?? 'pending') ?>"><?= status_label($a['reconciliation_status'] ?? 'pending') ?></span></td>
                        <td><span class="badge <?= status_badge($a['final_report_status'] ?? 'pending') ?>"><?= status_label($a['final_report_status'] ?? 'pending') ?></span></td>
                        <td class="text-sm"><?= e(fmt_date($a['closeout_date'])) ?></td>
                        <td><a href="funding-closeout.php?award_id=<?= $a['id'] ?>" class="btn btn-outline btn-sm">Manage</a></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>
<?php
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

// Detail view for a specific award
$stmt = $db->prepare("SELECT a.*, fo.title as opp_title, fo.funder, fo.amount, fo.currency
    FROM funding_awards a LEFT JOIN funding_opportunities fo ON a.opportunity_id = fo.id WHERE a.id=?");
$stmt->execute([$awardId]);
$award = $stmt->fetch();

$milestones = $db->prepare('SELECT * FROM funding_milestones WHERE award_id=? ORDER BY due_date');
$milestones->execute([$awardId]);
$milestones = $milestones->fetchAll();

$stmt = $db->prepare('SELECT * FROM funding_closeouts WHERE award_id=?');
$stmt->execute([$awardId]);
$closeout = $stmt->fetch();

$csrf = csrf_token();
?>

<div class="section-header">
    <div>
        <h2 class="section-title">Delivery & Closeout: <?= e($award['opp_title'] ?? 'Unknown') ?></h2>
        <p class="section-sub"><?= e($award['funder'] ?? '') ?> · <?= $award['amount'] ? e(number_format($award['amount'],0)) . ' ' . e($award['currency']) : '' ?></p>
    </div>
    <a href="funding-closeout.php" class="btn btn-sm btn-outline">← Back</a>
</div>

<div class="grid cols-2-1 mb-6">
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Delivery Milestones</h3>
            <button class="btn btn-primary btn-sm" onclick="openModal('modalMilestone')">Add Milestone</button>
        </div>
        <div class="card-body">
            <?php if (empty($milestones)): ?>
                <div class="empty-state" style="padding:1.5rem;"><p>No milestones yet. Add delivery milestones with due dates and reporting requirements.</p></div>
            <?php else: ?>
                <div class="timeline">
                    <?php foreach ($milestones as $m): ?>
                    <div class="timeline-item">
                        <div class="timeline-dot <?= $m['status']==='complete'?'success':($m['status']==='overdue'?'danger':($m['status']==='in_progress'?'accent':'')) ?>"></div>
                        <div class="timeline-content">
                            <div class="flex items-center justify-between">
                                <div class="timeline-title"><?= e($m['title']) ?></div>
                                <div class="flex gap-2">
                                    <span class="badge <?= status_badge($m['status']) ?>"><?= status_label($m['status']) ?></span>
                                    <?php if ($m['reporting_required']): ?><span class="badge badge-info">Report</span><?php endif; ?>
                                </div>
                            </div>
                            <div class="timeline-date">Due <?= e(fmt_date($m['due_date'])) ?><?php if ($m['completed_at']): ?> · Completed <?= e(fmt_date($m['completed_at'])) ?><?php endif; ?></div>
                            <?php if ($m['description']): ?><div class="timeline-body"><?= e($m['description']) ?></div><?php endif; ?>
                            <?php if ($m['notes']): ?><div class="text-xs text-2 mt-1">Notes: <?= e($m['notes']) ?></div><?php endif; ?>
                            <button class="btn btn-outline btn-sm mt-2" onclick="editMilestone(<?= htmlspecialchars(json_encode($m), ENT_QUOTES) ?>)">Update</button>
                            <form method="POST" style="display:inline" onsubmit="return confirm('Delete?')">
                                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                <input type="hidden" name="action" value="delete_milestone">
                                <input type="hidden" name="id" value="<?= $m['id'] ?>">
                                <input type="hidden" name="award_id" value="<?= $awardId ?>">
                                <button class="btn-ghost btn-sm">Delete</button>
                            </form>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h3 class="card-title">Closeout</h3></div>
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                <input type="hidden" name="action" value="update_closeout">
                <input type="hidden" name="award_id" value="<?= $awardId ?>">
                <div class="form-group"><label class="form-label">Reconciliation Status</label><select name="reconciliation_status" class="form-control">
                    <option value="pending" <?= ($closeout['reconciliation_status'] ?? '')==='pending'?'selected':'' ?>>Pending</option>
                    <option value="in_progress" <?= ($closeout['reconciliation_status'] ?? '')==='in_progress'?'selected':'' ?>>In Progress</option>
                    <option value="complete" <?= ($closeout['reconciliation_status'] ?? '')==='complete'?'selected':'' ?>>Complete</option>
                </select></div>
                <div class="form-group"><label class="form-label">Final Report Status</label><select name="final_report_status" class="form-control">
                    <option value="pending" <?= ($closeout['final_report_status'] ?? '')==='pending'?'selected':'' ?>>Pending</option>
                    <option value="draft" <?= ($closeout['final_report_status'] ?? '')==='draft'?'selected':'' ?>>Draft</option>
                    <option value="submitted" <?= ($closeout['final_report_status'] ?? '')==='submitted'?'selected':'' ?>>Submitted</option>
                    <option value="accepted" <?= ($closeout['final_report_status'] ?? '')==='accepted'?'selected':'' ?>>Accepted</option>
                </select></div>
                <div class="form-row">
                    <div class="form-group"><label class="form-label">Final Report Date</label><input type="date" name="final_report_date" class="form-control" value="<?= e($closeout['final_report_date'] ?? '') ?>"></div>
                    <div class="form-group"><label class="form-label">Closeout Date</label><input type="date" name="closeout_date" class="form-control" value="<?= e($closeout['closeout_date'] ?? '') ?>"></div>
                </div>
                <div class="form-group"><label class="form-label">Notes</label><textarea name="notes" class="form-control" rows="3"><?= e($closeout['notes'] ?? '') ?></textarea></div>
                <button type="submit" class="btn btn-primary btn-sm">Save Closeout</button>
            </form>
        </div>
    </div>
</div>

<!-- Add Milestone Modal -->
<div class="modal-overlay" id="modalMilestone">
    <div class="modal modal-lg">
        <div class="modal-header"><h2 class="modal-title">Add Delivery Milestone</h2><button class="modal-close" onclick="closeModal('modalMilestone')">&times;</button></div>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <input type="hidden" name="action" value="add_milestone">
            <input type="hidden" name="award_id" value="<?= $awardId ?>">
            <div class="modal-body">
                <div class="form-group"><label class="form-label">Title <span class="req">*</span></label><input type="text" name="title" class="form-control" required></div>
                <div class="form-row">
                    <div class="form-group"><label class="form-label">Due Date</label><input type="date" name="due_date" class="form-control"></div>
                    <div class="form-group"><label class="flex gap-2 items-center cursor-pointer" style="margin-top:1.75rem">
                        <input type="checkbox" name="reporting_required" value="1">
                        <span class="form-label" style="margin:0">Reporting required</span>
                    </label></div>
                </div>
                <div class="form-group"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="2"></textarea></div>
                <div class="form-group"><label class="form-label">Notes</label><textarea name="notes" class="form-control" rows="2"></textarea></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-outline" onclick="closeModal('modalMilestone')">Cancel</button><button type="submit" class="btn btn-primary">Add</button></div>
        </form>
    </div>
</div>

<!-- Edit Milestone Modal -->
<div class="modal-overlay" id="modalEditMilestone">
    <div class="modal">
        <div class="modal-header"><h2 class="modal-title">Update Milestone</h2><button class="modal-close" onclick="closeModal('modalEditMilestone')">&times;</button></div>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <input type="hidden" name="action" value="update_milestone">
            <input type="hidden" name="id" id="msId">
            <input type="hidden" name="award_id" value="<?= $awardId ?>">
            <div class="modal-body">
                <div class="form-group"><label class="form-label">Status</label><select name="status" class="form-control" id="msStatus">
                    <option value="pending">Pending</option><option value="in_progress">In Progress</option>
                    <option value="complete">Complete</option><option value="overdue">Overdue</option>
                </select></div>
                <div class="form-group"><label class="flex gap-2 items-center cursor-pointer">
                    <input type="checkbox" name="report_submitted" value="1" id="msReport">
                    <span class="form-label" style="margin:0">Report submitted</span>
                </label></div>
                <div class="form-group"><label class="form-label">Notes</label><textarea name="notes" class="form-control" rows="3" id="msNotes"></textarea></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-outline" onclick="closeModal('modalEditMilestone')">Cancel</button><button type="submit" class="btn btn-primary">Save</button></div>
        </form>
    </div>
</div>

<script>
function editMilestone(data) {
    document.getElementById('msId').value = data.id;
    document.getElementById('msStatus').value = data.status || 'pending';
    document.getElementById('msReport').checked = data.report_submitted == 1;
    document.getElementById('msNotes').value = data.notes || '';
    openModal('modalEditMilestone');
}
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
