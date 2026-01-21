<?php
declare(strict_types=1);

namespace Godyar;

// Ensure env is loaded (defines DB_* constants from .env)
require_once dirname(__DIR__) . '/env.php';

final class DB
{
    private static ?self $instance = null;
    private \PDO $connection;

    private function __construct()
    {
        // Required constants (env.php should define them)
        $host = defined('DB_HOST') ? (string)DB_HOST : 'localhost';
        $name = defined('DB_NAME') ? (string)DB_NAME : '';
        $user = defined('DB_USER') ? (string)DB_USER : '';
        $pass = defined('DB_PASS') ? (string)DB_PASS : '';
        $port = defined('DB_PORT') ? (string)DB_PORT : '3306';
        $charset = defined('DB_CHARSET') ? (string)DB_CHARSET : 'utf8mb4';

        // Driver + DSN
        $drv = defined('DB_DRIVER') ? strtolower((string)DB_DRIVER) : 'auto';
        $dsn = (defined('DB_DSN') && is_string(DB_DSN) && DB_DSN !== '') ? (string)DB_DSN : '';

        if ($drv === '' || $drv === 'auto') {
            if ($dsn !== '' && stripos($dsn, 'pgsql:') === 0) {
                $drv = 'pgsql';
            } else {
                $drv = 'mysql';
            }
        }

        if ($dsn === '') {
            if ($drv === 'pgsql') {
                $port = $port !== '' ? $port : '5432';
                $dsn = "pgsql:host={$host};port={$port};dbname={$name}";
            } else {
                $port = $port !== '' ? $port : '3306';
                $charset = $charset !== '' ? $charset : 'utf8mb4';
                $dsn = "mysql:host={$host};port={$port};dbname={$name};charset={$charset}";
            }
        }

        $options = [
            \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        $this->connection = new \PDO($dsn, $user, $pass, $options);

        if ($drv === 'pgsql') {
            // Ensure UTF-8 for PostgreSQL
            $this->connection->exec("SET client_encoding TO 'UTF8'");
        }
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public static function pdo(): \PDO
    {
        return self::getInstance()->connection;
    }

    public function query(string $sql, array $params = []): \PDOStatement
    {
        $stmt = $this->connection->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }
}
