<?php
declare(strict_types=1);

/**
 * Security logging (JSON Lines)
 * - Portable across environments (file-based)
 * - Writes to ROOT_PATH/storage/logs/security.log
 *
 * Disable via: GDY_SECURITY_LOG=0
 */

if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(__DIR__));
}

if (!function_exists('gdy_security_log')) {
    /**
     * @param array<string,mixed> $context
     */
    function gdy_security_log(string $event, array $context = []): void
    {
        $enabled = (string)($_ENV['GDY_SECURITY_LOG'] ?? getenv('GDY_SECURITY_LOG') ?? '1');
        if ($enabled === '0') {
            return;
        }

        $dir = rtrim(ROOT_PATH, '/\\') . '/storage/logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        $file = $dir . '/security.log';

        $entry = [
            'ts'    => gmdate('c'),
            'event' => $event,
            'ip'    => $_SERVER['REMOTE_ADDR'] ?? null,
            'path'  => $_SERVER['REQUEST_URI'] ?? null,
            'method'=> $_SERVER['REQUEST_METHOD'] ?? null,
            'ua'    => isset($_SERVER['HTTP_USER_AGENT']) ? substr((string)$_SERVER['HTTP_USER_AGENT'], 0, 200) : null,
            'ctx'   => $context,
        ];

        $line = json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (!is_string($line)) {
            return;
        }

        // Basic rotation: if file > 5MB, rotate to security.log.1
        if (is_file($file) && filesize($file) !== false && (int)filesize($file) > 5_000_000) {
            @rename($file, $file . '.1');
        }

        @file_put_contents($file, $line . "\n", FILE_APPEND | LOCK_EX);
    }
}
