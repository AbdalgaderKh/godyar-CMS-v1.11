<?php
declare(strict_types=1);

namespace Godyar;

// Ensure env is loaded (defines DB_* constants from env.php)
require_once dirname(__DIR__) . '/env.php';

/**
 * Minimal PDO wrapper used across the CMS.
 *
 * Design goals:
 * - Single source of truth for the PDO connection.
 * - Safe identifier quoting (table/column) via strict validation.
 * - Small, dependency-free API.
 */
final class DB
{
    private static ?self $instance = null;
    private \PDO $connection;

    private function __construct()
    {
        // Required constants (env.php should define them)
        $host = defined('DB_HOST') ? (string) DB_HOST : 'localhost';
        $name = defined('DB_NAME') ? (string) DB_NAME : '';
        $user = defined('DB_USER') ? (string) DB_USER : '';
        $pass = defined('DB_PASS') ? (string) DB_PASS : '';
        $port = defined('DB_PORT') ? (string) DB_PORT : '3306';
        $charset = defined('DB_CHARSET') ? (string) DB_CHARSET : 'utf8mb4';

        // Driver + DSN
        $drv = defined('DB_DRIVER') ? strtolower((string) DB_DRIVER) : 'auto';
        $dsn = (defined('DB_DSN') && is_string(DB_DSN) && DB_DSN !== '') ? (string) DB_DSN : '';

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

    public static function driver(): string
    {
        try {
            return (string) self::pdo()->getAttribute(\PDO::ATTR_DRIVER_NAME);
        } catch (\Throwable) {
            return 'unknown';
        }
    }

    /**
     * Quote a DB identifier (table/column) safely.
     * - Allows: [A-Za-z0-9_]+ segments separated by dots.
     * - Throws InvalidArgumentException on invalid input.
     */
    public static function quoteIdent(string $ident): string
    {
        $ident = trim($ident);
        if ($ident === '') {
            throw new \InvalidArgumentException('Empty identifier');
        }

        // allow schema.table or table.column segments
        $parts = explode('.', $ident);
        foreach ($parts as $p) {
            if ($p === '' || !preg_match('/^[A-Za-z0-9_]+$/', $p)) {
                throw new \InvalidArgumentException('Invalid identifier');
            }
        }

        $drv = self::driver();
        if ($drv === 'pgsql') {
            return implode('.', array_map(static fn ($p) => '"' . str_replace('"', '""', $p) . '"', $parts));
        }
        // default mysql/sqlite style
        return implode('.', array_map(static fn ($p) => '`' . str_replace('`', '``', $p) . '`', $parts));
    }

    public function query(string $sql, array $params = []): \PDOStatement
    {
        $stmt = $this->connection->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /** Convenience helpers */
    public function fetchOne(string $sql, array $params = []): ?array
    {
        $stmt = $this->query($sql, $params);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    public function fetchAll(string $sql, array $params = []): array
    {
        return $this->query($sql, $params)->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    public function fetchColumn(string $sql, array $params = [], int $col = 0): mixed
    {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchColumn($col);
    }

    public function execSafe(string $sql, array $params = []): int
    {
        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
    }

    public function transactional(callable $fn): mixed
    {
        $pdo = $this->connection;
        $pdo->beginTransaction();
        try {
            $res = $fn($pdo);
            $pdo->commit();
            return $res;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }
}
