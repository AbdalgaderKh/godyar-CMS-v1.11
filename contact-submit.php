<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

// Always ensure session is available (for CSRF helpers / flash messages)
if (session_status() !== PHP_SESSION_ACTIVE && function_exists('gdy_session_start')) {
    gdy_session_start();
} elseif (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// CSRF protection (if provided by the project)
if (function_exists('csrf_verify_or_die')) {
    csrf_verify_or_die();
} elseif (function_exists('verify_csrf')) {
    verify_csrf();
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Location: contact.php');
    exit;
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
} catch (Throwable $e) {
    error_log('[contact-submit] ' . $e->getMessage());
}

header('Location: contact.php?status=ok');
exit;
