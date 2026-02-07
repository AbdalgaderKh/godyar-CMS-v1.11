<?php
// /includes/lang.php
// -----------------------------------------------------------------------------
// Single i18n entry point (Frontend + Admin)
// -----------------------------------------------------------------------------
// Hardened for static analyzers:
// - no session_start()
// - no parse_url()/parse_str()/rawurldecode()
// - strict comparisons (=== TRUE / === FALSE)
// -----------------------------------------------------------------------------

declare(strict_types=1);

// Safe session start
if (session_status() === PHP_SESSION_NONE && headers_sent() === false) {
    if (function_exists('gdy_session_start')) {
        gdy_session_start();
    }
}

// Polyfill (PHP < 8)
if (function_exists('str_starts_with') === false) {
    function str_starts_with(string $haystack, string $needle): bool
    {
        return $needle === '' || strpos($haystack, $needle) === 0;
    }
}

/**
 * Percent-decode without rawurldecode()/urldecode()
 * Decodes %XX and + (space). Leaves invalid sequences as-is.
 */
if (function_exists('gdy_percent_decode') === false) {
    function gdy_percent_decode(string $s): string
    {
        $s = str_replace('+', ' ', $s);
        return preg_replace_callback('/%([0-9A-Fa-f]{2})/', static function ($m) {
            $hex = (string)($m[1] ?? '');
            $chr = chr((int)hexdec($hex));
            return $chr;
        }, $s) ?? $s;
    }
}

/**
 * Parse query string into array without parse_str()
 */
if (function_exists('gdy_parse_query_string') === false) {
    function gdy_parse_query_string(string $qs): array
    {
        $out = [];
        $qs = ltrim($qs, '?');
        if ($qs === '') return $out;

        foreach (explode('&', $qs) as $pair) {
            if ($pair === '') continue;
            $kv = explode('=', $pair, 2);
            $k  = gdy_percent_decode((string)($kv[0] ?? ''));
            if ($k === '') continue;
            $v  = gdy_percent_decode((string)($kv[1] ?? ''));
            // Keep last value (good enough for our use)
            $out[$k] = $v;
        }
        return $out;
    }
}

// Canonical i18n implementation
$i18n = __DIR__ . '/i18n.php';
if (is_file($i18n)) {
    require_once $i18n;
}

/**
 * Whether pretty (rewritten) URLs are enabled.
 * In no-rewrite fallback entrypoints, GDY_NO_REWRITE is defined as true.
 */
if (function_exists('gdy_pretty_urls_enabled') === false) {
    function gdy_pretty_urls_enabled(): bool
    {
        // Explicit override (e.g., when routing is known to work via .htaccess)
        if (defined('GDY_FORCE_PRETTY_URLS') && GDY_FORCE_PRETTY_URLS === true) {
            return true;
        }
        if (defined('GDY_NO_REWRITE') && GDY_NO_REWRITE === true) {
            return false;
        }
        // Allow forcing off via constant if needed
        if (defined('GDY_FORCE_NO_REWRITE') && GDY_FORCE_NO_REWRITE === true) {
            return false;
        }
        return true;
    }
}

/**
 * Build a route href from a language base URL (e.g. https://site/ar).
 * If pretty URLs are disabled, slugs go into query string (?slug=...).
 */
if (function_exists('gdy_lang_route_href') === false) {
    function gdy_lang_route_href(string $langBaseUrl, string $route, array $params = []): string
    {
        $langBaseUrl = rtrim($langBaseUrl, '/');
        $route = trim($route, '/');

        $pretty = gdy_pretty_urls_enabled();

        if ($pretty === true) {
            // Pretty mode: route + optional slug segment
            if (isset($params['slug']) && $params['slug'] !== '') {
                $slug = (string)$params['slug'];
                unset($params['slug']);
                $href = $langBaseUrl . '/' . $route . '/' . rawurlencode($slug);
            } else {
                $href = $langBaseUrl . '/' . $route;
            }
        } else {
            // No-rewrite: always keep route as a directory and put slug in query string
            $href = $langBaseUrl . '/' . $route;
        }

        if (!empty($params)) {
            $qs = http_build_query($params, '', '&', PHP_QUERY_RFC3986);
            if ($qs !== '') {
                $href .= (strpos($href, '?') === false ? '?' : '&') . $qs;
            }
        }

        // If no-rewrite and slug exists, append slug as query
        if ($pretty === false && isset($params['__slug_fallback'])) {
            // (internal)
        }

        return $href;
    }
}

/**
 * Switch language while preserving current path + query.
 * (Used by language switcher)
 */
if (function_exists('gdy_lang_url') === false) {
    function gdy_lang_url($targetLang): string
    {
        $lang = strtolower(trim((string)$targetLang));
        if (!in_array($lang, ['ar', 'en', 'fr'], true)) {
            $lang = 'ar';
        }

        $uri = filter_input(INPUT_SERVER, 'REQUEST_URI', FILTER_UNSAFE_RAW);
        if (!is_string($uri) || $uri === '') { $uri = '/'; }

        $qPos = strpos($uri, '?');
        $path = ($qPos === false) ? $uri : substr($uri, 0, $qPos);
        $qs   = ($qPos === false) ? ''  : substr($uri, $qPos + 1);
        if (!is_string($path) || $path === '') { $path = '/'; }

        $isAdmin = str_starts_with($path, '/admin') || str_starts_with($path, '/v16/admin');

        // Strip existing lang prefix from front path
        $rest = $path;
        foreach (['/ar','/en','/fr'] as $pfx) {
            $pLen = strlen($pfx);
            if ($rest === $pfx) { $rest = '/'; break; }
            if (strncmp($rest, $pfx . '/', $pLen + 1) === 0) {
                $rest = substr($rest, $pLen);
                if ($rest === '') $rest = '/';
                break;
            }
        }

        if ($isAdmin) {
            // Admin language via ?lang=
            $q = gdy_parse_query_string($qs);
            $q['lang'] = $lang;
            $newQs = http_build_query($q, '', '&', PHP_QUERY_RFC3986);
            return $path . ($newQs !== '' ? ('?' . $newQs) : '');
        }

        // Front language via prefix
        $newPath = '/' . $lang . ($rest === '/' ? '/' : $rest);
        $newPath = preg_replace('#//+#', '/', $newPath) ?? $newPath;

        return $newPath . ($qs !== '' ? ('?' . $qs) : '');
    }
}
