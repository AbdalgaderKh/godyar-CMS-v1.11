<?php
declare(strict_types=1);

namespace Godyar\Services;

// Compatibility wrapper:
// بعض الإصدارات تستدعي Godyar\Services\NewsService من src/Services،
// بينما التطبيق الأساسي يستخدم النسخة الموجودة في includes/classes/Services.
// هذا الملف يضمن عدم وجود أخطاء Parse ويضمن تحميل النسخة الموثوقة.

$root = dirname(__DIR__, 2);
$target = $root . '/includes/classes/Services/NewsService.php';
if (is_file($target)) {
    require_once $target;
}
