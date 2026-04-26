<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();
define('PAGE_TITLE', 'Reports');

$pdo      = db();
$type     = $_GET['type']      ?? 'sales';
$period   = $_GET['period']    ?? 'monthly';
$dateFrom = $_GET['date_from'] ?? date('Y-m-01');
$dateTo   = $_GET['date_to']   ?? date('Y-m-d');
$export   = $_GET['export']    ?? '';

// ── DATA FETCHERS ─────────────────────────────────────────

function fetchSalesReport(PDO $pdo, string $period, string $from, string $to): array {
    $groupBy = match($period) {
        'daily'   => 'DATE(s.sale_date)',
        'weekly'  => 'YEARWEEK(s.sale_date, 1)',
        'monthly' => 'DATE_FORMAT(s.sale_date, "%Y-%m")',
        default   => 'DATE(s.sale_date)',
    };
    $label = match($period) {
        'daily'   => 'DATE(s.sale_date)',
        'weekly'  => "CONCAT(YEAR(s.sale_date),\'-W\',LPAD(WEEK(s.sale_date,1),2,\'0\'))",
        'monthly' => "DATE_FORMAT(s.sale_date, '%M %Y')",
        default   => 'DATE(s.sale_date)',
    };
    $stmt = $pdo->prepare(
        "SELECT {$label} AS period_label,
                COUNT(DISTINCT s.id) AS transactions,
                SUM(s.total_amount)  AS revenue,
                SUM(s.discount)      AS discounts,
                AVG(s.total_amount)  AS avg_sale
         FROM sales s
         WHERE DATE(s.sale_date) BETWEEN ? AND ?
           AND s.status = 'completed'
         GROUP BY {$groupBy}
         ORDER BY {$groupBy} ASC"
    );
    $stmt->execute([$from, $to]);
    return $stmt->fetchAll();
}

function fetchInventoryReport(PDO $pdo): array {
    return $pdo->query(
        "SELECT m.name, c.name AS category, m.batch_no, m.expiry_date,
                m.purchase_price, m.selling_price, m.quantity,
                m.low_stock_threshold, s.name AS supplier,
                (m.quantity * m.selling_price) AS stock_value
         FROM medicines m
         LEFT JOIN categories c ON c.id = m.category_id
         LEFT JOIN suppliers  s ON s.id = m.supplier_id
         ORDER BY m.name ASC"
    )->fetchAll();
}

function fetchExpiredReport(PDO $pdo): array {
    return $pdo->query(
        "SELECT m.name, c.name AS category, m.batch_no,
                m.expiry_date, m.quantity,
                m.selling_price, (m.quantity * m.selling_price) AS loss_value,
                s.name AS supplier
         FROM medicines m
         LEFT JOIN categories c ON c.id = m.category_id
         LEFT JOIN suppliers  s ON s.id = m.supplier_id
         WHERE m.expiry_date < CURDATE()
         ORDER BY m.expiry_date ASC"
    )->fetchAll();
}

function fetchTopMedicinesReport(PDO $pdo, string $from, string $to): array {
    $stmt = $pdo->prepare(
        "SELECT m.name, c.name AS category,
                SUM(si.quantity)   AS qty_sold,
                SUM(si.total_price) AS revenue
         FROM sale_items si
         JOIN medicines m ON m.id = si.medicine_id
         JOIN sales     s ON s.id = si.sale_id
         LEFT JOIN categories c ON c.id = m.category_id
         WHERE DATE(s.sale_date) BETWEEN ? AND ? AND s.status='completed'
         GROUP BY m.id ORDER BY qty_sold DESC LIMIT 20"
    );
    $stmt->execute([$from, $to]);
    return $stmt->fetchAll();
}

// ── CSV EXPORT ─────────────────────────────────────────────
if ($export === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    $fn = "pharmacy_{$type}_".date('Ymd_His').'.csv';
    header("Content-Disposition: attachment; filename=\"{$fn}\"");
    $out = fopen('php://output', 'w');

    if ($type === 'sales') {
        fputcsv($out, ['Period','Transactions','Revenue ($)','Discounts ($)','Avg Sale ($)']);
        foreach (fetchSalesReport($pdo,$period,$dateFrom,$dateTo) as $r) {
            fputcsv($out, [$r['period_label'],$r['transactions'],
                number_format($r['revenue'],2),number_format($r['discounts'],2),number_format($r['avg_sale'],2)]);
        }
    } elseif ($type === 'inventory') {
        fputcsv($out, ['Name','Category','Batch No','Expiry Date','Purchase ($)','Selling ($)','Qty','Threshold','Supplier','Stock Value ($)']);
        foreach (fetchInventoryReport($pdo) as $r) {
            fputcsv($out, [$r['name'],$r['category']??'',$r['batch_no']??'',$r['expiry_date'],
                $r['purchase_price'],$r['selling_price'],$r['quantity'],$r['low_stock_threshold'],
                $r['supplier']??'',number_format($r['stock_value'],2)]);
        }
    } elseif ($type === 'expired') {
        fputcsv($out, ['Name','Category','Batch No','Expiry Date','Qty','Selling ($)','Loss Value ($)','Supplier']);
        foreach (fetchExpiredReport($pdo) as $r) {
            fputcsv($out, [$r['name'],$r['category']??'',$r['batch_no']??'',$r['expiry_date'],
                $r['quantity'],$r['selling_price'],number_format($r['loss_value'],2),$r['supplier']??'']);
        }
    } elseif ($type === 'top_medicines') {
        fputcsv($out, ['Medicine','Category','Qty Sold','Revenue ($)']);
        foreach (fetchTopMedicinesReport($pdo,$dateFrom,$dateTo) as $r) {
            fputcsv($out, [$r['name'],$r['category']??'',$r['qty_sold'],number_format($r['revenue'],2)]);
        }
    }
    fclose($out); exit;
}

// ── RENDER ────────────────────────────────────────────────
$salesData       = fetchSalesReport($pdo, $period, $dateFrom, $dateTo);
$inventoryData   = fetchInventoryReport($pdo);
$expiredData     = fetchExpiredReport($pdo);
$topMedsData     = fetchTopMedicinesReport($pdo, $dateFrom, $dateTo);

// Summary stats
$totalStockValue  = array_sum(array_column($inventoryData, 'stock_value'));
$totalExpiredLoss = array_sum(array_column($expiredData,   'loss_value'));
$totalRevenue     = array_sum(array_column($salesData,     'revenue'));

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex align-items-center justify-content-between mb-4">
  <h5 class="mb-0 fw-bold">Reports & Analytics</h5>
  <a href="?type=<?= urlencode($type) ?>&period=<?= urlencode($period) ?>&date_from=<?= urlencode($dateFrom) ?>&date_to=<?= urlencode($dateTo) ?>&export=csv"
     class="btn btn-success btn-sm"><i class="bi bi-download me-1"></i>Export CSV</a>
</div>

<!-- Report Type Tabs -->
<ul class="nav nav-pills mb-4 flex-wrap gap-1">
  <?php foreach (['sales'=>'Sales Report','inventory'=>'Inventory Report','expired'=>'Expired Medicines','top_medicines'=>'Top Medicines'] as $t => $lbl): ?>
    <li class="nav-item">
      <a href="?type=<?= $t ?>&period=<?= $period ?>&date_from=<?= $dateFrom ?>&date_to=<?= $dateTo ?>"
         class="nav-link <?= $type===$t?'active':'' ?>">
        <?= $lbl ?>
      </a>
    </li>
  <?php endforeach ?>
</ul>

<!-- Date filter (for sales / top medicines) -->
<?php if (in_array($type, ['sales','top_medicines'])): ?>
<div class="card table-card mb-4">
  <div class="card-body p-3">
    <form method="GET" class="row g-2 align-items-end">
      <input type="hidden" name="type" value="<?= $type ?>"/>
      <?php if ($type === 'sales'): ?>
      <div class="col-auto">
        <select name="period" class="form-select form-select-sm">
          <?php foreach (['daily'=>'Daily','weekly'=>'Weekly','monthly'=>'Monthly'] as $pk=>$pl): ?>
            <option value="<?= $pk ?>" <?= $period===$pk?'selected':'' ?>><?= $pl ?></option>
          <?php endforeach ?>
        </select>
      </div>
      <?php endif ?>
      <div class="col-auto"><label class="form-label mb-0 me-1" style="font-size:.8rem;">From</label>
        <input type="date" name="date_from" class="form-control form-control-sm" value="<?= $dateFrom ?>"/>
      </div>
      <div class="col-auto"><label class="form-label mb-0 me-1" style="font-size:.8rem;">To</label>
        <input type="date" name="date_to" class="form-control form-control-sm" value="<?= $dateTo ?>"/>
      </div>
      <div class="col-auto"><button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-search me-1"></i>Apply</button></div>
    </form>
  </div>
</div>
<?php endif ?>

<!-- ── SALES REPORT ── -->
<?php if ($type === 'sales'): ?>
  <!-- Summary -->
  <div class="row g-3 mb-4">
    <?php
    $totalTx    = array_sum(array_column($salesData,'transactions'));
    $totalDisc  = array_sum(array_column($salesData,'discounts'));
    $summCards  = [
      ['Revenue', formatCurrency($totalRevenue),  '#eff6ff','#2563eb','bi-cash-stack'],
      ['Transactions', number_format($totalTx),   '#f0fdf4','#16a34a','bi-receipt'],
      ['Avg Sale', $totalTx > 0 ? formatCurrency($totalRevenue/$totalTx) : '$0.00', '#fefce8','#ca8a04','bi-graph-up'],
      ['Discounts', formatCurrency($totalDisc),   '#fff1f2','#e11d48','bi-tag'],
    ];
    foreach ($summCards as [$lbl,$val,$bg,$ic,$icon]): ?>
    <div class="col-6 col-md-3">
      <div class="card stat-card">
        <div class="card-body p-3 d-flex align-items-center gap-3">
          <div class="icon-box" style="background:<?= $bg ?>;color:<?= $ic ?>;"><i class="bi <?= $icon ?>"></i></div>
          <div><div style="font-size:.7rem;color:#64748b;font-weight:600;"><?= $lbl ?></div>
               <div style="font-size:1.1rem;font-weight:700;"><?= $val ?></div></div>
        </div>
      </div>
    </div>
    <?php endforeach ?>
  </div>

  <!-- Chart -->
  <?php if (!empty($salesData)): ?>
  <div class="card table-card mb-4">
    <div class="card-header bg-white border-0 pt-3 pb-0"><h6 class="fw-bold mb-0">Revenue Trend</h6></div>
    <div class="card-body"><canvas id="salesTrendChart" height="80"></canvas></div>
  </div>
  <?php endif ?>

  <!-- Table -->
  <div class="card table-card">
    <div class="table-responsive">
      <table class="table align-middle mb-0">
        <thead><tr><th>Period</th><th class="text-end">Transactions</th><th class="text-end">Revenue</th><th class="text-end">Discounts</th><th class="text-end">Avg Sale</th></tr></thead>
        <tbody>
        <?php if (empty($salesData)): ?>
          <tr><td colspan="5" class="text-center text-muted py-5"><i class="bi bi-bar-chart fs-2 d-block mb-2"></i>No data for selected period.</td></tr>
        <?php else: foreach ($salesData as $r): ?>
          <tr>
            <td class="fw-600"><?= sanitize($r['period_label']) ?></td>
            <td class="text-end"><?= number_format($r['transactions']) ?></td>
            <td class="text-end text-primary fw-bold"><?= formatCurrency($r['revenue']) ?></td>
            <td class="text-end text-danger"><?= formatCurrency($r['discounts']) ?></td>
            <td class="text-end text-muted"><?= formatCurrency($r['avg_sale']) ?></td>
          </tr>
        <?php endforeach; endif ?>
        </tbody>
        <?php if (!empty($salesData)): ?>
        <tfoot style="background:#f8fafc;">
          <tr class="fw-bold">
            <td>TOTAL</td>
            <td class="text-end"><?= number_format($totalTx) ?></td>
            <td class="text-end text-primary"><?= formatCurrency($totalRevenue) ?></td>
            <td class="text-end text-danger"><?= formatCurrency($totalDisc) ?></td>
            <td class="text-end">—</td>
          </tr>
        </tfoot>
        <?php endif ?>
      </table>
    </div>
  </div>

  <script>
  const ctx = document.getElementById('salesTrendChart')?.getContext('2d');
  if (ctx) {
    new Chart(ctx, {
      type: 'line',
      data: {
        labels: <?= json_encode(array_column($salesData,'period_label')) ?>,
        datasets: [{
          label: 'Revenue',
          data: <?= json_encode(array_column($salesData,'revenue')) ?>,
          borderColor: '#2563eb', backgroundColor: 'rgba(37,99,235,.08)',
          borderWidth: 2, pointRadius: 4, fill: true, tension: .3,
        }]
      },
      options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: {
          y: { beginAtZero:true, ticks: { callback: v => '$'+v.toLocaleString() }, grid: { color:'#f1f5f9' } },
          x: { grid: { display:false } }
        }
      }
    });
  }
  </script>

<!-- ── INVENTORY REPORT ── -->
<?php elseif ($type === 'inventory'): ?>
  <!-- Summary row -->
  <div class="row g-3 mb-4">
    <?php
    $inStock   = count(array_filter($inventoryData, fn($r) => $r['quantity'] > $r['low_stock_threshold']));
    $lowStock  = count(array_filter($inventoryData, fn($r) => $r['quantity'] > 0 && $r['quantity'] <= $r['low_stock_threshold']));
    $outStock  = count(array_filter($inventoryData, fn($r) => $r['quantity'] == 0));
    $cards = [
      ['Total Stock Value', formatCurrency($totalStockValue), '#eff6ff','#2563eb','bi-currency-dollar'],
      ['In Stock',   number_format($inStock),  '#f0fdf4','#16a34a','bi-check-circle'],
      ['Low Stock',  number_format($lowStock), '#fff7ed','#f97316','bi-exclamation-circle'],
      ['Out of Stock',number_format($outStock),'#fef2f2','#ef4444','bi-x-circle'],
    ];
    foreach ($cards as [$lbl,$val,$bg,$ic,$icon]): ?>
    <div class="col-6 col-md-3">
      <div class="card stat-card">
        <div class="card-body p-3 d-flex align-items-center gap-3">
          <div class="icon-box" style="background:<?= $bg ?>;color:<?= $ic ?>;"><i class="bi <?= $icon ?>"></i></div>
          <div><div style="font-size:.7rem;color:#64748b;font-weight:600;"><?= $lbl ?></div>
               <div style="font-size:1.1rem;font-weight:700;"><?= $val ?></div></div>
        </div>
      </div>
    </div>
    <?php endforeach ?>
  </div>

  <div class="card table-card">
    <div class="table-responsive">
      <table class="table align-middle mb-0">
        <thead><tr><th>Medicine</th><th>Category</th><th>Batch</th><th>Expiry</th><th class="text-end">Buy</th><th class="text-end">Sell</th><th class="text-end">Qty</th><th>Stock</th><th>Expiry Status</th><th class="text-end">Value</th></tr></thead>
        <tbody>
        <?php foreach ($inventoryData as $r): ?>
          <tr>
            <td class="fw-600"><?= sanitize($r['name']) ?></td>
            <td><span class="badge bg-light text-dark"><?= sanitize($r['category']??'—') ?></span></td>
            <td><code><?= sanitize($r['batch_no']??'—') ?></code></td>
            <td><?= formatDate($r['expiry_date']) ?></td>
            <td class="text-end"><?= formatCurrency($r['purchase_price']) ?></td>
            <td class="text-end"><?= formatCurrency($r['selling_price']) ?></td>
            <td class="text-end fw-bold"><?= number_format($r['quantity']) ?></td>
            <td><?= stockBadge($r['quantity'], $r['low_stock_threshold']) ?></td>
            <td><?= expiryBadge($r['expiry_date']) ?></td>
            <td class="text-end text-primary fw-600"><?= formatCurrency($r['stock_value']) ?></td>
          </tr>
        <?php endforeach ?>
        </tbody>
        <tfoot style="background:#f8fafc;">
          <tr class="fw-bold"><td colspan="9">TOTAL STOCK VALUE</td><td class="text-end text-primary"><?= formatCurrency($totalStockValue) ?></td></tr>
        </tfoot>
      </table>
    </div>
  </div>

<!-- ── EXPIRED REPORT ── -->
<?php elseif ($type === 'expired'): ?>
  <div class="row g-3 mb-4">
    <div class="col-md-4">
      <div class="card stat-card">
        <div class="card-body p-3 d-flex align-items-center gap-3">
          <div class="icon-box" style="background:#fef2f2;color:#ef4444;"><i class="bi bi-calendar-x"></i></div>
          <div><div style="font-size:.7rem;color:#64748b;font-weight:600;">EXPIRED MEDICINES</div>
               <div style="font-size:1.1rem;font-weight:700;"><?= count($expiredData) ?></div></div>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card stat-card">
        <div class="card-body p-3 d-flex align-items-center gap-3">
          <div class="icon-box" style="background:#fff1f2;color:#e11d48;"><i class="bi bi-currency-dollar"></i></div>
          <div><div style="font-size:.7rem;color:#64748b;font-weight:600;">POTENTIAL LOSS</div>
               <div style="font-size:1.1rem;font-weight:700;"><?= formatCurrency($totalExpiredLoss) ?></div></div>
        </div>
      </div>
    </div>
  </div>

  <div class="card table-card">
    <div class="table-responsive">
      <table class="table align-middle mb-0">
        <thead><tr><th>Medicine</th><th>Category</th><th>Batch No</th><th>Expired On</th><th class="text-end">Qty</th><th class="text-end">Unit Price</th><th class="text-end">Loss Value</th><th>Supplier</th></tr></thead>
        <tbody>
        <?php if (empty($expiredData)): ?>
          <tr><td colspan="8" class="text-center text-muted py-5"><i class="bi bi-check-circle text-success fs-2 d-block mb-2"></i>No expired medicines found!</td></tr>
        <?php else: foreach ($expiredData as $r): ?>
          <tr class="table-danger" style="background:#fff5f5;">
            <td class="fw-600"><?= sanitize($r['name']) ?></td>
            <td><?= sanitize($r['category']??'—') ?></td>
            <td><code><?= sanitize($r['batch_no']??'—') ?></code></td>
            <td class="text-danger fw-600"><?= formatDate($r['expiry_date']) ?></td>
            <td class="text-end"><?= $r['quantity'] ?></td>
            <td class="text-end"><?= formatCurrency($r['selling_price']) ?></td>
            <td class="text-end fw-bold text-danger"><?= formatCurrency($r['loss_value']) ?></td>
            <td><?= sanitize($r['supplier']??'—') ?></td>
          </tr>
        <?php endforeach; endif ?>
        </tbody>
        <?php if (!empty($expiredData)): ?>
        <tfoot style="background:#fef2f2;">
          <tr class="fw-bold"><td colspan="6">TOTAL LOSS</td><td class="text-end text-danger"><?= formatCurrency($totalExpiredLoss) ?></td><td></td></tr>
        </tfoot>
        <?php endif ?>
      </table>
    </div>
  </div>

<!-- ── TOP MEDICINES ── -->
<?php elseif ($type === 'top_medicines'): ?>
  <div class="row g-3 mb-4">
    <?php
    $totalQtySold = array_sum(array_column($topMedsData,'qty_sold'));
    $totalTopRev  = array_sum(array_column($topMedsData,'revenue'));
    ?>
    <div class="col-md-4">
      <div class="card stat-card">
        <div class="card-body p-3 d-flex align-items-center gap-3">
          <div class="icon-box" style="background:#eff6ff;color:#2563eb;"><i class="bi bi-capsule-pill"></i></div>
          <div><div style="font-size:.7rem;color:#64748b;font-weight:600;">UNITS SOLD</div>
               <div style="font-size:1.1rem;font-weight:700;"><?= number_format($totalQtySold) ?></div></div>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card stat-card">
        <div class="card-body p-3 d-flex align-items-center gap-3">
          <div class="icon-box" style="background:#f0fdf4;color:#16a34a;"><i class="bi bi-cash-stack"></i></div>
          <div><div style="font-size:.7rem;color:#64748b;font-weight:600;">REVENUE</div>
               <div style="font-size:1.1rem;font-weight:700;"><?= formatCurrency($totalTopRev) ?></div></div>
        </div>
      </div>
    </div>
  </div>

  <?php if (!empty($topMedsData)): ?>
  <div class="card table-card mb-4">
    <div class="card-header bg-white border-0 pt-3 pb-0"><h6 class="fw-bold mb-0">Top 10 Medicines by Quantity Sold</h6></div>
    <div class="card-body"><canvas id="topMedsChart" height="80"></canvas></div>
  </div>
  <?php endif ?>

  <div class="card table-card">
    <div class="table-responsive">
      <table class="table align-middle mb-0">
        <thead><tr><th>#</th><th>Medicine</th><th>Category</th><th class="text-end">Qty Sold</th><th class="text-end">Revenue</th><th>Share</th></tr></thead>
        <tbody>
        <?php if (empty($topMedsData)): ?>
          <tr><td colspan="6" class="text-center text-muted py-5">No sales data for this period.</td></tr>
        <?php else: foreach ($topMedsData as $i => $r): ?>
          <tr>
            <td><span class="badge" style="background:<?= $i<3?'#2563eb':'#e2e8f0' ?>;color:<?= $i<3?'#fff':'#374151' ?>"><?= $i+1 ?></span></td>
            <td class="fw-600"><?= sanitize($r['name']) ?></td>
            <td><span class="badge bg-light text-dark"><?= sanitize($r['category']??'—') ?></span></td>
            <td class="text-end fw-bold"><?= number_format($r['qty_sold']) ?></td>
            <td class="text-end text-primary fw-bold"><?= formatCurrency($r['revenue']) ?></td>
            <td style="min-width:120px;">
              <?php $pct = $totalTopRev > 0 ? round($r['revenue']/$totalTopRev*100,1) : 0; ?>
              <div class="d-flex align-items-center gap-2">
                <div class="progress flex-grow-1" style="height:6px;">
                  <div class="progress-bar bg-primary" style="width:<?= $pct ?>%"></div>
                </div>
                <small><?= $pct ?>%</small>
              </div>
            </td>
          </tr>
        <?php endforeach; endif ?>
        </tbody>
      </table>
    </div>
  </div>

  <script>
  const tmc = document.getElementById('topMedsChart')?.getContext('2d');
  if (tmc) {
    const top10 = <?= json_encode(array_slice($topMedsData,0,10)) ?>;
    new Chart(tmc, {
      type: 'bar',
      data: {
        labels: top10.map(r => r.name),
        datasets: [{
          label: 'Qty Sold',
          data: top10.map(r => r.qty_sold),
          backgroundColor: top10.map((_,i)=>['#2563eb','#3b82f6','#60a5fa','#93c5fd','#bfdbfe'][i%5]),
          borderRadius: 6,
        }]
      },
      options: {
        responsive:true,
        plugins:{legend:{display:false}},
        scales:{y:{beginAtZero:true,grid:{color:'#f1f5f9'}},x:{grid:{display:false}}}
      }
    });
  }
  </script>
<?php endif ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
