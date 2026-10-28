<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();
$user = current_user();
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($page_title ?? APP_NAME) ?> · <?= e(APP_NAME) ?></title>
    <link rel="icon" type="image/png" href="assets/images/rvibs-logo.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css?v=<?= filemtime(__DIR__ . '/../assets/css/style.css') ?>">
</head>
<body>
<div class="app">
    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="logo">
                <img class="logo-mark" src="assets/images/rvibs-logo.png" alt="RVIBS">
                <div class="logo-text">
                    <span class="logo-title">Executive Office</span>
                    <span class="logo-sub">RVIBS & ATS</span>
                </div>
            </div>
            <button class="sidebar-close" onclick="toggleSidebar()" aria-label="Close menu">✕</button>
        </div>

        <nav class="sidebar-nav">
            <a href="dashboard.php" class="nav-item <?= $currentPage === 'dashboard.php' ? 'active' : '' ?>">
                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
                <span>Dashboard</span>
            </a>

            <div class="nav-section-label">Executive Office</div>

            <a href="outcomes.php" class="nav-item <?= $currentPage === 'outcomes.php' ? 'active' : '' ?>">
                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="6"/><circle cx="12" cy="12" r="2"/></svg>
                <span>90-Day Outcomes</span>
            </a>
            <a href="commitments.php" class="nav-item <?= $currentPage === 'commitments.php' ? 'active' : '' ?>">
                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
                <span>Commitments</span>
            </a>
            <a href="ideas.php" class="nav-item <?= $currentPage === 'ideas.php' ? 'active' : '' ?>">
                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 18h6"/><path d="M10 22h4"/><path d="M12 2a7 7 0 0 0-4 12.7c.6.5 1 1.3 1 2.1V18h6v-1.2c0-.8.4-1.6 1-2.1A7 7 0 0 0 12 2z"/></svg>
                <span>Ideas Pipeline</span>
            </a>
            <a href="decisions.php" class="nav-item <?= $currentPage === 'decisions.php' ? 'active' : '' ?>">
                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 3l7.07 16.97 2.51-7.39 7.39-2.51z"/><path d="M13 13l6 6"/></svg>
                <span>Delegated Authority</span>
            </a>
            <a href="escalations.php" class="nav-item <?= $currentPage === 'escalations.php' ? 'active' : '' ?>">
                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                <span>Escalations</span>
                <?php
                    $openEsc = get_db()->query("SELECT COUNT(*) as c FROM escalations WHERE status='open'")->fetch()['c'];
                    if ($openEsc > 0): ?>
                    <span class="nav-badge"><?= $openEsc ?></span>
                <?php endif; ?>
            </a>
            <a href="weekly-review.php" class="nav-item <?= $currentPage === 'weekly-review.php' ? 'active' : '' ?>">
                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                <span>Weekly Review</span>
            </a>
            <a href="daily-brief.php" class="nav-item <?= $currentPage === 'daily-brief.php' ? 'active' : '' ?>">
                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2v2m0 16v2M4.93 4.93l1.41 1.41m11.32 11.32l1.41 1.41M2 12h2m16 0h2M4.93 19.07l1.41-1.41m11.32-11.32l1.41-1.41"/><circle cx="12" cy="12" r="4"/></svg>
                <span>Daily Brief</span>
            </a>

            <div class="nav-section-label">Funding & Resources</div>

            <a href="funding-readiness.php" class="nav-item <?= $currentPage === 'funding-readiness.php' ? 'active' : '' ?>">
                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                <span>Institutional Readiness</span>
            </a>
            <a href="funding-opportunities.php" class="nav-item <?= $currentPage === 'funding-opportunities.php' ? 'active' : '' ?>">
                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <span>Opportunities</span>
            </a>
            <a href="funding-applications.php" class="nav-item <?= $currentPage === 'funding-applications.php' ? 'active' : '' ?>">
                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                <span>Applications</span>
            </a>
            <a href="funding-awards.php" class="nav-item <?= $currentPage === 'funding-awards.php' ? 'active' : '' ?>">
                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="7"/><polyline points="8.21 13.89 7 23 12 20 17 23 15.79 13.88"/></svg>
                <span>Awards & Disbursements</span>
            </a>
            <a href="funding-closeout.php" class="nav-item <?= $currentPage === 'funding-closeout.php' ? 'active' : '' ?>">
                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                <span>Delivery & Closeout</span>
            </a>
            <a href="partners.php" class="nav-item <?= $currentPage === 'partners.php' ? 'active' : '' ?>">
                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                <span>Partners</span>
            </a>

            <?php if (has_role('chairman_ceo', 'executive_coordinator')): ?>
            <div class="nav-section-label">Communications</div>
            <a href="communications.php" class="nav-item <?= $currentPage === 'communications.php' ? 'active' : '' ?>">
                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                <span>Communications Agent</span>
                <?php
                    $unreadComms = get_db()->query("SELECT COUNT(*) as c FROM communications WHERE is_pending=1")->fetch()['c'];
                    if ($unreadComms > 0): ?>
                    <span class="nav-badge accent"><?= $unreadComms ?></span>
                <?php endif; ?>
            </a>
            <?php endif; ?>

            <?php if (has_role('system_admin')): ?>
            <div class="nav-section-label">Administration</div>
            <a href="admin-users.php" class="nav-item <?= $currentPage === 'admin-users.php' ? 'active' : '' ?>">
                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                <span>User Management</span>
            </a>
            <a href="admin-settings.php" class="nav-item <?= $currentPage === 'admin-settings.php' ? 'active' : '' ?>">
                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
                <span>Settings</span>
            </a>
            <a href="audit-log.php" class="nav-item <?= $currentPage === 'audit-log.php' ? 'active' : '' ?>">
                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 8v4l4 2"/><circle cx="12" cy="12" r="10"/></svg>
                <span>Audit Log</span>
            </a>
            <?php endif; ?>
        </nav>

        <div class="sidebar-footer">
            <div class="user-chip">
                <?php if (!empty($user['avatar_url'])): ?>
                    <img src="<?= e($user['avatar_url']) ?>" alt="" class="user-avatar">
                <?php else: ?>
                    <span class="user-avatar-placeholder"><?= strtoupper(substr($user['name'], 0, 1)) ?></span>
                <?php endif; ?>
                <div class="user-chip-info">
                    <span class="user-chip-name"><?= e($user['name']) ?></span>
                    <span class="user-chip-role"><?= e($user['role_name'] ?? 'Pending Role') ?></span>
                </div>
            </div>
            <a href="logout.php" class="logout-btn" title="Sign out">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
            </a>
        </div>
    </aside>

    <!-- Mobile overlay -->
    <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

    <!-- Main content -->
    <div class="main">
        <header class="topbar">
            <button class="menu-toggle" onclick="toggleSidebar()" aria-label="Toggle menu">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="24" height="24"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
            </button>
            <h1 class="topbar-title"><?= e($page_title ?? 'Dashboard') ?></h1>
            <div class="topbar-right">
                <?php if (has_role('system_admin', 'chairman_ceo', 'executive_coordinator')):
                    $pendingUsers = get_db()->query("SELECT COUNT(*) as c FROM users WHERE status='pending' AND email != 'support@rvibs.ac.ke'")->fetch()['c'];
                    if ($pendingUsers > 0): ?>
                    <a href="admin-users.php" class="topbar-alert" title="<?= $pendingUsers ?> pending user(s) need role assignment">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20"><path d="M15 17h5l-1.4-1.4A2 2 0 0 1 18 14.2V11a6 6 0 0 0-4-5.7V5a2 2 0 0 0-4 0v.3A6 6 0 0 0 6 11v3.2c0 .5-.2 1-.6 1.4L4 17h5"/><path d="M9 17a3 3 0 0 0 6 0"/></svg>
                        <span class="alert-dot"></span>
                    </a>
                    <?php endif; ?>
                <?php endif; ?>
                <div class="topbar-user">
                    <?php if (!empty($user['avatar_url'])): ?>
                        <img src="<?= e($user['avatar_url']) ?>" alt="" class="topbar-avatar">
                    <?php else: ?>
                        <span class="topbar-avatar-placeholder"><?= strtoupper(substr($user['name'], 0, 1)) ?></span>
                    <?php endif; ?>
                </div>
            </div>
        </header>

        <main class="content">
