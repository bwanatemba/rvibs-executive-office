<?php
/**
 * RVIBS Executive Office - Application Configuration
 * Update these values for your cPanel hosting environment.
 */

// ---- Database Configuration ----
define('DB_HOST', 'localhost');
define('DB_NAME', 'rvibs_exec_office');
define('DB_USER', 'your_db_user');
define('DB_PASS', 'your_db_password');
define('DB_CHARSET', 'utf8mb4');

// ---- Google OAuth Configuration ----
// Create these at https://console.cloud.google.com/apis/credentials
// Set the redirect URI to: https://yourdomain.com/oauth-callback.php
define('GOOGLE_CLIENT_ID', '');
define('GOOGLE_CLIENT_SECRET', '');
define('GOOGLE_REDIRECT_URI', ''); // e.g. https://exec.rvibs.ac.ke/oauth-callback.php

// ---- Application Configuration ----
define('APP_NAME', 'RVIBS & ATS Executive Office');
define('APP_URL', ''); // e.g. https://exec.rvibs.ac.ke
define('ALLOWED_EMAIL_DOMAIN', 'rvibs.ac.ke');
define('TIMEZONE', 'Africa/Nairobi');

// ---- Session Configuration ----
define('SESSION_TIMEOUT', 28800); // 8 hours in seconds

// ---- Error Reporting (set to 0 in production) ----
error_reporting(E_ALL);
ini_set('display_errors', 0);

date_default_timezone_set(TIMEZONE);

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => SESSION_TIMEOUT,
        'path' => '/',
        'secure' => !empty($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}
