<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();
define('PAGE_TITLE', 'Suppliers');
require_once __DIR__ . '/../includes/header.php';

$search = trim($_GET['search'] ?? '');
$page   = max(1, (int)($_GET['page'] ?? 1));
$per    = 20;

$where  = ['1=1'];
$params = [];
if ($search) { $where[] = '(name LIKE ? OR contact LIKE ? OR email LIKE ?)'; $params = array_fill(0,3,"%$search%"); }
$w = implode(' AND ', $where);

$total = (int) db()->prepare("SELECT COUNT(*) FROM suppliers WHERE $w")->execute($params) &&
         db()->prepare("SELECT COUNT(*) FROM suppliers WHERE $w")->execute($params);
// Re-do properly
$countStmt = db()->prepare("SELECT COUNT(*) FROM suppliers WHERE $w");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$pag = paginate($total, $per, $page);

$stmt = db()->prepare("SELECT s.*, COUNT(m.id) AS medicine_count FROM suppliers s LEFT JOIN medicines m ON m.supplier_id=s.id WHERE $w GROUP BY s.id ORDER BY s.name LIMIT $per OFFSET {$pag['offset']}");
$stmt->execute($params);
$suppliers = $stmt->fetchAll();
?>

<div class="d-flex align-items-center justify-content-between mb-4">
  <div><h5 class="mb-0 fw-bold">Suppliers</h5><small class="text-muted"><?= $total ?> suppliers</small></div>
  <?php if (isAdmin()): ?>
    <a href="<?= base_url('suppliers/add.php') ?>" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Add Supplier</a>
  <?php endif ?>
</div>

<div class="card table-card mb-4">
  <div class="card-body p-3">
    <form method="GET" class="row g-2">
      <div class="col-md-4">
        <input type="text" name="search" class="form-control" placeholder="Search suppliers…" value="<?= sanitize($search) ?>"/>
      </div>
      <div class="col-auto">
        <button type="submit" class="btn btn-primary"><i class="bi bi-search me-1"></i>Search</button>
        <a href="?" class="btn btn-outline-secondary ms-1"><i class="bi bi-x"></i></a>
      </div>
    </form>
  </div>
</div>

<div class="card table-card">
  <div class="table-responsive">
    <table class="table align-middle mb-0">
      <thead><tr><th>#</th><th>Supplier Name</th><th>Contact</th><th>Email</th><th>Address</th><th>Medicines</th><th>Status</th><th>Actions</th></tr></thead>
      <tbody>
      <?php if (empty($suppliers)): ?>
        <tr><td colspan="8" class="text-center text-muted py-5"><i class="bi bi-truck fs-2 d-block mb-2"></i>No suppliers found.</td></tr>
      <?php else: foreach ($suppliers as $i => $s): ?>
        <tr>
          <td class="text-muted"><?= $pag['offset']+$i+1 ?></td>
          <td><strong><?= sanitize($s['name']) ?></strong></td>
          <td><?= sanitize($s['contact'] ?? '—') ?></td>
          <td><?= sanitize($s['email']   ?? '—') ?></td>
          <td><small><?= sanitize($s['address'] ?? '—') ?></small></td>
          <td><span class="badge bg-primary"><?= $s['medicine_count'] ?></span></td>
          <td>
            <?php if ($s['status']==='active'): ?>
              <span class="badge bg-success">Active</span>
            <?php else: ?>
              <span class="badge bg-secondary">Inactive</span>
            <?php endif ?>
          </td>
          <td>
            <div class="d-flex gap-1">
              <a href="<?= base_url('suppliers/edit.php') ?>?id=<?= $s['id'] ?>" class="btn btn-sm btn-outline-primary" title="Edit"><i class="bi bi-pencil"></i></a>
              <?php if (isAdmin()): ?>
                <a href="<?= base_url('suppliers/delete.php') ?>?id=<?= $s['id'] ?>"
                   class="btn btn-sm btn-outline-danger"
                   data-confirm="Delete supplier '<?= sanitize($s['name']) ?>'?">
                  <i class="bi bi-trash"></i>
                </a>
              <?php endif ?>
            </div>
          </td>
        </tr>
      <?php endforeach; endif ?>
      </tbody>
    </table>
  </div>
  <?php if ($pag['total_pages']>1): ?>
    <div class="card-footer bg-white border-0 py-3"><?= paginationLinks($pag,'?search='.urlencode($search)) ?></div>
  <?php endif ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
