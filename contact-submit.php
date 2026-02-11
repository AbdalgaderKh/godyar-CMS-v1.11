<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

// Accept only POST
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Location: contact.php');
    exit;
}

// Rate limit (portable)
if (function_exists('gody_rate_limit')) {
    if (!gody_rate_limit('contact', 8, 600)) { // 8 submissions / 10 minutes per IP
        $retry = function_exists('gody_rate_limit_retry_after') ? gody_rate_limit_retry_after('contact') : 600;
        if (function_exists('gdy_security_log')) { gdy_security_log('rate_limited', ['bucket'=>'contact','retry_after'=>$retry]); }
        http_response_code(429);
        header('Retry-After: ' . max(1, $retry));
        // preserve UX: redirect with a safe message
        $_SESSION['flash_error'] = 'تم تجاوز الحد المسموح لإرسال الرسائل. حاول لاحقاً.';
        header('Location: contact.php');
        exit;
    }
}

// CSRF (central)
if (function_exists('csrf_verify_any_or_die')) {
    csrf_verify_any_or_die();
} elseif (function_exists('csrf_verify_or_die')) {
    csrf_verify_or_die();
}

if (!function_exists('sanitize_value')) {
    function sanitize_value($v): string
    {
        return htmlspecialchars(trim((string)$v), ENT_QUOTES, 'UTF-8');
    }
}

$name    = sanitize_value($_POST['name'] ?? '');
$email   = sanitize_value($_POST['email'] ?? '');
$subject = sanitize_value($_POST['subject'] ?? '');
$message = trim((string)($_POST['message'] ?? ''));

if ($name === '' || $email === '' || $message === '') {
    header('Location: contact.php?status=error');
    exit;
}

try {
    $pdo = function_exists('gdy_pdo_safe') ? gdy_pdo_safe() : null;

    if ($pdo instanceof PDO) {
        $tableOk = true;
        if (function_exists('gdy_db_table_exists')) {
            $tableOk = (bool)gdy_db_table_exists($pdo, 'contact_messages');
        }

        if ($tableOk) {
            $stmt = $pdo->prepare(
                'INSERT INTO contact_messages (name, email, subject, message, status, is_read, created_at) '
                . 'VALUES (:name, :email, :subject, :message, :status, :is_read, :created_at)'
            );
            $stmt->execute([
                ':name'       => $name,
                ':email'      => $email,
                ':subject'    => $subject,
                ':message'    => $message,
                ':status'     => 'new',
                ':is_read'    => 0,
                ':created_at' => date('Y-m-d H:i:s'),
            ]);
        }
    }
} catch (Exception $e) {
    error_log('[contact-submit] ' . $e->getMessage());
}

header('Location: contact.php?status=ok');
exit;