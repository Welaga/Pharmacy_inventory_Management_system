<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireAdmin();
define('PAGE_TITLE', 'Add User');
$errors = [];
$data   = ['role'=>'pharmacist','status'=>'active'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $data = [
        'name'     => trim($_POST['name']     ?? ''),
        'email'    => trim($_POST['email']    ?? ''),
        'password' => trim($_POST['password'] ?? ''),
        'confirm'  => trim($_POST['confirm']  ?? ''),
        'role'     => in_array($_POST['role']??'',['admin','pharmacist']) ? $_POST['role'] : 'pharmacist',
        'status'   => $_POST['status'] === 'inactive' ? 'inactive' : 'active',
    ];

    if (!$data['name'])              $errors[] = 'Name is required.';
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email is required.';
    if (strlen($data['password']) < 8) $errors[] = 'Password must be at least 8 characters.';
    if ($data['password'] !== $data['confirm']) $errors[] = 'Passwords do not match.';

    // Check email uniqueness
    if (empty($errors)) {
        $chk = db()->prepare("SELECT id FROM users WHERE email=?");
        $chk->execute([$data['email']]);
        if ($chk->fetch()) $errors[] = 'Email already in use.';
    }

    if (empty($errors)) {
        $hash = password_hash($data['password'], PASSWORD_BCRYPT);
        db()->prepare("INSERT INTO users (name,email,password,role,status) VALUES (?,?,?,?,?)")
            ->execute([$data['name'],$data['email'],$hash,$data['role'],$data['status']]);
        $newId = db()->lastInsertId();
        auditLog('CREATE_USER','users',$newId,null,['name'=>$data['name'],'email'=>$data['email'],'role'=>$data['role']]);
        setFlash('success',"User '{$data['name']}' created.");
        header('Location: '.base_url('users/index.php')); exit;
    }
}
require_once __DIR__ . '/../includes/header.php';
?>
<div class="d-flex align-items-center gap-2 mb-4">
  <a href="<?= base_url('users/index.php') ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i></a>
  <h5 class="mb-0 fw-bold">Add New User</h5>
</div>
<?php if (!empty($errors)): ?>
  <div class="alert alert-danger flash-alert"><?= implode('<br>', array_map('sanitize', $errors)) ?></div>
<?php endif ?>
<div class="card table-card"><div class="card-body p-4">
  <form method="POST">
    <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>"/>
    <div class="row g-3">
      <div class="col-md-6">
        <label class="form-label">Full Name <span class="text-danger">*</span></label>
        <input type="text" name="name" class="form-control" value="<?= sanitize($data['name']??'') ?>" required/>
      </div>
      <div class="col-md-6">
        <label class="form-label">Email <span class="text-danger">*</span></label>
        <input type="email" name="email" class="form-control" value="<?= sanitize($data['email']??'') ?>" required/>
      </div>
      <div class="col-md-6">
        <label class="form-label">Password <span class="text-danger">*</span></label>
        <input type="password" name="password" class="form-control" minlength="8" required/>
        <div class="form-text">Minimum 8 characters.</div>
      </div>
      <div class="col-md-6">
        <label class="form-label">Confirm Password <span class="text-danger">*</span></label>
        <input type="password" name="confirm" class="form-control" required/>
      </div>
      <div class="col-md-3">
        <label class="form-label">Role</label>
        <select name="role" class="form-select">
          <option value="pharmacist" <?= ($data['role']??'')==='pharmacist'?'selected':'' ?>>Pharmacist</option>
          <option value="admin"      <?= ($data['role']??'')==='admin'     ?'selected':'' ?>>Admin</option>
        </select>
      </div>
      <div class="col-md-3">
        <label class="form-label">Status</label>
        <select name="status" class="form-select">
          <option value="active"   <?= ($data['status']??'active')==='active'  ?'selected':'' ?>>Active</option>
          <option value="inactive" <?= ($data['status']??'')==='inactive'?'selected':'' ?>>Inactive</option>
        </select>
      </div>
    </div>
    <div class="mt-4 d-flex gap-2">
      <button type="submit" class="btn btn-primary px-4"><i class="bi bi-check-lg me-1"></i>Create User</button>
      <a href="<?= base_url('users/index.php') ?>" class="btn btn-outline-secondary">Cancel</a>
    </div>
  </form>
</div></div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
