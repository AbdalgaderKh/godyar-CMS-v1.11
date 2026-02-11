<?php
declare(strict_types=1);

/**
 * CLEAN Environment Loader (dependency-free)
 * - Loads variables from ROOT_PATH/.env (or ENV_FILE if set)
 * - Populates getenv()/putenv and $_ENV/$_SERVER
 * - Provides env($key, $default = null) helper
 *
 * Shared hosting note:
 * - DB_* must ALWAYS come from .env to avoid cPanel injected env collisions.
 */

if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(__DIR__));
}

if (!function_exists('env')) {
    function env(string $key, $default = null) {
        $val = getenv($key);
        return ($val === false) ? $default : $val;
    }
}

if (!function_exists('gdy_load_env_file')) {
    function gdy_load_env_file(?string $path = null): void {
        $envPath = $path;
        if ($envPath === null || $envPath === '') {
            $envPath = (string) getenv('ENV_FILE');
            if ($envPath === '') {
                $envPath = ROOT_PATH . '/.env';
            }
        }

        if (!is_file($envPath) || !is_readable($envPath)) {
            return;
        }

        $raw = (string) file_get_contents($envPath);
        if ($raw === '') {
            return;
        }

        $lines = preg_split("/\r\n|\n|\r/", $raw) ?: [];
        foreach ($lines as $line) {
            $line = trim((string) $line);
            if ($line === '' || $line[0] === '#') {
                continue;
            }

            $pos = strpos($line, '=');
            if ($pos === false) {
                continue;
            }

            $k = trim(substr($line, 0, $pos));
            if ($k === '') {
                continue;
            }

            $v = trim(substr($line, $pos + 1));
            $v = trim($v, "\"'"); // strip surrounding quotes safely

            // DB_* should ALWAYS come from .env (avoid injected env collisions)
            if (strpos($k, 'DB_') !== 0) {
                $existing = getenv($k);
                if ($existing !== false && trim((string)$existing) !== '') {
                    continue;
                }
            }

            putenv($k . '=' . $v);
            $_ENV[$k] = $v;
            $_SERVER[$k] = $v;
        }
    }
}

gdy_load_env_file();
