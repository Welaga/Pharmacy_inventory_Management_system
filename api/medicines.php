<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();
header('Content-Type: application/json; charset=utf-8');

$q     = trim($_GET['q']     ?? '');
$limit = min(20, (int)($_GET['limit'] ?? 10));

if (strlen($q) < 1) {
    echo json_encode([]); exit;
}

$stmt = db()->prepare(
    "SELECT m.id, m.name, m.selling_price, m.quantity, m.barcode,
            m.expiry_date, c.name AS category
     FROM medicines m
     LEFT JOIN categories c ON c.id = m.category_id
     WHERE (m.name LIKE ? OR m.barcode LIKE ?)
       AND m.quantity > 0
       AND m.expiry_date >= CURDATE()
     ORDER BY m.name ASC
     LIMIT ?"
);
$stmt->execute(["%$q%", "%$q%", $limit]);
$results = $stmt->fetchAll();

// Format for response
$out = array_map(fn($m) => [
    'id'            => (int)$m['id'],
    'name'          => $m['name'],
    'selling_price' => (float)$m['selling_price'],
    'quantity'      => (int)$m['quantity'],
    'barcode'       => $m['barcode'] ?? '',
    'expiry_date'   => $m['expiry_date'],
    'category'      => $m['category'] ?? '',
], $results);

echo json_encode($out);
