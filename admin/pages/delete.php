<?php
declare(strict_types=1);

require_once __DIR__ . '/../_admin_guard.php';
require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';



// 1) معلومات الصفحة (ليست مهمة هنا لكن للاتساق)
$currentPage = 'pages';
$pageTitle   = __('t_b10973702c', 'حذف صفحة');

// 2) التحقق من تسجيل الدخول
try {
    if (class_exists(Auth::class) && method_exists(Auth::class, 'isLoggedIn')) {
        if (!Auth::isLoggedIn()) {
            header('Location: ' . (function_exists('gdy_base_url') ? rtrim((string)gdy_base_url(), '/') : '') . '/admin/login');
            exit;
        }
    } else {
        if (empty($_SESSION['user']) || (($_SESSION['user']['role'] ?? '') === 'guest')) {
            header('Location: ' . (function_exists('gdy_base_url') ? rtrim((string)gdy_base_url(), '/') : '') . '/admin/login');
            exit;
        }
    }
} catch (Exception $e) {
    error_log('[Godyar Pages Delete] Auth check error: ' . $e->getMessage());
    if (empty($_SESSION['user']) || (($_SESSION['user']['role'] ?? '') === 'guest')) {
        header('Location: ' . (function_exists('gdy_base_url') ? rtrim((string)gdy_base_url(), '/') : '') . '/admin/login');
        exit;
    }
}

// 3) تهيئة PDO
$pdo = gdy_pdo_safe();
if (($pdo instanceof PDO) === false) {
    header('Location: index.php?dberror=1');
    exit;
}

// 4) قراءة المعرف وحذف
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id || $id <= 0) {
    header('Location: index.php');
    exit;
}

try {
    $stmt = $pdo->prepare("DELETE FROM pages WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $id]);
    header('Location: index.php?deleted=1');
    exit;
} catch (Exception $e) {
    error_log('[Godyar Pages Delete] Delete error: ' . $e->getMessage());
    header('Location: index.php?deleted=0');
    exit;
}