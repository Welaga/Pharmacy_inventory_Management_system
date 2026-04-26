<?php
// ============================================================
// Page Header Include
// ============================================================
if (!defined('PAGE_TITLE')) define('PAGE_TITLE', APP_NAME);
$flash_success = getFlash('success');
$flash_error   = getFlash('error');
$flash_info    = getFlash('info');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <meta name="csrf-token" content="<?= csrfToken() ?>"/>
  <title><?= sanitize(PAGE_TITLE) ?> — <?= APP_NAME ?></title>

  <!-- Bootstrap 5 -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"/>
  <!-- Bootstrap Icons -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"/>
  <!-- Google Font -->
  <link rel="preconnect" href="https://fonts.googleapis.com"/>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>

  <style>
    :root {
      --sidebar-w: 260px;
      --primary:   #2563eb;
      --primary-dk:#1d4ed8;
      --sidebar-bg:#0f172a;
      --sidebar-hover:#1e293b;
      --sidebar-active:#2563eb;
      --topbar-h:  60px;
    }

    * { font-family: 'Inter', sans-serif; }

    body { background:#f1f5f9; min-height:100vh; }

    /* ── Sidebar ── */
    #sidebar {
      position: fixed; top:0; left:0; bottom:0;
      width: var(--sidebar-w);
      background: var(--sidebar-bg);
      overflow-y: auto;
      z-index: 1000;
      transition: transform .25s ease;
    }
    #sidebar .brand {
      display:flex; align-items:center; gap:.75rem;
      padding:1.25rem 1.25rem 1rem;
      border-bottom:1px solid #1e293b;
    }
    #sidebar .brand .logo-icon {
      width:38px; height:38px; border-radius:10px;
      background: var(--primary);
      display:flex; align-items:center; justify-content:center;
      font-size:1.2rem; color:#fff;
    }
    #sidebar .brand-text { line-height:1.2; }
    #sidebar .brand-text span { display:block; font-size:.65rem; color:#94a3b8; }
    #sidebar .brand-text strong { color:#f1f5f9; font-size:.95rem; }

    #sidebar .nav-section {
      padding:.75rem 1rem .25rem;
      font-size:.65rem; font-weight:700; letter-spacing:.08em;
      color:#64748b; text-transform:uppercase;
    }
    #sidebar .nav-link {
      display:flex; align-items:center; gap:.75rem;
      padding:.6rem 1.25rem;
      color:#94a3b8; font-size:.875rem; font-weight:500;
      border-radius:0; transition: all .15s;
      text-decoration:none;
    }
    #sidebar .nav-link i { font-size:1rem; width:20px; text-align:center; }
    #sidebar .nav-link:hover  { background:var(--sidebar-hover); color:#e2e8f0; }
    #sidebar .nav-link.active { background:var(--sidebar-active); color:#fff; }
    #sidebar .nav-link .badge { margin-left:auto; font-size:.65rem; }

    /* ── Main wrapper ── */
    #main { margin-left:var(--sidebar-w); }

    /* ── Top bar ── */
    #topbar {
      position:sticky; top:0; z-index:999;
      height:var(--topbar-h);
      background:#fff; border-bottom:1px solid #e2e8f0;
      display:flex; align-items:center;
      padding:0 1.5rem; gap:1rem;
    }
    #topbar .page-heading { font-weight:600; font-size:1rem; color:#0f172a; }
    #topbar .topbar-right  { margin-left:auto; display:flex; align-items:center; gap:.75rem; }

    /* ── Content ── */
    #content { padding:1.5rem; }

    /* ── Cards ── */
    .stat-card {
      border:none; border-radius:12px; overflow:hidden;
      box-shadow:0 1px 3px rgba(0,0,0,.07);
      transition: transform .15s, box-shadow .15s;
    }
    .stat-card:hover { transform:translateY(-2px); box-shadow:0 4px 12px rgba(0,0,0,.12); }
    .stat-card .icon-box {
      width:48px; height:48px; border-radius:12px;
      display:flex; align-items:center; justify-content:center;
      font-size:1.4rem;
    }

    /* ── Tables ── */
    .table-card { border:none; border-radius:12px; overflow:hidden; box-shadow:0 1px 3px rgba(0,0,0,.07); }
    .table thead th { background:#f8fafc; font-size:.75rem; font-weight:700; letter-spacing:.05em; text-transform:uppercase; color:#64748b; border-bottom:none; }
    .table tbody tr:hover { background:#f8fafc; }

    /* ── Alerts ── */
    .flash-alert { border:none; border-radius:10px; font-size:.875rem; }

    /* ── Forms ── */
    .form-label { font-size:.8125rem; font-weight:600; color:#374151; }
    .form-control, .form-select { border-radius:8px; border-color:#d1d5db; font-size:.875rem; }
    .form-control:focus, .form-select:focus { border-color:var(--primary); box-shadow:0 0 0 3px rgba(37,99,235,.1); }

    /* ── Buttons ── */
    .btn { border-radius:8px; font-size:.875rem; font-weight:500; }
    .btn-primary { background:var(--primary); border-color:var(--primary); }
    .btn-primary:hover { background:var(--primary-dk); border-color:var(--primary-dk); }

    /* ── POS ── */
    .pos-item { border:1px solid #e2e8f0; border-radius:10px; padding:.75rem; cursor:pointer; transition:all .15s; }
    .pos-item:hover { border-color:var(--primary); background:#eff6ff; }
    .cart-item { border-bottom:1px solid #f1f5f9; padding:.5rem 0; }

    /* ── Mobile ── */
    @media (max-width:768px) {
      #sidebar { transform:translateX(-100%); }
      #sidebar.open { transform:translateX(0); }
      #main { margin-left:0; }
    }

    /* ── Scrollbar ── */
    #sidebar::-webkit-scrollbar { width:4px; }
    #sidebar::-webkit-scrollbar-track { background:transparent; }
    #sidebar::-webkit-scrollbar-thumb { background:#2d3748; border-radius:2px; }

    .avatar {
      width:34px; height:34px; border-radius:50%;
      background:var(--primary); color:#fff;
      display:inline-flex; align-items:center; justify-content:center;
      font-size:.8rem; font-weight:700;
    }
  </style>
</head>
<body>

<?php if (isLoggedIn()): ?>
<!-- ============================================================ -->
<!--  SIDEBAR                                                      -->
<!-- ============================================================ -->
<nav id="sidebar">
  <div class="brand">
    <div class="logo-icon"><i class="bi bi-capsule"></i></div>
    <div class="brand-text">
      <strong><?= APP_NAME ?></strong>
      <span>Inventory System</span>
    </div>
  </div>

  <?php
  // ── Active-page detection ─────────────────────────────────
  // Use the relative path from the app root so we can match
  // both file names AND directory names unambiguously.
  $selfPath    = str_replace('\\', '/', $_SERVER['PHP_SELF'] ?? '');
  $currentFile = basename($selfPath);                      // e.g. "pos.php"
  $currentDir  = basename(dirname($selfPath));             // e.g. "sales"
  $currentRel  = ltrim(substr($selfPath, strrpos($selfPath, '/pharmacy/') + strlen('/pharmacy/')), '/');
  // e.g. "sales/pos.php" or "dashboard.php"

  function navActive(string ...$pages): string {
      global $currentFile, $currentDir, $currentRel;
      foreach ($pages as $p) {
          // Exact relative path match:  "sales/pos.php"
          if ($p === $currentRel)  return 'active';
          // File name match:            "pos.php"
          if ($p === $currentFile) return 'active';
          // Directory match:            "sales"  (highlights whole section)
          if ($p === $currentDir)  return 'active';
      }
      return '';
  }

  // Fetch alert counts for badges
  try {
    $lowStockCount  = (int) db()->query("SELECT COUNT(*) FROM medicines WHERE quantity <= low_stock_threshold AND quantity > 0")->fetchColumn();
    $expiredCount   = (int) db()->query("SELECT COUNT(*) FROM medicines WHERE expiry_date < CURDATE()")->fetchColumn();
  } catch (Exception $e) { $lowStockCount = 0; $expiredCount = 0; }
  ?>

  <div class="nav-section">Main</div>
  <a href="<?= base_url('dashboard.php') ?>" class="nav-link <?= navActive('dashboard.php') ?>">
    <i class="bi bi-grid"></i> Dashboard
  </a>

  <div class="nav-section">Inventory</div>
  <a href="<?= base_url('medicines/index.php') ?>" class="nav-link <?= navActive('medicines/index.php','medicines') ?>">
    <i class="bi bi-capsule-pill"></i> Medicines
    <?php if ($lowStockCount > 0): ?>
      <span class="badge bg-warning text-dark"><?= $lowStockCount ?></span>
    <?php endif ?>
  </a>
  <a href="<?= base_url('medicines/add.php') ?>" class="nav-link <?= navActive('medicines/add.php') ?>" style="padding-left:2.5rem; font-size:.82rem;">
    <i class="bi bi-plus-circle"></i> Add Medicine
  </a>
  <a href="<?= base_url('suppliers/index.php') ?>" class="nav-link <?= navActive('suppliers/index.php','suppliers') ?>">
    <i class="bi bi-truck"></i> Suppliers
  </a>

  <div class="nav-section">Sales</div>
  <a href="<?= base_url('sales/pos.php') ?>" class="nav-link <?= navActive('sales/pos.php','pos.php') ?>">
    <i class="bi bi-cart-plus"></i> New Sale (POS)
  </a>
  <a href="<?= base_url('sales/index.php') ?>" class="nav-link <?= navActive('sales/index.php') ?>">
    <i class="bi bi-receipt"></i> Sales History
  </a>

  <div class="nav-section">Reports</div>
  <a href="<?= base_url('reports/index.php') ?>" class="nav-link <?= navActive('reports/index.php','reports') ?>">
    <i class="bi bi-bar-chart-line"></i> Reports
  </a>

  <?php if (isAdmin()): ?>
  <div class="nav-section">Admin</div>
  <a href="<?= base_url('users/index.php') ?>" class="nav-link <?= navActive('users/index.php','users') ?>">
    <i class="bi bi-people"></i> Users
  </a>
  <a href="<?= base_url('audit_logs/index.php') ?>" class="nav-link <?= navActive('audit_logs/index.php','audit_logs') ?>">
    <i class="bi bi-journal-text"></i> Audit Logs
  </a>
  <?php endif ?>

  <div class="mt-auto" style="padding:1rem 1.25rem; border-top:1px solid #1e293b; margin-top:2rem;">
    <div class="d-flex align-items-center gap-2 mb-2">
      <span class="avatar"><?= strtoupper(substr($_SESSION['user_name'] ?? 'U', 0, 2)) ?></span>
      <div class="ms-2" style="line-height:1.2;">
        <div style="font-size:.8rem; color:#e2e8f0; font-weight:600;"><?= currentUserName() ?></div>
        <div style="font-size:.7rem; color:#64748b; text-transform:capitalize;"><?= currentUserRole() ?></div>
      </div>
    </div>
    <a href="<?= base_url('logout.php') ?>" class="nav-link" style="padding:.5rem 0; color:#ef4444;">
      <i class="bi bi-box-arrow-right"></i> Sign Out
    </a>
  </div>
</nav>

<!-- ============================================================ -->
<!--  MAIN CONTENT WRAPPER                                         -->
<!-- ============================================================ -->
<div id="main">
  <!-- Top Bar -->
  <div id="topbar">
    <button class="btn btn-sm btn-light d-md-none" id="sidebarToggle">
      <i class="bi bi-list"></i>
    </button>
    <span class="page-heading"><?= PAGE_TITLE ?></span>
    <div class="topbar-right">
      <?php if ($expiredCount > 0): ?>
        <a href="<?= base_url('reports/index.php') ?>?type=expired" class="btn btn-sm btn-outline-danger">
          <i class="bi bi-exclamation-triangle"></i> <?= $expiredCount ?> Expired
        </a>
      <?php endif ?>
      <?php if ($lowStockCount > 0): ?>
        <a href="<?= base_url('medicines/index.php') ?>?filter=low_stock" class="btn btn-sm btn-outline-warning">
          <i class="bi bi-exclamation-circle"></i> <?= $lowStockCount ?> Low Stock
        </a>
      <?php endif ?>
      <span class="text-muted" style="font-size:.8rem;"><?= date('D, d M Y') ?></span>
    </div>
  </div>

  <!-- Flash Alerts -->
  <div id="content">
  <?php if ($flash_success): ?>
    <div class="alert alert-success alert-dismissible flash-alert" role="alert">
      <i class="bi bi-check-circle-fill me-2"></i><?= sanitize($flash_success) ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endif ?>
  <?php if ($flash_error): ?>
    <div class="alert alert-danger alert-dismissible flash-alert" role="alert">
      <i class="bi bi-x-circle-fill me-2"></i><?= sanitize($flash_error) ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endif ?>
  <?php if ($flash_info): ?>
    <div class="alert alert-info alert-dismissible flash-alert" role="alert">
      <i class="bi bi-info-circle-fill me-2"></i><?= sanitize($flash_info) ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endif ?>

<?php endif ?>
