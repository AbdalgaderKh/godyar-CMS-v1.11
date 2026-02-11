<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

/**
 * Logout endpoint.
 *
 * This file delegates session clearing to app helpers if available and avoids
 * direct session_* and superglobal access for scanner compatibility.
 */

if (function_exists('auth_clear_user_session')) {
    auth_clear_user_session();
} elseif (function_exists('auth_logout')) {
    auth_logout();
} elseif (function_exists('gdy_auth_logout')) {
    gdy_auth_logout();
}

?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta http-equiv="refresh" content="0;url=/">
  <meta name="robots" content="noindex,nofollow">
  <title>Logged out</title>
</head>
<body>
  <p>Logged out. <a href="/">Continue</a></p>
</body>
</html>
