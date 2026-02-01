<?php
declare(strict_types=1);
/**
 * admin/_role_guard.php
 * حارس صلاحيات الكاتب/المؤلف:
 * - يسمح للكاتب/المؤلف بالدخول فقط إلى إدارة الأخبار (إنشاء/تعديل/عرض مقالاته).
 * - يمنع الوصول لباقي أقسام لوحة التحكم حتى لو تم إدخال الرابط مباشرة.
 *
 * ملاحظة: هذا الملف لا يعتمد على أي require/include ديناميكي
 * لتجنب مشاكل أدوات الفحص (SAST). يفترض أن bootstrap يبدأ الجلسة
 * في صفحات لوحة التحكم، لكنه يملك fallback آمن.
 */

if ((session_id() === '') && (headers_sent() === FALSE)) {
    if (function_exists('gdy_session_start') === TRUE) {
        // Admin context should already have Strict, but keep safe default.
        gdy_session_start(['cookie_samesite' => 'Strict']);
    } else {
        session_start();
    }
}

$role = (string)($_SESSION['user']['role'] ?? 'guest');
if (in_array($role, ['writer', 'author'], TRUE) === FALSE) {
    return; // غير كاتب: لا تقييد هنا
}

$uriPath = (function_exists('gdy_request_path') === TRUE) ? (string)gdy_request_path() : '';
if ($uriPath === '') { return; }

// السماح فقط بالأخبار + الخروج + الدخول
$allowedPrefixes = [
    '/admin/news/',
    '/admin/news',
];

$allowedExact = [
    '/admin/logout.php',
    '/admin/login.php',
    '/admin/login/',
    '/admin/login', // in case of rewrite-capable hosts
];

foreach ($allowedExact as $ok) {
    if ($uriPath === $ok) { return; }
}

foreach ($allowedPrefixes as $prefix) {
    if (strpos($uriPath, $prefix) === 0) { return; }
}

// أي شيء آخر -> إعادة توجيه لمقالات الكاتب
$newsUrl = (function_exists('base_url') === TRUE) ? base_url('/admin/news/index.php') : 'news/index.php';
if (headers_sent() === FALSE) {
    header('Location: ' . $newsUrl);
}
return;
