<?php
namespace Godyar\Services;

use PDO;
use Godyar\DB;

/**
 * SettingsService
 *
 * Step 15:
 * - دعم Constructor Injection (المفضل): new SettingsService(PDO $pdo)
 * - الإبقاء على static methods للتوافق الخلفي.
 */
final class SettingsService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }


private function loadAllCached(int $ttl = 600): array
{
    $loader = function (): array {
        $out = [];

        $col = function_exists('gdy_settings_value_column')
            ? gdy_settings_value_column($this->pdo)
            : 'setting_value';

        // Use an alias to keep downstream logic unchanged.
        $st = $this->pdo->query("SELECT setting_key, {$col} AS value FROM settings");

        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $k = (string)($row['setting_key'] ?? '');
            if ($k === '') { continue; }
            $v = $row['value'] ?? null;
            if (is_string($v)) {
                $decoded = json_decode($v, true);
                $out[$k] = ($decoded === null && json_last_error() !== JSON_ERROR_NONE) ? $v : $decoded;
            } else {
                $out[$k] = $v;
            }
        }
        return $out;
    };

    if (class_exists('\\Cache')) {
        return \Cache::remember('settings_all_v1', $ttl, $loader);
    }
    return $loader();
}

    public function getValue(string $key, $default = null)
{
    try {
        $all = $this->loadAllCached(600);
        return array_key_exists($key, $all) ? $all[$key] : $default;
    } catch (\Throwable $e) {
        return $default;
    }
}


    public function setValue(string $key, $value): void
    {
        $col = function_exists('gdy_settings_value_column') ? gdy_settings_value_column($this->pdo) : 'setting_value';
        $hasUpdatedAt = false;
        try {
            $cols = $this->pdo->query("SHOW COLUMNS FROM settings")->fetchAll(PDO::FETCH_COLUMN);
            $hasUpdatedAt = (is_array($cols) && in_array('updated_at', $cols, true));
        } catch (\Throwable $e) { $hasUpdatedAt = false; }
        $val = is_array($value)
            ? json_encode($value, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)
            : (string)$value;

        $now = date('Y-m-d H:i:s');
gdy_db_upsert(
            $this->pdo,
            'settings',
            [
                'setting_key' => $key,
                $col          => $val,
                'updated_at'  => $now,
            ],
            ['setting_key'],
	            array_filter([$col, ($hasUpdatedAt === true) ? 'updated_at' : null])
        );
}

    /** @param array<string, mixed> $pairs */
    public function setMany(array $pairs): void
    {
        $col = function_exists('gdy_settings_value_column') ? gdy_settings_value_column($this->pdo) : 'setting_value';
        $hasUpdatedAt = false;
        try {
            $cols = $this->pdo->query("SHOW COLUMNS FROM settings")->fetchAll(PDO::FETCH_COLUMN);
            $hasUpdatedAt = (is_array($cols) && in_array('updated_at', $cols, true));
        } catch (\Throwable $e) { $hasUpdatedAt = false; }
        $this->pdo->beginTransaction();
        try {
            $now = date('Y-m-d H:i:s');
foreach ($pairs as $k => $v) {
                $val = is_array($v)
                    ? json_encode($v, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)
                    : (string)$v;

                gdy_db_upsert(
                    $this->pdo,
                    'settings',
                    [
                        'setting_key' => $k,
                        $col          => $val,
                        'updated_at'  => $now,
                    ],
                    ['setting_key'],
	                    array_filter([$col, ($hasUpdatedAt === true) ? 'updated_at' : null])
                );
            }
$this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    // ------------------------
    // Backward-compatible static API
    // ------------------------
    public static function get(string $key, $default = null)
    {
        return (new self(DB::pdo()))->getValue($key, $default);
    }

    public static function set(string $key, $value): void
    {
        (new self(DB::pdo()))->setValue($key, $value);
    }

    /** @param array<string, mixed> $pairs */
    public static function many(array $pairs): void
    {
        (new self(DB::pdo()))->setMany($pairs);
    }
}
