<?php
// /includes/site_settings.php
// Robust site settings layer backed by DB table `settings`.
// Designed for PHP 8.1+ and both MySQL/MariaDB and PostgreSQL.
// Keys are stored in `setting_key` (PK). Values are stored as TEXT in `setting_value`.
//
// NOTE: This file intentionally avoids non-portable SQL where possible and performs a
// conservative auto-migration if a legacy schema is found.

declare(strict_types=1);

/**
 * Normalize a logo path stored in DB.
 *
 * الهدف: توحيد قيمة site_logo لتكون مساراً يبدأ بـ "/" داخل نفس الدومين.
 *
 * أمثلة:
 *  - uploads/logo.png          => /uploads/logo.png
 *  - /uploads/logo.png         => /uploads/logo.png
 *  - https://godyar.org/x.png  => /x.png (إذا كان نفس الدومين)
 *  - data:image/...            => تُترك كما هي
 */
if (!function_exists('gdy_normalize_site_logo_value')) {
    function gdy_normalize_site_logo_value(string $raw, string $baseUrl = ''): string {
        $v = trim($raw);
        if ($v === '') return '';

        // Keep data URIs (rare but valid)
        if (stripos($v, 'data:') === 0) return $v;

        // Remove surrounding quotes (some DB tools store with quotes)
        $v = trim($v, " \t\n\r\0\x0B\"'");
        if ($v === '') return '';

        // Normalize BASE_URL
        $baseUrl = trim($baseUrl);

        // If it's an absolute URL and belongs to our domain, strip it to path
        if (preg_match('~^https?://~i', $v)) {
            $u = @parse_url($v);
            if (is_array($u)) {
                $path = $u['path'] ?? '';
                $host = strtolower((string)($u['host'] ?? ''));
                $baseHost = '';
                if ($baseUrl !== '') {
                    $bu = @parse_url($baseUrl);
                    if (is_array($bu)) $baseHost = strtolower((string)($bu['host'] ?? ''));
                }
                if ($baseHost !== '' && $host === $baseHost && $path !== '') {
                    $v = $path;
                }
            }
        }

        // Convert protocol-relative //example.com/path to /path if same host
        if (strpos($v, '//') === 0 && $baseUrl !== '') {
            $maybe = 'https:' . $v;
            $u = @parse_url($maybe);
            $bu = @parse_url($baseUrl);
            if (is_array($u) && is_array($bu)) {
                $host = strtolower((string)($u['host'] ?? ''));
                $baseHost = strtolower((string)($bu['host'] ?? ''));
                $path = $u['path'] ?? '';
                if ($baseHost !== '' && $host === $baseHost && $path !== '') {
                    $v = $path;
                }
            }
        }

        // Remove any leading BASE_URL fragments accidentally stored.
        if ($baseUrl !== '') {
            $b = rtrim($baseUrl, '/');
            if ($b !== '' && stripos($v, $b) === 0) {
                $v = substr($v, strlen($b));
            }
        }

        // Ensure it is a site-root path
        $v = ltrim($v);
        if ($v !== '' && $v[0] !== '/') {
            $v = '/' . $v;
        }

        // Collapse duplicate slashes: //uploads//logo.png => /uploads/logo.png
        $v = preg_replace('~/{2,}~', '/', $v) ?? $v;

        return $v;
    }
}

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
    function gdy_load_settings($pdo, bool $forceRefresh = false): array {
        static $cache = null;

        // If DB is not available (e.g., during install or misconfigured env), return an empty settings array.
        if (!($pdo instanceof PDO)) {
            return [];
        }

        if ($cache !== null && !$forceRefresh) {
            return $cache;
        }
if (!$forceRefresh && class_exists('\\Cache')) {
    $cached = \Cache::get('site_settings_all_v1');
    if (is_array($cached)) {
        $cache = $cached;
        return $cache;
    }
}


        gdy_ensure_settings_table($pdo);

        $rows = $pdo->query("SELECT setting_key, setting_value FROM settings")->fetchAll(PDO::FETCH_ASSOC);
        $out = [];
        foreach ($rows as $r) {
            $k = (string)($r['setting_key'] ?? '');
            if ($k === '') { continue; }
            $out[$k] = (string)($r['setting_value'] ?? '');
        }

	    // ------------------------------------------------------------------
	    // ✅ Compatibility aliases for dotted setting keys
	    // لوحة التحكم تخزن بعض المفاتيح بصيغة "site.logo" بينما واجهة الموقع
	    // تعتمد على مفاتيح تقليدية مثل "site_logo" (خصوصاً الصفحات التي تستخدم
	    // include للـ header مباشرة مثل contact.php و /page/*).
	    // لذلك ننشئ Alias حتى لا يفشل إظهار الشعار/الاسم في هذه الصفحات.
	    $aliases = [
	        'site.logo'       => 'site_logo',
	        'site.name'       => 'site_name',
	        'site.desc'       => 'site_desc',
	        'site.url'        => 'site_url',
	        'site.email'      => 'site_email',
	        'site.phone'      => 'site_phone',
	        'site.address'    => 'site_address',
	        'site.favicon'    => 'site_favicon',
	        'site.theme_color'=> 'theme_color',
	    ];
	    foreach ($aliases as $from => $to) {
	        if ((!array_key_exists($to, $out) || trim((string)$out[$to]) === '') && array_key_exists($from, $out)) {
	            $out[$to] = (string)$out[$from];
	        }
	    }

        // ✅ Auto-fix: normalize site_logo value and write back to DB if needed.
        // هذا يحل مشكلة اختلاف المسار بين صفحات بروتيرات مختلفة (/ar vs /page/...)
        // حيث كانت قيمة الشعار أحياناً تُحفظ بدون "/" أو كـ URL كامل.
        if (array_key_exists('site_logo', $out)) {
            $base = defined('BASE_URL') ? (string)BASE_URL : '';
            $fixed = gdy_normalize_site_logo_value((string)$out['site_logo'], $base);
            if ($fixed !== (string)$out['site_logo']) {
                try {
	                // حاول UPDATE أولاً، وإذا لم توجد السطر (لأن المفتاح محفوظ بصيغة site.logo) قم بإدراجه.
	                $stmt = $pdo->prepare('UPDATE settings SET setting_value = :v WHERE setting_key = :k');
	                $stmt->execute([':v' => $fixed, ':k' => 'site_logo']);
	                if ((int)$stmt->rowCount() === 0) {
	                    $ins = $pdo->prepare('INSERT INTO settings (setting_key, setting_value) VALUES (:k, :v)');
	                    $ins->execute([':k' => 'site_logo', ':v' => $fixed]);
	                }
	                $out['site_logo'] = $fixed;
                } catch (Throwable $e) {
                    // ignore (read-only DB / permissions)
                }
            }
        }

        $cache = $out;
        if (class_exists('\\Cache')) { \Cache::put('site_settings_all_v1', $out, 600); }
        return $out;
    }
}

/**
 * Backward-compatibility alias.
 * بعض أجزاء النظام القديمة تستخدم site_settings_load().
 */
function site_settings_load($pdo, bool $forceRefresh = false): array {
    return gdy_load_settings($pdo, $forceRefresh);
}

if (!function_exists('site_setting')) {
    /**
     * Fetch a single setting value (string). Returns $default if missing.
     */
    function site_setting($pdo, string $key, $default = ''): string {
        if (!($pdo instanceof PDO)) { return (string)$default; }
        $all = gdy_load_settings($pdo, false);
        return array_key_exists($key, $all) ? (string)$all[$key] : (string)$default;
    }
}

if (!function_exists('site_settings_all')) {
    function site_settings_all($pdo): array {
        if (!($pdo instanceof PDO)) { return []; }
        return gdy_load_settings($pdo, false);
    }
}

if (!function_exists('site_settings_set')) {
    /**
     * Set a setting key to a string value (upsert).
     */
    function site_settings_set($pdo, string $key, string $value): bool {
        if (!($pdo instanceof PDO)) { return false; }
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

// -------------------------------------------------------------
// Frontend options helper
// -------------------------------------------------------------
// بعض أجزاء الواجهة (PageController/ContactController/صفحات قديمة)
// تعتمد على دالة موحّدة لتجهيز متغيرات الثيم/الهوية.
// كانت مفقودة في بعض النسخ بعد التنظيف، ما يسبب Fatal Error.

if (!function_exists('gdy_prepare_frontend_options')) {
    /**
     * Build a normalized set of frontend variables from the settings array.
     * This function must be side-effect free (no DB calls) and safe to call
     * from any controller.
     *
     * @param array<string,string> $settings
     * @return array<string,mixed>
     */
    function gdy_prepare_frontend_options($settings = null): array {
        if (!is_array($settings)) {
            // Backward compatible: allow calling with no args.
            try {
                $pdo = function_exists('gdy_pdo_safe') ? gdy_pdo_safe() : null;
            } catch (Throwable $e) {
                $pdo = null;
            }
            $settings = ($pdo instanceof PDO) ? gdy_load_settings($pdo, false) : [];
        }
        // Base URL
        $baseUrl = function_exists('base_url') ? rtrim((string)base_url(), '/') : (defined('GODYAR_BASE_URL') ? (string)GODYAR_BASE_URL : '');
        if ($baseUrl === '') { $baseUrl = '/'; }

        // Language
        $lang = function_exists('gdy_lang') ? (string)gdy_lang() : (string)($settings['site_lang'] ?? $settings['lang'] ?? 'ar');
        $lang = trim($lang);
        if ($lang === '') { $lang = 'ar'; }

        // Identity
        $siteName    = (string)($settings['site_name'] ?? $settings['settings.site_name'] ?? 'Godyar');
        $siteTagline = (string)($settings['site_tagline'] ?? $settings['settings.site_tagline'] ?? '');
        $siteLogo    = (string)($settings['site_logo'] ?? $settings['settings.site_logo'] ?? '');

        // Theme / palette
        $frontPreset = (string)($settings['front_preset'] ?? $settings['settings.front_preset'] ?? 'default');
        $frontPreset = strtolower(trim($frontPreset)) ?: 'default';

        $primaryColor = (string)($settings['primary_color']
            ?? $settings['theme_primary']
            ?? $settings['settings.primary_color']
            ?? '#111111');

        // Force default palette unless explicitly custom
        if ($frontPreset !== 'custom') {
            $primaryColor = '#111111';
        }

        $primaryDark = (string)($settings['primary_dark']
            ?? $settings['theme_primary_dark']
            ?? $settings['settings.primary_dark']
            ?? '');

        if ($primaryDark === '') {
            $hex = ltrim($primaryColor, '#');
            if (preg_match('/^[0-9a-f]{6}$/i', $hex)) {
                $r = max(0, hexdec(substr($hex, 0, 2)) - 40);
                $g = max(0, hexdec(substr($hex, 2, 2)) - 40);
                $b = max(0, hexdec(substr($hex, 4, 2)) - 40);
                $primaryDark = sprintf('#%02x%02x%02x', $r, $g, $b);
            } else {
                $primaryDark = '#000000';
            }
        }

        $primaryRgb = '17, 17, 17';
        try {
            $hex = ltrim($primaryColor, '#');
            if (preg_match('/^[0-9a-f]{6}$/i', $hex)) {
                $r = hexdec(substr($hex, 0, 2));
                $g = hexdec(substr($hex, 2, 2));
                $b = hexdec(substr($hex, 4, 2));
                $primaryRgb = $r . ', ' . $g . ', ' . $b;
            }
        } catch (Throwable $e) {
            $primaryRgb = '17, 17, 17';
        }

        $themeClass = (string)($settings['theme_class'] ?? 'theme-default');
        if ($themeClass === '') { $themeClass = 'theme-default'; }

        // Header background
        $headerBgEnabled = ((string)($settings['theme_header_bg_enabled'] ?? $settings['settings.theme_header_bg_enabled'] ?? '0') === '1');

        // Search placeholder
        $searchPlaceholder = (string)($settings['search_placeholder'] ?? $settings['settings.search_placeholder'] ?? 'ابحث...');

        // Navigation base URL (language-prefixed) for page routes
        $navBaseUrl = rtrim($baseUrl, '/') . '/' . trim($lang, '/');
        if ($baseUrl === '/' || $baseUrl === '') { $navBaseUrl = '/' . trim($lang, '/'); }

        return [
            // commonly extracted variables
            'baseUrl'            => $baseUrl,
            'navBaseUrl'         => $navBaseUrl,
            '_gdyLang'           => $lang,
            'siteName'           => $siteName,
            'siteTagline'        => $siteTagline,
            'siteLogo'           => $siteLogo,
            'frontPreset'        => $frontPreset,
            'primaryColor'       => $primaryColor,
            'primaryDark'        => $primaryDark,
            'primaryRgb'         => $primaryRgb,
            'themeClass'         => $themeClass,
            'headerBgEnabled'    => $headerBgEnabled,
            'searchPlaceholder'  => $searchPlaceholder,
            // keep raw settings available for templates that read it directly
            'settings'           => $settings,
        ];
    }
}
