<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();
define('PAGE_TITLE', 'Edit Medicine');

$id = (int)($_GET['id'] ?? 0);
if (!$id) { setFlash('error','Invalid medicine.'); header('Location: '.base_url('medicines/index.php')); exit; }

$med = db()->prepare("SELECT * FROM medicines WHERE id = ?");
$med->execute([$id]);
$data = $med->fetch();
if (!$data) { setFlash('error','Medicine not found.'); header('Location: '.base_url('medicines/index.php')); exit; }

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $old = $data;
    $data = [
        'id'                 => $id,
        'name'               => trim($_POST['name']               ?? ''),
        'category_id'        => (int)($_POST['category_id']       ?? 0) ?: null,
        'batch_no'           => trim($_POST['batch_no']           ?? ''),
        'expiry_date'        => $_POST['expiry_date']             ?? '',
        'purchase_price'     => (float)($_POST['purchase_price']  ?? 0),
        'selling_price'      => (float)($_POST['selling_price']   ?? 0),
        'quantity'           => (int)($_POST['quantity']          ?? 0),
        'low_stock_threshold'=> (int)($_POST['low_stock_threshold']?? 10),
        'supplier_id'        => (int)($_POST['supplier_id']       ?? 0) ?: null,
        'barcode'            => trim($_POST['barcode']            ?? ''),
        'description'        => trim($_POST['description']        ?? ''),
    ];

    if (!$data['name'])           $errors[] = 'Medicine name is required.';
    if (!$data['expiry_date'])    $errors[] = 'Expiry date is required.';
    if ($data['purchase_price'] <= 0) $errors[] = 'Purchase price must be > 0.';
    if ($data['selling_price']  <= 0) $errors[] = 'Selling price must be > 0.';

    if (empty($errors)) {
        db()->prepare(
            "UPDATE medicines SET name=?,category_id=?,batch_no=?,expiry_date=?,purchase_price=?,
             selling_price=?,quantity=?,low_stock_threshold=?,supplier_id=?,barcode=?,description=?
             WHERE id=?"
        )->execute([
            $data['name'], $data['category_id'], $data['batch_no'], $data['expiry_date'],
            $data['purchase_price'], $data['selling_price'], $data['quantity'],
            $data['low_stock_threshold'], $data['supplier_id'], $data['barcode'],
            $data['description'], $id,
        ]);
        auditLog('UPDATE_MEDICINE','medicines',$id,$old,$data);
        setFlash('success',"Medicine '{$data['name']}' updated.");
        header('Location: '.base_url('medicines/index.php'));
        exit;
    }
}

$categories = db()->query("SELECT id,name FROM categories ORDER BY name")->fetchAll();
$suppliers  = db()->query("SELECT id,name FROM suppliers WHERE status='active' ORDER BY name")->fetchAll();
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex align-items-center gap-2 mb-4">
  <a href="<?= base_url('medicines/index.php') ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i></a>
  <h5 class="mb-0 fw-bold">Edit Medicine</h5>
</div>

<?php if (!empty($errors)): ?>
  <div class="alert alert-danger flash-alert"><?= implode('<br>', array_map('sanitize', $errors)) ?></div>
<?php endif ?>

<div class="card table-card">
  <div class="card-body p-4">
    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>"/>
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label">Medicine Name <span class="text-danger">*</span></label>
          <input type="text" name="name" class="form-control" value="<?= sanitize($data['name']) ?>" required/>
        </div>
        <div class="col-md-3">
          <label class="form-label">Category</label>
          <select name="category_id" class="form-select">
            <option value="">— Select —</option>
            <?php foreach ($categories as $c): ?>
              <option value="<?= $c['id'] ?>" <?= $data['category_id']==$c['id']?'selected':'' ?>><?= sanitize($c['name']) ?></option>
            <?php endforeach ?>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label">Batch No.</label>
          <input type="text" name="batch_no" class="form-control" value="<?= sanitize($data['batch_no']) ?>"/>
        </div>
        <div class="col-md-3">
          <label class="form-label">Expiry Date <span class="text-danger">*</span></label>
          <input type="date" name="expiry_date" class="form-control" value="<?= $data['expiry_date'] ?>" required/>
        </div>
        <div class="col-md-3">
          <label class="form-label">Purchase Price ($)</label>
          <input type="number" name="purchase_price" class="form-control" step="0.01" min="0" value="<?= $data['purchase_price'] ?>" required/>
        </div>
        <div class="col-md-3">
          <label class="form-label">Selling Price ($)</label>
          <input type="number" name="selling_price" class="form-control" step="0.01" min="0" value="<?= $data['selling_price'] ?>" required/>
        </div>
        <div class="col-md-3">
          <label class="form-label">Quantity</label>
          <input type="number" name="quantity" class="form-control" min="0" value="<?= $data['quantity'] ?>"/>
        </div>
        <div class="col-md-3">
          <label class="form-label">Low Stock Threshold</label>
          <input type="number" name="low_stock_threshold" class="form-control" min="0" value="<?= $data['low_stock_threshold'] ?>"/>
        </div>
        <div class="col-md-3">
          <label class="form-label">Supplier</label>
          <select name="supplier_id" class="form-select">
            <option value="">— Select —</option>
            <?php foreach ($suppliers as $s): ?>
              <option value="<?= $s['id'] ?>" <?= $data['supplier_id']==$s['id']?'selected':'' ?>><?= sanitize($s['name']) ?></option>
            <?php endforeach ?>
          </select>
        </div>
        <div class="col-md-6">
          <label class="form-label">Barcode</label>
          <input type="text" name="barcode" class="form-control" value="<?= sanitize($data['barcode'] ?? '') ?>"/>
        </div>
        <div class="col-12">
          <label class="form-label">Description</label>
          <textarea name="description" class="form-control" rows="3"><?= sanitize($data['description'] ?? '') ?></textarea>
        </div>
      </div>
      <div class="mt-4 d-flex gap-2">
        <button type="submit" class="btn btn-primary px-4"><i class="bi bi-check-lg me-1"></i>Update</button>
        <a href="<?= base_url('medicines/index.php') ?>" class="btn btn-outline-secondary">Cancel</a>
      </div>
    </form>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
