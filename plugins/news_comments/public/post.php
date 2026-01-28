<?php
// plugins/news_comments/public/post.php
declare(strict_types=1);

require_once __DIR__ . '/../../../includes/bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    if (function_exists('gdy_session_start')) { gdy_session_start(); } else { @session_start(); }
}

$newsId = (int)($_POST['news_id'] ?? 0);
$name   = (string)($_POST['name'] ?? '');
$email  = (string)($_POST['email'] ?? '');
$body   = (string)($_POST['body'] ?? '');
$redir  = (string)($_POST['redirect'] ?? '/');

function bad(string $msg, int $code=400): void {
    http_response_code($code);
    header('Content-Type: text/plain; charset=utf-8');
    echo $msg;
    exit;
}

// CSRF (خفيف): في حال كان التحقق متاحًا
$token = (string)($_POST['csrf_token'] ?? '');
if (function_exists('verify_csrf_token')) {
    if (!verify_csrf_token($token)) bad('CSRF', 403);
} else {
    $sess = (string)($_SESSION['csrf_token'] ?? '');
    if ($sess === '' || $token === '' || !hash_equals($sess, $token)) bad('CSRF', 403);
}

if ($newsId <= 0) bad('validation', 422);

$pdo = function_exists('gdy_pdo_safe') ? gdy_pdo_safe() : null;
if (!($pdo instanceof PDO)) bad('db', 500);

// تنظيف بسيط
if (function_exists('gdy_clean_user_text')) {
    $body = gdy_clean_user_text($body, 2000);
} else {
    $body = trim(strip_tags($body));
}
$name = trim(strip_tags($name));
$email = trim(strip_tags($email));
if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) $email = '';

if ($name === '' || $body === '') bad('validation', 422);

// Auto-approve للجميع كما طلبت
$status = 'approved';

try {
    $st = $pdo->prepare("INSERT INTO news_comments (news_id, name, email, body, status, created_at)
                         VALUES (:nid, :name, :email, :body, :st, NOW())");
    $st->execute([
        ':nid' => $newsId,
        ':name' => mb_substr($name, 0, 150, 'UTF-8'),
        ':email' => $email === '' ? 'guest@local.invalid' : mb_substr($email, 0, 190, 'UTF-8'),
        ':body' => $body,
        ':st' => $status,
    ]);
} catch (Throwable $e) {
    // تسجيل خطأ إن توفر سجل
    if (defined('GDY_PHP_ERROR_LOG') && GDY_PHP_ERROR_LOG) {
        error_log('[news_comments] insert failed: '.$e->getMessage());
    }
    bad('server', 500);
}

// رجوع للخبر
header('Location: ' . $redir, true, 303);
exit;
