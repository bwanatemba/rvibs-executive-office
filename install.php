<?php
/**
 * Installation script — run once to set up the database.
 * Visit https://yourdomain.com/install.php in your browser.
 * DELETE this file after installation.
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';

$step = $_GET['step'] ?? 'intro';
$messages = [];
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'install') {
    try {
        // Connect without database name first to create it
        $dsn = 'mysql:host=' . DB_HOST . ';charset=utf8mb4';
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

        // Create database
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE `" . DB_NAME . "`");

        // Run schema
        $schema = file_get_contents(__DIR__ . '/database/schema.sql');
        // Remove comment lines that would cause issues with PDO::exec
        $schema = preg_replace('/^--.*$/m', '', $schema);
        // Split on semicolons (simple approach)
        $statements = array_filter(array_map('trim', explode(';', $schema)));
        $count = 0;
        foreach ($statements as $sql) {
            if (!empty($sql) && stripos($sql, 'CREATE TABLE') !== false || stripos($sql, 'INSERT INTO') !== false || stripos($sql, 'SET ') !== false) {
                $pdo->exec($sql);
                $count++;
            }
        }

        $messages[] = "Database and tables created successfully ($count statements executed).";
        $messages[] = "Seed data inserted: 6 roles, 3 users (system admin, 2 director accounts), default settings.";
        $messages[] = "Installation complete. Please delete install.php.";
        $step = 'done';
    } catch (PDOException $e) {
        $error = $e->getMessage();
        $step = 'error';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Install · <?= e(APP_NAME) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css?v=<?= time() ?>">
</head>
<body>
<div class="login-page">
    <div class="login-card" style="max-width: 560px;">
        <div class="login-logo">RV</div>
        <h1 class="login-title">Installation</h1>
        <p class="login-sub">RVIBS Executive Office — Database Setup</p>

        <?php if ($step === 'intro'): ?>
            <div class="alert alert-info">
                This will create the database tables and seed initial data.<br>
                Make sure your database credentials are set in <code>config/config.php</code>.
            </div>
            <div class="card" style="margin-bottom: 1rem;">
                <div class="card-body">
                    <div class="form-group"><strong>Database Host:</strong> <?= e(DB_HOST) ?></div>
                    <div class="form-group"><strong>Database Name:</strong> <?= e(DB_NAME) ?></div>
                    <div class="form-group"><strong>Database User:</strong> <?= e(DB_USER) ?></div>
                </div>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="install">
                <button type="submit" class="btn btn-primary btn-block btn-lg">Run Installation</button>
            </form>
        <?php elseif ($step === 'done'): ?>
            <div class="alert alert-success">
                Installation completed successfully!
            </div>
            <?php foreach ($messages as $msg): ?>
                <p class="text-sm text-2 mt-2">✓ <?= e($msg) ?></p>
            <?php endforeach; ?>
            <div class="alert alert-warning mt-4">
                Please delete <code>install.php</code> from your server now.
            </div>
            <a href="login.php" class="btn btn-accent btn-block btn-lg" style="margin-top:1rem;">Go to Login</a>
        <?php elseif ($step === 'error'): ?>
            <div class="alert alert-danger">
                Installation failed: <?= e($error) ?>
            </div>
            <p class="text-sm text-2 mt-4">Check your database credentials in <code>config/config.php</code> and try again.</p>
            <a href="install.php" class="btn btn-outline btn-block" style="margin-top:1rem;">Try Again</a>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
