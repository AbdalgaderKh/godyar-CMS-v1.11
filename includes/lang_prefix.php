<?php
declare(strict_types=1);

/**
 * includes/lang_prefix.php (R5 - no redirects)
 *
 * Determines current language (ar/en/fr) safely.
 * Supports override parameters for no-rewrite environments (set by .htaccess):
 *   - __lang : forced language
 *   - __path : forced path (without language prefix)
 * Or via constants (set by fallback entrypoints):
 *   - GDY_FORCE_LANG
 *   - GDY_FORCE_PATH
 *
 * This file must not redirect or output.
 */

$supported   = ['ar','en','fr'];
$defaultLang = 'ar';

// --- Overrides from fallback entrypoints (do not trust user input blindly) ---
$forcedLang = '';
if (defined('GDY_FORCE_LANG')) {
    $tmp = strtolower(trim((string)GDY_FORCE_LANG));
    if (in_array($tmp, $supported, true)) { $forcedLang = $tmp; }
} else {
    $tmp = filter_input(INPUT_GET, '__lang', FILTER_UNSAFE_RAW);
    if (is_string($tmp)) {
        $tmp = strtolower(trim($tmp));
        if (in_array($tmp, $supported, true)) { $forcedLang = $tmp; }
    }
}

$forcedPath = '';
if (defined('GDY_FORCE_PATH')) {
    $tmp = (string)GDY_FORCE_PATH;
} else {
    $tmp = filter_input(INPUT_GET, '__path', FILTER_UNSAFE_RAW);
    $tmp = is_string($tmp) ? $tmp : '';
}
if ($tmp !== '') {
    if ($tmp[0] !== '/') { $tmp = '/' . ltrim($tmp, '/'); }
    // strip control chars
    $tmp = preg_replace('/[\x00-\x1F\x7F]/u', '', $tmp) ?? '/';
    $forcedPath = $tmp;
}

// Read URI safely
$uri = filter_input(INPUT_SERVER, 'REQUEST_URI', FILTER_UNSAFE_RAW);
if (!is_string($uri) || $uri === '') { $uri = '/'; }
$qPos = strpos($uri, '?');
$path = ($qPos === false) ? $uri : substr($uri, 0, $qPos);
if (!is_string($path) || $path === '') { $path = '/'; }

// If we have a forcedPath, use it for lang detection stripping (no-rewrite)
$pathForLang = ($forcedPath !== '') ? $forcedPath : $path;
$pathForLang = '/' . ltrim($pathForLang, '/');

$lang = '';

// 0) Forced
if ($forcedLang !== '') {
    $lang = $forcedLang;
}

// 1) URL prefix (if present)
if ($lang === '') {
    // Fast prefix check without parse_url/parse_str
    if (strncmp($pathForLang, '/ar', 3) === 0 && ($pathForLang === '/ar' || $pathForLang[3] === '/')) {
        $lang = 'ar';
    } elseif (strncmp($pathForLang, '/en', 3) === 0 && ($pathForLang === '/en' || $pathForLang[3] === '/')) {
        $lang = 'en';
    } elseif (strncmp($pathForLang, '/fr', 3) === 0 && ($pathForLang === '/fr' || $pathForLang[3] === '/')) {
        $lang = 'fr';
    }
}

// 2) GET lang (only when not forced)
if ($lang === '') {
    $tmp = filter_input(INPUT_GET, 'lang', FILTER_UNSAFE_RAW);
    $tmp = is_string($tmp) ? strtolower($tmp) : '';
    if (in_array($tmp, $supported, true)) { $lang = $tmp; }
}

// 3) Cookie
if ($lang === '') {
    $tmp = filter_input(INPUT_COOKIE, 'lang', FILTER_UNSAFE_RAW);
    $tmp = is_string($tmp) ? strtolower($tmp) : '';
    if (in_array($tmp, $supported, true)) { $lang = $tmp; }
}

// 4) Default
if ($lang === '' || !in_array($lang, $supported, true)) {
    $lang = $defaultLang;
}

// Publish to runtime
$__g = $lang; // local alias to avoid reusing $lang in superglobals assignments
$_GET['lang']     = $__g;
$GLOBALS['lang']  = $lang;

if (!defined('GDY_LANG')) {
    define('GDY_LANG', $lang);
}

// When language is injected by routing, we can safely generate pretty URLs.
if (!defined('GDY_FORCE_PRETTY_URLS')) {
    define('GDY_FORCE_PRETTY_URLS', true);
}

// Persist cookie (best-effort, no redirects)
if (headers_sent() === false) {
    $https = filter_input(INPUT_SERVER, 'HTTPS', FILTER_UNSAFE_RAW);
    $xfp   = filter_input(INPUT_SERVER, 'HTTP_X_FORWARDED_PROTO', FILTER_UNSAFE_RAW);
    $port  = filter_input(INPUT_SERVER, 'SERVER_PORT', FILTER_UNSAFE_RAW);

    $isSecure = (is_string($https) && $https !== '' && strtolower($https) !== 'off')
        || (is_string($xfp) && strtolower($xfp) === 'https')
        || (is_string($port) && (int)$port === 443);

    setcookie('lang', $lang, [
        'expires'  => time() + 31536000,
        'path'     => '/',
        'secure'   => $isSecure,
        'httponly' => false,
        'samesite' => 'Lax',
    ]);
}

// (no need to unset; we read via filter_input/const)
