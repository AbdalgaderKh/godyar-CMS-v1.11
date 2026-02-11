<?php
declare(strict_types=1);

/**
 * Language route fallback (no rewrite environments).
 * Forwards to main front controller while forcing language + route path.
 */

$__lang = 'en';
$__forcedPath = '/tag';

// Pretty URLs are handled by .htaccess; do not force query-string routing.

$_GET['__lang'] = $__lang;
$_GET['__path'] = $__forcedPath;

$__root = dirname(__DIR__, 2);
$__front = $__root . '/index.php';
if (is_file($__front)) {
    require $__front;
} else {
    http_response_code(500);
    echo 'Front controller not found.';
}
