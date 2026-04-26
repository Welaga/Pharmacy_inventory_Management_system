<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireAdmin();
$id = (int)($_GET['id'] ?? 0);
if (!$id) { setFlash('error','Invalid ID.'); header('Location: '.base_url('suppliers/index.php')); exit; }

$stmt = db()->prepare("SELECT * FROM suppliers WHERE id=?");
$stmt->execute([$id]);
$sup = $stmt->fetch();
if (!$sup) { setFlash('error','Supplier not found.'); header('Location: '.base_url('suppliers/index.php')); exit; }

// Check for linked medicines
$check = db()->prepare("SELECT COUNT(*) FROM medicines WHERE supplier_id=?");
$check->execute([$id]);
if ((int)$check->fetchColumn() > 0) {
    setFlash('error',"Cannot delete '{$sup['name']}' — it has linked medicines. Update those medicines first.");
    header('Location: '.base_url('suppliers/index.php')); exit;
}

db()->prepare("DELETE FROM suppliers WHERE id=?")->execute([$id]);
auditLog('DELETE_SUPPLIER','suppliers',$id,$sup,null);
setFlash('success',"Supplier '{$sup['name']}' deleted.");
header('Location: '.base_url('suppliers/index.php'));
exit;
