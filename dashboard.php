<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

requireLogin();
define('PAGE_TITLE', 'Dashboard');
require_once __DIR__ . '/includes/header.php';

$stats = getDashboardStats();

// Top selling medicines (this month)
$topMeds = db()->query(
    "SELECT m.name, SUM(si.quantity) AS total_qty, SUM(si.total_price) AS total_revenue
     FROM sale_items si
     JOIN medicines m ON m.id = si.medicine_id
     JOIN sales s ON s.id = si.sale_id
     WHERE MONTH(s.sale_date)=MONTH(CURDATE()) AND s.status='completed'
     GROUP BY m.id ORDER BY total_qty DESC LIMIT 5"
)->fetchAll();

// Low-stock medicines
$lowStockMeds = db()->query(
    "SELECT m.name, m.quantity, m.low_stock_threshold, c.name AS category
     FROM medicines m LEFT JOIN categories c ON c.id = m.category_id
     WHERE m.quantity <= m.low_stock_threshold
     ORDER BY m.quantity ASC LIMIT 8"
)->fetchAll();

// Expiring soon / expired
$expiryAlerts = db()->query(
    "SELECT name, expiry_date, quantity
     FROM medicines
     WHERE expiry_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
     ORDER BY expiry_date ASC LIMIT 8"
)->fetchAll();

// Sales chart data (last 7 days)
$salesDays = [];
$salesVals = [];
for ($i = 6; $i >= 0; $i--) {
    $salesDays[] = date('D d', strtotime("-{$i} days"));
}
$salesMap = [];
foreach ($stats['recentSales'] as $row) {
    $salesMap[$row['day']] = (float)$row['total'];
}
for ($i = 6; $i >= 0; $i--) {
    $key = date('Y-m-d', strtotime("-{$i} days"));
    $salesVals[] = $salesMap[$key] ?? 0;
}
?>

<!-- Stat Cards Row -->
<div class="row g-3 mb-4">
  <?php
  $cards = [
    ['icon'=>'bi-capsule-pill','label'=>'Total Medicines',  'value'=> $stats['totalMeds'],     'bg'=>'#eff6ff','ic'=>'#2563eb'],
    ['icon'=>'bi-exclamation-triangle','label'=>'Low Stock','value'=> $stats['lowStock'],       'bg'=>'#fff7ed','ic'=>'#f97316'],
    ['icon'=>'bi-x-circle',    'label'=>'Out of Stock',     'value'=> $stats['outOfStock'],     'bg'=>'#fef2f2','ic'=>'#ef4444'],
    ['icon'=>'bi-calendar-x',  'label'=>'Expired Items',    'value'=> $stats['expired'],        'bg'=>'#fdf4ff','ic'=>'#a21caf'],
    ['icon'=>'bi-calendar-event','label'=>'Expiring ≤30d',  'value'=> $stats['expiringSoon'],   'bg'=>'#fefce8','ic'=>'#ca8a04'],
    ['icon'=>'bi-truck',       'label'=>'Active Suppliers', 'value'=> $stats['totalSuppliers'], 'bg'=>'#f0fdf4','ic'=>'#16a34a'],
    ['icon'=>'bi-cash-stack',  'label'=>"Today's Sales",    'value'=> formatCurrency($stats['todaySales']), 'bg'=>'#f0fdfa','ic'=>'#0d9488'],
    ['icon'=>'bi-graph-up',    'label'=>'Monthly Revenue',  'value'=> formatCurrency($stats['monthlySales']), 'bg'=>'#eff6ff','ic'=>'#2563eb'],
  ];
  foreach ($cards as $c): ?>
    <div class="col-6 col-sm-4 col-md-3">
      <div class="card stat-card h-100">
        <div class="card-body d-flex align-items-center gap-3 p-3">
          <div class="icon-box" style="background:<?= $c['bg'] ?>; color:<?= $c['ic'] ?>;">
            <i class="bi <?= $c['icon'] ?>"></i>
          </div>
          <div>
            <div style="font-size:.75rem; color:#64748b; font-weight:600;"><?= $c['label'] ?></div>
            <div style="font-size:1.25rem; font-weight:700; color:#0f172a;"><?= $c['value'] ?></div>
          </div>
        </div>
      </div>
    </div>
  <?php endforeach ?>
</div>

<!-- Charts + Tables Row -->
<div class="row g-3 mb-4">
  <!-- Sales Chart -->
  <div class="col-md-7">
    <div class="card table-card h-100">
      <div class="card-header bg-white border-0 pt-3 pb-0 d-flex align-items-center justify-content-between">
        <h6 class="mb-0 fw-600">Sales – Last 7 Days</h6>
        <span class="badge bg-primary" style="font-size:.7rem;">
          <?= formatCurrency($stats['weeklySales']) ?> this week
        </span>
      </div>
      <div class="card-body">
        <canvas id="salesChart" height="100"></canvas>
      </div>
    </div>
  </div>

  <!-- Top Medicines -->
  <div class="col-md-5">
    <div class="card table-card h-100">
      <div class="card-header bg-white border-0 pt-3 pb-0">
        <h6 class="mb-0 fw-600">Top Selling This Month</h6>
      </div>
      <div class="card-body p-0">
        <?php if (empty($topMeds)): ?>
          <div class="text-center text-muted p-4"><i class="bi bi-bar-chart fs-2"></i><br>No sales yet this month</div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table mb-0">
              <thead><tr><th>Medicine</th><th class="text-end">Qty</th><th class="text-end">Revenue</th></tr></thead>
              <tbody>
              <?php foreach ($topMeds as $i => $m): ?>
                <tr>
                  <td>
                    <span class="badge me-1" style="background:#eff6ff; color:#2563eb;"><?= $i+1 ?></span>
                    <?= sanitize($m['name']) ?>
                  </td>
                  <td class="text-end fw-600"><?= $m['total_qty'] ?></td>
                  <td class="text-end text-success fw-600"><?= formatCurrency($m['total_revenue']) ?></td>
                </tr>
              <?php endforeach ?>
              </tbody>
            </table>
          </div>
        <?php endif ?>
      </div>
    </div>
  </div>
</div>

<!-- Alerts Row -->
<div class="row g-3">
  <!-- Low Stock Alerts -->
  <div class="col-md-6">
    <div class="card table-card">
      <div class="card-header bg-white border-0 pt-3 pb-0 d-flex align-items-center gap-2">
        <i class="bi bi-exclamation-circle-fill text-warning"></i>
        <h6 class="mb-0 fw-600">Low Stock Alerts</h6>
        <a href="<?= base_url('medicines/index.php') ?>?filter=low_stock" class="ms-auto btn btn-sm btn-outline-warning" style="font-size:.75rem;">View All</a>
      </div>
      <div class="card-body p-0">
        <?php if (empty($lowStockMeds)): ?>
          <div class="text-center text-muted p-4"><i class="bi bi-check-circle text-success fs-2"></i><br>All stock levels are healthy</div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table mb-0">
              <thead><tr><th>Medicine</th><th>Category</th><th class="text-end">Qty</th><th class="text-end">Threshold</th></tr></thead>
              <tbody>
              <?php foreach ($lowStockMeds as $m): ?>
                <tr>
                  <td><?= sanitize($m['name']) ?></td>
                  <td><span class="badge bg-light text-dark"><?= sanitize($m['category'] ?? '—') ?></span></td>
                  <td class="text-end">
                    <?php if ($m['quantity'] == 0): ?>
                      <span class="badge bg-danger">0</span>
                    <?php else: ?>
                      <span class="badge bg-warning text-dark"><?= $m['quantity'] ?></span>
                    <?php endif ?>
                  </td>
                  <td class="text-end text-muted"><?= $m['low_stock_threshold'] ?></td>
                </tr>
              <?php endforeach ?>
              </tbody>
            </table>
          </div>
        <?php endif ?>
      </div>
    </div>
  </div>

  <!-- Expiry Alerts -->
  <div class="col-md-6">
    <div class="card table-card">
      <div class="card-header bg-white border-0 pt-3 pb-0 d-flex align-items-center gap-2">
        <i class="bi bi-calendar-x-fill text-danger"></i>
        <h6 class="mb-0 fw-600">Expiry Alerts</h6>
        <a href="<?= base_url('reports/index.php') ?>?type=expired" class="ms-auto btn btn-sm btn-outline-danger" style="font-size:.75rem;">View All</a>
      </div>
      <div class="card-body p-0">
        <?php if (empty($expiryAlerts)): ?>
          <div class="text-center text-muted p-4"><i class="bi bi-check-circle text-success fs-2"></i><br>No expiry alerts</div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table mb-0">
              <thead><tr><th>Medicine</th><th>Expiry Date</th><th>Status</th><th class="text-end">Qty</th></tr></thead>
              <tbody>
              <?php foreach ($expiryAlerts as $m): ?>
                <tr>
                  <td><?= sanitize($m['name']) ?></td>
                  <td><?= formatDate($m['expiry_date']) ?></td>
                  <td><?= expiryBadge($m['expiry_date']) ?></td>
                  <td class="text-end"><?= $m['quantity'] ?></td>
                </tr>
              <?php endforeach ?>
              </tbody>
            </table>
          </div>
        <?php endif ?>
      </div>
    </div>
  </div>
</div>

<script>
const ctx = document.getElementById('salesChart')?.getContext('2d');
if (ctx) {
  new Chart(ctx, {
    type: 'bar',
    data: {
      labels: <?= json_encode($salesDays) ?>,
      datasets: [{
        label: 'Sales ($)',
        data: <?= json_encode($salesVals) ?>,
        backgroundColor: 'rgba(37,99,235,.15)',
        borderColor: '#2563eb',
        borderWidth: 2,
        borderRadius: 6,
        fill: true,
      }]
    },
    options: {
      responsive: true,
      plugins: { legend: { display: false } },
      scales: {
        y: { beginAtZero: true, grid: { color: '#f1f5f9' },
             ticks: { callback: v => '$' + v.toLocaleString() } },
        x: { grid: { display: false } }
      }
    }
  });
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
