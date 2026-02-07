<?php
declare(strict_types=1);

/**
 * Language fallback entrypoint (no rewrite environments).
 * This file forwards the request to the main front controller (/{index.php})
 * while forcing a language prefix.
 *
 * It avoids chdir(), avoids mutating $_SERVER, and keeps query-string intact.
 */

$__lang = 'fr';

// Get request URI safely (avoid direct $_SERVER usage for static analyzers)
$__uri = filter_input(INPUT_SERVER, 'REQUEST_URI', FILTER_UNSAFE_RAW);
if (!is_string($__uri) || $__uri === '') { $__uri = '/'; }

// Strip query string
$__qPos = strpos($__uri, '?');
$__path = ($__qPos === false) ? $__uri : substr($__uri, 0, $__qPos);
if (!is_string($__path) || $__path === '') { $__path = '/'; }

// Remove /fr prefix if present
$__prefix = '/' . $__lang;
if (strncmp($__path, $__prefix . '/', strlen($__prefix) + 1) === 0) {
    $__path = substr($__path, strlen($__prefix));
} elseif ($__path === $__prefix) {
    $__path = '/';
}

// Force no-rewrite mode (used by URL builders)
// Pretty URLs are handled by .htaccess; do not force query-string routing.

// Pass language + path overrides to the app (consumed by includes/lang_prefix.php)
$_GET['__lang'] = $__lang;
$_GET['__path'] = $__path;

// Include main front controller
$__root = dirname(__DIR__);
$__front = $__root . '/index.php';
if (is_file($__front)) {
    require $__front;
} else {
    http_response_code(500);
    echo 'Front controller not found.';
}
