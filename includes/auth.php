<?php
// ============================================================
// Authentication & Session Helpers
// ============================================================

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => isset($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();
}

/**
 * Redirect if user is not logged in.
 */
function requireLogin(): void {
    if (empty($_SESSION['user_id'])) {
        header('Location: ' . base_url('index.php'));
        exit;
    }
}

/**
 * Redirect if user is not an admin.
 */
function requireAdmin(): void {
    requireLogin();
    if (($_SESSION['user_role'] ?? '') !== 'admin') {
        $_SESSION['flash_error'] = 'Access denied. Admin privileges required.';
        header('Location: ' . base_url('dashboard.php'));
        exit;
    }
}

function isAdmin(): bool {
    return ($_SESSION['user_role'] ?? '') === 'admin';
}

function isLoggedIn(): bool {
    return !empty($_SESSION['user_id']);
}

function currentUserId(): int {
    return (int)($_SESSION['user_id'] ?? 0);
}

function currentUserName(): string {
    return htmlspecialchars($_SESSION['user_name'] ?? 'Unknown');
}

function currentUserRole(): string {
    return $_SESSION['user_role'] ?? 'pharmacist';
}

/**
 * Log an audit action.
 */
function auditLog(string $action, string $table = '', int $recordId = 0, $oldVal = null, $newVal = null): void {
    try {
        $stmt = db()->prepare(
            "INSERT INTO audit_logs (user_id, action, table_name, record_id, old_values, new_values, ip_address)
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            currentUserId(),
            $action,
            $table,
            $recordId ?: null,
            $oldVal  ? json_encode($oldVal)  : null,
            $newVal  ? json_encode($newVal)  : null,
            $_SERVER['REMOTE_ADDR'] ?? null,
        ]);
    } catch (PDOException $e) {
        // Fail silently – don't break the app for a log failure
    }
}

/**
 * Flash messages.
 */
function setFlash(string $type, string $msg): void {
    $_SESSION["flash_{$type}"] = $msg;
}

function getFlash(string $type): string {
    $msg = $_SESSION["flash_{$type}"] ?? '';
    unset($_SESSION["flash_{$type}"]);
    return $msg;
}

/**
 * CSRF helpers.
 */
function csrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrf(): void {
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!hash_equals(csrfToken(), $token)) {
        http_response_code(403);
        die('CSRF token mismatch.');
    }
}
