<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();
define('PAGE_TITLE', 'Medicines');
require_once __DIR__ . '/../includes/header.php';

// ── Query params ──────────────────────────────────────────
$search    = trim($_GET['search']   ?? '');
$filter    = $_GET['filter']        ?? '';
$catFilter = (int)($_GET['cat']     ?? 0);
$page      = max(1, (int)($_GET['page'] ?? 1));
$perPage   = 20;

// ── Build WHERE ───────────────────────────────────────────
$where  = ['1=1'];
$params = [];

if ($search) {
    $where[]  = '(m.name LIKE ? OR m.batch_no LIKE ? OR m.barcode LIKE ?)';
    $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%";
}
if ($filter === 'low_stock') {
    $where[] = 'm.quantity <= m.low_stock_threshold';
} elseif ($filter === 'out_of_stock') {
    $where[] = 'm.quantity = 0';
} elseif ($filter === 'expired') {
    $where[] = 'm.expiry_date < CURDATE()';
} elseif ($filter === 'expiring') {
    $where[] = 'm.expiry_date >= CURDATE() AND m.expiry_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)';
}
if ($catFilter) {
    $where[]  = 'm.category_id = ?';
    $params[] = $catFilter;
}
$whereSQL = implode(' AND ', $where);

// ── Count ─────────────────────────────────────────────────
$countStmt = db()->prepare("SELECT COUNT(*) FROM medicines m WHERE $whereSQL");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$pagination = paginate($total, $perPage, $page);

// ── Fetch medicines ───────────────────────────────────────
$stmt = db()->prepare(
    "SELECT m.*, c.name AS category, s.name AS supplier_name
     FROM medicines m
     LEFT JOIN categories c ON c.id = m.category_id
     LEFT JOIN suppliers s  ON s.id = m.supplier_id
     WHERE $whereSQL
     ORDER BY m.name ASC
     LIMIT {$perPage} OFFSET {$pagination['offset']}"
);
$stmt->execute($params);
$medicines = $stmt->fetchAll();

// Categories for filter dropdown
$categories = db()->query("SELECT id, name FROM categories ORDER BY name")->fetchAll();

$baseUrl = '?' . http_build_query(array_filter(['search'=>$search,'filter'=>$filter,'cat'=>$catFilter]));
?>

<!-- Page header -->
<div class="d-flex align-items-center justify-content-between mb-4">
  <div>
    <h5 class="mb-0 fw-bold">Medicine Inventory</h5>
    <small class="text-muted"><?= number_format($total) ?> records found</small>
  </div>
  <?php if (isAdmin()): ?>
    <a href="<?= base_url('medicines/add.php') ?>" class="btn btn-primary">
      <i class="bi bi-plus-lg me-1"></i>Add Medicine
    </a>
  <?php endif ?>
</div>

<!-- Filters bar -->
<div class="card table-card mb-4">
  <div class="card-body p-3">
    <form method="GET" class="row g-2 align-items-end">
      <div class="col-md-4">
        <input type="text" name="search" class="form-control" placeholder="Search by name, batch, barcode…"
               value="<?= sanitize($search) ?>"/>
      </div>
      <div class="col-md-2">
        <select name="cat" class="form-select">
          <option value="">All Categories</option>
          <?php foreach ($categories as $cat): ?>
            <option value="<?= $cat['id'] ?>" <?= $catFilter==$cat['id']?'selected':'' ?>><?= sanitize($cat['name']) ?></option>
          <?php endforeach ?>
        </select>
      </div>
      <div class="col-md-2">
        <select name="filter" class="form-select">
          <option value="">All Status</option>
          <option value="low_stock"   <?= $filter==='low_stock'  ?'selected':'' ?>>Low Stock</option>
          <option value="out_of_stock"<?= $filter==='out_of_stock'?'selected':'' ?>>Out of Stock</option>
          <option value="expired"     <?= $filter==='expired'    ?'selected':'' ?>>Expired</option>
          <option value="expiring"    <?= $filter==='expiring'   ?'selected':'' ?>>Expiring Soon</option>
        </select>
      </div>
      <div class="col-auto">
        <button type="submit" class="btn btn-primary"><i class="bi bi-search me-1"></i>Search</button>
        <a href="?" class="btn btn-outline-secondary ms-1"><i class="bi bi-x"></i></a>
      </div>
    </form>
  </div>
</div>

<!-- Quick filter badges -->
<div class="d-flex gap-2 mb-3 flex-wrap">
  <a href="?" class="btn btn-sm <?= !$filter?'btn-primary':'btn-outline-secondary' ?>">All</a>
  <a href="?filter=low_stock"    class="btn btn-sm <?= $filter==='low_stock'   ?'btn-warning':'btn-outline-warning' ?> text-dark">Low Stock</a>
  <a href="?filter=out_of_stock" class="btn btn-sm <?= $filter==='out_of_stock'?'btn-danger':'btn-outline-danger' ?>">Out of Stock</a>
  <a href="?filter=expired"      class="btn btn-sm <?= $filter==='expired'     ?'btn-danger':'btn-outline-danger' ?>">Expired</a>
  <a href="?filter=expiring"     class="btn btn-sm <?= $filter==='expiring'    ?'btn-info text-white':'btn-outline-info' ?>">Expiring Soon</a>
</div>

<!-- Table -->
<div class="card table-card">
  <div class="table-responsive">
    <table class="table align-middle mb-0">
      <thead>
        <tr>
          <th>#</th>
          <th>Medicine Name</th>
          <th>Category</th>
          <th>Batch No.</th>
          <th>Expiry Date</th>
          <th class="text-end">Purchase</th>
          <th class="text-end">Selling</th>
          <th class="text-end">Qty</th>
          <th>Supplier</th>
          <th>Stock</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
      <?php if (empty($medicines)): ?>
        <tr><td colspan="11" class="text-center text-muted py-5">
          <i class="bi bi-inbox fs-2 d-block mb-2"></i>No medicines found.
        </td></tr>
      <?php else: ?>
        <?php foreach ($medicines as $i => $med): ?>
          <tr>
            <td class="text-muted"><?= $pagination['offset'] + $i + 1 ?></td>
            <td>
              <strong><?= sanitize($med['name']) ?></strong>
              <?php if ($med['description']): ?>
                <br><small class="text-muted"><?= sanitize(substr($med['description'], 0, 50)) ?>…</small>
              <?php endif ?>
            </td>
            <td><span class="badge bg-light text-dark"><?= sanitize($med['category'] ?? '—') ?></span></td>
            <td><code><?= sanitize($med['batch_no'] ?? '—') ?></code></td>
            <td>
              <?= formatDate($med['expiry_date']) ?><br>
              <?= expiryBadge($med['expiry_date']) ?>
            </td>
            <td class="text-end"><?= formatCurrency($med['purchase_price']) ?></td>
            <td class="text-end fw-600 text-primary"><?= formatCurrency($med['selling_price']) ?></td>
            <td class="text-end fw-bold"><?= number_format($med['quantity']) ?></td>
            <td><?= sanitize($med['supplier_name'] ?? '—') ?></td>
            <td><?= stockBadge($med['quantity'], $med['low_stock_threshold']) ?></td>
            <td>
              <div class="d-flex gap-1">
                <a href="<?= base_url('medicines/edit.php') ?>?id=<?= $med['id'] ?>"
                   class="btn btn-sm btn-outline-primary" title="Edit">
                  <i class="bi bi-pencil"></i>
                </a>
                <?php if (isAdmin()): ?>
                  <a href="<?= base_url('medicines/delete.php') ?>?id=<?= $med['id'] ?>"
                     class="btn btn-sm btn-outline-danger"
                     data-confirm="Delete '<?= sanitize($med['name']) ?>'? This cannot be undone."
                     title="Delete">
                    <i class="bi bi-trash"></i>
                  </a>
                <?php endif ?>
              </div>
            </td>
          </tr>
        <?php endforeach ?>
      <?php endif ?>
      </tbody>
    </table>
  </div>
  <?php if ($pagination['total_pages'] > 1): ?>
    <div class="card-footer bg-white border-0 py-3">
      <?= paginationLinks($pagination, $baseUrl) ?>
    </div>
  <?php endif ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
