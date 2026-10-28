<?php
require_once __DIR__ . '/config/config.php';
if (isset($_SESSION['user_id'])) {
    log_action('logout', 'user', $_SESSION['user_id']);
}
session_destroy();
redirect('login.php');
