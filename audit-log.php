<?php
$page_title = 'Audit Log';
require_role('system_admin');
require_once __DIR__ . '/includes/header.php';
$db = get_db();

$filter = $_GET['action'] ?? '';
$where = '';
if ($filter) { $where = 'WHERE action = ' . $db->quote($filter); }

$logs = $db->query("SELECT * FROM audit_log $where ORDER BY created_at DESC LIMIT 200")->fetchAll();
$actions = $db->query("SELECT DISTINCT action FROM audit_log ORDER BY action")->fetchAll(PDO::FETCH_COLUMN);
?>

<div class="section-header">
    <div>
        <h2 class="section-title">Audit Log</h2>
        <p class="section-sub">Complete audit trail of all actions taken in the system</p>
    </div>
</div>

<div class="filter-bar">
    <a href="audit-log.php" class="btn btn-sm <?= !$filter ? 'btn-primary' : 'btn-outline' ?>">All Actions</a>
    <?php foreach ($actions as $a): ?>
        <a href="audit-log.php?action=<?= e($a) ?>" class="badge <?= $filter === $a ? 'badge-primary' : 'badge-neutral' ?>"><?= e(ucwords(str_replace('_', ' ', $a))) ?></a>
    <?php endforeach; ?>
</div>

<div class="card">
    <div class="table-wrap" style="border:none;">
        <?php if (empty($logs)): ?>
            <div class="empty-state">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M12 8v4l4 2"/><circle cx="12" cy="12" r="10"/></svg>
                <h3>No audit entries</h3>
                <p>Audit log entries will appear here as users interact with the system.</p>
            </div>
        <?php else: ?>
            <table class="data-table">
                <thead><tr><th>When</th><th>User</th><th>Action</th><th>Entity</th><th>IP</th></tr></thead>
                <tbody>
                    <?php foreach ($logs as $l): ?>
                    <tr>
                        <td class="text-sm text-3"><?= e(fmt_datetime($l['created_at'])) ?></td>
                        <td class="text-sm"><?= e($l['user_name'] ?? 'System') ?></td>
                        <td><span class="badge badge-primary"><?= e(ucwords(str_replace('_', ' ', $l['action']))) ?></span></td>
                        <td class="text-sm"><?= $l['entity_type'] ? e($l['entity_type']) . ' #' . e($l['entity_id']) : '—' ?></td>
                        <td class="text-sm text-3"><?= e($l['ip_address'] ?? '—') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
