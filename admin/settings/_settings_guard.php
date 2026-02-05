<?php
declare(strict_types=1);

require_once __DIR__ . '/../_admin_guard.php';
require_once __DIR__ . '/../../includes/bootstrap.php';

/** @var PDO|null $pdo */
$pdo = gdy_pdo_safe();

if (!function_exists('h')) {
    function h($v): string {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('settings_get')) {
    function settings_get(string $key, $default = '') {
        global $pdo;

        if (!($pdo instanceof PDO)) {
            return $default;
        }

        try {
            $col = function_exists('gdy_settings_value_column') ? gdy_settings_value_column($pdo) : 'setting_value';
            $stmt = $pdo->prepare("SELECT {$col} FROM settings WHERE setting_key = ? LIMIT 1");
            $stmt->execute([$key]);
            $v = $stmt->fetchColumn();
            return ($v === false) ? $default : $v;
        } catch (Throwable $e) {
            error_log('[settings_get] ' . $e->getMessage());
            return $default;
        }
    }
}

if (!function_exists('settings_save')) {
    function settings_save(array $pairs): void {
        global $pdo;

        if (!($pdo instanceof PDO) || empty($pairs)) {
            return;
        }

        try {
            $pdo->beginTransaction();

            $now = date('Y-m-d H:i:s');
            $col = function_exists('gdy_settings_value_column') ? gdy_settings_value_column($pdo) : 'setting_value';

            // Detect optional updated_at column (legacy schema)
            $hasUpdatedAt = false;
            try {
                $cols = $pdo->query("SHOW COLUMNS FROM settings")->fetchAll(PDO::FETCH_COLUMN);
                $hasUpdatedAt = (is_array($cols) && in_array('updated_at', $cols, true));
            } catch (Throwable $e) {
                $hasUpdatedAt = false;
            }

            foreach ($pairs as $k => $v) {
                $data = [
                    'setting_key' => (string)$k,
                    $col          => (string)$v,
                ];
                if ($hasUpdatedAt) {
                    $data['updated_at'] = $now;
                }

                $updateCols = [$col];
                if ($hasUpdatedAt) {
                    $updateCols[] = 'updated_at';
                }

                gdy_db_upsert($pdo, 'settings', $data, ['setting_key'], $updateCols);
            }

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('[settings_save] ' . $e->getMessage());
        }
    }
}

