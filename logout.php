<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

// Ensure session is available
if (session_status() !== PHP_SESSION_ACTIVE) {
    if (function_exists('gdy_session_start')) {
        gdy_session_start();
    } else {
        @session_start();
    }
}

// Clear app-level auth/session state
if (function_exists('auth_clear_user_session')) {
    auth_clear_user_session();
}

// Clear common session keys (member + admin safety)
if (isset($_SESSION) && is_array($_SESSION)) {
    unset(
        $_SESSION['user'],
        $_SESSION['is_member_logged'],
        $_SESSION['user_id'],
        $_SESSION['user_email'],
        $_SESSION['user_role'],
        $_SESSION['user_name'],
        $_SESSION['admin'],
        $_SESSION['is_admin_logged']
    );
    $_SESSION = [];
}

// Remove session cookie + destroy session
if (session_status() === PHP_SESSION_ACTIVE) {
    if ((int)ini_get('session.use_cookies') === 1) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires'  => time() - 42000,
            'path'     => $p['path'] ?? '/',
            'domain'   => $p['domain'] ?? '',
            'secure'   => (bool)($p['secure'] ?? false),
            'httponly' => (bool)($p['httponly'] ?? true),
            'samesite' => 'Lax',
        ]);
    }

    @session_destroy();
}

// Redirect back to language home if possible
$baseUrl = function_exists('base_url') ? rtrim((string)base_url(), '/') : '';
$lang    = defined('GDY_LANG') ? (string)GDY_LANG : '';
$to      = ($baseUrl !== '' ? $baseUrl : '') . '/';
if ($lang === 'ar' || $lang === 'en' || $lang === 'fr') {
    $to = ($baseUrl !== '' ? $baseUrl : '') . '/' . $lang . '/';
}

header('Location: ' . $to);
exit;
