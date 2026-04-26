<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireAdmin();
define('PAGE_TITLE', 'Edit Supplier');

$id = (int)($_GET['id'] ?? 0);
if (!$id) { setFlash('error','Invalid ID.'); header('Location: '.base_url('suppliers/index.php')); exit; }

$stmt = db()->prepare("SELECT * FROM suppliers WHERE id=?");
$stmt->execute([$id]);
$data = $stmt->fetch();
if (!$data) { setFlash('error','Supplier not found.'); header('Location: '.base_url('suppliers/index.php')); exit; }

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $old  = $data;
    $data = [
        'id'      => $id,
        'name'    => trim($_POST['name']    ?? ''),
        'contact' => trim($_POST['contact'] ?? ''),
        'email'   => trim($_POST['email']   ?? ''),
        'address' => trim($_POST['address'] ?? ''),
        'status'  => $_POST['status'] === 'inactive' ? 'inactive' : 'active',
    ];
    if (!$data['name']) $errors[] = 'Supplier name is required.';
    if ($data['email'] && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email.';

    if (empty($errors)) {
        db()->prepare("UPDATE suppliers SET name=?,contact=?,email=?,address=?,status=? WHERE id=?")
            ->execute([$data['name'],$data['contact'],$data['email'],$data['address'],$data['status'],$id]);
        auditLog('UPDATE_SUPPLIER','suppliers',$id,$old,$data);
        setFlash('success',"Supplier '{$data['name']}' updated.");
        header('Location: '.base_url('suppliers/index.php')); exit;
    }
}
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex align-items-center gap-2 mb-4">
  <a href="<?= base_url('suppliers/index.php') ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i></a>
  <h5 class="mb-0 fw-bold">Edit Supplier</h5>
</div>
<?php if (!empty($errors)): ?>
  <div class="alert alert-danger flash-alert"><?= implode('<br>', array_map('sanitize', $errors)) ?></div>
<?php endif ?>

<div class="card table-card"><div class="card-body p-4">
  <form method="POST">
    <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>"/>
    <div class="row g-3">
      <div class="col-md-6">
        <label class="form-label">Supplier Name <span class="text-danger">*</span></label>
        <input type="text" name="name" class="form-control" value="<?= sanitize($data['name']) ?>" required/>
      </div>
      <div class="col-md-3">
        <label class="form-label">Contact</label>
        <input type="text" name="contact" class="form-control" value="<?= sanitize($data['contact'] ?? '') ?>"/>
      </div>
      <div class="col-md-3">
        <label class="form-label">Email</label>
        <input type="email" name="email" class="form-control" value="<?= sanitize($data['email'] ?? '') ?>"/>
      </div>
      <div class="col-md-9">
        <label class="form-label">Address</label>
        <textarea name="address" class="form-control" rows="2"><?= sanitize($data['address'] ?? '') ?></textarea>
      </div>
      <div class="col-md-3">
        <label class="form-label">Status</label>
        <select name="status" class="form-select">
          <option value="active"   <?= $data['status']==='active'  ?'selected':'' ?>>Active</option>
          <option value="inactive" <?= $data['status']==='inactive'?'selected':'' ?>>Inactive</option>
        </select>
      </div>
    </div>
    <div class="mt-4 d-flex gap-2">
      <button type="submit" class="btn btn-primary px-4"><i class="bi bi-check-lg me-1"></i>Update</button>
      <a href="<?= base_url('suppliers/index.php') ?>" class="btn btn-outline-secondary">Cancel</a>
    </div>
  </form>
</div></div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
