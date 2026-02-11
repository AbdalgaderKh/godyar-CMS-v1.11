<?php
declare(strict_types=1);

namespace Godyar;

use PDO;
use PDOException;

// This patch helper is not part of runtime; keep it syntactically valid.
$envCandidates = [
    dirname(__DIR__, 4) . '/includes/env.php',
    dirname(__DIR__, 3) . '/env.php',
];
foreach ($envCandidates as $c) {
    if (is_file($c)) {
        require_once $c;
        break;
    }
}

class DB
{
    private static ?self $instance = null;
    private PDO $connection;

    private function __construct()
    {
        try {
            $host = defined('DB_HOST') ? (string)DB_HOST : 'localhost';
            $dbname = defined('DB_NAME') ? (string)DB_NAME : '';
            $username = defined('DB_USER') ? (string)DB_USER : '';
            $password = defined('DB_PASS') ? (string)DB_PASS : '';

            $port = (defined('DB_PORT') && (string)DB_PORT !== '') ? (string)DB_PORT : '3306';

            if (defined('DB_DSN') && is_string(DB_DSN) && trim(DB_DSN) !== '') {
                $dsn = trim((string)DB_DSN);
            } else {
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
        } catch (PDOException $e) {
            error_log("DB connect failed: " . $e->getMessage());
            throw new \RuntimeException("فشل الاتصال بقاعدة البيانات");
        }
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
        return $this->query($sql, $params)->fetchAll() ?: [];
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
