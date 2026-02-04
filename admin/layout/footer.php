<?php


// admin/layout/footer.php
// ملاحظة: Bootstrap JS يتم تحميله في header.php.
// هذا الفوتر يضيف فقط سكربتات الإدارة الأساسية + سكربتات الصفحات (إن وُجدت).

$__base = defined('GODYAR_BASE_URL') ? rtrim((string)GODYAR_BASE_URL, '/') : '';
$__admin = $__base . '/admin';

// Cache-busting version (fallback إلى الوقت الحالي)
$__v = defined('GODYAR_ADMIN_ASSET_VERSION')
    ? (string)GODYAR_ADMIN_ASSET_VERSION
    : (string)(file_exists(__DIR__ . '/../../assets/admin/js/admin-csp.js') ? filemtime(__DIR__ . '/../../assets/admin/js/admin-csp.js') : time());

// CMS version badge (safe, optional)
try {
    $vfile = __DIR__ . '/../../includes/version.php';
    if (is_file($vfile)) {
        require_once $vfile;
    }
} catch (Throwable $e) {
    // ignore
}
?>

<footer class="gdy-admin-footer" aria-label="footer">
  <style>
    .gdy-admin-footer{
      border-top: 1px solid rgba(0,0,0,.08);
      padding: 10px 14px;
      margin-top: 18px;
      font-size: .85rem;
      color: rgba(0,0,0,.6);
      display:flex;
      justify-content: space-between;
      align-items:center;
      flex-wrap: wrap;
      gap: 8px;
    }
    .gdy-admin-badge{
      display:inline-flex;
      align-items:center;
      padding: .15rem .5rem;
      border-radius: 999px;
      border: 1px solid rgba(0,0,0,.10);
      background: rgba(0,0,0,.03);
      font-weight: 700;
    }
  </style>

  <div>© <?php echo (int)date('Y'); ?> <?php echo htmlspecialchars(defined('GODYAR_CMS_COPYRIGHT') ? (string)GODYAR_CMS_COPYRIGHT : 'Godyar CMS', ENT_QUOTES, 'UTF-8'); ?></div>
  <div class="gdy-admin-badge"><?php echo htmlspecialchars(function_exists('gdy_cms_badge') ? gdy_cms_badge() : 'Godyar CMS v1.11', ENT_QUOTES, 'UTF-8'); ?></div>
</footer>

<script src="<?php echo htmlspecialchars($__adminAssets . '/js/admin-csp.js?v=' . urlencode($__v), ENT_QUOTES, 'UTF-8'); ?>"></script>

<?php
// Page specific scripts
if (!empty($pageScripts)) {
    echo $pageScripts;
}
?>

</body>
</html>
