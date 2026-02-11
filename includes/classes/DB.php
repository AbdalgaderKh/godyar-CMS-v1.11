<?php
declare(strict_types=1);

namespace Godyar;

use PDO;
use PDOException;
use Throwable;

/**
 * DB (Hotfix)
 * - Reads credentials from constants OR environment variables (.env via includes/env.php)
 * - Supports DB_DSN and DB_PORT
 * - Logs real error to php_error.log but shows generic message to users
 * - Optional debug via ?dbdebug=1
 */
class DB
{
    private static ?self $instance = null;
    private PDO $connection;

    private function __construct()
    {
        try {
            $host = self::env('DB_HOST', defined('DB_HOST') ? (string)DB_HOST : 'localhost');
            $dbname = self::env('DB_NAME', defined('DB_NAME') ? (string)DB_NAME : '');
            $username = self::env('DB_USER', defined('DB_USER') ? (string)DB_USER : '');
            $password = self::env('DB_PASS', defined('DB_PASS') ? (string)DB_PASS : '');

            $port = self::env('DB_PORT', defined('DB_PORT') ? (string)DB_PORT : '');
            if ($port === '') $port = '3306';

            $dsnFromEnv = self::env('DB_DSN', defined('DB_DSN') ? (string)DB_DSN : '');

            if ($dsnFromEnv !== '') {
                $dsn = trim($dsnFromEnv);
            } else {
                // Basic validation
                if ($dbname === '' || $username === '') {
                    throw new PDOException('Missing DB_NAME or DB_USER (check .env)');
                }
                $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";
            }

            $this->connection = new PDO(
                $dsn,
                $username,
                $password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );
        } catch (Throwable $e) {
            error_log('[DB connect failed] ' . $e->getMessage());

            // Optional debug via URL param (do NOT leave enabled on public sites for long)
            if (isset($_GET['dbdebug']) && $_GET['dbdebug'] === '1') {
                http_response_code(500);
                die('DB ERROR: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
            }

            throw new \RuntimeException("فشل الاتصال بقاعدة البيانات.");
        }
    }

    private static function env(string $key, string $fallback = ''): string
    {
        // Prefer $_ENV, then getenv
        if (isset($_ENV[$key]) && $_ENV[$key] !== '') return (string)$_ENV[$key];
        $v = getenv($key);
        if ($v !== false && $v !== '') return (string)$v;
        // Also support $_SERVER
        if (isset($_SERVER[$key]) && $_SERVER[$key] !== '') return (string)$_SERVER[$key];
        return $fallback;
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection(): PDO
    {
        return $this->connection;
    }

    public function query(string $sql, array $params = [])
    {
        $stmt = $this->connection->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public function fetchAll(string $sql, array $params = []): array
    {
        $res = $this->query($sql, $params)->fetchAll();
        return is_array($res) ? $res : [];
    }

    public function fetchOne(string $sql, array $params = []): array|false
    {
        return $this->query($sql, $params)->fetch();
    }

    public function lastInsertId(): string
    {
        return (string)$this->connection->lastInsertId();
    }

    public static function pdo(): PDO
    {
        return self::getInstance()->getConnection();
    }
}
