<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/google_auth.php';
require_once __DIR__ . '/includes/auth.php';

// Redirect to dashboard if already logged in
if (isset($_SESSION['user_id'])) {
    redirect('dashboard.php');
}

$googleUrl = google_auth_url();
$error = $_GET['error'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In · <?= e(APP_NAME) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css?v=<?= time() ?>">
</head>
<body>
<div class="login-page">
    <div class="login-card">
        <div class="login-logo">RV</div>
        <h1 class="login-title">Executive Office</h1>
        <p class="login-sub">RVIBS · ATS · ATS Kindergarten</p>

        <?php if ($error === 'domain'): ?>
            <div class="alert alert-danger">
                Only <strong>@<?= e(ALLOWED_EMAIL_DOMAIN) ?></strong> Google Workspace accounts can access this platform.
            </div>
        <?php elseif ($error === 'oauth'): ?>
            <div class="alert alert-danger">
                Google sign-in failed. Please try again or contact your administrator.
            </div>
        <?php elseif ($error === 'pending'): ?>
            <div class="alert alert-warning">
                Your account has been created but is pending role assignment. An administrator will assign your role shortly.
            </div>
        <?php elseif (!GOOGLE_CLIENT_ID): ?>
            <div class="alert alert-info">
                Google OAuth is not yet configured. Please set up Google Client ID and Secret in <code>config/config.php</code>.<br><br>
                For local testing, you can use the <a href="dev-login.php">developer login</a>.
            </div>
        <?php endif; ?>

        <?php if (GOOGLE_CLIENT_ID): ?>
            <a href="<?= e($googleUrl) ?>" class="google-btn">
                <svg class="google-icon" viewBox="0 0 24 24"><path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/><path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/><path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l3.66-2.84z"/><path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/></svg>
                <span>Sign in with Google</span>
            </a>
        <?php else: ?>
            <a href="dev-login.php" class="google-btn">
                <svg class="google-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                <span>Developer Login</span>
            </a>
        <?php endif; ?>

        <div class="login-info">
            Access is restricted to authorised <strong>RVIBS Google Workspace</strong> accounts.<br>
            New accounts require role assignment by an administrator.<br><br>
            Need help? Contact <a href="mailto:support@rvibs.ac.ke">support@rvibs.ac.ke</a>
        </div>
    </div>
</div>
</body>
</html>
