<?php
/**
 * Developer login page — for testing without Google OAuth configured.
 * Lists seeded users so you can sign in as different roles.
 * DISABLE or remove this file in production.
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';

if (isset($_POST['user_id'])) {
    $db = get_db();
    $stmt = $db->prepare('SELECT u.*, r.role_key FROM users u LEFT JOIN roles r ON u.role_id = r.id WHERE u.id = ?');
    $stmt->execute([$_POST['user_id']]);
    $user = $stmt->fetch();
    if ($user) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_email'] = $user['email'];
        log_action('dev_login', 'user', $user['id']);
        redirect('dashboard.php');
    }
}

$db = get_db();
$users = $db->query('SELECT u.*, r.role_key, r.name AS role_name FROM users u LEFT JOIN roles r ON u.role_id = r.id ORDER BY r.sort_order, u.name')->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Developer Login · <?= e(APP_NAME) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css?v=<?= time() ?>">
</head>
<body>
<div class="login-page">
    <div class="login-card" style="max-width: 540px;">
        <div class="login-logo">RV</div>
        <h1 class="login-title">Developer Login</h1>
        <p class="login-sub">Select a user to sign in as (for testing only)</p>

        <div class="alert alert-warning">
            This page is for development testing only. Remove <code>dev-login.php</code> in production.
        </div>

        <form method="POST" style="margin-top: 1.5rem;">
            <?php foreach ($users as $u): ?>
                <button type="submit" name="user_id" value="<?= $u['id'] ?>" class="google-btn" style="margin-bottom: 0.625rem; justify-content: flex-start; padding-left: 1rem;">
                    <span class="avatar-circle"><?= strtoupper(substr($u['name'], 0, 1)) ?></span>
                    <span style="flex:1; text-align:left;">
                        <?= e($u['name']) ?> <span class="text-3 text-sm">(<?= e($u['email']) ?>)</span>
                    </span>
                    <span class="badge badge-primary"><?= e($u['role_name'] ?? 'No Role') ?></span>
                </button>
            <?php endforeach; ?>
        </form>

        <div class="login-info">
            <a href="login.php">← Back to Google login</a>
        </div>
    </div>
</div>
</body>
</html>
