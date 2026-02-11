<?php
declare(strict_types=1);

/**
 * Godyar CMS - Migration Runner (CLI only)
 *
 * Usage:
 *   php tools/migrate.php            # apply pending migrations
 *   php tools/migrate.php --dry-run  # print what would run, no changes
 *   php tools/migrate.php --status   # show pending/applied
 *
 * Safety:
 * - CLI only.
 * - Requires ALLOW_SCHEMA_CHANGES=1 (env) to actually apply changes.
 * - Tracks applied migrations in schema_migrations table.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Forbidden\n");
}

$root = dirname(__DIR__);
if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', $root);
}

// Bootstrap (loads env, db, autoload, etc.)
require_once ROOT_PATH . '/includes/bootstrap.php';

if (!function_exists('gdy_db_schema_allowed')) {
    fwrite(STDERR, "[fatal] db.php schema helpers are missing.\n");
    exit(2);
}

$argv = $_SERVER['argv'] ?? [];
$dryRun = in_array('--dry-run', $argv, true) || (function_exists('getenv') && (string)getenv('MIGRATION_DRY_RUN') === '1');
$statusOnly = in_array('--status', $argv, true);

if (!$dryRun && !gdy_db_schema_allowed()) {
    fwrite(STDERR, "[fatal] Schema changes are disabled. Set ALLOW_SCHEMA_CHANGES=1 to enable migrations.\n");
    exit(3);
}

$pdo = Godyar\DB::pdo();
$driver = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

function mlog(string $msg): void { fwrite(STDOUT, $msg . PHP_EOL); }
function merr(string $msg): void { fwrite(STDERR, $msg . PHP_EOL); }

function ensure_migrations_table(PDO $pdo, string $driver): void
{
    // Create tracking table if missing.
    // Keep schema simple and compatible.
    if ($driver === 'pgsql') {
        $sql = "CREATE TABLE IF NOT EXISTS schema_migrations (
            id BIGSERIAL PRIMARY KEY,
            name VARCHAR(255) NOT NULL UNIQUE,
            checksum CHAR(64) NOT NULL,
            applied_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
        )";
    } elseif ($driver === 'sqlite') {
        $sql = "CREATE TABLE IF NOT EXISTS schema_migrations (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL UNIQUE,
            checksum TEXT NOT NULL,
            applied_at TEXT NOT NULL DEFAULT (datetime('now'))
        )";
    } else { // mysql/mariadb
        $sql = "CREATE TABLE IF NOT EXISTS schema_migrations (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(255) NOT NULL,
            checksum CHAR(64) NOT NULL,
            applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_schema_migrations_name (name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    }

    // Use schema helper to enforce ALLOW_SCHEMA_CHANGES gate
    gdy_db_ensure_table('schema_migrations', $sql, $pdo);
}

function fetch_applied(PDO $pdo): array
{
    $applied = [];
    try {
        $stmt = $pdo->query("SELECT name, checksum, applied_at FROM schema_migrations ORDER BY id ASC");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $applied[(string)$row['name']] = [
                'checksum' => (string)$row['checksum'],
                'applied_at' => (string)$row['applied_at'],
            ];
        }
    } catch (Exception $e) {
        // Table might not exist yet.
    }
    return $applied;
}

function list_migration_files(string $root, string $driver): array
{
    $dirs = [
        $root . '/database/migrations',
        $root . '/admin/db/migrations',
        $root . '/migrations',
    ];

    $files = [];
    foreach ($dirs as $dir) {
        if (!is_dir($dir)) { continue; }
        $candidate = $dir;
        if ($driver === 'pgsql' && is_dir($dir . '/postgresql')) {
            $candidate = $dir . '/postgresql';
        }
        $glob = glob($candidate . '/*.sql') ?: [];
        sort($glob, SORT_STRING);

        foreach ($glob as $path) {
            $base = basename($path);
            // De-dupe by filename; prefer database/migrations over admin/db/migrations over /migrations.
            if (!isset($files[$base])) {
                $files[$base] = $path;
                continue;
            }
        }
    }

    // Return in filename sort order for determinism.
    ksort($files, SORT_STRING);
    return array_values($files);
}

/**
 * Split SQL into statements (conservative).
 * Handles quotes/backticks and line comments, but not stored procedures with custom delimiters.
 */
function split_sql_statements(string $sql): array
{
    $sql = str_replace(["\r\n", "\r"], "\n", $sql);
    $len = strlen($sql);
    $stmts = [];
    $buf = '';

    $inS = false; // single quote
    $inD = false; // double quote
    $inB = false; // backtick
    $inLC = false; // -- line comment
    $inBC = false; // /* block comment */

    for ($i = 0; $i < $len; $i++) {
        $ch = $sql[$i];
        $nx = ($i + 1 < $len) ? $sql[$i + 1] : '';

        // Handle comment modes
        if ($inLC) {
            if ($ch === "\n") { $inLC = false; $buf .= $ch; }
            continue;
        }
        if ($inBC) {
            if ($ch === '*' && $nx === '/') { $inBC = false; $i++; }
            continue;
        }

        if (!$inS && !$inD && !$inB) {
            // Start of comments
            if ($ch === '-' && $nx === '-') { $inLC = true; $i++; continue; }
            if ($ch === '/' && $nx === '*') { $inBC = true; $i++; continue; }
        }

        // Toggle quote states
        if (!$inD && !$inB && $ch === "'" ) {
            // Handle escaped '' in SQL
            if ($inS && $nx === "'") { $buf .= "''"; $i++; continue; }
            $inS = !$inS;
            $buf .= $ch;
            continue;
        }
        if (!$inS && !$inB && $ch === '"' ) {
            $inD = !$inD;
            $buf .= $ch;
            continue;
        }
        if (!$inS && !$inD && $ch === '`') {
            $inB = !$inB;
            $buf .= $ch;
            continue;
        }

        // Statement delimiter
        if (!$inS && !$inD && !$inB && $ch === ';') {
            $stmt = trim($buf);
            if ($stmt !== '') { $stmts[] = $stmt; }
            $buf = '';
            continue;
        }

        $buf .= $ch;
    }

    $tail = trim($buf);
    if ($tail !== '') { $stmts[] = $tail; }
    return $stmts;
}

function record_migration(PDO $pdo, string $name, string $checksum): void
{
    $stmt = $pdo->prepare("INSERT INTO schema_migrations (name, checksum) VALUES (?, ?)");
    $stmt->execute([$name, $checksum]);
}

function checksum_of(string $path): string
{
    $raw = file_get_contents($path);
    if ($raw === false) { return hash('sha256', ''); }
    // Normalize whitespace lightly to avoid accidental checksum drift due to CRLF
    $norm = str_replace(["\r\n", "\r"], "\n", $raw);
    return hash('sha256', $norm);
}

try {
    if (!$dryRun) {
        ensure_migrations_table($pdo, $driver);
    } else {
        mlog("[info] Dry-run enabled. No DB changes will be applied.");
    }

    $applied = fetch_applied($pdo);
    $files = list_migration_files(ROOT_PATH, $driver);

    if (empty($files)) {
        mlog("[info] No migration files found.");
        exit(0);
    }

    // Build plan
    $plan = [];
    foreach ($files as $path) {
        $name = basename($path);
        $sum = checksum_of($path);
        $already = $applied[$name] ?? null;

        if ($already !== null) {
            if (($already['checksum'] ?? '') !== $sum) {
                $allowMismatch = (string)getenv('ALLOW_MIGRATION_CHECKSUM_MISMATCH');
                $ok = ($allowMismatch !== '' && !in_array(strtolower($allowMismatch), ['0','false','off','no'], true));
                $msg = "[warn] Checksum mismatch for applied migration {$name}. (applied={$already['checksum']} current={$sum})";
                if (!$ok) {
                    merr($msg);
                    merr("[fatal] Refusing to continue. Set ALLOW_MIGRATION_CHECKSUM_MISMATCH=1 if you accept this risk.");
                    exit(4);
                }
                merr($msg . " (continuing due to ALLOW_MIGRATION_CHECKSUM_MISMATCH=1)");
            }
            continue;
        }
        $plan[] = ['name' => $name, 'path' => $path, 'checksum' => $sum];
    }

    if ($statusOnly) {
        mlog("Driver: {$driver}");
        mlog("Applied: " . count($applied));
        mlog("Pending: " . count($plan));
        if (!empty($plan)) {
            mlog("Pending list:");
            foreach ($plan as $m) { mlog(" - " . $m['name']); }
        }
        exit(0);
    }

    if (empty($plan)) {
        mlog("[info] No pending migrations.");
        exit(0);
    }

    mlog("[info] Pending migrations: " . count($plan));
    foreach ($plan as $m) {
        mlog("==> " . $m['name']);

        $sql = (string)file_get_contents($m['path']);
        $sqlTrim = trim($sql);

        if ($sqlTrim === '') {
            mlog("    [skip] empty migration file");
            if (!$dryRun) { record_migration($pdo, $m['name'], $m['checksum']); }
            continue;
        }

        $stmts = split_sql_statements($sql);
        if (empty($stmts)) {
            mlog("    [skip] no statements detected");
            if (!$dryRun) { record_migration($pdo, $m['name'], $m['checksum']); }
            continue;
        }

        if ($dryRun) {
            foreach ($stmts as $s) {
                mlog("    [dry-run] " . preg_replace('/\s+/', ' ', trim($s)));
            }
            continue;
        }

        // Apply statements (transactional when possible)
        $canTxn = !in_array($driver, ['mysql'], true) ? true : true; // keep true; MySQL DDL auto-commits anyway
        if ($canTxn) { $pdo->beginTransaction(); }

        try {
            foreach ($stmts as $s) {
                $pdo->exec($s);
            }
            record_migration($pdo, $m['name'], $m['checksum']);
            if ($canTxn) { $pdo->commit(); }
            mlog("    [ok] applied");
        } catch (Exception $e) {
            if ($canTxn && $pdo->inTransaction()) { $pdo->rollBack(); }
            merr("    [fail] " . $e->getMessage());
            exit(5);
        }
    }

    mlog("[done] Migrations complete.");
    exit(0);

} catch (Exception $e) {
    merr("[fatal] " . $e->getMessage());
    exit(9);
}
