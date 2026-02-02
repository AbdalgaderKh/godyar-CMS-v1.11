<?php
declare(strict_types=1);

/**
 * admin/i18n.php
 * -------------
 * Minimal, stable i18n for Admin (AR/EN/FR)
 * - Switch via ?lang=ar|en|fr
 * - Persist via session + cookie
 * - Dictionary source: /admin/lang/{lang}.php
 *
 * Provides:
 * - gdy_current_lang()
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

if (!function_exists('gdy_set_cookie_rfc')) {
    function gdy_set_cookie_rfc(string $name, string $value, int $ttlSeconds, string $path = '/', bool $secure = false, bool $httpOnly = true, string $sameSite = 'Lax'): void
    {
        if (headers_sent()) {
            return;
        }
        $ttlSeconds = max(0, $ttlSeconds);
        $expires = gmdate('D, d M Y H:i:s \G\M\T', time() + $ttlSeconds);
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

if (!function_exists('gdy_current_lang')) {
    function gdy_current_lang(): string
    {
        $allowed = ['ar','en','fr'];

        $q = isset($_GET['lang']) ? strtolower(trim((string)$_GET['lang'])) : '';
        if ($q !== '' && in_array($q, $allowed, true)) {
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
        if ($s !== '' && in_array($s, $allowed, true)) {
            return $s;
        }

        $c = isset($_COOKIE['gdy_lang']) ? strtolower(trim((string)$_COOKIE['gdy_lang'])) : '';
        if ($c !== '' && in_array($c, $allowed, true)) {
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
        return gdy_current_lang() === 'ar';
    }
}

if (!function_exists('gdy_admin_locale_dict')) {
    function gdy_admin_locale_dict(): array
    {
        static $cache = [];
        $lang = gdy_current_lang();
        if (isset($cache[$lang])) {
            return $cache[$lang];
        }

        $dir = realpath(__DIR__ . '/lang') ?: (__DIR__ . '/lang');
        $file = rtrim($dir, '/\\') . '/' . $lang . '.php';
        $dict = [];
        if (is_file($file)) {
            $tmp = require $file;
            if (is_array($tmp)) {
                $dict = $tmp;
            }
        }

        $cache[$lang] = $dict;
        return $dict;
    }
}

if (!function_exists('__')) {
    function __(string $key, ?string $fallback = null): string
    {
        $dict = gdy_admin_locale_dict();
        if (array_key_exists($key, $dict)) {
            $v = $dict[$key];
            return is_string($v) ? $v : (string)$v;
        }
        return $fallback ?? $key;
    }
}
