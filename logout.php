<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';

try {
    if (isset($_SESSION['user_id'])) {
        log_action('logout', 'user', $_SESSION['user_id']);
    }
} catch (Throwable $error) {
    // An audit failure must not prevent the user from signing out.
    error_log('Logout audit failed: ' . $error->getMessage());
}

$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', [
        'expires' => time() - 42000,
        'path' => $params['path'],
        'domain' => $params['domain'],
        'secure' => $params['secure'],
        'httponly' => $params['httponly'],
        'samesite' => $params['samesite'] ?? 'Lax',
    ]);
}
session_destroy();
header('Cache-Control: no-store');
redirect('login.php?signed_out=1');
