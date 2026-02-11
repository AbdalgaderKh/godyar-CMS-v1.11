<?php
declare(strict_types=1);

/**
 * Language prefix detector
 *
 * Rules:
 * - Do not mutate superglobals.
 * - Do not set cookies here.
 * - Use strict comparisons.
 *
 * Expected URLs:
 *   /ar/...
 *   /en/...
 *   /fr/...
 */

if (defined('GDY_LANG')) {
    return;
}

$supported = ['ar', 'en', 'fr'];

// Allow a hard override (e.g., installer or CLI)
if (defined('GDY_FORCE_LANG')) {
    $forced = (string)GDY_FORCE_LANG;
    if (in_array($forced, $supported, true)) {
        define('GDY_LANG', $forced);
        define('GDY_FORCE_PRETTY_URLS', true);
        return;
    }
}

// Read REQUEST_URI safely (avoid direct $_SERVER usage)
$uri = filter_input(INPUT_SERVER, 'REQUEST_URI', FILTER_UNSAFE_RAW);
$uri = is_string($uri) && $uri !== '' ? $uri : '/';

// Strip query string without parse_url
$qpos = strpos($uri, '?');
$path = ($qpos === false) ? $uri : substr($uri, 0, $qpos);
$path = is_string($path) && $path !== '' ? $path : '/';

$trim = trim($path, '/');
$seg0 = '';
if ($trim !== '') {
    $parts = explode('/', $trim, 2);
    $seg0  = isset($parts[0]) ? strtolower((string)$parts[0]) : '';
}

$lang = in_array($seg0, $supported, true) ? $seg0 : 'ar'; // default

define('GDY_LANG', $lang);
define('GDY_FORCE_PRETTY_URLS', true);
