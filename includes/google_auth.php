<?php
/**
 * Google OAuth 2.0 implementation (no external dependencies)
 * Uses Google's OAuth 2.0 endpoints directly via cURL/stream context.
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

/**
 * Build the Google OAuth authorization URL
 */
function google_auth_url() {
    $params = [
        'client_id' => GOOGLE_CLIENT_ID,
        'redirect_uri' => GOOGLE_REDIRECT_URI,
        'response_type' => 'code',
        'scope' => 'openid email profile',
        'access_type' => 'online',
        'prompt' => 'select_account',
    ];
    return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
}

/**
 * HTTP request helper — prefers cURL, falls back to file_get_contents
 */
function google_http_request($url, $post_data = null, $headers = []) {
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => $headers ?: ['Content-Type: application/x-www-form-urlencoded'],
        ];
        if ($post_data !== null) {
            $opts[CURLOPT_POST] = true;
            $opts[CURLOPT_POSTFIELDS] = $post_data;
        }
        curl_setopt_array($ch, $opts);
        $response = curl_exec($ch);
        curl_close($ch);
        return $response;
    }
    // Fallback to file_get_contents
    $opts = ['http' => ['method' => $post_data !== null ? 'POST' : 'GET', 'ignore_errors' => true]];
    if ($post_data !== null) {
        $opts['http']['content'] = $post_data;
        $opts['http']['header'] = 'Content-Type: application/x-www-form-urlencoded';
    }
    if ($headers) {
        $opts['http']['header'] = implode("\r\n", $headers);
    }
    return @file_get_contents($url, false, stream_context_create($opts));
}

/**
 * Exchange authorization code for access token
 */
function google_exchange_code($code) {
    $data = http_build_query([
        'code' => $code,
        'client_id' => GOOGLE_CLIENT_ID,
        'client_secret' => GOOGLE_CLIENT_SECRET,
        'redirect_uri' => GOOGLE_REDIRECT_URI,
        'grant_type' => 'authorization_code',
    ]);
    $response = google_http_request('https://oauth2.googleapis.com/token', $data);
    if (!$response) return null;
    return json_decode($response, true);
}

/**
 * Get user info using access token
 */
function google_get_userinfo($access_token) {
    $response = google_http_request(
        'https://www.googleapis.com/oauth2/v2/userinfo',
        null,
        ["Authorization: Bearer $access_token"]
    );
    if (!$response) return null;
    return json_decode($response, true);
}

/**
 * Check if an email belongs to the allowed domain
 */
function is_allowed_domain($email) {
    $domain = substr(strrchr($email, '@'), 1);
    return strtolower($domain) === strtolower(ALLOWED_EMAIL_DOMAIN);
}

/**
 * Authenticate or create user from Google user info
 * Returns user array or null if not allowed
 */
function authenticate_google_user($userinfo) {
    if (!isset($userinfo['email'])) return null;
    if (!is_allowed_domain($userinfo['email'])) return ['error' => 'Only @' . ALLOWED_EMAIL_DOMAIN . ' accounts are allowed.'];

    $db = get_db();
    $email = $userinfo['email'];
    $name = $userinfo['name'] ?? $userinfo['email'];
    $google_id = $userinfo['id'] ?? null;
    $avatar = $userinfo['picture'] ?? null;

    // Check if user exists
    $stmt = $db->prepare('SELECT u.*, r.role_key FROM users u LEFT JOIN roles r ON u.role_id = r.id WHERE u.email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user) {
        // Update existing user
        $stmt = $db->prepare('UPDATE users SET name = ?, google_id = ?, avatar_url = ?, last_login = NOW(), status = "active" WHERE id = ?');
        $stmt->execute([$name, $google_id, $avatar, $user['id']]);
        $user['name'] = $name;
        $user['avatar_url'] = $avatar;
    } else {
        // Check if this is the system admin email
        if ($email === 'support@rvibs.ac.ke') {
            $stmt = $db->prepare('SELECT id FROM users WHERE email = ?');
            $stmt->execute([$email]);
            $existing = $stmt->fetch();
            if ($existing) {
                $stmt = $db->prepare('UPDATE users SET name = ?, google_id = ?, avatar_url = ?, last_login = NOW(), status = "active" WHERE id = ?');
                $stmt->execute([$name, $google_id, $avatar, $existing['id']]);
                $stmt = $db->prepare('SELECT u.*, r.role_key FROM users u LEFT JOIN roles r ON u.role_id = r.id WHERE u.id = ?');
                $stmt->execute([$existing['id']]);
                $user = $stmt->fetch();
            }
        }
        if (!$user) {
            // New user - create as pending with no role
            $stmt = $db->prepare('INSERT INTO users (name, email, google_id, avatar_url, status, role_id) VALUES (?, ?, ?, ?, "pending", NULL)');
            $stmt->execute([$name, $email, $google_id, $avatar]);
            $id = $db->lastInsertId();
            $user = [
                'id' => $id,
                'name' => $name,
                'email' => $email,
                'role_key' => null,
                'role_id' => null,
                'status' => 'pending',
                'avatar_url' => $avatar,
            ];
        }
    }

    return $user;
}
