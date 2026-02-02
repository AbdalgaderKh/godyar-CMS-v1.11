<?php
declare(strict_types=1);

// Health check endpoint (portable)
// - In production, require token via ?token=... or X-Health-Token header.
// - Without token, only allows localhost.
require_once __DIR__ . '/includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

function gdy_client_ip(): string {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    return is_string($ip) ? $ip : '';
}

function gdy_is_localhost_ip(string $ip): bool {
    return in_array($ip, ['127.0.0.1', '::1'], true);
}

$enabled = getenv('GDY_HEALTHCHECK_ENABLED');
if ($enabled !== false && (string)$enabled === '0') {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'disabled'], JSON_UNESCAPED_UNICODE);
    exit;
}

$tokenEnv = (string)(getenv('GDY_HEALTHCHECK_TOKEN') ?: '');
$tokenReq = (string)($_GET['token'] ?? ($_SERVER['HTTP_X_HEALTH_TOKEN'] ?? ''));
$ip = gdy_client_ip();

$allow = false;
if ($tokenEnv !== '' && hash_equals($tokenEnv, $tokenReq)) {
    $allow = true;
} elseif ($tokenEnv === '' && gdy_is_localhost_ip($ip)) {
    // No token configured: allow only localhost.
    $allow = true;
} elseif ($tokenEnv !== '' && gdy_is_localhost_ip($ip)) {
    // Token configured, but still allow localhost.
    $allow = true;
}

if (!$allow) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'forbidden'], JSON_UNESCAPED_UNICODE);
    exit;
}

$checks = [];

// PHP
$checks['php'] = [
    'version' => PHP_VERSION,
    'sapi' => PHP_SAPI,
];

// HTTPS detection
$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
$checks['https'] = (bool)$https;

// Session
$checks['session'] = [
    'active' => (session_status() === PHP_SESSION_ACTIVE),
    'name' => session_name(),
];

// Storage
$storage = defined('GODYAR_STORAGE') ? GODYAR_STORAGE : (__DIR__ . '/storage');
$checks['storage'] = [
    'path' => $storage,
    'exists' => is_dir($storage),
    'writable' => is_dir($storage) && is_writable($storage),
];

// DB
$dbOk = false;
$dbDriver = 'unknown';
try {
    if (function_exists('gdy_pdo_safe')) {
        $pdo = gdy_pdo_safe();
        if ($pdo instanceof PDO) {
            $dbDriver = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
            $pdo->query('SELECT 1');
            $dbOk = true;
        }
    }
} catch (Throwable $e) {
    $dbOk = false;
}
$checks['db'] = [
    'ok' => $dbOk,
    'driver' => $dbDriver,
];

// Basic runtime limits (informational)
$checks['limits'] = [
    'memory_limit' => ini_get('memory_limit'),
    'upload_max_filesize' => ini_get('upload_max_filesize'),
    'post_max_size' => ini_get('post_max_size'),
];

echo json_encode([
    'ok' => ($checks['storage']['exists'] && $checks['storage']['writable'] && $dbOk),
    'time' => gmdate('c'),
    'checks' => $checks,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
