<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';
if (isset($_SESSION['user_id'])) {
    redirect('dashboard.php');
} else {
    redirect('login.php');
}
