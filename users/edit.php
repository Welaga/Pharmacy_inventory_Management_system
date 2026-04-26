<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireAdmin();
define('PAGE_TITLE', 'Edit User');

$id = (int)($_GET['id'] ?? 0);
if (!$id) { setFlash('error','Invalid ID.'); header('Location: '.base_url('users/index.php')); exit; }

$stmt = db()->prepare("SELECT * FROM users WHERE id=?");
$stmt->execute([$id]);
$data = $stmt->fetch();
if (!$data) { setFlash('error','User not found.'); header('Location: '.base_url('users/index.php')); exit; }

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $old = $data;
    $postData = [
        'name'     => trim($_POST['name']    ?? ''),
        'email'    => trim($_POST['email']   ?? ''),
        'role'     => in_array($_POST['role']??'',['admin','pharmacist']) ? $_POST['role'] : 'pharmacist',
        'status'   => $_POST['status'] === 'inactive' ? 'inactive' : 'active',
        'password' => trim($_POST['password'] ?? ''),
        'confirm'  => trim($_POST['confirm']  ?? ''),
    ];

    if (!$postData['name'])  $errors[] = 'Name is required.';
    if (!filter_var($postData['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email is required.';

    // Check email uniqueness (exclude self)
    $chk = db()->prepare("SELECT id FROM users WHERE email=? AND id != ?");
    $chk->execute([$postData['email'], $id]);
    if ($chk->fetch()) $errors[] = 'Email already in use.';

    if ($postData['password']) {
        if (strlen($postData['password']) < 8) $errors[] = 'Password must be at least 8 characters.';
        if ($postData['password'] !== $postData['confirm']) $errors[] = 'Passwords do not match.';
    }

    if (empty($errors)) {
        if ($postData['password']) {
            $hash = password_hash($postData['password'], PASSWORD_BCRYPT);
            db()->prepare("UPDATE users SET name=?,email=?,role=?,status=?,password=? WHERE id=?")
                ->execute([$postData['name'],$postData['email'],$postData['role'],$postData['status'],$hash,$id]);
        } else {
            db()->prepare("UPDATE users SET name=?,email=?,role=?,status=? WHERE id=?")
                ->execute([$postData['name'],$postData['email'],$postData['role'],$postData['status'],$id]);
        }
        $data = array_merge($data, $postData);
        auditLog('UPDATE_USER','users',$id,$old,$postData);
        setFlash('success',"User '{$postData['name']}' updated.");
        header('Location: '.base_url('users/index.php')); exit;
    }
    $data = array_merge($data, $postData);
}
require_once __DIR__ . '/../includes/header.php';
?>
<div class="d-flex align-items-center gap-2 mb-4">
  <a href="<?= base_url('users/index.php') ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i></a>
  <h5 class="mb-0 fw-bold">Edit User</h5>
</div>
<?php if (!empty($errors)): ?>
  <div class="alert alert-danger flash-alert"><?= implode('<br>', array_map('sanitize', $errors)) ?></div>
<?php endif ?>
<div class="card table-card"><div class="card-body p-4">
  <form method="POST">
    <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>"/>
    <div class="row g-3">
      <div class="col-md-6">
        <label class="form-label">Full Name</label>
        <input type="text" name="name" class="form-control" value="<?= sanitize($data['name']) ?>" required/>
      </div>
      <div class="col-md-6">
        <label class="form-label">Email</label>
        <input type="email" name="email" class="form-control" value="<?= sanitize($data['email']) ?>" required/>
      </div>
      <div class="col-md-6">
        <label class="form-label">New Password <span class="text-muted">(leave blank to keep current)</span></label>
        <input type="password" name="password" class="form-control" minlength="8"/>
      </div>
      <div class="col-md-6">
        <label class="form-label">Confirm New Password</label>
        <input type="password" name="confirm" class="form-control"/>
      </div>
      <div class="col-md-3">
        <label class="form-label">Role</label>
        <select name="role" class="form-select">
          <option value="pharmacist" <?= $data['role']==='pharmacist'?'selected':'' ?>>Pharmacist</option>
          <option value="admin"      <?= $data['role']==='admin'     ?'selected':'' ?>>Admin</option>
        </select>
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
      <button type="submit" class="btn btn-primary px-4"><i class="bi bi-check-lg me-1"></i>Update User</button>
      <a href="<?= base_url('users/index.php') ?>" class="btn btn-outline-secondary">Cancel</a>
    </div>
  </form>
</div></div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
