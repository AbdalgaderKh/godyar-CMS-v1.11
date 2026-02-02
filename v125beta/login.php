<?php
declare(strict_types=1);

// مدخل صفحة تسجيل الدخول: نفوض التنفيذ للكنترولر الأصلي
if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', __DIR__);
}

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/frontend/controllers/Auth/LoginController.php';

return;
