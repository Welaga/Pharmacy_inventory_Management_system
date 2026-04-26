<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireAdmin();
$id = (int)($_GET['id'] ?? 0);
if (!$id) { setFlash('error','Invalid ID.'); header('Location: '.base_url('sales/index.php')); exit; }

$stmt = db()->prepare("SELECT * FROM sales WHERE id=?");
$stmt->execute([$id]);
$sale = $stmt->fetch();
if (!$sale || $sale['status'] !== 'completed') {
    setFlash('error','Sale not found or already cancelled/refunded.');
    header('Location: '.base_url('sales/index.php')); exit;
}

db()->prepare("UPDATE sales SET status='cancelled' WHERE id=?")->execute([$id]);
auditLog('CANCEL_SALE','sales',$id,['status'=>'completed'],['status'=>'cancelled']);
setFlash('info',"Sale #".str_pad($id,6,'0',STR_PAD_LEFT)." has been cancelled.");
header('Location: '.base_url('sales/index.php')); exit;
