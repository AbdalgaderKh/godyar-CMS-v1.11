<?php
// /includes/lang.php
// -----------------------------------------------------------------------------
// Single i18n entry point (Frontend + Admin)
// -----------------------------------------------------------------------------
// This project historically shipped multiple i18n implementations (lang.php,
// translation.php, i18n.php). On some deployments they were included together,
// which caused fatal redeclare errors (e.g., gdy_lang()) and missing helper
// functions (e.g., gdy_regex_replace()).
//
// This file is now the *only* recommended include. It:
//  - starts the session safely
//  - loads includes/i18n.php (the canonical implementation)
//  - provides backwards-compatible helpers used across templates/controllers
//  - avoids redeclare conflicts by guarding every helper

declare(strict_types=1);

// Safe session start (no suppression)
if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}

// Polyfill (PHP < 8)
if (!function_exists('str_starts_with')) {
    function str_starts_with(string $haystack, string $needle): bool
    {
        return $needle === '' || strpos($haystack, $needle) === 0;
    }
}

// Regex wrappers used in older code
if (!function_exists('gdy_regex_replace')) {
    function gdy_regex_replace($pattern, $replacement, $subject, $limit = -1, &$count = null)
    {
        if ($count === null) {
            return preg_replace($pattern, $replacement, $subject, (int)$limit);
        }
        $tmp = 0;
        $out = preg_replace($pattern, $replacement, $subject, (int)$limit, $tmp);
        $count = $tmp;
        return $out;
    }
}
if (!function_exists('gdy_regex_replace_callback')) {
    function gdy_regex_replace_callback($pattern, $callback, $subject, $limit = -1, &$count = null)
    {
        if ($count === null) {
            return preg_replace_callback($pattern, $callback, $subject, (int)$limit);
        }
        $tmp = 0;
        $out = preg_replace_callback($pattern, $callback, $subject, (int)$limit, $tmp);
        $count = $tmp;
        return $out;
    }
}

// Canonical i18n implementation
$i18n = __DIR__ . '/i18n.php';
if (is_file($i18n)) {
    require_once $i18n;
}

// Backward compatible: some legacy templates expect detect_lang()
if (!function_exists('detect_lang')) {
    function detect_lang(): string
    {
        return function_exists('gdy_lang') ? (string)gdy_lang() : 'ar';
    }
}

// Backward compatible: is_rtl() / gdy_is_rtl()
if (!function_exists('is_rtl')) {
    function is_rtl($lang = null): bool
    {
        $lang = is_string($lang) && $lang !== '' ? $lang : (function_exists('gdy_lang') ? (string)gdy_lang() : 'ar');
        return $lang === 'ar';
    }
}
if (!function_exists('gdy_is_rtl')) {
    function gdy_is_rtl($lang = null): bool
    {
        return is_rtl($lang);
    }
}

// Backward compatible: explicit language set helper
if (!function_exists('gdy_set_lang')) {
    function gdy_set_lang($lang): string
    {
        $lang = strtolower(trim((string)$lang));
        $allowed = ['ar', 'en', 'fr'];
        if (!in_array($lang, $allowed, true)) {
            $lang = 'ar';
        }
        if (session_status() !== PHP_SESSION_ACTIVE && !headers_sent()) {
            session_start();
        }
        $_SESSION['gdy_lang'] = $lang;
        $_SESSION['lang'] = $lang;

        // keep cookies aligned
        if (function_exists('gdy_set_cookie_rfc') && !headers_sent()) {
            $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                || ((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
                || ((int)($_SERVER['SERVER_PORT'] ?? 0) === 443);
            $ttl = 60 * 60 * 24 * 90;
            gdy_set_cookie_rfc('gdy_lang', $lang, $ttl, '/', $isSecure, true, 'Lax');
            gdy_set_cookie_rfc('lang', $lang, $ttl, '/', $isSecure, true, 'Lax');
        }

        return $lang;
    }
}

// Backward compatible: language switch URLs.
//  - Public: /ar or /en or /fr
//  - Admin:  keep query (?lang=)
if (!function_exists('gdy_lang_url')) {
    function gdy_lang_url($targetLang): string
    {
        $lang = strtolower(trim((string)$targetLang));
        if (!in_array($lang, ['ar', 'en', 'fr'], true)) {
            $lang = 'ar';
        }

        $uri = (string)($_SERVER['REQUEST_URI'] ?? '/');
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';

        $isAdmin = str_starts_with($path, '/admin') || str_starts_with($path, '/v16/admin');
        if ($isAdmin) {
            $q = [];
            $qs = (string)(parse_url($uri, PHP_URL_QUERY) ?: '');
            if ($qs !== '') {
                parse_str($qs, $q);
            }
            $q['lang'] = $lang;
            $newQs = http_build_query($q);
            return $path . ($newQs ? ('?' . $newQs) : '');
        }

        // Public home in that language
        return '/' . $lang;
    }
}

// Legacy stubs (some old code calls these, but i18n.php handles loading internally)
if (!function_exists('ensure_i18n_loaded')) {
    function ensure_i18n_loaded(): bool
    {
        return true;
    }
}
if (!function_exists('load_translations')) {
    function load_translations($lang): array
    {
        // i18n.php keeps an internal cache; this stub avoids fatal calls.
        return [];
    }
}
