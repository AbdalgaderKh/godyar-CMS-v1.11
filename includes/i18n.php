<?php
declare(strict_types=1);

/**
 * Frontend i18n (AR/EN/FR)
 * ------------------------
 * - Switch via ?lang=ar|en|fr
 * - Persist via session + cookie
 * - Dictionary source: /languages/{lang}.php (+ optional {lang}_patch.php)
 *
 * Provides:
 * - gdy_lang()
 * - gdy_is_rtl()
 * - __($key, $fallback = null)
 */

if (!function_exists('gdy_session_start')) {
    function gdy_session_start(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE && !headers_sent()) {
            session_start();
        }
    }
}

if (!defined('GDY_SUPPORTED_LANGS')) {
    define('GDY_SUPPORTED_LANGS', ['ar', 'en', 'fr']);
}

if (!function_exists('gdy_set_cookie_rfc')) {
    function gdy_set_cookie_rfc(string $name, string $value, int $ttlSeconds, string $path = '/', bool $secure = false, bool $httpOnly = true, string $sameSite = 'Lax'): void
    {
        if (headers_sent()) return;
        $ttlSeconds = max(0, $ttlSeconds);
        $expires = gmdate('D, d M Y H:i:s', time() + $ttlSeconds) . ' GMT';
        $cookie = $name . '=' . rawurlencode($value)
            . '; Expires=' . $expires
            . '; Max-Age=' . $ttlSeconds
            . '; Path=' . $path
            . '; SameSite=' . $sameSite
            . ($secure ? '; Secure' : '')
            . ($httpOnly ? '; HttpOnly' : '');
        header('Set-Cookie: ' . $cookie, false);
    }
}

if (!function_exists('gdy_lang')) {
    function gdy_lang(): string
    {
        $supported = GDY_SUPPORTED_LANGS;

        $q = isset($_GET['lang']) ? strtolower(trim((string)$_GET['lang'])) : '';
        if ($q !== '' && in_array($q, $supported, true)) {
            gdy_session_start();
            $_SESSION['gdy_lang'] = $q;
            if (!headers_sent()) {
                $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                    || ((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
                    || ((int)($_SERVER['SERVER_PORT'] ?? 0) === 443);
                $ttl = 60 * 60 * 24 * 90;
                gdy_set_cookie_rfc('gdy_lang', $q, $ttl, '/', $isSecure, true, 'Lax');
                gdy_set_cookie_rfc('lang', $q, $ttl, '/', $isSecure, true, 'Lax');
            }
            return $q;
        }

        gdy_session_start();
        $s = isset($_SESSION['gdy_lang']) ? strtolower(trim((string)$_SESSION['gdy_lang'])) : '';
        if ($s !== '' && in_array($s, $supported, true)) {
            return $s;
        }

        $c = isset($_COOKIE['gdy_lang']) ? strtolower(trim((string)$_COOKIE['gdy_lang'])) : '';
        if ($c !== '' && in_array($c, $supported, true)) {
            $_SESSION['gdy_lang'] = $c;
            return $c;
        }

        $_SESSION['gdy_lang'] = 'ar';
        return 'ar';
    }
}

if (!function_exists('gdy_is_rtl')) {
    function gdy_is_rtl(): bool
    {
        return gdy_lang() === 'ar';
    }
}

if (!function_exists('gdy_i18n_safe_file')) {
    function gdy_i18n_safe_file(string $file, string $baseDir): ?string
    {
        $base = rtrim((string)realpath($baseDir), '/\\');
        if ($base === '') return null;

        $real = realpath($file);
        if ($real === false) return null;
        $real = (string)$real;

        if (strpos($real, $base . DIRECTORY_SEPARATOR) !== 0) return null;
        return $real;
    }
}

if (!function_exists('gdy_locale_dict')) {
    function gdy_locale_dict(): array
    {
        static $cache = [];
        $lang = gdy_lang();
        if (isset($cache[$lang])) return $cache[$lang];

        $root = defined('ROOT_PATH') ? (string)ROOT_PATH : dirname(__DIR__);
        $langDir = rtrim((string)(realpath($root . '/languages') ?: ($root . '/languages')), '/\\');

        $dict = [];
        $file = $langDir . '/' . $lang . '.php';
        $safe = gdy_i18n_safe_file($file, $langDir);
        if ($safe && is_file($safe)) {
            $tmp = require $safe;
            if (is_array($tmp)) $dict = $tmp;
        }

        $patch = $langDir . '/' . $lang . '_patch.php';
        $safePatch = gdy_i18n_safe_file($patch, $langDir);
        if ($safePatch && is_file($safePatch)) {
            $tmp2 = require $safePatch;
            if (is_array($tmp2)) $dict = array_merge($dict, $tmp2);
        }

        $cache[$lang] = $dict;
        return $dict;
    }
}

if (!function_exists('__')) {
    /**
     * Translation helper.
     * Backward compatible with legacy calls:
     *  - __('KEY')
     *  - __('KEY', 'fallback')
     *  - __('KEY', ['name' => 'X'], 'fallback')
     */
    function __($key, $varsOrFallback = null, $fallback = null): string
    {
        $key = trim((string)$key);
        if ($key === '') return '';

        $vars = [];
        $fb = null;

        if (is_array($varsOrFallback)) {
            $vars = $varsOrFallback;
            $fb = is_string($fallback) ? $fallback : null;
        } elseif (is_string($varsOrFallback) && $varsOrFallback !== '') {
            $fb = $varsOrFallback;
        } else {
            $fb = is_string($fallback) ? $fallback : null;
        }

        $dict = gdy_locale_dict();
        $out = null;
        if (array_key_exists($key, $dict)) {
            $v = $dict[$key];
            $out = is_string($v) ? $v : (string)$v;
        }
        if ($out === null || $out === '') {
            $out = $fb ?? $key;
        }

        // Simple placeholder replacement: {name}
        if ($vars) {
            foreach ($vars as $k => $v) {
                $ph = '{' . (string)$k . '}';
                $out = str_replace($ph, (string)$v, $out);
            }
        }

        return $out;
    }
}
