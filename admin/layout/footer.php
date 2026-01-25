<?php


// admin/layout/footer.php
// ملاحظة: Bootstrap JS يتم تحميله في header.php.
// هذا الفوتر يضيف فقط سكربتات الإدارة الأساسية + سكربتات الصفحات (إن وُجدت).

$__base = defined('GODYAR_BASE_URL') ? rtrim((string)GODYAR_BASE_URL, '/') : '';
$__admin = $__base . '/admin';

// Cache-busting version (fallback إلى الوقت الحالي)
$__v = defined('GODYAR_ADMIN_ASSET_VERSION')
    ? (string)GODYAR_ADMIN_ASSET_VERSION
    : (string)(file_exists(__DIR__ . '/../assets/js/admin-csp.js') ? filemtime(__DIR__ . '/../assets/js/admin-csp.js') : time());
?>

<script src="<?php echo htmlspecialchars($__admin . '/assets/js/admin-csp.js?v=' . urlencode($__v), ENT_QUOTES, 'UTF-8'); ?>"></script>

<?php
// Page specific scripts
if (!empty($pageScripts)) {
    echo $pageScripts;
}
?>

</body>
</html>
