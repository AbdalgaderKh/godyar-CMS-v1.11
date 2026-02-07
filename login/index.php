<?php
declare(strict_types=1);
/**
 * Fallback router when .htaccess rewrite is not applied by the server.
 * This file simply executes /login.php from the project root.
 */
@chdir(dirname(__DIR__)); // go to public_html
require 'login.php';
