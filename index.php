<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

// Already logged in → go to dashboard
if (isLoggedIn()) {
    header('Location: ' . base_url('dashboard.php'));
    exit;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email']    ?? '');
    $password = trim($_POST['password'] ?? '');

    if (!$email)    $errors[] = 'Email is required.';
    if (!$password) $errors[] = 'Password is required.';

    if (empty($errors)) {
        try {
            $stmt = db()->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if (!$user) {
                $errors[] = 'No account found with that email address.';
            } elseif ($user['status'] !== 'active') {
                $errors[] = 'This account is deactivated. Contact your administrator.';
            } elseif (!password_verify($password, $user['password'])) {
                $errors[] = 'Incorrect password. Please try again.';
            } else {
                session_regenerate_id(true);
                $_SESSION['user_id']   = $user['id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['user_role'] = $user['role'];
                auditLog('LOGIN', 'users', $user['id']);
                header('Location: ' . base_url('dashboard.php'));
                exit;
            }
        } catch (PDOException $e) {
            $errors[] = 'Database error: ' . $e->getMessage() . ' — Make sure you imported setup.sql.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Login — <?= APP_NAME ?></title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"/>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"/>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
  <style>
    * { font-family:'Inter',sans-serif; }
    body {
      min-height:100vh; margin:0;
      background: linear-gradient(135deg, #0f172a 0%, #1e3a5f 50%, #0f172a 100%);
      display:flex; align-items:center; justify-content:center;
    }
    .login-card {
      background:#fff; border-radius:16px;
      padding:2.5rem; width:100%; max-width:400px;
      box-shadow: 0 25px 60px rgba(0,0,0,.4);
    }
    .login-icon {
      width:60px; height:60px; background:#2563eb; border-radius:16px;
      display:flex; align-items:center; justify-content:center;
      font-size:1.8rem; color:#fff; margin:0 auto 1.25rem;
    }
    .form-control { border-radius:8px; padding:.65rem 1rem; font-size:.875rem; }
    .form-control:focus { border-color:#2563eb; box-shadow:0 0 0 3px rgba(37,99,235,.15); }
    .btn-login { background:#2563eb; border:none; border-radius:8px; padding:.75rem; font-weight:600; width:100%; }
    .btn-login:hover { background:#1d4ed8; }
    .hint-box { background:#f0f9ff; border:1px solid #bae6fd; border-radius:8px; padding:.75rem 1rem; font-size:.8rem; color:#0369a1; }
  </style>
</head>
<body>
<div class="login-card">
  <div class="login-icon"><i class="bi bi-capsule"></i></div>
  <h4 class="text-center fw-700 mb-1" style="font-weight:700;"><?= APP_NAME ?></h4>
  <p class="text-center text-muted mb-4" style="font-size:.875rem;">Sign in to your account</p>

  <?php if (!empty($errors)): ?>
    <div class="alert alert-danger py-2 mb-3" style="font-size:.875rem; border-radius:8px;">
      <i class="bi bi-x-circle me-1"></i><?= implode('<br>', array_map('sanitize', $errors)) ?>
    </div>
  <?php endif ?>

  <form method="POST" novalidate>
    <div class="mb-3">
      <label class="form-label" style="font-size:.8125rem; font-weight:600;">Email Address</label>
      <div class="input-group">
        <span class="input-group-text"><i class="bi bi-envelope"></i></span>
        <input type="email" name="email" class="form-control" placeholder="you@example.com"
               value="<?= sanitize($_POST['email'] ?? '') ?>" required/>
      </div>
    </div>
    <div class="mb-4">
      <label class="form-label" style="font-size:.8125rem; font-weight:600;">Password</label>
      <div class="input-group">
        <span class="input-group-text"><i class="bi bi-lock"></i></span>
        <input type="password" name="password" id="passwordInput" class="form-control" placeholder="••••••••" required/>
        <button type="button" class="btn btn-outline-secondary" onclick="togglePwd()">
          <i class="bi bi-eye" id="eyeIcon"></i>
        </button>
      </div>
    </div>
    <button type="submit" class="btn btn-primary btn-login text-white">
      <i class="bi bi-box-arrow-in-right me-2"></i>Sign In
    </button>
  </form>

  <div class="hint-box mt-4">
    <strong>Demo-Credentials:</strong><br>
    Admin: <code>admin@pharmacy.com</code> / <code>admin123</code><br>
    Staff: <code>jane@pharmacy.com</code> / <code>pharma123</code>
  </div>
</div>

<script>
function togglePwd() {
  const inp = document.getElementById('passwordInput');
  const ico = document.getElementById('eyeIcon');
  if (inp.type === 'password') { inp.type='text'; ico.className='bi bi-eye-slash'; }
  else { inp.type='password'; ico.className='bi bi-eye'; }
}
</script>
</body>
</html>
