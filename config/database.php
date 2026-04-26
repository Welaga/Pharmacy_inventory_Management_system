<?php
// ============================================================
// Database Configuration
// ============================================================

define('DB_HOST',    getenv('DB_HOST')    ?: '127.0.0.1');
define('DB_USER',    getenv('DB_USER')    ?: 'root');
define('DB_PASS',    getenv('DB_PASS')    ?: '');
define('DB_NAME',    getenv('DB_NAME')    ?: 'Pharmacy_DB');
define('DB_CHARSET', 'utf8mb4');

define('APP_NAME',    'PharmaCare IMS');
define('APP_VERSION', '1.0.0');
define('LOW_STOCK_DAYS',   30); // Days before expiry considered "expiring soon"

class Database {
    private static ?PDO $instance = null;

    public static function connect(): PDO {
        if (self::$instance === null) {
            $dsn = sprintf(
                'mysql:host=127.0.0.1;dbname=Pharmacy_DB;charset=utf8mb4',
                DB_HOST, DB_NAME, DB_CHARSET
            );
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            try {
                self::$instance = new PDO($dsn, DB_USER, DB_PASS, $options);
            } catch (PDOException $e) {
                http_response_code(500);
                die(json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]));
            }
        }
        return self::$instance;
    }

    private function __construct() {}
    private function __clone()   {}
}

function db(): PDO {
    return Database::connect();
}
