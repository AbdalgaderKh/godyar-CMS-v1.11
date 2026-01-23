<?php
declare(strict_types=1);

require_once __DIR__ . '/../_admin_guard.php';
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/classes/SafeUploader.php';

// Avoid literal namespace backslashes in source (JSON transport constraints)
$SafeUploaderClass = 'Godyar' . chr(92) . 'SafeUploader';

if (!class_exists($SafeUploaderClass)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Uploader missing'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (class_exists('Godyar' . chr(92) . 'Auth')) {
    $AuthClass = 'Godyar' . chr(92) . 'Auth';
    $logged = (bool)$AuthClass::isLoggedIn();
} else {
    $logged = !empty($_SESSION['user_id']);
}

if (!$logged) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => __('t_ceb90cbe05', 'غير مصرح')], JSON_UNESCAPED_UNICODE);
    exit;
}

// Optional CSRF
if (function_exists('verify_csrf_token')) {
    $token = (string)($_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
    if ($token === '' || !verify_csrf_token($token)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'CSRF'], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

if (empty($_FILES['image']) || !is_array($_FILES['image'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => __('t_d51fab540f', 'لا يوجد ملف مرفوع')], JSON_UNESCAPED_UNICODE);
    exit;
}

$destAbs = rtrim((string)ROOT_PATH, '/') . '/uploads/editor';
$urlPrefix = '/uploads/editor';

// Ensure upload folder is non-executable
$ht = $destAbs . '/.htaccess';
if (!is_dir($destAbs)) {
    if (function_exists('gdy_mkdir')) {
        gdy_mkdir($destAbs, 0755, true);
    } else {
        mkdir($destAbs, 0755, true);
    }
}
if (is_dir($destAbs) && !is_file($ht)) {
    @file_put_contents($ht, "<IfModule mod_php.c>\n  php_flag engine off\n</IfModule>\n<IfModule mod_php7.c>\n  php_flag engine off\n</IfModule>\n<FilesMatch \\\"\\\.(php|phtml|phar)\\\$\\\">\n  Require all denied\n</FilesMatch>\nOptions -Indexes\n");
}

$allowedExt = ['jpg','jpeg','png','webp','gif'];
$allowedMime = [
    'jpg'  => ['image/jpeg'],
    'jpeg' => ['image/jpeg'],
    'png'  => ['image/png'],
    'webp' => ['image/webp'],
    'gif'  => ['image/gif'],
];

$res = $SafeUploaderClass::upload($_FILES['image'], [
    'dest_abs_dir' => $destAbs,
    'url_prefix' => $urlPrefix,
    'max_bytes' => 5 * 1024 * 1024,
    'allowed_ext' => $allowedExt,
    'allowed_mime' => $allowedMime,
    'prefix' => 'img_',
]);

if (!($res['success'] ?? false)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => (string)($res['error'] ?? 'failed')], JSON_UNESCAPED_UNICODE);
    exit;
}

$base = function_exists('base_url') ? rtrim((string)base_url(), '/') : '';
$url = $base !== '' ? ($base . (string)$res['rel_url']) : (string)$res['rel_url'];

echo json_encode(['success' => true, 'url' => $url], JSON_UNESCAPED_UNICODE);
