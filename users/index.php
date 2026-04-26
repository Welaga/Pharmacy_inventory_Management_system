<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireAdmin();
define('PAGE_TITLE', 'User Management');
require_once __DIR__ . '/../includes/header.php';

$users = db()->query(
    "SELECT u.*,
            (SELECT COUNT(*) FROM sales WHERE user_id=u.id) AS sale_count
     FROM users u ORDER BY u.created_at DESC"
)->fetchAll();
?>

<div class="d-flex align-items-center justify-content-between mb-4">
  <h5 class="mb-0 fw-bold">User Management</h5>
  <a href="<?= base_url('users/add.php') ?>" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Add User</a>
</div>

<div class="card table-card">
  <div class="table-responsive">
    <table class="table align-middle mb-0">
      <thead><tr><th>#</th><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Sales</th><th>Created</th><th>Actions</th></tr></thead>
      <tbody>
      <?php foreach ($users as $i => $u): ?>
        <tr>
          <td class="text-muted"><?= $i+1 ?></td>
          <td>
            <div class="d-flex align-items-center gap-2">
              <span class="avatar" style="width:32px;height:32px;font-size:.75rem;"><?= strtoupper(substr($u['name'],0,2)) ?></span>
              <strong><?= sanitize($u['name']) ?></strong>
              <?php if ($u['id'] == currentUserId()): ?><span class="badge bg-primary ms-1">You</span><?php endif ?>
            </div>
          </td>
          <td><?= sanitize($u['email']) ?></td>
          <td>
            <?php if ($u['role']==='admin'): ?>
              <span class="badge bg-danger">Admin</span>
            <?php else: ?>
              <span class="badge bg-info text-white">Pharmacist</span>
            <?php endif ?>
          </td>
          <td>
            <?php if ($u['status']==='active'): ?>
              <span class="badge bg-success">Active</span>
            <?php else: ?>
              <span class="badge bg-secondary">Inactive</span>
            <?php endif ?>
          </td>
          <td><span class="badge bg-light text-dark"><?= $u['sale_count'] ?></span></td>
          <td style="font-size:.8rem;"><?= formatDate($u['created_at']) ?></td>
          <td>
            <div class="d-flex gap-1">
              <a href="<?= base_url('users/edit.php') ?>?id=<?= $u['id'] ?>" class="btn btn-sm btn-outline-primary" title="Edit"><i class="bi bi-pencil"></i></a>
              <?php if ($u['id'] != currentUserId()): ?>
                <a href="<?= base_url('users/delete.php') ?>?id=<?= $u['id'] ?>"
                   class="btn btn-sm btn-outline-danger"
                   data-confirm="Delete user '<?= sanitize($u['name']) ?>'?">
                  <i class="bi bi-trash"></i>
                </a>
              <?php endif ?>
            </div>
          </td>
        </tr>
      <?php endforeach ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
