<?php
/**
 * PharmaCare IMS — Diagnostic / Setup Check
 * --------------------------------------------
 * Open this in your browser FIRST:  http://localhost/pharmacy/test.php
 * DELETE this file before going live!
 */
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$checks = [];

// ── 1. PHP Version ────────────────────────────────────────
$phpVer  = PHP_VERSION;
$phpOk   = version_compare($phpVer, '7.4.0', '>=');
$checks[] = [
    'label'  => 'PHP Version',
    'value'  => $phpVer,
    'ok'     => $phpOk,
    'note'   => $phpOk ? '' : 'Requires PHP 7.4+',
];

// ── 2. PDO Extension ──────────────────────────────────────
$pdoOk = extension_loaded('pdo') && extension_loaded('pdo_mysql');
$checks[] = [
    'label' => 'PDO + pdo_mysql',
    'value' => $pdoOk ? 'Enabled' : 'MISSING',
    'ok'    => $pdoOk,
    'note'  => $pdoOk ? '' : 'Enable pdo_mysql in php.ini',
];

// ── 3. Sessions ───────────────────────────────────────────
session_start();
$_SESSION['test'] = 'ok';
$sessOk = ($_SESSION['test'] ?? '') === 'ok';
$checks[] = [
    'label' => 'PHP Sessions',
    'value' => $sessOk ? 'Working (save_path: ' . session_save_path() . ')' : 'FAILED',
    'ok'    => $sessOk,
    'note'  => '',
];

// ── 4. Database Connection ────────────────────────────────
$dbHost = 'localhost';
$dbUser = 'root';
$dbPass = '';
$dbName = 'pharmacy_db';
$dbOk   = false;
$dbMsg  = '';

try {
    $pdo   = new PDO("mysql:host=$dbHost;charset=utf8mb4", $dbUser, $dbPass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $dbMsg = 'Connected to MySQL ✓';

    // Check if DB exists
    $exists = $pdo->query("SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME='$dbName'")->fetchColumn();
    if ($exists) {
        $pdo2   = new PDO("mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4", $dbUser, $dbPass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $tables = $pdo2->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        $dbMsg  = "Database '$dbName' found. Tables: " . implode(', ', $tables);
        $dbOk   = count($tables) >= 6;
        if (!$dbOk) $dbMsg .= ' ← Run setup.sql to create tables!';
    } else {
        $dbMsg = "MySQL connected but database '$dbName' does NOT exist. Run setup.sql!";
    }
} catch (PDOException $e) {
    $dbMsg = 'FAILED: ' . $e->getMessage();
}

$checks[] = [
    'label' => 'Database',
    'value' => $dbMsg,
    'ok'    => $dbOk,
    'note'  => $dbOk ? '' : 'Run setup.sql in phpMyAdmin',
];

// ── 5. Writable Session Dir ───────────────────────────────
$savePath = session_save_path() ?: sys_get_temp_dir();
$writeOk  = is_writable($savePath);
$checks[] = [
    'label' => 'Session Directory Writable',
    'value' => $savePath,
    'ok'    => $writeOk,
    'note'  => $writeOk ? '' : 'Check folder permissions',
];

// ── 6. JSON Extension ─────────────────────────────────────
$checks[] = [
    'label' => 'JSON Extension',
    'value' => function_exists('json_encode') ? 'Enabled' : 'MISSING',
    'ok'    => function_exists('json_encode'),
    'note'  => '',
];

// ── 7. base_url() test ────────────────────────────────────
$script  = $_SERVER['SCRIPT_NAME'] ?? '/pharmacy/test.php';
$appRoot = rtrim(dirname($script), '/\\');
$checks[] = [
    'label' => 'Detected App Root',
    'value' => $appRoot . '/',
    'ok'    => str_contains($script, 'pharmacy'),
    'note'  => 'Login page will be at: http://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . $appRoot . '/index.php',
];


// ── 8. Navigation URL Test ────────────────────────────────
require_once __DIR__ . '/includes/functions.php';
$testUrl = base_url('dashboard.php');
$navOk   = (strpos($testUrl, 'http') === 0) && (strpos($testUrl, 'pharmacy/dashboard.php') !== false || strpos($testUrl, '/dashboard.php') !== false);
$checks[] = [
    'label' => 'Navigation base_url()',
    'value' => $testUrl . ' (from app root)',
    'ok'    => $navOk,
    'note'  => $navOk ? '' : 'base_url() is miscalculated — links will 404',
];

$allOk = !in_array(false, array_column($checks, 'ok'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>PharmaCare IMS — Setup Check</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"/>
  <style>
    body { background:#f1f5f9; font-family:Inter,sans-serif; padding:2rem; }
    .card { border-radius:12px; border:none; box-shadow:0 2px 8px rgba(0,0,0,.08); }
    code { background:#f8fafc; padding:2px 6px; border-radius:4px; font-size:.85em; }
  </style>
</head>
<body>
<div class="container" style="max-width:780px;">
  <div class="d-flex align-items-center gap-3 mb-4">
    <div style="width:48px;height:48px;background:#2563eb;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1.5rem;color:#fff;">💊</div>
    <div>
      <h4 class="mb-0 fw-bold">PharmaCare IMS — Setup Diagnostics</h4>
      <small class="text-muted">Run this before opening the app. Delete test.php when done.</small>
    </div>
  </div>

  <?php if ($allOk): ?>
    <div class="alert alert-success fw-bold">
      ✅ All checks passed! <a href="index.php" class="btn btn-success btn-sm ms-3">Open Login Page →</a>
    </div>
  <?php else: ?>
    <div class="alert alert-warning fw-bold">
      ⚠️ Some checks failed — fix the items marked in red below, then refresh.
    </div>
  <?php endif ?>

  <div class="card mb-4">
    <div class="card-body p-0">
      <table class="table mb-0" style="border-radius:12px;overflow:hidden;">
        <thead><tr style="background:#f8fafc;"><th>Check</th><th>Result</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach ($checks as $c): ?>
          <tr>
            <td class="fw-600"><?= htmlspecialchars($c['label']) ?></td>
            <td>
              <code><?= htmlspecialchars($c['value']) ?></code>
              <?php if ($c['note']): ?>
                <br><small class="text-danger"><?= htmlspecialchars($c['note']) ?></small>
              <?php endif ?>
            </td>
            <td>
              <?php if ($c['ok']): ?>
                <span class="badge bg-success">✓ OK</span>
              <?php else: ?>
                <span class="badge bg-danger">✗ FAIL</span>
              <?php endif ?>
            </td>
          </tr>
        <?php endforeach ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="card">
    <div class="card-body">
      <h6 class="fw-bold mb-3">🔧 Quick Fix Guide</h6>
      <ol style="font-size:.9rem; line-height:2;">
        <li><strong>500 Error on index.php?</strong> → The <code>.htaccess</code> has been fixed in the latest ZIP. Replace your old one.</li>
        <li><strong>Database not found?</strong> → Open phpMyAdmin → SQL tab → paste contents of <code>setup.sql</code> → Go.</li>
        <li><strong>PDO missing?</strong> → In XAMPP: open <code>php.ini</code>, find <code>;extension=pdo_mysql</code>, remove the <code>;</code>, restart Apache.</li>
        <li><strong>Can't connect to MySQL?</strong> → Make sure MySQL is <strong>running</strong> in the XAMPP Control Panel.</li>
        <li><strong>AllowOverride error?</strong> → Open <code>httpd.conf</code>, find the <code>&lt;Directory "C:/xampp/htdocs"&gt;</code> block, change <code>AllowOverride None</code> to <code>AllowOverride All</code>.</li>
      </ol>
      <div class="alert alert-info mt-3 mb-0" style="font-size:.85rem;">
        🗑️ <strong>Remember:</strong> Delete <code>test.php</code> after setup is complete — it exposes server info.
      </div>
    </div>
  </div>

  <p class="text-muted mt-3" style="font-size:.8rem;">
    PHP <?= phpversion() ?> | <?= PHP_OS ?> | Server: <?= $_SERVER['SERVER_SOFTWARE'] ?? 'unknown' ?>
  </p>
</div>
</body>
</html>
