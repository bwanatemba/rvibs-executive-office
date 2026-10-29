<?php
$page_title = 'Settings';
require_once __DIR__ . '/includes/auth.php';
require_role('system_admin');
$db = get_db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'update_settings') {
        foreach ($_POST['settings'] as $key => $value) {
            $db->prepare('INSERT INTO settings (setting_key, setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=?')
                ->execute([$key, $value, $value]);
        }
        log_action('update_settings', 'settings');
        toast_redirect('Settings saved', 'admin-settings.php');
    }
}

$settings = [];
foreach ($db->query('SELECT * FROM settings')->fetchAll() as $s) {
    $settings[$s['setting_key']] = $s['setting_value'];
}
$csrf = csrf_token();
$google_configured = !empty(GOOGLE_CLIENT_ID);
require_once __DIR__ . '/includes/header.php';
?>

<div class="section-header">
    <div>
        <h2 class="section-title">Settings</h2>
        <p class="section-sub">Application configuration and integration settings</p>
    </div>
</div>

<div class="grid grid-2">
    <div class="card">
        <div class="card-header"><h3 class="card-title">Organisation</h3></div>
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                <input type="hidden" name="action" value="update_settings">
                <div class="form-group"><label class="form-label">Organisation Name</label>
                    <input type="text" name="settings[org_name]" class="form-control" value="<?= e($settings['org_name'] ?? 'RVIBS & ATS Executive Office') ?>"></div>
                <div class="form-group"><label class="form-label">Current Quarter</label>
                    <input type="text" name="settings[current_quarter]" class="form-control" value="<?= e($settings['current_quarter'] ?? '') ?>" placeholder="e.g. Q1 2026-2027"></div>
                <div class="form-group"><label class="form-label">Director Email (director@rvibs.ac.ke)</label>
                    <input type="email" name="settings[director_email]" class="form-control" value="<?= e($settings['director_email'] ?? 'director@rvibs.ac.ke') ?>"></div>
                <div class="form-group"><label class="form-label">Coordinator Email (Emma's account)</label>
                    <input type="email" name="settings[coordinator_email]" class="form-control" value="<?= e($settings['coordinator_email'] ?? '') ?>" placeholder="e.g. emma@rvibs.ac.ke"></div>
                <button type="submit" class="btn btn-primary btn-sm">Save</button>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h3 class="card-title">Google Workspace Authentication</h3></div>
        <div class="card-body">
            <div class="form-group"><label class="form-label">Allowed Domain</label>
                <input type="text" class="form-control" value="<?= e(ALLOWED_EMAIL_DOMAIN) ?>" readonly></div>
            <div class="form-group"><label class="form-label">Google Client ID</label>
                <input type="text" class="form-control" value="<?= e(GOOGLE_CLIENT_ID ?: '(not configured)') ?>" readonly>
                <?php if (!$google_configured): ?>
                <div class="form-hint" style="color:var(--c-warning)">Not yet configured. Set in <code>config/config.php</code></div>
                <?php endif; ?></div>
            <div class="form-group"><label class="form-label">Google Client Secret</label>
                <input type="text" class="form-control" value="<?= GOOGLE_CLIENT_SECRET ? '••••••••••••' : '(not configured)' ?>" readonly></div>
            <div class="form-group"><label class="form-label">Redirect URI</label>
                <input type="text" class="form-control" value="<?= e(GOOGLE_REDIRECT_URI ?: '(not configured)') ?>" readonly></div>
            <?php if (!$google_configured): ?>
            <div class="alert alert-info">
                To enable Google login:
                <ol style="margin:0.5rem 0 0 1rem; padding:0;">
                    <li>Go to <a href="https://console.cloud.google.com/apis/credentials" target="_blank">Google Cloud Console</a></li>
                    <li>Create OAuth 2.0 credentials</li>
                    <li>Set redirect URI to your domain + <code>/oauth-callback.php</code></li>
                    <li>Copy Client ID and Secret to <code>config/config.php</code></li>
                </ol>
            </div>
            <?php else: ?>
            <div class="alert alert-success">Google Workspace authentication is configured and active.</div>
            <?php endif; ?>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h3 class="card-title">WhatsApp Integration</h3></div>
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                <input type="hidden" name="action" value="update_settings">
                <div class="form-group"><label class="flex gap-2 items-center cursor-pointer">
                    <input type="checkbox" name="settings[whatsapp_enabled]" value="1" <?= ($settings['whatsapp_enabled'] ?? '0') === '1' ? 'checked' : '' ?>>
                    <span class="form-label" style="margin:0">Enable WhatsApp integration</span>
                </label></div>
                <div class="form-group"><label class="form-label">WhatsApp Number</label>
                    <input type="text" name="settings[whatsapp_number]" class="form-control" value="<?= e($settings['whatsapp_number'] ?? '') ?>" placeholder="+254..."></div>
                <button type="submit" class="btn btn-primary btn-sm">Save</button>
            </form>
            <div class="alert alert-warning mt-3">
                <strong>Scope note:</strong> WhatsApp access (individual chats, groups, history) must be confirmed before committing to scope. Begin with reading, summarising and drafting — no automatic sending or binding commitments.
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h3 class="card-title">Email Accounts</h3></div>
        <div class="card-body">
            <div class="list-item">
                <div class="list-item-icon" style="background:var(--c-primary); color:#fff;"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg></div>
                <div class="list-item-body">
                    <div class="list-item-title">director@rvibs.ac.ke</div>
                    <div class="list-item-sub">Managed day-to-day by Emma (Executive Coordinator). The Director can assign this account.</div>
                </div>
            </div>
            <div class="list-item">
                <div class="list-item-icon" style="background:var(--c-accent); color:#fff;"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg></div>
                <div class="list-item-body">
                    <div class="list-item-title">gitau.john@rvibs.ac.ke</div>
                    <div class="list-item-sub">Director's direct account. The Communications Agent supports this account.</div>
                </div>
            </div>
            <div class="list-item">
                <div class="list-item-icon" style="background:var(--c-neutral); color:#fff;"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg></div>
                <div class="list-item-body">
                    <div class="list-item-title">support@rvibs.ac.ke</div>
                    <div class="list-item-sub">System Administrator account.</div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
