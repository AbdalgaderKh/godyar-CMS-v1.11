<?php
declare(strict_types=1);

// cron/backup.php — مهمة نسخ احتياطي مبسطة (بدون أوامر shell)
define('CRON_MODE', true);

require_once __DIR__ . '/../includes/bootstrap.php';

$pdo = $pdo ?? (function_exists('gdy_pdo_safe') ? gdy_pdo_safe() : null);

$backupDir = __DIR__ . '/../backups/' . date('Y-m') . '/';
if (!is_dir($backupDir)) {
    mkdir($backupDir, 0755, true);
}

$ts = date('Y-m-d_H-i-s');
$results = [
    'database' => ['skipped' => true],
    'files'    => ['skipped' => true],
];

if ($pdo instanceof PDO) {
    try {
        $sql = "-- Godyar CMS DB backup\n-- Generated at: " . date('c') . "\n\n";
        $driver = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver !== 'mysql') {
            // This lightweight dumper currently supports MySQL/MariaDB only.
            throw new RuntimeException('DB backup is supported only for MySQL/MariaDB in this script.');
        }

        $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN) ?: [];
        foreach ($tables as $table) {
            $table = (string)$table;
            if ($table === '') continue;

            if (!preg_match('/^[A-Za-z0-9_]+$/', $table)) {
                // Defensive: skip unexpected table names
                continue;
            }

            $qt = \Godyar\DB::quoteIdent($table);

            $row = $pdo->query('SHOW CREATE TABLE ' . $qt)->fetch(PDO::FETCH_ASSOC);
            $create = $row['Create Table'] ?? null;
            if (!$create) continue;

            $sql .= "DROP TABLE IF EXISTS `{$table}`;\n";
            $sql .= $create . ";\n\n";

            $stmt = $pdo->query('SELECT * FROM ' . $qt);
            while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $cols = array_keys($r);
                $vals = [];
                foreach ($cols as $c) {
                    $v = $r[$c];
                    $vals[] = ($v === null) ? 'NULL' : $pdo->quote((string)$v);
                }
                $colList = '`' . implode('`,`', array_map(static fn($c)=>str_replace('`','``',$c), $cols)) . '`';
                $sql .= "INSERT INTO `{$table}` ({$colList}) VALUES (" . implode(',', $vals) . ");\n";
            }
            $sql .= "\n";
        }

        $dbFile = $backupDir . "db-{$ts}.sql.gz";
        file_put_contents($dbFile, gzencode($sql, 9));
        $results['database'] = ['success' => true, 'file' => basename($dbFile)];
    } catch (Throwable $e) {
        $results['database'] = ['success' => false, 'error' => $e->getMessage()];
    }
}

try {
    $zipFile = $backupDir . "files-{$ts}.zip";
    $zip = new ZipArchive();
    if ($zip->open($zipFile, ZipArchive::CREATE) !== true) {
        throw new RuntimeException('Cannot open zip: ' . $zipFile);
    }

    $paths = [
        __DIR__ . '/../includes',
        __DIR__ . '/../admin',
        __DIR__ . '/../frontend',
        __DIR__ . '/../assets',
        __DIR__ . '/../config',
    ];

    $root = realpath(__DIR__ . '/..');
    foreach ($paths as $p) {
        $pReal = realpath($p);
        if (!$pReal || !is_dir($pReal)) continue;

        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($pReal, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($it as $file) {
            /** @var SplFileInfo $file */
            $path = $file->getRealPath();
            if (!$path) continue;
            $rel = ltrim(str_replace($root, '', $path), DIRECTORY_SEPARATOR);

            if ($file->isDir()) {
                $zip->addEmptyDir($rel);
            } else {
                $zip->addFile($path, $rel);
            }
        }
    }

    $zip->close();
    $results['files'] = ['success' => true, 'file' => basename($zipFile)];
} catch (Throwable $e) {
    $results['files'] = ['success' => false, 'error' => $e->getMessage()];
}

echo "Backup results:\n";
print_r($results);
exit(0);
