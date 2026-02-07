<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    gdy_session_start();
}

$baseUrl = function_exists('base_url') ? rtrim((string)base_url(), '/') : '';
if ($baseUrl === '') {
    $baseUrl = '/';
}

// Clear known user session keys
unset(
    $_SESSION['user'],
    $_SESSION['is_member_logged'],
    $_SESSION['user_id'],
    $_SESSION['user_email'],
    $_SESSION['user_role'],
    $_SESSION['user_name'],
    $_SESSION['csrf_token'],
    $_SESSION['csrf_token_ts']
);

// If the app provides a dedicated clearer, call it too.
if (function_exists('auth_clear_user_session')) {
    try {
        auth_clear_user_session();
    } catch (Throwable $e) {
        // ignore
    }
}

// Destroy session properly (fixes "تذكرني" session cookie persisting)
$_SESSION = [];

$useCookies = (bool)ini_get('session.use_cookies');
if ($useCookies) {
    $params  = session_get_cookie_params();
    $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');

    // Clear the session cookie with the same attributes used on login
    setcookie(
        session_name(),
        '',
        [
            'expires'  => time() - 3600,
            'path'     => $params['path'] ?? '/',
            'domain'   => $params['domain'] ?? '',
            'secure'   => $isSecure,
            'httponly' => (bool)($params['httponly'] ?? true),
            // Login uses Strict; keep it consistent so the browser actually removes it.
            'samesite' => 'Strict',
        ]
    );
}

// Finally destroy server-side session
if (session_status() === PHP_SESSION_ACTIVE) {
    session_destroy();
}

// Rotate ID to prevent fixation (best-effort)
if (function_exists('session_regenerate_id')) {
    @session_regenerate_id(true);
}

header('Location: ' . $baseUrl . '/');
exit;
