<?php
declare(strict_types=1);

// هذا الملف كان يحتوي على نسخة قديمة متكررة من صفحة البحث.
// للإبقاء على التوافق مع الروابط القديمة، نقوم بتفويض التنفيذ إلى الكنترولر الحالي.

// ✅ إضافة علامة أن هذه صفحة بحث (للفوتر)
$GLOBALS['isSearchPage'] = true;

require_once __DIR__ . '/controllers/SearchController.php';