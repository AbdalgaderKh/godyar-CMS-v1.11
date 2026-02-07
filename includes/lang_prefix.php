<?php
declare(strict_types=1);

/**
 * includes/lang_prefix.php (R4 - NO REDIRECTS)
 *
 * Purpose:
 * - Determine current language (ar/en/fr) from:
 *   1) URL prefix (/ar, /en, /fr) if still present (e.g., direct includes)
 *   2) $_GET['lang']
 *   3) Cookie 'lang'
 *   4) Default 'ar'
 *
 * IMPORTANT:
 * - This file MUST NOT redirect to avoid ERR_TOO_MANY_REDIRECTS loops.
 * - This file MUST NOT output anything.
 */

$supported = ['ar','en','fr'];
$defaultLang = 'ar';

// Polyfill for PHP < 8 (this file is included before includes/lang.php)
if (!function_exists('str_starts_with')) {
    function str_starts_with(string $haystack, string $needle): bool
    {
        return $needle === '' || strpos($haystack, $needle) === 0;
    }
}

$uri = (string)($_SERVER['REQUEST_URI'] ?? '/');
// Avoid parse_url()/parse_str() (often discouraged by security linters).
// We only need the path segment from REQUEST_URI.
$qpos = strpos($uri, '?');
$path = ($qpos === false) ? $uri : substr($uri, 0, $qpos);
if (!is_string($path) || $path === '') { $path = '/'; }

// Detect base prefix if installed in subfolder
$script = (string)($_SERVER['SCRIPT_NAME'] ?? '');
$dir = str_replace('\\', '/', dirname($script));
$basePrefix = ($dir === '/' || $dir === '.' || $dir === '\\') ? '' : rtrim($dir, '/');

$pathForLang = $path;
if ($basePrefix !== '' && str_starts_with($pathForLang, $basePrefix . '/')) {
    $pathForLang = substr($pathForLang, strlen($basePrefix));
    if ($pathForLang === '') $pathForLang = '/';
}
$pathForLang = '/' . ltrim($pathForLang, '/');

$lang = '';

// 1) URL prefix (if present)
if (preg_match('#^/(ar|en|fr)(?:/|$)#', $pathForLang, $m)) {
    $lang = (string)$m[1];
}

// 2) GET
if ($lang === '' && !empty($_GET['lang']) && in_array((string)$_GET['lang'], $supported, true)) {
    $lang = (string)$_GET['lang'];
}

// 3) Cookie
if ($lang === '' && !empty($_COOKIE['lang']) && in_array((string)$_COOKIE['lang'], $supported, true)) {
    $lang = (string)$_COOKIE['lang'];
}

// 4) Default
if ($lang === '' || !in_array($lang, $supported, true)) {
    $lang = $defaultLang;
}

// Publish to runtime
$_GET['lang'] = $lang;
$_COOKIE['lang'] = $lang;
$GLOBALS['lang'] = $lang;

if (!defined('GDY_LANG')) {
    define('GDY_LANG', $lang);
}

// NOTE:
// We intentionally do NOT set cookies from here.
// Cookie persistence is handled by includes/i18n.php (gdy_lang()) so we avoid
// duplicate Set-Cookie headers and keep login/language switching stable.
