<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();
header('Content-Type: application/json; charset=utf-8');

echo json_encode([
    'success' => true,
    'stats'   => getDashboardStats(),
    'time'    => date('H:i:s'),
]);
