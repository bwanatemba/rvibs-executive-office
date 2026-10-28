<?php
$page_title = 'User Management';
require_role('system_admin');
require_once __DIR__ . '/includes/header.php';
$db = get_db();
$user = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'assign_role') {
        $id = (int)$_POST['user_id'];
        $roleId = (int)$_POST['role_id'];
        $status = $_POST['status'] ?? 'active';
        $db->prepare('UPDATE users SET role_id=?, status=? WHERE id=?')->execute([$roleId, $status, $id]);
        log_action('assign_role', 'user', $id, null, ['role_id' => $roleId, 'status' => $status]);
        toast_redirect('Role assigned', 'admin-users.php');
    } elseif ($action === 'toggle_status') {
        $id = (int)$_POST['user_id'];
        $status = $_POST['status'];
        $db->prepare('UPDATE users SET status=? WHERE id=?')->execute([$status, $id]);
        log_action('toggle_status', 'user', $id, null, ['status' => $status]);
        toast_redirect('User status updated', 'admin-users.php');
    } elseif ($action === 'delete') {
        $id = (int)$_POST['user_id'];
        if ($id == $user['id']) { toast_redirect('Cannot delete yourself', 'admin-users.php', 'error'); }
        $db->prepare('DELETE FROM users WHERE id=?')->execute([$id]);
        log_action('delete', 'user', $id);
        toast_redirect('User deleted', 'admin-users.php');
    }
}

$users = $db->query('SELECT u.*, r.role_key, r.name AS role_name FROM users u LEFT JOIN roles r ON u.role_id = r.id ORDER BY r.sort_order, u.name')->fetchAll();
$roles = $db->query('SELECT * FROM roles ORDER BY sort_order')->fetchAll();
$csrf = csrf_token();
?>

<div class="section-header">
    <div>
        <h2 class="section-title">User Management</h2>
        <p class="section-sub">Assign roles to users. System admin assigns Chairman & CEO, Executive Coordinator, RVIBS Principal, ATS Lead, and Finance roles.</p>
    </div>
</div>

<div class="card">
    <div class="table-wrap" style="border:none;">
        <table class="data-table">
            <thead><tr><th>User</th><th>Email</th><th>Role</th><th>Status</th><th>Last Login</th><th>Actions</th></tr></thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                <tr>
                    <td class="cell-title"><?= e($u['name']) ?>
                        <?php if ($u['id'] == $user['id']): ?><span class="badge badge-primary ml-2">You</span><?php endif; ?>
                    </td>
                    <td class="text-sm"><?= e($u['email']) ?></td>
                    <td>
                        <select class="form-control" style="min-width:180px" onchange="assignRole(<?= $u['id'] ?>, this.value, '<?= $csrf ?>')">
                            <option value="0" <?= !$u['role_id'] ? 'selected' : '' ?>>No Role</option>
                            <?php foreach ($roles as $r): ?>
                                <option value="<?= $r['id'] ?>" <?= $u['role_id'] == $r['id'] ? 'selected' : '' ?>><?= e($r['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td>
                        <select class="form-control" style="min-width:120px" onchange="toggleStatus(<?= $u['id'] ?>, this.value, '<?= $csrf ?>')">
                            <option value="pending" <?= $u['status']==='pending'?'selected':'' ?>>Pending</option>
                            <option value="active" <?= $u['status']==='active'?'selected':'' ?>>Active</option>
                            <option value="inactive" <?= $u['status']==='inactive'?'selected':'' ?>>Inactive</option>
                        </select>
                    </td>
                    <td class="text-sm text-3"><?= e(fmt_datetime($u['last_login'])) ?></td>
                    <td>
                        <?php if ($u['email'] !== 'support@rvibs.ac.ke' && $u['id'] != $user['id']): ?>
                        <form method="POST" style="display:inline" onsubmit="return confirm('Delete this user?')">
                            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                            <button class="btn-ghost btn-sm"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/></svg></button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="grid grid-3 mt-6">
    <?php foreach ($roles as $r): ?>
        <div class="card">
            <div class="card-body">
                <h3 class="fw-600 text-sm mb-2" style="font-family:var(--font-heading)"><?= e($r['name']) ?></h3>
                <p class="text-xs text-2"><?= e($r['description']) ?></p>
                <div class="flex gap-2 flex-wrap mt-2">
                    <?php if ($r['can_manage_users']): ?><span class="badge badge-primary">Manage Users</span><?php endif; ?>
                    <?php if ($r['can_view_all']): ?><span class="badge badge-info">View All</span><?php endif; ?>
                    <?php if ($r['can_approve']): ?><span class="badge badge-success">Approve</span><?php endif; ?>
                    <?php if ($r['can_manage_finance']): ?><span class="badge badge-warning">Finance</span><?php endif; ?>
                    <?php if ($r['can_manage_funding']): ?><span class="badge badge-accent">Funding</span><?php endif; ?>
                    <?php if ($r['can_coordinate']): ?><span class="badge badge-neutral">Coordinate</span><?php endif; ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<form id="assignForm" method="POST" style="display:none;">
    <input type="hidden" name="csrf_token">
    <input type="hidden" name="action" value="assign_role">
    <input type="hidden" name="user_id">
    <input type="hidden" name="role_id">
</form>
<form id="statusForm" method="POST" style="display:none;">
    <input type="hidden" name="csrf_token">
    <input type="hidden" name="action" value="toggle_status">
    <input type="hidden" name="user_id">
    <input type="hidden" name="status">
</form>

<script>
function assignRole(userId, roleId, csrf) {
    const f = document.getElementById('assignForm');
    f.querySelector('[name="csrf_token"]').value = csrf;
    f.querySelector('[name="user_id"]').value = userId;
    f.querySelector('[name="role_id"]').value = roleId;
    f.submit();
}
function toggleStatus(userId, status, csrf) {
    const f = document.getElementById('statusForm');
    f.querySelector('[name="csrf_token"]').value = csrf;
    f.querySelector('[name="user_id"]').value = userId;
    f.querySelector('[name="status"]').value = status;
    f.submit();
}
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
