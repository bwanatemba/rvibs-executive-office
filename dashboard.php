<?php
$page_title = 'Dashboard';
require_once __DIR__ . '/includes/header.php';
$db = get_db();
$user = current_user();
$userId = $user['id'];
$role = $user['role_key'];

// ---- Stats ----
$myCommitments = $db->prepare("SELECT * FROM commitments WHERE owner_id = ? AND status NOT IN ('complete') ORDER BY due_date ASC");
$myCommitments->execute([$userId]);
$commitments = $myCommitments->fetchAll();

$overdueCount = $db->prepare("SELECT COUNT(*) as c FROM commitments WHERE owner_id = ? AND status NOT IN ('complete') AND due_date < CURDATE()");
$overdueCount->execute([$userId]);
$overdue = $overdueCount->fetch()['c'];

$openEscalations = $db->query("SELECT COUNT(*) as c FROM escalations WHERE status='open'")->fetch()['c'];

$activeOpportunities = $db->query("SELECT COUNT(*) as c FROM funding_opportunities WHERE status IN ('discovered','verified','eligible','pursuing','preparing','submitted','monitoring')")->fetch()['c'];

$pendingDecisions = $db->prepare("SELECT COUNT(*) as c FROM decisions WHERE status='pending' AND (owner_id = ? OR requires_director_approval = 1)");
$pendingDecisions->execute([$userId]);
$myPendingDecisions = $pendingDecisions->fetch()['c'];

$allPendingDecisions = $db->query("SELECT COUNT(*) as c FROM decisions WHERE status='pending'")->fetch()['c'];

$outcomes = $db->query("SELECT * FROM outcomes ORDER BY id LIMIT 5")->fetchAll();

$fundingPipeline = $db->query("
    SELECT fo.status, COUNT(*) as count
    FROM funding_opportunities fo
    GROUP BY fo.status
    ORDER BY FIELD(fo.status, 'discovered','verified','eligible','pursuing','preparing','submitted','monitoring','awarded','declined','closed')
")->fetchAll();

$recentActivity = $db->query("
    SELECT * FROM audit_log ORDER BY created_at DESC LIMIT 8
")->fetchAll();

// CEO three questions data
$decideItems = $db->query("
    (SELECT 'decision' as type, id, title, status, created_at FROM decisions WHERE status='pending' ORDER BY created_at DESC LIMIT 5)
    UNION ALL
    (SELECT 'escalation' as type, id, title, status, created_at FROM escalations WHERE status='open' ORDER BY priority DESC, created_at DESC LIMIT 5)
    ORDER BY created_at DESC LIMIT 8
")->fetchAll();

$myCommitted = $db->prepare("
    SELECT * FROM commitments WHERE owner_id = ? AND status NOT IN ('complete') ORDER BY due_date ASC LIMIT 5
");
$myCommitted->execute([$userId]);
$myCommittedItems = $myCommitted->fetchAll();

$materialProblems = $db->query("
    (SELECT 'escalation' as type, id, title, description, priority, created_at FROM escalations WHERE status='open' AND priority IN ('high','critical') ORDER BY priority DESC)
    UNION ALL
    (SELECT 'commitment' as type, id, title, blocker as description, 'critical' as priority, updated_at as created_at FROM commitments WHERE status='blocked' ORDER BY updated_at DESC)
    ORDER BY created_at DESC LIMIT 5
")->fetchAll();
?>

<!-- Welcome header -->
<div class="section-header mb-6">
    <div>
        <h2 class="section-title">Welcome back, <?= e(explode(' ', $user['name'])[0]) ?></h2>
        <p class="section-sub"><?= e($user['role_name'] ?? 'Role pending') ?> · <?= e(fmt_date(date('Y-m-d'))) ?></p>
    </div>
    <div class="flex gap-2">
        <a href="commitments.php?action=new" class="btn btn-outline btn-sm">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            New Commitment
        </a>
        <a href="funding-opportunities.php?action=new" class="btn btn-accent btn-sm">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            New Opportunity
        </a>
    </div>
</div>

<!-- Stat cards -->
<div class="grid grid-4 mb-6">
    <div class="stat-card">
        <div class="stat-icon <?= $overdue > 0 ? 'danger' : '' ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
        </div>
        <div class="stat-info">
            <div class="stat-value"><?= count($commitments) ?></div>
            <div class="stat-label">My Open Commitments</div>
            <?php if ($overdue > 0): ?>
            <div class="stat-sub" style="color: var(--c-danger)"><?= $overdue ?> overdue</div>
            <?php endif; ?>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon <?= $openEscalations > 0 ? 'warning' : 'success' ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
        </div>
        <div class="stat-info">
            <div class="stat-value"><?= $openEscalations ?></div>
            <div class="stat-label">Open Escalations</div>
            <div class="stat-sub">Material exceptions</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon accent">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        </div>
        <div class="stat-info">
            <div class="stat-value"><?= $activeOpportunities ?></div>
            <div class="stat-label">Active Funding</div>
            <div class="stat-sub">Opportunities in pipeline</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon <?= $allPendingDecisions > 0 ? 'warning' : 'success' ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 3l7.07 16.97 2.51-7.39 7.39-2.51z"/><path d="M13 13l6 6"/></svg>
        </div>
        <div class="stat-info">
            <div class="stat-value"><?= $allPendingDecisions ?></div>
            <div class="stat-label">Pending Decisions</div>
            <div class="stat-sub"><?= $myPendingDecisions ?> assigned to me</div>
        </div>
    </div>
</div>

<?php if (in_array($role, ['chairman_ceo', 'system_admin'])): ?>
<!-- CEO Three Questions -->
<div class="grid cols-1-2 mb-6">
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">What must I decide?</h3>
            <span class="badge badge-warning"><?= $allPendingDecisions ?> pending</span>
        </div>
        <div class="card-body">
            <?php if (empty($decideItems)): ?>
                <div class="empty-state" style="padding: 1.5rem;">
                    <p>No pending decisions. You're all caught up.</p>
                </div>
            <?php else: ?>
                <?php foreach ($decideItems as $item): ?>
                    <div class="list-item">
                        <div class="list-item-icon <?= $item['type'] === 'escalation' ? '' : '' ?>">
                            <?php if ($item['type'] === 'escalation'): ?>
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/></svg>
                            <?php else: ?>
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><path d="M3 3l7.07 16.97 2.51-7.39 7.39-2.51z"/></svg>
                            <?php endif; ?>
                        </div>
                        <div class="list-item-body">
                            <div class="list-item-title">
                                <a href="<?= $item['type'] === 'escalation' ? 'escalations.php?id=' . $item['id'] : 'decisions.php?id=' . $item['id'] ?>" class="cell-link"><?= e($item['title']) ?></a>
                            </div>
                            <div class="list-item-sub">
                                <?= $item['type'] === 'escalation' ? 'Escalation' : 'Decision' ?> · <?= e(fmt_datetime($item['created_at'])) ?>
                            </div>
                        </div>
                        <span class="badge <?= $item['type'] === 'escalation' ? 'badge-danger' : 'badge-warning' ?>"><?= status_label($item['status']) ?></span>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">What I've personally committed to</h3>
            <a href="commitments.php?filter=mine" class="text-sm">View all →</a>
        </div>
        <div class="card-body">
            <?php if (empty($myCommittedItems)): ?>
                <div class="empty-state" style="padding: 1.5rem;">
                    <p>No open personal commitments. Your commitments are subject to the same accountability as everyone else's.</p>
                </div>
            <?php else: ?>
                <?php foreach ($myCommittedItems as $c):
                    $days = days_until($c['due_date']);
                    $isOverdue = $days !== null && $days < 0;
                ?>
                    <div class="list-item">
                        <div class="list-item-icon <?= $isOverdue ? '' : '' ?>">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
                        </div>
                        <div class="list-item-body">
                            <div class="list-item-title">
                                <a href="commitments.php?id=<?= $c['id'] ?>" class="cell-link"><?= e($c['title']) ?></a>
                            </div>
                            <div class="list-item-sub">Due <?= e(fmt_date($c['due_date'])) ?>
                                <?php if ($isOverdue): ?> · <span style="color: var(--c-danger)"><?= abs($days) ?> days overdue</span>
                                <?php elseif ($days !== null && $days <= 3): ?> · <span style="color: var(--c-warning)"><?= $days == 0 ? 'Due today' : $days . ' day' . ($days > 1 ? 's' : '') . ' left' ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <span class="badge <?= status_badge($c['status']) ?>"><?= status_label($c['status']) ?></span>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Material problems requiring intervention -->
<div class="card mb-6">
    <div class="card-header">
        <h3 class="card-title">Material problems requiring my intervention</h3>
        <a href="escalations.php" class="text-sm">View all →</a>
    </div>
    <div class="card-body">
        <?php if (empty($materialProblems)): ?>
            <div class="empty-state" style="padding: 1.5rem;">
                <p>No material problems at this time.</p>
            </div>
        <?php else: ?>
            <div class="table-wrap" style="border: none;">
                <table class="data-table">
                    <thead>
                        <tr><th>Issue</th><th>Type</th><th>Priority</th><th>Detail</th><th>Raised</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($materialProblems as $p): ?>
                        <tr>
                            <td class="cell-title">
                                <a href="<?= $p['type'] === 'escalation' ? 'escalations.php?id='.$p['id'] : 'commitments.php?id='.$p['id'] ?>" class="cell-link"><?= e($p['title']) ?></a>
                            </td>
                            <td><span class="badge badge-neutral"><?= $p['type'] === 'escalation' ? 'Escalation' : 'Blocked' ?></span></td>
                            <td><span class="badge <?= status_badge($p['priority']) ?>"><?= status_label($p['priority']) ?></span></td>
                            <td class="text-sm text-2"><?= e(substr($p['description'] ?? '', 0, 100)) ?><?= strlen($p['description'] ?? '') > 100 ? '...' : '' ?></td>
                            <td class="text-sm text-3"><?= e(fmt_date($p['created_at'])) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<!-- 90-Day Outcomes -->
<div class="card mb-6">
    <div class="card-header">
        <h3 class="card-title">90-Day Outcomes</h3>
        <a href="outcomes.php" class="text-sm">Manage →</a>
    </div>
    <div class="card-body">
        <?php if (empty($outcomes)): ?>
            <div class="empty-state">
                <h3>No outcomes set</h3>
                <p>Define your three agreed 90-day outcomes with accountable owners and measurable progress.</p>
                <a href="outcomes.php?action=new" class="btn btn-primary btn-sm" style="margin-top: 1rem;">Add Outcome</a>
            </div>
        <?php else: ?>
            <div class="grid grid-3">
                <?php foreach ($outcomes as $o): ?>
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <span class="badge <?= status_badge($o['status']) ?>"><?= status_label($o['status']) ?></span>
                            <span class="text-xs text-3">Due <?= e(fmt_date($o['due_date'])) ?></span>
                        </div>
                        <div class="fw-600 text-sm mb-2"><?= e($o['title']) ?></div>
                        <div class="flex items-center gap-2 mb-2">
                            <div class="progress flex-1">
                                <div class="progress-bar <?= $o['progress'] >= 100 ? 'success' : ($o['status'] === 'at_risk' ? 'warning' : ($o['status'] === 'off_track' ? 'danger' : '')) ?>" style="width: <?= $o['progress'] ?>%"></div>
                            </div>
                            <span class="text-xs fw-600"><?= $o['progress'] ?>%</span>
                        </div>
                        <div class="text-xs text-3">Owner: <?= e(get_user_name($o['owner_id'])) ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Funding Pipeline + Recent Activity -->
<div class="grid cols-2-1 mb-6">
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Funding Pipeline</h3>
            <a href="funding-opportunities.php" class="text-sm">View all →</a>
        </div>
        <div class="card-body">
            <?php
            $stages = ['discovered' => 'Discovered', 'verified' => 'Verified', 'eligible' => 'Eligible', 'pursuing' => 'Pursuing', 'preparing' => 'Preparing', 'submitted' => 'Submitted', 'monitoring' => 'Monitoring', 'awarded' => 'Awarded', 'declined' => 'Declined', 'closed' => 'Closed'];
            $pipelineData = [];
            foreach ($fundingPipeline as $p) $pipelineData[$p['status']] = $p['count'];
            ?>
            <div class="grid" style="grid-template-columns: repeat(5, 1fr); gap: 0.5rem;">
                <?php foreach (array_slice(array_keys($stages), 0, 5) as $stage): ?>
                    <div style="text-align: center; padding: 0.75rem 0.25rem; border-radius: var(--radius); background: var(--c-surface-2);">
                        <div class="stat-value" style="font-size: 1.25rem; color: var(--c-primary);"><?= $pipelineData[$stage] ?? 0 ?></div>
                        <div class="text-xs text-2"><?= $stages[$stage] ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="grid" style="grid-template-columns: repeat(5, 1fr); gap: 0.5rem; margin-top: 0.5rem;">
                <?php foreach (array_slice(array_keys($stages), 5) as $stage): ?>
                    <div style="text-align: center; padding: 0.75rem 0.25rem; border-radius: var(--radius); background: var(--c-surface-2);">
                        <div class="stat-value" style="font-size: 1.25rem; color: var(--c-text-3);"><?= $pipelineData[$stage] ?? 0 ?></div>
                        <div class="text-xs text-2"><?= $stages[$stage] ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Recent Activity</h3>
        </div>
        <div class="card-body" style="padding: 0.5rem;">
            <?php if (empty($recentActivity)): ?>
                <div class="empty-state" style="padding: 1.5rem;">
                    <p>No recent activity.</p>
                </div>
            <?php else: ?>
                <div class="timeline">
                    <?php foreach ($recentActivity as $a): ?>
                        <div class="timeline-item">
                            <div class="timeline-dot"></div>
                            <div class="timeline-content">
                                <div class="timeline-title"><?= e($a['user_name'] ?? 'System') ?> · <?= e(ucwords(str_replace('_', ' ', $a['action']))) ?></div>
                                <div class="timeline-date"><?= e(fmt_datetime($a['created_at'])) ?></div>
                                <?php if ($a['entity_type']): ?>
                                    <div class="timeline-body"><?= e($a['entity_type']) ?> #<?= e($a['entity_id']) ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
