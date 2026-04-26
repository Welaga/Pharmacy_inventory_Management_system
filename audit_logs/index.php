<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireAdmin();
define('PAGE_TITLE', 'Audit Logs');
require_once __DIR__ . '/../includes/header.php';

$search    = trim($_GET['search']    ?? '');
$userId    = (int)($_GET['user_id']  ?? 0);
$action    = trim($_GET['action']    ?? '');
$dateFrom  = $_GET['date_from']      ?? '';
$dateTo    = $_GET['date_to']        ?? '';
$page      = max(1,(int)($_GET['page'] ?? 1));
$perPage   = 30;

$where  = ['1=1'];
$params = [];
if ($search)   { $where[] = '(al.action LIKE ? OR al.table_name LIKE ?)'; $params[] = "%$search%"; $params[] = "%$search%"; }
if ($userId)   { $where[] = 'al.user_id = ?';          $params[] = $userId; }
if ($action)   { $where[] = 'al.action = ?';           $params[] = $action; }
if ($dateFrom) { $where[] = 'DATE(al.created_at) >= ?';$params[] = $dateFrom; }
if ($dateTo)   { $where[] = 'DATE(al.created_at) <= ?';$params[] = $dateTo; }
$w = implode(' AND ', $where);

$countStmt = db()->prepare("SELECT COUNT(*) FROM audit_logs al WHERE $w");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$pag   = paginate($total, $perPage, $page);

$stmt = db()->prepare(
    "SELECT al.*, u.name AS user_name FROM audit_logs al
     LEFT JOIN users u ON u.id = al.user_id
     WHERE $w ORDER BY al.created_at DESC
     LIMIT {$perPage} OFFSET {$pag['offset']}"
);
$stmt->execute($params);
$logs = $stmt->fetchAll();

$users   = db()->query("SELECT id,name FROM users ORDER BY name")->fetchAll();
$actions = db()->query("SELECT DISTINCT action FROM audit_logs ORDER BY action")->fetchAll(PDO::FETCH_COLUMN);

$baseUrl = '?'.http_build_query(array_filter(['search'=>$search,'user_id'=>$userId,'action'=>$action,'date_from'=>$dateFrom,'date_to'=>$dateTo]));

// Action color map
function actionBadge(string $action): string {
    $color = match(true) {
        str_starts_with($action,'CREATE') => 'success',
        str_starts_with($action,'UPDATE') => 'primary',
        str_starts_with($action,'DELETE') => 'danger',
        str_starts_with($action,'CANCEL') => 'warning',
        $action === 'LOGIN'               => 'info',
        $action === 'LOGOUT'              => 'secondary',
        default                           => 'light text-dark',
    };
    return "<span class=\"badge bg-{$color}\">$action</span>";
}
?>

<div class="d-flex align-items-center justify-content-between mb-4">
  <div>
    <h5 class="mb-0 fw-bold">Audit Logs</h5>
    <small class="text-muted"><?= number_format($total) ?> log entries</small>
  </div>
</div>

<!-- Filters -->
<div class="card table-card mb-4">
  <div class="card-body p-3">
    <form method="GET" class="row g-2 align-items-end">
      <div class="col-md-2">
        <input type="text" name="search" class="form-control" placeholder="Search action…" value="<?= sanitize($search) ?>"/>
      </div>
      <div class="col-md-2">
        <select name="user_id" class="form-select">
          <option value="">All Users</option>
          <?php foreach ($users as $u): ?>
            <option value="<?= $u['id'] ?>" <?= $userId==$u['id']?'selected':'' ?>><?= sanitize($u['name']) ?></option>
          <?php endforeach ?>
        </select>
      </div>
      <div class="col-md-2">
        <select name="action" class="form-select">
          <option value="">All Actions</option>
          <?php foreach ($actions as $a): ?>
            <option value="<?= sanitize($a) ?>" <?= $action===$a?'selected':'' ?>><?= sanitize($a) ?></option>
          <?php endforeach ?>
        </select>
      </div>
      <div class="col-md-2">
        <input type="date" name="date_from" class="form-control" value="<?= sanitize($dateFrom) ?>"/>
      </div>
      <div class="col-md-2">
        <input type="date" name="date_to" class="form-control" value="<?= sanitize($dateTo) ?>"/>
      </div>
      <div class="col-auto">
        <button type="submit" class="btn btn-primary"><i class="bi bi-search me-1"></i>Filter</button>
        <a href="?" class="btn btn-outline-secondary ms-1"><i class="bi bi-x"></i></a>
      </div>
    </form>
  </div>
</div>

<div class="card table-card">
  <div class="table-responsive">
    <table class="table align-middle mb-0">
      <thead>
        <tr><th>Timestamp</th><th>User</th><th>Action</th><th>Table</th><th>Record ID</th><th>IP Address</th><th>Details</th></tr>
      </thead>
      <tbody>
      <?php if (empty($logs)): ?>
        <tr><td colspan="7" class="text-center text-muted py-5">
          <i class="bi bi-journal-x fs-2 d-block mb-2"></i>No audit logs found.
        </td></tr>
      <?php else: foreach ($logs as $log): ?>
        <tr>
          <td style="font-size:.8rem; white-space:nowrap;">
            <?= date('d M Y', strtotime($log['created_at'])) ?><br>
            <span class="text-muted"><?= date('H:i:s', strtotime($log['created_at'])) ?></span>
          </td>
          <td>
            <span class="fw-600"><?= sanitize($log['user_name'] ?? 'System') ?></span>
          </td>
          <td><?= actionBadge($log['action']) ?></td>
          <td><code><?= sanitize($log['table_name'] ?? '—') ?></code></td>
          <td class="text-muted"><?= $log['record_id'] ?? '—' ?></td>
          <td style="font-size:.8rem;"><code><?= sanitize($log['ip_address'] ?? '—') ?></code></td>
          <td>
            <?php if ($log['old_values'] || $log['new_values']): ?>
              <button class="btn btn-xs btn-outline-secondary" style="font-size:.7rem;padding:2px 8px;"
                      data-bs-toggle="modal" data-bs-target="#logModal"
                      data-old="<?= htmlspecialchars($log['old_values']??'{}') ?>"
                      data-new="<?= htmlspecialchars($log['new_values']??'{}') ?>">
                <i class="bi bi-eye"></i> View
              </button>
            <?php else: ?>
              <span class="text-muted">—</span>
            <?php endif ?>
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

<!-- Detail Modal -->
<div class="modal fade" id="logModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Log Details</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <div class="row g-3">
          <div class="col-md-6">
            <h6 class="fw-bold text-muted">Previous Values</h6>
            <pre id="oldValues" style="background:#f8fafc;border-radius:8px;padding:1rem;font-size:.75rem;overflow-x:auto;"></pre>
          </div>
          <div class="col-md-6">
            <h6 class="fw-bold text-muted">New Values</h6>
            <pre id="newValues" style="background:#f0fdf4;border-radius:8px;padding:1rem;font-size:.75rem;overflow-x:auto;"></pre>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
document.getElementById('logModal').addEventListener('show.bs.modal', function(e) {
    const btn = e.relatedTarget;
    const fmt = v => { try { return JSON.stringify(JSON.parse(v), null, 2); } catch { return v || 'N/A'; } };
    document.getElementById('oldValues').textContent = fmt(btn.dataset.old);
    document.getElementById('newValues').textContent = fmt(btn.dataset.new);
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
