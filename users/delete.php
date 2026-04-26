<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireAdmin();
$id = (int)($_GET['id'] ?? 0);
if (!$id || $id === currentUserId()) {
    setFlash('error','Cannot delete this user.');
    header('Location: '.base_url('users/index.php')); exit;
}
$stmt = db()->prepare("SELECT * FROM users WHERE id=?");
$stmt->execute([$id]);
$user = $stmt->fetch();
if (!$user) { setFlash('error','User not found.'); header('Location: '.base_url('users/index.php')); exit; }

// Check if has sales
$chk = db()->prepare("SELECT COUNT(*) FROM sales WHERE user_id=?");
$chk->execute([$id]);
if ((int)$chk->fetchColumn() > 0) {
    setFlash('error',"Cannot delete '{$user['name']}' — they have associated sales records. Deactivate the account instead.");
    header('Location: '.base_url('users/index.php')); exit;
}

db()->prepare("DELETE FROM users WHERE id=?")->execute([$id]);
auditLog('DELETE_USER','users',$id,$user,null);
setFlash('success',"User '{$user['name']}' deleted.");
header('Location: '.base_url('users/index.php')); exit;
