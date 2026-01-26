<?php
// /includes/site_settings.php
// Robust site settings layer backed by DB table `settings`.
// Designed for PHP 8.1+ and both MySQL/MariaDB and PostgreSQL.
// Keys are stored in `setting_key` (PK). Values are stored as TEXT in `setting_value`.
//
// NOTE: This file intentionally avoids non-portable SQL where possible and performs a
// conservative auto-migration if a legacy schema is found.

declare(strict_types=1);

if (!function_exists('gdy_pdo_is_pgsql')) {
    function gdy_pdo_is_pgsql(PDO $pdo): bool {
        try {
            return stripos((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME), 'pgsql') !== false;
        } catch (Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('gdy_ensure_settings_table')) {
    function gdy_ensure_settings_table(PDO $pdo): void {
        $isPg = gdy_pdo_is_pgsql($pdo);

        if ($isPg) {
            // PostgreSQL: check table existence in public schema
            $stmt = $pdo->prepare("SELECT to_regclass('public.settings')");
            $stmt->execute();
            $exists = (string)$stmt->fetchColumn();
            if ($exists === '' || strtolower($exists) === 'null') {
                $pdo->exec("CREATE TABLE settings (
                    setting_key   VARCHAR(191) PRIMARY KEY,
                    setting_value TEXT NOT NULL
                )");
            }
            // Ensure columns exist (lightweight)
            $cols = $pdo->query("SELECT column_name FROM information_schema.columns WHERE table_schema='public' AND table_name='settings'")->fetchAll(PDO::FETCH_COLUMN);
            if (!in_array('setting_key', $cols, true)) {
                $pdo->exec("ALTER TABLE settings ADD COLUMN setting_key VARCHAR(191)");
            }
            if (!in_array('setting_value', $cols, true)) {
                $pdo->exec("ALTER TABLE settings ADD COLUMN setting_value TEXT");
            }
        } else {
            // MySQL/MariaDB
            $stmt = $pdo->prepare("SHOW TABLES LIKE 'settings'");
            $stmt->execute();
            $exists = (bool)$stmt->fetchColumn();

            if (!$exists) {
                $pdo->exec("CREATE TABLE settings (
                    setting_key   VARCHAR(191) NOT NULL PRIMARY KEY,
                    setting_value TEXT NOT NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            } else {
                // Make sure required columns exist
                $cols = $pdo->query("SHOW COLUMNS FROM settings")->fetchAll(PDO::FETCH_COLUMN);
                if (!in_array('setting_key', $cols, true)) {
                    // Legacy table (rare): add column then backfill if possible
                    $pdo->exec("ALTER TABLE settings ADD COLUMN setting_key VARCHAR(191) NULL");
                }
                if (!in_array('setting_value', $cols, true)) {
                    // Some legacy installs used `value`
                    if (in_array('value', $cols, true)) {
                        $pdo->exec("ALTER TABLE settings CHANGE COLUMN value setting_value TEXT NOT NULL");
                    } else {
                        $pdo->exec("ALTER TABLE settings ADD COLUMN setting_value TEXT NOT NULL");
                    }
                }

                // Ensure primary key exists on setting_key
                try {
                    $pdo->exec("ALTER TABLE settings ADD PRIMARY KEY (setting_key)");
                } catch (Throwable $e) {
                    // ignore if already exists
                }
            }
        }
    }
}

if (!function_exists('gdy_load_settings')) {
    /**
     * Return associative array of settings.
     */
    function gdy_load_settings(PDO $pdo, bool $forceRefresh = false): array {
        static $cache = null;

        if ($cache !== null && !$forceRefresh) {
            return $cache;
        }

        gdy_ensure_settings_table($pdo);

        $rows = $pdo->query("SELECT setting_key, setting_value FROM settings")->fetchAll(PDO::FETCH_ASSOC);
        $out = [];
        foreach ($rows as $r) {
            $k = (string)($r['setting_key'] ?? '');
            if ($k === '') { continue; }
            $out[$k] = (string)($r['setting_value'] ?? '');
        }

        $cache = $out;
        return $out;
    }
}

/**
 * Backward-compatibility alias.
 * بعض أجزاء النظام القديمة تستخدم site_settings_load().
 */
function site_settings_load(PDO $pdo, bool $forceRefresh = false): array {
    return gdy_load_settings($pdo, $forceRefresh);
}

if (!function_exists('site_setting')) {
    /**
     * Fetch a single setting value (string). Returns $default if missing.
     */
    function site_setting(PDO $pdo, string $key, $default = ''): string {
        $all = gdy_load_settings($pdo, false);
        return array_key_exists($key, $all) ? (string)$all[$key] : (string)$default;
    }
}

if (!function_exists('site_settings_all')) {
    function site_settings_all(PDO $pdo): array {
        return gdy_load_settings($pdo, false);
    }
}

if (!function_exists('site_settings_set')) {
    /**
     * Set a setting key to a string value (upsert).
     */
    function site_settings_set(PDO $pdo, string $key, string $value): bool {
        $key = trim($key);
        if ($key === '') { return false; }

        gdy_ensure_settings_table($pdo);

        $isPg = gdy_pdo_is_pgsql($pdo);

        try {
            if ($isPg) {
                $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value)
                    VALUES (:k, :v)
                    ON CONFLICT (setting_key) DO UPDATE SET setting_value = EXCLUDED.setting_value");
                $ok = $stmt->execute([':k' => $key, ':v' => $value]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value)
                    VALUES (:k, :v)
                    ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
                $ok = $stmt->execute([':k' => $key, ':v' => $value]);
            }

            // Refresh in-memory cache
            gdy_load_settings($pdo, true);
            return (bool)$ok;
        } catch (Throwable $e) {
            return false;
        }
    }
}
