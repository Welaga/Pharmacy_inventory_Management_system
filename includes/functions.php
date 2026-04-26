<?php
// ============================================================
// Global Helper Functions
// ============================================================

/**
 * Build an absolute URL relative to the app root.
 *
 * Uses __FILE__ (always pharmacy/includes/functions.php) so the
 * app-root path is computed from the filesystem — never from the
 * currently-executing script.  This means it returns the correct
 * base no matter which sub-directory the calling script lives in.
 */
function base_url(string $path = ''): string {
    static $base = null;

    if ($base === null) {
        $scheme  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host    = $_SERVER['HTTP_HOST'] ?? 'localhost';

        // __FILE__ = .../pharmacy/includes/functions.php
        // dirname level 2 always gives .../pharmacy  (the app root)
        $appAbsPath = rtrim(str_replace('\\', '/', dirname(__FILE__, 2)), '/');
        $docRoot    = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT'] ?? ''), '/');

        // Build the web path: strip the filesystem doc-root prefix
        if ($docRoot && strpos($appAbsPath, $docRoot) === 0) {
            $appWebPath = substr($appAbsPath, strlen($docRoot));
        } else {
            // Fallback: derive from SCRIPT_NAME by counting sub-folder depth
            $scriptParts = explode('/', trim($_SERVER['SCRIPT_NAME'] ?? '', '/'));
            $depth       = count($scriptParts) - 1; // depth of CURRENT script below doc-root
            // functions.php is always 2 levels deep (pharmacy/includes/functions.php)
            // so app root is (depth - 1) levels up from the current script's folder
            $appWebPath  = '/' . implode('/', array_slice($scriptParts, 0, max(1, count($scriptParts) - $depth)));
            // Simpler reliable fallback: just go to the folder that contains /includes/
            $script = $_SERVER['SCRIPT_NAME'] ?? '/index.php';
            $parts  = explode('/', trim($script, '/'));
            // Find the index of the app folder (the one containing 'includes' sub-folder)
            // Strategy: strip everything from the sub-page folder name onward
            $known_subdirs = ['medicines','suppliers','sales','reports','users','audit_logs','api','config','includes'];
            $appParts = [];
            foreach ($parts as $p) {
                if (in_array($p, $known_subdirs)) break;
                if (pathinfo($p, PATHINFO_EXTENSION)) break; // stop at a .php file
                $appParts[] = $p;
            }
            $appWebPath = '/' . implode('/', $appParts);
        }

        $appWebPath = rtrim($appWebPath, '/');
        $base = $scheme . '://' . $host . $appWebPath;
    }

    return $base . '/' . ltrim($path, '/');
}

function sanitize(string $value): string {
    return htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
}

function formatCurrency(float $amount, string $symbol = '$'): string {
    return $symbol . number_format($amount, 2);
}

function formatDate(string $date, string $format = 'M d, Y'): string {
    return $date ? date($format, strtotime($date)) : '–';
}

function isExpired(string $expiryDate): bool {
    return strtotime($expiryDate) < time();
}

function isExpiringSoon(string $expiryDate, int $days = 30): bool {
    $expiry = strtotime($expiryDate);
    return $expiry >= time() && $expiry <= strtotime("+{$days} days");
}

function isLowStock(int $qty, int $threshold): bool {
    return $qty <= $threshold;
}

function stockBadge(int $qty, int $threshold): string {
    if ($qty === 0)                   return '<span class="badge bg-danger">Out of Stock</span>';
    if (isLowStock($qty, $threshold)) return '<span class="badge bg-warning text-dark">Low Stock</span>';
    return '<span class="badge bg-success">In Stock</span>';
}

function expiryBadge(string $expiryDate): string {
    if (isExpired($expiryDate))          return '<span class="badge bg-danger">Expired</span>';
    if (isExpiringSoon($expiryDate, 30)) return '<span class="badge bg-warning text-dark">Expiring Soon</span>';
    return '<span class="badge bg-success">Valid</span>';
}

function paginate(int $total, int $perPage, int $currentPage): array {
    $totalPages = (int) ceil($total / $perPage);
    return [
        'total'       => $total,
        'per_page'    => $perPage,
        'current'     => $currentPage,
        'total_pages' => $totalPages,
        'offset'      => ($currentPage - 1) * $perPage,
        'has_prev'    => $currentPage > 1,
        'has_next'    => $currentPage < $totalPages,
    ];
}

function paginationLinks(array $p, string $baseUrl): string {
    if ($p['total_pages'] <= 1) return '';
    $html = '<nav><ul class="pagination pagination-sm justify-content-center mb-0">';
    $html .= '<li class="page-item ' . (!$p['has_prev'] ? 'disabled' : '') . '">
                <a class="page-link" href="' . $baseUrl . '&page=' . ($p['current'] - 1) . '">&laquo;</a></li>';
    for ($i = max(1, $p['current'] - 2); $i <= min($p['total_pages'], $p['current'] + 2); $i++) {
        $active = $i === $p['current'] ? 'active' : '';
        $html  .= '<li class="page-item ' . $active . '"><a class="page-link" href="' . $baseUrl . '&page=' . $i . '">' . $i . '</a></li>';
    }
    $html .= '<li class="page-item ' . (!$p['has_next'] ? 'disabled' : '') . '">
                <a class="page-link" href="' . $baseUrl . '&page=' . ($p['current'] + 1) . '">&raquo;</a></li>';
    $html .= '</ul></nav>';
    return $html;
}

// ─── Dashboard stats helpers ───────────────────────────────

function getDashboardStats(): array {
    $pdo = db();

    // Total medicines
    $totalMeds = (int) $pdo->query("SELECT COUNT(*) FROM medicines")->fetchColumn();

    // Low stock (quantity <= low_stock_threshold)
    $lowStock = (int) $pdo->query(
        "SELECT COUNT(*) FROM medicines WHERE quantity <= low_stock_threshold AND quantity > 0"
    )->fetchColumn();

    // Out of stock
    $outOfStock = (int) $pdo->query("SELECT COUNT(*) FROM medicines WHERE quantity = 0")->fetchColumn();

    // Expired
    $expired = (int) $pdo->query("SELECT COUNT(*) FROM medicines WHERE expiry_date < CURDATE()")->fetchColumn();

    // Expiring within 30 days
    $expiringSoon = (int) $pdo->query(
        "SELECT COUNT(*) FROM medicines WHERE expiry_date >= CURDATE() AND expiry_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)"
    )->fetchColumn();

    // Today's sales
    $todaySales = (float) $pdo->query(
        "SELECT COALESCE(SUM(total_amount),0) FROM sales WHERE DATE(sale_date)=CURDATE() AND status='completed'"
    )->fetchColumn();

    // Today's transactions
    $todayTx = (int) $pdo->query(
        "SELECT COUNT(*) FROM sales WHERE DATE(sale_date)=CURDATE() AND status='completed'"
    )->fetchColumn();

    // Weekly sales
    $weeklySales = (float) $pdo->query(
        "SELECT COALESCE(SUM(total_amount),0) FROM sales WHERE sale_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) AND status='completed'"
    )->fetchColumn();

    // Monthly sales
    $monthlySales = (float) $pdo->query(
        "SELECT COALESCE(SUM(total_amount),0) FROM sales WHERE MONTH(sale_date)=MONTH(CURDATE()) AND YEAR(sale_date)=YEAR(CURDATE()) AND status='completed'"
    )->fetchColumn();

    // Total suppliers
    $totalSuppliers = (int) $pdo->query("SELECT COUNT(*) FROM suppliers WHERE status='active'")->fetchColumn();

    // Recent sales (last 7 days by day)
    $recentSalesStmt = $pdo->query(
        "SELECT DATE(sale_date) as day, COALESCE(SUM(total_amount),0) as total
         FROM sales WHERE sale_date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) AND status='completed'
         GROUP BY DATE(sale_date) ORDER BY day ASC"
    );
    $recentSales = $recentSalesStmt->fetchAll();

    return compact(
        'totalMeds','lowStock','outOfStock','expired','expiringSoon',
        'todaySales','todayTx','weeklySales','monthlySales',
        'totalSuppliers','recentSales'
    );
}
