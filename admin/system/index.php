<?php
declare(strict_types=1);

require_once __DIR__ . '/../_admin_guard.php';

use Godyar\Auth;
Auth::requireLogin();
Auth::requirePermission('system.view');

?><!doctype html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="utf-8">
  <title>System</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body style="font-family: Arial, sans-serif; padding: 16px;">
  <h1>System</h1>
  <p>هذه الصفحة تم تبسيطها لتفادي أخطاء Syntax في الإصدارات السابقة.</p>
  <ul>
    <li><a href="/admin/index.php">لوحة التحكم</a></li>
    <li><a href="/admin/settings/index.php">الإعدادات</a></li>
    <li><a href="/admin/logs/index.php">السجلات</a></li>
  </ul>
</body>
</html>
