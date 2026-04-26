<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireAdmin();

$id = (int)($_GET['id'] ?? 0);
if (!$id) { setFlash('error','Invalid ID.'); header('Location: '.base_url('medicines/index.php')); exit; }

$stmt = db()->prepare("SELECT * FROM medicines WHERE id=?");
$stmt->execute([$id]);
$med = $stmt->fetch();

if (!$med) { setFlash('error','Medicine not found.'); header('Location: '.base_url('medicines/index.php')); exit; }

// Check if in use (sale_items)
$inUse = (int) db()->prepare("SELECT COUNT(*) FROM sale_items WHERE medicine_id=?")->execute([$id]);
$check = db()->prepare("SELECT COUNT(*) FROM sale_items WHERE medicine_id=?");
$check->execute([$id]);
if ((int)$check->fetchColumn() > 0) {
    setFlash('error',"Cannot delete '{$med['name']}' — it has associated sales records.");
    header('Location: '.base_url('medicines/index.php'));
    exit;
}

db()->prepare("DELETE FROM medicines WHERE id=?")->execute([$id]);
auditLog('DELETE_MEDICINE','medicines',$id,$med,null);
setFlash('success',"Medicine '{$med['name']}' deleted.");
header('Location: '.base_url('medicines/index.php'));
exit;
