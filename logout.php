<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

if (isLoggedIn()) {
    auditLog('LOGOUT', 'users', currentUserId());
    session_destroy();
}

header('Location: ' . base_url('index.php'));
exit;
