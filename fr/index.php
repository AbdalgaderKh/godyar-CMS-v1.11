<?php
declare(strict_types=1);
/**
 * Fallback entrypoint for /fr/ when mod_rewrite/.htaccess is not applied.
 * Ensures the language prefix is present, then runs /index.php from root.
 */
$uri = isset($_SERVER['REQUEST_URI']) ? (string)$_SERVER['REQUEST_URI'] : '/';
if (strpos($uri, '/fr') !== 0) {
    $_SERVER['REQUEST_URI'] = '/fr' . $uri;
}
@chdir(dirname(__DIR__)); // go to public_html
require 'index.php';
