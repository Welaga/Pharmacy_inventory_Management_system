<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();
define('PAGE_TITLE', 'Sales History');
require_once __DIR__ . '/../includes/header.php';

$search    = trim($_GET['search']   ?? '');
$dateFrom  = $_GET['date_from']     ?? '';
$dateTo    = $_GET['date_to']       ?? '';
$status    = $_GET['status']        ?? '';
$page      = max(1, (int)($_GET['page'] ?? 1));
$perPage   = 20;

$where  = ['1=1'];
$params = [];

if ($search) {
    $where[]  = '(s.customer_name LIKE ? OR u.name LIKE ?)';
    $params[] = "%$search%"; $params[] = "%$search%";
}
if ($dateFrom) { $where[] = 'DATE(s.sale_date) >= ?'; $params[] = $dateFrom; }
if ($dateTo)   { $where[] = 'DATE(s.sale_date) <= ?'; $params[] = $dateTo; }
if ($status)   { $where[] = 's.status = ?';           $params[] = $status; }
$w = implode(' AND ', $where);

$countStmt = db()->prepare("SELECT COUNT(*) FROM sales s JOIN users u ON u.id=s.user_id WHERE $w");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$pag   = paginate($total, $perPage, $page);

$stmt = db()->prepare(
    "SELECT s.*, u.name AS cashier,
            COUNT(si.id) AS item_count
     FROM sales s
     JOIN users u ON u.id = s.user_id
     LEFT JOIN sale_items si ON si.sale_id = s.id
     WHERE $w
     GROUP BY s.id
     ORDER BY s.sale_date DESC
     LIMIT {$perPage} OFFSET {$pag['offset']}"
);
$stmt->execute($params);
$sales = $stmt->fetchAll();

// Summary for filtered period
$sumStmt = db()->prepare("SELECT COALESCE(SUM(total_amount),0) AS total_rev, COUNT(*) AS total_tx FROM sales s JOIN users u ON u.id=s.user_id WHERE $w AND s.status='completed'");
$sumStmt->execute($params);
$summary = $sumStmt->fetch();

$baseUrl = '?' . http_build_query(array_filter(['search'=>$search,'date_from'=>$dateFrom,'date_to'=>$dateTo,'status'=>$status]));
?>

<div class="d-flex align-items-center justify-content-between mb-4">
  <div>
    <h5 class="mb-0 fw-bold">Sales History</h5>
    <small class="text-muted"><?= number_format($total) ?> transactions</small>
  </div>
  <a href="<?= base_url('sales/pos.php') ?>" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>New Sale</a>
</div>

<!-- Summary Cards -->
<div class="row g-3 mb-4">
  <div class="col-6 col-md-3">
    <div class="card stat-card">
      <div class="card-body p-3 d-flex align-items-center gap-3">
        <div class="icon-box" style="background:#eff6ff;color:#2563eb;"><i class="bi bi-cash-stack"></i></div>
        <div><div style="font-size:.7rem;color:#64748b;font-weight:600;">REVENUE</div>
             <div style="font-size:1.1rem;font-weight:700;"><?= formatCurrency($summary['total_rev']) ?></div></div>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card stat-card">
      <div class="card-body p-3 d-flex align-items-center gap-3">
        <div class="icon-box" style="background:#f0fdf4;color:#16a34a;"><i class="bi bi-receipt"></i></div>
        <div><div style="font-size:.7rem;color:#64748b;font-weight:600;">TRANSACTIONS</div>
             <div style="font-size:1.1rem;font-weight:700;"><?= number_format($summary['total_tx']) ?></div></div>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card stat-card">
      <div class="card-body p-3 d-flex align-items-center gap-3">
        <div class="icon-box" style="background:#fefce8;color:#ca8a04;"><i class="bi bi-graph-up"></i></div>
        <div><div style="font-size:.7rem;color:#64748b;font-weight:600;">AVG SALE</div>
             <div style="font-size:1.1rem;font-weight:700;"><?= $summary['total_tx'] > 0 ? formatCurrency($summary['total_rev'] / $summary['total_tx']) : '$0.00' ?></div></div>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card stat-card">
      <div class="card-body p-3 d-flex align-items-center gap-3">
        <div class="icon-box" style="background:#f0fdfa;color:#0d9488;"><i class="bi bi-calendar-check"></i></div>
        <div><div style="font-size:.7rem;color:#64748b;font-weight:600;">TODAY</div>
             <div style="font-size:1.1rem;font-weight:700;"><?= formatCurrency(db()->query("SELECT COALESCE(SUM(total_amount),0) FROM sales WHERE DATE(sale_date)=CURDATE() AND status='completed'")->fetchColumn()) ?></div></div>
      </div>
    </div>
  </div>
</div>

<!-- Filters -->
<div class="card table-card mb-4">
  <div class="card-body p-3">
    <form method="GET" class="row g-2 align-items-end">
      <div class="col-md-3">
        <input type="text" name="search" class="form-control" placeholder="Customer or cashier…" value="<?= sanitize($search) ?>"/>
      </div>
      <div class="col-md-2">
        <input type="date" name="date_from" class="form-control" value="<?= sanitize($dateFrom) ?>"/>
      </div>
      <div class="col-md-2">
        <input type="date" name="date_to" class="form-control" value="<?= sanitize($dateTo) ?>"/>
      </div>
      <div class="col-md-2">
        <select name="status" class="form-select">
          <option value="">All Status</option>
          <option value="completed" <?= $status==='completed'?'selected':'' ?>>Completed</option>
          <option value="cancelled" <?= $status==='cancelled'?'selected':'' ?>>Cancelled</option>
          <option value="refunded"  <?= $status==='refunded' ?'selected':'' ?>>Refunded</option>
        </select>
      </div>
      <div class="col-auto">
        <button type="submit" class="btn btn-primary"><i class="bi bi-search me-1"></i>Filter</button>
        <a href="?" class="btn btn-outline-secondary ms-1"><i class="bi bi-x"></i></a>
      </div>
    </form>
  </div>
</div>

<!-- Table -->
<div class="card table-card">
  <div class="table-responsive">
    <table class="table align-middle mb-0">
      <thead>
        <tr>
          <th>Receipt #</th>
          <th>Date & Time</th>
          <th>Customer</th>
          <th>Cashier</th>
          <th class="text-center">Items</th>
          <th class="text-end">Discount</th>
          <th class="text-end">Total</th>
          <th>Payment</th>
          <th>Status</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
      <?php if (empty($sales)): ?>
        <tr><td colspan="10" class="text-center text-muted py-5">
          <i class="bi bi-receipt fs-2 d-block mb-2"></i>No sales found.
        </td></tr>
      <?php else: foreach ($sales as $s): ?>
        <tr>
          <td><code><?= str_pad($s['id'],6,'0',STR_PAD_LEFT) ?></code></td>
          <td style="font-size:.8rem;"><?= date('d M Y', strtotime($s['sale_date'])) ?><br>
            <span class="text-muted"><?= date('h:i A', strtotime($s['sale_date'])) ?></span>
          </td>
          <td><?= sanitize($s['customer_name']) ?></td>
          <td><?= sanitize($s['cashier']) ?></td>
          <td class="text-center"><span class="badge bg-light text-dark"><?= $s['item_count'] ?></span></td>
          <td class="text-end text-muted"><?= $s['discount'] > 0 ? '— '.formatCurrency($s['discount']) : '—' ?></td>
          <td class="text-end fw-bold text-primary"><?= formatCurrency($s['total_amount']) ?></td>
          <td>
            <?php $pm = $s['payment_method'];
            $pmClass = $pm==='cash'?'success':($pm==='card'?'info':'warning'); ?>
            <span class="badge bg-<?= $pmClass ?>"><?= ucfirst($pm) ?></span>
          </td>
          <td>
            <?php $stClass = $s['status']==='completed'?'success':($s['status']==='cancelled'?'secondary':'warning'); ?>
            <span class="badge bg-<?= $stClass ?>"><?= ucfirst($s['status']) ?></span>
          </td>
          <td>
            <div class="d-flex gap-1">
              <a href="<?= base_url('sales/receipt.php') ?>?id=<?= $s['id'] ?>"
                 class="btn btn-sm btn-outline-primary" title="View Receipt">
                <i class="bi bi-receipt"></i>
              </a>
              <?php if (isAdmin() && $s['status'] === 'completed'): ?>
                <a href="<?= base_url('sales/cancel.php') ?>?id=<?= $s['id'] ?>"
                   class="btn btn-sm btn-outline-warning"
                   data-confirm="Cancel sale #<?= str_pad($s['id'],6,'0',STR_PAD_LEFT) ?>? Stock will NOT be automatically restored."
                   title="Cancel">
                  <i class="bi bi-x-circle"></i>
                </a>
              <?php endif ?>
            </div>
          </td>
        </tr>
      <?php endforeach; endif ?>
      </tbody>
    </table>
  </div>
  <?php if ($pag['total_pages'] > 1): ?>
    <div class="card-footer bg-white border-0 py-3"><?= paginationLinks($pag, $baseUrl) ?></div>
  <?php endif ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
