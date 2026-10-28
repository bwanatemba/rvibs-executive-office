<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/google_auth.php';
require_once __DIR__ . '/includes/auth.php';

// Handle Google OAuth callback
if (!isset($_GET['code'])) {
    redirect('login.php?error=oauth');
}

// Exchange code for tokens
$tokens = google_exchange_code($_GET['code']);
if (!$tokens || !isset($tokens['access_token'])) {
    redirect('login.php?error=oauth');
}

// Get user info
$userinfo = google_get_userinfo($tokens['access_token']);
if (!$userinfo || !isset($userinfo['email'])) {
    redirect('login.php?error=oauth');
}

// Authenticate user
$user = authenticate_google_user($userinfo);
if (!$user) {
    redirect('login.php?error=oauth');
}
if (isset($user['error'])) {
    redirect('login.php?error=domain');
}

// Set session
$_SESSION['user_id'] = $user['id'];
$_SESSION['user_email'] = $user['email'];

// Update last login
$db = get_db();
$stmt = $db->prepare('UPDATE users SET last_login = NOW() WHERE id = ?');
$stmt->execute([$user['id']]);

log_action('login', 'user', $user['id']);

// Redirect to dashboard or pending page
if (!isset($user['role_key']) || $user['role_key'] === null) {
    redirect('login.php?error=pending');
}

redirect('dashboard.php');
