<?php
declare(strict_types=1);

/**
 * includes/lang_prefix.php (R5 - no redirects)
 *
 * Determines current language (ar/en/fr) safely.
 * Supports override parameters for no-rewrite environments:
 *   - $_GET['__lang'] : forced language
 *   - $_GET['__path'] : forced path (without language prefix)
 *
 * This file must not redirect or output.
 */

$supported   = ['ar','en','fr'];
$defaultLang = 'ar';

// --- Overrides from fallback entrypoints (do not trust user input blindly) ---
$forcedLang = '';
if (isset($_GET['__lang'])) {
    $tmp = strtolower(trim((string)$_GET['__lang']));
    if (in_array($tmp, $supported, true)) { $forcedLang = $tmp; }
}

$forcedPath = '';
if (isset($_GET['__path'])) {
    $tmp = (string)$_GET['__path'];
    if ($tmp === '' || $tmp[0] !== '/') { $tmp = '/' . ltrim($tmp, '/'); }
    // keep only printable ASCII + UTF-8; strip control chars
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
if ($lang === '' && isset($_GET['lang'])) {
    $tmp = strtolower((string)$_GET['lang']);
    if (in_array($tmp, $supported, true)) { $lang = $tmp; }
}

// 3) Cookie
if ($lang === '' && isset($_COOKIE['lang'])) {
    $tmp = strtolower((string)$_COOKIE['lang']);
    if (in_array($tmp, $supported, true)) { $lang = $tmp; }
}

// 4) Default
if ($lang === '' || !in_array($lang, $supported, true)) {
    $lang = $defaultLang;
}

// Publish to runtime
$_GET['lang']     = $lang;
$_COOKIE['lang']  = $lang;
$GLOBALS['lang']  = $lang;

if (!defined('GDY_LANG')) {
    define('GDY_LANG', $lang);
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

// Cleanup internal overrides so they won't leak into app logic
unset($_GET['__lang'], $_GET['__path']);
