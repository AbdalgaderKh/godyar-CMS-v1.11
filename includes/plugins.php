<?php
declare(strict_types=1);

/**
 * Godyar Plugin System (برو)
 * Path: /godyar/includes/plugins.php
 *
 * - كل إضافة توضع داخل /godyar/plugins/{PluginFolder}
 * - داخل كل إضافة ملف Plugin.php يعيد كائن يطبّق GodyarPluginInterface
 * - (اختياري) ملف plugin.json لتعريف الاسم و enabled وغيرها
 *
 * - PluginManager:
 *     - loadAll(): تحميل جميع الإضافات المفعّلة
 *     - addHook(): تسجيل hook
 *     - doHook(): تنفيذ hook (يشبه actions)
 *     - filter(): تمربر قيمة عبر سلسلة من الفلاتر
 *
 * - دوال مساعدة:
 *     - g_plugins()
 *     - g_do_hook($hook, ...)
 *     - g_apply_filters($hook, $value, ...)
 */

interface GodyarPluginInterface
{
    /**
     * تستدعى عند تحميل الإضافة.
     * الإضافة تستخدم $pm->addHook() لتسجيل الهواكس.
     */
    public function register(PluginManager $pm): void;
}

final class PluginManager
{
    private static ?PluginManager $instance = null;

    /** @var array<string,object> slug => instance */
    private array $plugins = [];

    /** @var array<string,array> slug => meta */
    private array $meta = [];

    /** @var array<string, array<int, array{0:int,1:callable}>> */
    private array $hooks = [];

    private function __construct() {}

    public static function instance(): PluginManager
    {
        if (!self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * تحميل جميع الإضافات من مجلد /plugins
     * كل إضافة داخل مجلد:
     *   /plugins/PluginFolder/plugin.json (اختياري)
     *   /plugins/PluginFolder/Plugin.php (إلزامي)
     *
     * Plugin.php يجب أن يُرجع (return) كائن يطبّق GodyarPluginInterface.
     */
    public function loadAll(?string $baseDir = null): void
    {
        $base = $baseDir ?: dirname(__DIR__) . '/plugins';
        if (!is_dir($base)) {
            return;
        }

        $dirs = scandir($base);
        if (!is_array($dirs)) {
            return;
        }

        foreach ($dirs as $dir) {
            if ($dir === '.' || $dir === '..') {
                continue;
            }

            $pluginPath = $base . '/' . $dir;
            if (!is_dir($pluginPath)) {
                continue;
            }

            $slug = $dir;

            // قراءة meta من plugin.json (إن وجد)
            $meta = [
                'slug'    => $slug,
                'enabled' => true,
            ];
            $metaFile = $pluginPath . '/plugin.json';
            if (is_file($metaFile)) {
                $json = gdy_file_get_contents($metaFile);
                if (is_string($json) && $json !== '') {
                    $decoded = json_decode($json, true);
                    if (is_array($decoded)) {
                        $meta = array_merge($meta, $decoded);
                    }
                }
            }

            // حقل enabled
            $enabled = $meta['enabled'] ?? true;
            if (is_string($enabled)) {
                $enabled = in_array(strtolower($enabled), ['1','true','yes','on'], true);
            } else {
                $enabled = (bool)$enabled;
            }
            if (!$enabled) {
                continue; // الإضافة معطّلة
            }

            $main = $pluginPath . '/Plugin.php';
            if (!is_file($main)) {
                continue;
            }

            try {
                // يجب أن يُرجع كائن يطبّق GodyarPluginInterface
                $instance = include $main;

                if ($instance instanceof GodyarPluginInterface) {
                    // Install/Migrate عند التفعيل (قبل register)
                    $this->maybeMigratePlugin($slug, $pluginPath, $meta, $instance);

                    $this->meta[$slug]    = $meta;
                    $this->plugins[$slug] = $instance;
                    $instance->register($this);
                }
            } catch (\Throwable $e) {
                error_log('[Godyar Plugin] Failed to load plugin ' . $slug . ': ' . $e->getMessage());
            }
        }
    }

    /**
     * تشغيل migrations يدويًا لإضافة واحدة (حتى لو كانت مُعطّلة) من لوحة الإدارة.
     * - إذا $force=true: يتم تشغيل migrate من 0 -> schema_version (مفيد عند وجود أعمدة مفقودة رغم تسجيل النسخة).
     *
     * @return array{ok:bool,message:string,from?:int,to?:int}
     */
    public function runMigrationsFor(string $slug, ?string $baseDir = null, bool $force = false): array
    {
        $slug = preg_replace('~[^A-Za-z0-9_\-]~', '', $slug) ?: '';
        if ($slug === '') {
            return ['ok' => false, 'message' => 'Invalid slug'];
        }

        $base = $baseDir ?: dirname(__DIR__) . '/plugins';
        $pluginPath = rtrim($base, '/\\') . DIRECTORY_SEPARATOR . $slug;
        if (!is_dir($pluginPath)) {
            return ['ok' => false, 'message' => 'Plugin folder not found'];
        }

        $meta = ['slug' => $slug, 'enabled' => true];
        $metaFile = $pluginPath . '/plugin.json';
        if (is_file($metaFile)) {
            $json = gdy_file_get_contents($metaFile);
            if (is_string($json) && $json !== '') {
                $decoded = json_decode($json, true);
                if (is_array($decoded)) {
                    $meta = array_merge($meta, $decoded);
                }
            }
        }

        $target = (int)($meta['schema_version'] ?? 0);
        if ($target <= 0) {
            return ['ok' => false, 'message' => 'No schema_version configured'];
        }

        $main = $pluginPath . '/Plugin.php';
        if (!is_file($main)) {
            return ['ok' => false, 'message' => 'Plugin.php not found'];
        }

        $pdo = function_exists('gdy_pdo_safe') ? gdy_pdo_safe() : null;
        if (!$pdo instanceof \PDO) {
            return ['ok' => false, 'message' => 'DB connection not available'];
        }

        try {
            $instance = include $main;
            if (!$instance instanceof GodyarPluginInterface) {
                return ['ok' => false, 'message' => 'Plugin does not implement interface'];
            }

            $this->ensureSchemaTable($pdo);
            $installed = $this->getInstalledSchemaVersion($pdo, $slug);
            $from = $force ? 0 : $installed;

            // migrate() preferred
            if (method_exists($instance, 'migrate')) {
                $ref = new \ReflectionMethod($instance, 'migrate');
                $argc = $ref->getNumberOfParameters();
                $args = [$pdo, $from, $target, $pluginPath, $meta];
                $ref->invokeArgs($instance, array_slice($args, 0, $argc));
                $this->setInstalledSchemaVersion($pdo, $slug, $target);
                return ['ok' => true, 'message' => 'Migrations executed', 'from' => $from, 'to' => $target];
            }

            // install() fallback (only for fresh installs)
            if ($from === 0 && method_exists($instance, 'install')) {
                $ref = new \ReflectionMethod($instance, 'install');
                $argc = $ref->getNumberOfParameters();
                $args = [$pdo, $pluginPath, $meta];
                $ref->invokeArgs($instance, array_slice($args, 0, $argc));
                $this->setInstalledSchemaVersion($pdo, $slug, $target);
                return ['ok' => true, 'message' => 'Install executed', 'from' => 0, 'to' => $target];
            }

            return ['ok' => false, 'message' => 'No migrate/install method found'];
        } catch (\Throwable $e) {
            error_log('[Godyar Plugin] manual migrate failed for ' . $slug . ': ' . $e->getMessage());
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * تشغيل migrations لكل الإضافات داخل مجلد /plugins.
     *
     * @return array<int,array{slug:string,ok:bool,message:string,from?:int,to?:int}>
     */
    public function runMigrationsForAll(?string $baseDir = null, bool $force = false): array
    {
        $base = $baseDir ?: dirname(__DIR__) . '/plugins';
        if (!is_dir($base)) return [];

        $out = [];
        $dirs = scandir($base);
        if (!is_array($dirs)) return [];

        foreach ($dirs as $dir) {
            if ($dir === '.' || $dir === '..') continue;
            if (!is_dir($base . '/' . $dir)) continue;
            $res = $this->runMigrationsFor($dir, $base, $force);
            $out[] = array_merge(['slug' => $dir], $res);
        }
        return $out;
    }


    /**
     * تسجيل hook
     */
    

/**
 * تشغيل install/migrate للإضافة (يعمل قبل register).
 * يعتمد على plugin.json -> schema_version (عدد صحيح).
 * يتم حفظ النسخة المثبّتة داخل جدول godyar_plugin_schema.
 */
private function maybeMigratePlugin(string $slug, string $pluginPath, array $meta, GodyarPluginInterface $instance): void
{
    $target = (int)($meta['schema_version'] ?? 0);
    if ($target <= 0) {
        return;
    }

    $pdo = function_exists('gdy_pdo_safe') ? gdy_pdo_safe() : null;
    if (!$pdo instanceof \PDO) {
        return;
    }

    try {
        $this->ensureSchemaTable($pdo);
        $from = $this->getInstalledSchemaVersion($pdo, $slug);

        if ($target <= $from) {
            return; // لا يوجد شيء للتنفيذ
        }

        // 1) migrate(PDO $pdo, int $from, int $to, string $pluginPath, array $meta)
        if (method_exists($instance, 'migrate')) {
            $ref = new \ReflectionMethod($instance, 'migrate');
            $argc = $ref->getNumberOfParameters();
            if ($argc >= 2) {
                // مرونة في تمرير المعاملات حسب التوقيع
                $args = [$pdo, $from, $target, $pluginPath, $meta];
                $ref->invokeArgs($instance, array_slice($args, 0, $argc));
            }
            $this->setInstalledSchemaVersion($pdo, $slug, $target);
            return;
        }

        // 2) install(PDO $pdo, string $pluginPath, array $meta) — ينفذ فقط عند from=0
        if ($from === 0 && method_exists($instance, 'install')) {
            $ref = new \ReflectionMethod($instance, 'install');
            $argc = $ref->getNumberOfParameters();
            $args = [$pdo, $pluginPath, $meta];
            $ref->invokeArgs($instance, array_slice($args, 0, $argc));
            $this->setInstalledSchemaVersion($pdo, $slug, $target);
        }
    } catch (\Throwable $e) {
        // لا نكسر الموقع؛ فقط نسجل الخطأ
        error_log('[Godyar Plugin] migrate failed for ' . $slug . ': ' . $e->getMessage());
    }
}

private function ensureSchemaTable(\PDO $pdo): void
{
    // جدول صغير لتتبع نسخ مخطط الإضافات
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS godyar_plugin_schema (
            slug VARCHAR(120) NOT NULL PRIMARY KEY,
            schema_version INT NOT NULL DEFAULT 0,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}

private function getInstalledSchemaVersion(\PDO $pdo, string $slug): int
{
    try {
        $st = $pdo->prepare("SELECT schema_version FROM godyar_plugin_schema WHERE slug=? LIMIT 1");
        $st->execute([$slug]);
        $v = $st->fetchColumn();
        return is_numeric($v) ? (int)$v : 0;
    } catch (\Throwable $e) {
        return 0;
    }
}

private function setInstalledSchemaVersion(\PDO $pdo, string $slug, int $version): void
{
    $version = (int)$version;
    try {
        $st = $pdo->prepare("
            INSERT INTO godyar_plugin_schema (slug, schema_version)
            VALUES (?, ?)
            ON DUPLICATE KEY UPDATE schema_version=VALUES(schema_version)
        ");
        $st->execute([$slug, $version]);
    } catch (\Throwable $e) {
        // ignore
    }
}

public function addHook(string $hook, callable $callback, int $priority = 10): void
    {
        $this->hooks[$hook][] = [$priority, $callback];
        usort($this->hooks[$hook], static function ($a, $b) {
            return $a[0] <=> $b[0];
        });
    }

    /**
     * تنفيذ hook (يشبه action)
     * يسمح بتمرير المتغيرات بالـ reference لمنح الإضافات صلاحية التعديل على المصفوفات.
     */
    public function doHook(string $hook, &...$args): void
    {
        if (empty($this->hooks[$hook])) {
            return;
        }

        foreach ($this->hooks[$hook] as [$priority, $cb]) {
            try {
                $cb(...$args);
            } catch (\Throwable $e) {
                error_log('[Godyar Plugin] Error in hook ' . $hook . ': ' . $e->getMessage());
            }
        }
    }

    /**
     * فلاتر لإرجاع قيمة بعد تمريرها على الإضافات.
     */
    public function filter(string $hook, $value, ...$args)
    {
        if (empty($this->hooks[$hook])) {
            return $value;
        }

        $result = $value;

        foreach ($this->hooks[$hook] as [$priority, $cb]) {
            try {
                $result = $cb($result, ...$args);
            } catch (\Throwable $e) {
                error_log('[Godyar Plugin] Error in filter ' . $hook . ': ' . $e->getMessage());
            }
        }

        return $result;
    }

    /**
     * جميع الإضافات المحمّلة
     * @return array<string,object>
     */
    public function all(): array
    {
        return $this->plugins;
    }

    /**
     * معلومات meta لجميع الإضافات أو لإضافة معيّنة.
     */
    public function meta(?string $slug = null): array
    {
        if ($slug === null) {
            return $this->meta;
        }
        return $this->meta[$slug] ?? [];
    }
}

// دوال مساعدة global
if (!function_exists('g_plugins')) {
    function g_plugins(): PluginManager
    {
        return PluginManager::instance();
    }
}

if (!function_exists('g_do_hook')) {
    /**
     * تنفيذ hook (يشبه action)
     */
    function g_do_hook(string $hook, &...$args): void
    {
        PluginManager::instance()->doHook($hook, ...$args);
    }
}

if (!function_exists('g_apply_filters')) {
    /**
     * تمريـر قيمة على فلاتر الإضافات
     */
    function g_apply_filters(string $hook, $value, ...$args)
    {
        return PluginManager::instance()->filter($hook, $value, ...$args);
    }
}
