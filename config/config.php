<?php
/**
 * RVIBS Executive Office - Application Configuration
 * Update these values for your cPanel hosting environment.
 */

// Optional machine-specific settings (not committed to source control).
$localConfig = is_file(__DIR__ . '/config.local.php') ? require __DIR__ . '/config.local.php' : [];

// ---- Database Configuration ----
define('DB_HOST', 'localhost');
define('DB_NAME', 'rvibs_exec_office');
define('DB_USER', $localConfig['DB_USER'] ?? 'your_db_user');
define('DB_PASS', $localConfig['DB_PASS'] ?? 'your_db_password');
define('DB_CHARSET', 'utf8mb4');

// ---- Google OAuth Configuration ----
// Create these at https://console.cloud.google.com/apis/credentials
// Set the redirect URI to: https://yourdomain.com/oauth-callback.php
define('GOOGLE_CLIENT_ID', $localConfig['GOOGLE_CLIENT_ID'] ?? '');
define('GOOGLE_CLIENT_SECRET', $localConfig['GOOGLE_CLIENT_SECRET'] ?? '');
define('GOOGLE_REDIRECT_URI', $localConfig['GOOGLE_REDIRECT_URI'] ?? ''); // e.g. https://exec.rvibs.ac.ke/oauth-callback.php

// ---- Application Configuration ----
define('APP_NAME', 'RVIBS & ATS Executive Office');
define('APP_URL', $localConfig['APP_URL'] ?? ''); // e.g. https://exec.rvibs.ac.ke
define('ALLOWED_EMAIL_DOMAIN', 'rvibs.ac.ke');
define('TIMEZONE', 'Africa/Nairobi');

// ---- Session Configuration ----
define('SESSION_TIMEOUT', 28800); // 8 hours in seconds

// ---- Production Error Reporting ----
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

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
