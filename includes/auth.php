<?php
/**
 * Authentication & authorization helpers
 */

require_once __DIR__ . '/../config/database.php';

/**
 * Get the current logged-in user or null
 */
function current_user() {
    if (!isset($_SESSION['user_id'])) return null;
    static $user = null;
    if ($user === null) {
        $db = get_db();
        $stmt = $db->prepare('
            SELECT u.*, r.role_key, r.name AS role_name, r.can_manage_users, r.can_view_all,
                   r.can_approve, r.can_manage_finance, r.can_manage_funding, r.can_coordinate
            FROM users u
            LEFT JOIN roles r ON u.role_id = r.id
            WHERE u.id = ?
        ');
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
        if (!$user) {
            session_destroy();
            return null;
        }
    }
    return $user;
}

/**
 * Require login - redirects to login page if not authenticated
 */
function require_login() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }
}

/**
 * Require a specific role or set of roles
 */
function require_role(...$roles) {
    require_login();
    $user = current_user();
    if (!$user || !in_array($user['role_key'], $roles)) {
        http_response_code(403);
        die('Access denied. You do not have permission to view this page.');
    }
}

/**
 * Check if the current user has a specific permission
 */
function can($permission) {
    $user = current_user();
    if (!$user) return false;
    if ($user['role_key'] === 'system_admin') return true;
    return !empty($user[$permission]);
}

/**
 * Check if user has one of the given role keys
 */
function has_role(...$role_keys) {
    $user = current_user();
    if (!$user) return false;
    return in_array($user['role_key'], $role_keys);
}

/**
 * Log an action to the audit log
 */
function log_action($action, $entity_type = null, $entity_id = null, $old_values = null, $new_values = null) {
    $user = current_user();
    $db = get_db();
    $stmt = $db->prepare('
        INSERT INTO audit_log (user_id, user_name, action, entity_type, entity_id, old_values, new_values, ip_address)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ');
    $stmt->execute([
        $user['id'] ?? null,
        $user['name'] ?? 'system',
        $action,
        $entity_type,
        $entity_id,
        $old_values ? json_encode($old_values) : null,
        $new_values ? json_encode($new_values) : null,
        $_SERVER['REMOTE_ADDR'] ?? null
    ]);
}

/**
 * Redirect helper
 */
function redirect($path) {
    header("Location: $path");
    exit;
}

/**
 * Escape HTML
 */
function e($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Format date for display
 */
function fmt_date($date) {
    if (!$date) return '—';
    $ts = strtotime($date);
    return date('j M Y', $ts);
}

/**
 * Format date for datetime display
 */
function fmt_datetime($datetime) {
    if (!$datetime) return '—';
    $ts = strtotime($datetime);
    return date('j M Y, H:i', $ts);
}

/**
 * Days until a date (negative if past)
 */
function days_until($date) {
    if (!$date) return null;
    $now = new DateTime('today');
    $target = new DateTime($date);
    return $now->diff($target)->days * ($target >= $now ? 1 : -1);
}

/**
 * Get status badge class
 */
function status_badge($status) {
    $classes = [
        'on_track' => 'badge-success',
        'at_risk' => 'badge-warning',
        'off_track' => 'badge-danger',
        'complete' => 'badge-success',
        'open' => 'badge-info',
        'in_progress' => 'badge-info',
        'blocked' => 'badge-danger',
        'overdue' => 'badge-danger',
        'new' => 'badge-neutral',
        'under_review' => 'badge-warning',
        'approved' => 'badge-success',
        'declined' => 'badge-danger',
        'converted' => 'badge-success',
        'pending' => 'badge-neutral',
        'escalated' => 'badge-danger',
        'resolved' => 'badge-success',
        'discovered' => 'badge-neutral',
        'verified' => 'badge-info',
        'ineligible' => 'badge-danger',
        'eligible' => 'badge-success',
        'pursuing' => 'badge-info',
        'preparing' => 'badge-warning',
        'submitted' => 'badge-info',
        'monitoring' => 'badge-warning',
        'awarded' => 'badge-success',
        'closed' => 'badge-neutral',
        'ready' => 'badge-success',
        'not_ready' => 'badge-danger',
        'in_review' => 'badge-warning',
        'changes_required' => 'badge-warning',
        'rejected' => 'badge-danger',
        'withdrawn' => 'badge-danger',
        'pass' => 'badge-success',
        'fail' => 'badge-danger',
        'conditional' => 'badge-warning',
        'not_started' => 'badge-neutral',
        'draft' => 'badge-neutral',
        'published' => 'badge-success',
        'unread' => 'badge-info',
        'read' => 'badge-neutral',
        'summarized' => 'badge-info',
        'drafted' => 'badge-warning',
        'actioned' => 'badge-success',
        'active' => 'badge-success',
        'inactive' => 'badge-neutral',
        'low' => 'badge-neutral',
        'medium' => 'badge-info',
        'high' => 'badge-warning',
        'critical' => 'badge-danger',
        'cash' => 'badge-success',
        'in_kind' => 'badge-info',
    ];
    return $classes[$status] ?? 'badge-neutral';
}

/**
 * Human-readable status
 */
function status_label($status) {
    return ucwords(str_replace('_', ' ', $status));
}

/**
 * Get all users for dropdowns
 */
function get_users_list() {
    $db = get_db();
    return $db->query('SELECT u.id, u.name, u.email, r.role_key FROM users u LEFT JOIN roles r ON u.role_id = r.id WHERE u.status = "active" ORDER BY u.name')->fetchAll();
}

/**
 * Get user name by ID
 */
function get_user_name($id) {
    if (!$id) return 'Unassigned';
    static $cache = [];
    if (!isset($cache[$id])) {
        $db = get_db();
        $stmt = $db->prepare('SELECT name FROM users WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        $cache[$id] = $row ? $row['name'] : 'Unknown';
    }
    return $cache[$id];
}

/**
 * Get setting value
 */
function get_setting($key, $default = '') {
    $db = get_db();
    $stmt = $db->prepare('SELECT setting_value FROM settings WHERE setting_key = ?');
    $stmt->execute([$key]);
    $row = $stmt->fetch();
    return $row ? $row['setting_value'] : $default;
}

/**
 * Generate CSRF token
 */
function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token
 */
function verify_csrf() {
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        die('Invalid CSRF token.');
    }
}

/**
 * JSON response
 */
function json_response($data, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

/**
 * Redirect with a toast message (stored in session, displayed on next page)
 */
function toast_redirect($message, $url, $type = 'success') {
    $_SESSION['toast_msg'] = $message;
    $_SESSION['toast_type'] = $type;
    redirect($url);
}

/**
 * Display session toast if present
 */
function render_session_toast() {
    if (!empty($_SESSION['toast_msg'])) {
        $msg = json_encode($_SESSION['toast_msg'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        $type = json_encode($_SESSION['toast_type'] ?? 'success', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        echo "<script>document.addEventListener('DOMContentLoaded', function() { showSaveNotification($msg, $type); });</script>";
        unset($_SESSION['toast_msg'], $_SESSION['toast_type']);
    }
}
