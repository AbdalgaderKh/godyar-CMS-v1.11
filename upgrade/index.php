<?php
declare(strict_types=1);

/**
 * Godyar CMS - Upgrade Hotfix (v10.1)
 * - Remove dependency on Godyar helper functions (gdy_*)
 * - Backup DB.php safely
 * - Apply patched DB.php
 * - Optionally remove dev/diagnostic files
 * - Create upgrade.lock
 *
 * Usage:
 * 1) Upload /upgrade folder into your site root.
 * 2) Visit: https://YOUR-DOMAIN/upgrade/
 * 3) Click "Apply Upgrade"
 * 4) Delete the /upgrade folder afterwards.
 */

$root = realpath(__DIR__ . '/..');
if ($root === false) {
    http_response_code(500);
    echo "Root not found";
    exit;
}

// Refuse to run if already completed
$lockFile = $root . '/upgrade.lock';
if (is_file($lockFile) === true) {
    http_response_code(403);
    echo "<h2>✅ تم تنفيذ الترقية مسبقاً</h2><p>إذا تحتاج إعادة، احذف ملف <code>upgrade.lock</code> (بحذر) ثم أعد المحاولة.</p>";
    exit;
}

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

/**
 * Ensure directory exists (no gdy_mkdir dependency)
 */
function ensure_dir(string $dir, int $mode = 0755): bool
{
    if (is_dir($dir)) return true;
    return @mkdir($dir, $mode, true);
}

/**
 * Safe copy (no gdy_copy dependency)
 */
function safe_copy(string $src, string $dst): bool
{
    if (!is_file($src)) return false;
    $dir = dirname($dst);
    if (!ensure_dir($dir)) return false;
    return @copy($src, $dst);
}

/**
 * Safe unlink (no gdy_unlink dependency)
 */
function safe_unlink(string $path): bool
{
    if (!is_file($path)) return false;
    return @unlink($path);
}

/**
 * Safe file_put_contents (no gdy_file_put_contents dependency)
 */
function safe_write(string $path, string $data, int $flags = 0): bool
{
    $dir = dirname($path);
    if (!ensure_dir($dir)) return false;
    return (file_put_contents($path, $data, $flags) !== false);
}

$action = $_POST['action'] ?? '';

$checks = [];
$checks['php_version'] = version_compare(PHP_VERSION, '7.4.0', '>=');
$checks['pdo_mysql']   = extension_loaded('pdo_mysql');
$checks['env_exists']  = is_file($root . '/.env') || is_file($root . '/config/.env');
$checks['target_db']   = is_file($root . '/includes/classes/DB.php');
$checks['patch_db']    = is_file(__DIR__ . '/patches/includes/classes/DB.php');

$warnings = [];
if ($checks['env_exists'] === false)  $warnings[] = "ملف .env غير موجود (المثبت سيُنشئه أو أنشئه يدويًا).";
if ($checks['pdo_mysql'] === false)   $warnings[] = "امتداد pdo_mysql غير مُفعّل في PHP.";
if ($checks['target_db'] === false)   $warnings[] = "الملف الهدف includes/classes/DB.php غير موجود.";
if ($checks['patch_db'] === false)    $warnings[] = "ملف الترقيعة غير موجود داخل upgrade/patches.";

$canApply = $checks['php_version'] && $checks['pdo_mysql'] && $checks['target_db'] && $checks['patch_db'];

$removed  = [];
$backedUp = null;
$written  = false;

if ($action === 'apply' && $canApply) {

    // 1) Backup existing DB.php
    $backupDir = $root . '/storage/backups';
    ensure_dir($backupDir, 0755);

    $ts = date('Ymd_His');
    $src = $root . '/includes/classes/DB.php';
    $backupFile = $backupDir . '/DB.php.bak_' . $ts;

    if (safe_copy($src, $backupFile)) {
        $backedUp = $backupFile;
    }

    // 2) Apply patched DB.php
    $patch = __DIR__ . '/patches/includes/classes/DB.php';
    $data  = @file_get_contents($patch);

    if ($data !== false) {
        $written = (file_put_contents($src, $data, LOCK_EX) !== false);
    }

    // 3) Optional: remove dangerous / dev files (if present)
    $toRemove = [
        $root . '/admin_set_admin.php',
        $root . '/health.php',
        $root . '/opcache_reset.php',
        $root . '/check_imagick.php',
        $root . '/db_patch.php',
    ];

    foreach ($toRemove as $f) {
        if (is_file($f) === true) {
            if (safe_unlink($f)) $removed[] = basename($f);
        }
    }

    // 4) Create lock file
    if ($written) {
        safe_write($lockFile, "upgraded_at=" . date('c') . "\n", LOCK_EX);
    }
}

?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>ترقية Godyar CMS - Hotfix v10.1</title>
  <style>
    body{font-family:system-ui,-apple-system,"Segoe UI",Tahoma,Arial;max-width:900px;margin:30px auto;padding:0 16px;line-height:1.7}
    .card{border:1px solid #e5e7eb;border-radius:12px;padding:16px;margin:14px 0}
    .ok{color:#047857}
    .bad{color:#b91c1c}
    code{background:#f3f4f6;padding:2px 6px;border-radius:6px}
    button{padding:10px 14px;border-radius:10px;border:0;cursor:pointer}
    .btn{background:#111827;color:#fff}
    .btn:disabled{opacity:.5;cursor:not-allowed}
    .muted{color:#6b7280}
    ul{margin:0;padding-right:18px}
  </style>
</head>
<body>
  <h1>ترقية Godyar CMS (Hotfix v10.1)</h1>
  <p class="muted">هذه الترقية تعالج مشكلة الاتصال بقاعدة البيانات بإضافة دعم <code>DB_PORT</code> و <code>DB_DSN</code> داخل <code>includes/classes/DB.php</code>.</p>

  <div class="card">
    <h3>نتيجة الفحص</h3>
    <ul>
      <li>PHP 7.4+: <?php echo ($checks['php_version']) ? '<span class="ok">✅ OK</span>' : '<span class="bad">❌ غير متوافق</span>'; ?></li>
      <li>pdo_mysql: <?php echo ($checks['pdo_mysql']) ? '<span class="ok">✅ OK</span>' : '<span class="bad">❌ غير مُفعّل</span>'; ?></li>
      <li>وجود .env: <?php echo ($checks['env_exists']) ? '<span class="ok">✅ موجود</span>' : '<span class="bad">⚠️ غير موجود</span>'; ?></li>
      <li>وجود الملف الهدف DB.php: <?php echo ($checks['target_db']) ? '<span class="ok">✅ موجود</span>' : '<span class="bad">❌ مفقود</span>'; ?></li>
      <li>وجود ملف الترقيعة: <?php echo ($checks['patch_db']) ? '<span class="ok">✅ موجود</span>' : '<span class="bad">❌ مفقود</span>'; ?></li>
    </ul>

    <?php if (!empty($warnings)): ?>
      <p class="bad"><strong>ملاحظات:</strong></p>
      <ul>
        <?php foreach ($warnings as $w): ?><li class="bad"><?php echo h($w); ?></li><?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </div>

  <?php if ($action === 'apply'): ?>
    <div class="card">
      <h3>نتيجة التنفيذ</h3>
      <?php if ($written): ?>
        <p class="ok">✅ تم تحديث <code>includes/classes/DB.php</code> بنجاح.</p>
      <?php else: ?>
        <p class="bad">❌ لم يتم تحديث الملف (تحقق من الصلاحيات أو مسار الملف).</p>
      <?php endif; ?>

      <?php if (!empty($backedUp)): ?>
        <p class="ok">✅ نسخة احتياطية: <code><?php echo h(str_replace($root, '', $backedUp)); ?></code></p>
      <?php endif; ?>

      <?php if (!empty($removed)): ?>
        <p class="ok">✅ تم حذف ملفات تطوير/تشخيص: <code><?php echo h(implode(', ', $removed)); ?></code></p>
      <?php endif; ?>

      <?php if ($written): ?>
        <p><strong>الخطوة التالية:</strong></p>
        <ol>
          <li>جرّب فتح الصفحة الرئيسية أو <code>/install/</code> حسب حالتك.</li>
          <li>بعد التأكد أن كل شيء يعمل: احذف مجلد <code>/upgrade</code> من السيرفر فورًا.</li>
        </ol>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <div class="card">
    <h3>تنفيذ الترقية</h3>
    <form method="post">
      <input type="hidden" name="action" value="apply">
      <button class="btn" <?php echo ($canApply) ? '' : 'disabled'; ?>>تطبيق الترقية الآن</button>
    </form>
    <p class="muted">مهم: احذف مجلد <code>/upgrade</code> فور الانتهاء.</p>
  </div>

</body>
</html>
