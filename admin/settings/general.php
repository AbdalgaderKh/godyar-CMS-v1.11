<?php
require_once __DIR__ . '/_settings_guard.php';
require_once __DIR__ . '/_settings_meta.php';
settings_apply_context();
require_once __DIR__ . '/../layout/app_start.php';

$notice = '';
$error  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (function_exists('verify_csrf') === true) { verify_csrf(); }

    try {
        
    // رفع شعار الموقع (اختياري)
    $logoUrl = settings_get('site_logo', settings_get('site.logo', ''));
    if (isset($_POST['remove_logo']) === true) {
        $logoUrl = '';
    }
    if (isset($_FILES['site_logo']) && is_array($_FILES['site_logo']) && (int)((!empty($_FILES['site_logo']['error']) ? $_FILES['site_logo']['error'] : UPLOAD_ERR_NO_FILE)) !== UPLOAD_ERR_NO_FILE) {
        if ((int)$_FILES['site_logo']['error'] === UPLOAD_ERR_OK) {
            $tmp = (string)($_FILES['site_logo']['tmp_name'] ?? '');
            $name = (string)($_FILES['site_logo']['name'] ?? 'logo');
            $size = (int)($_FILES['site_logo']['size'] ?? 0);
            if ($size > 2 * 1024 * 1024) {
                throw new RuntimeException('حجم الشعار أكبر من 2MB.');
            }
            $mime = '';
            if (function_exists('finfo_open') === true) {
                $fi = gdy_finfo_open(FILEINFO_MIME_TYPE);
                if ((empty($fi) === false)) {
                    $mime = (string)gdy_finfo_file($fi, $tmp);
                    gdy_finfo_close($fi);
                }
            }
            $allowed = ['image/png'=>'png','image/jpeg'=>'jpg','image/webp'=>'webp','image/gif'=>'gif','image/svg+xml'=>'svg'];
            if (isset($allowed[$mime]) === false) {
                throw new RuntimeException('صيغة الشعار غير مدعومة.');
            }
            $root = defined('ROOT_PATH') ? rtrim((string)ROOT_PATH, '/\\') : rtrim(dirname(__DIR__, 2), '/\\');
            $dir = $root . '/assets/uploads/site/';
            if (is_dir($dir) === false) { gdy_mkdir($dir, 0755, true); }
            $ext = $allowed[$mime];
            $fn = 'logo_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            $dest = $dir . $fn;
            if (!gdy_move_uploaded_file($tmp, $dest)) {
                throw new RuntimeException('تعذر حفظ الشعار على السيرفر.');
            }
            gdy_chmod($dest, 0644);
            $logoUrl = rtrim((string)base_url(), '/') . '/assets/uploads/site/' . $fn;
        } else {
            throw new RuntimeException('فشل رفع الشعار.');
        }
    }

    // رفع Favicon (اختياري)
    $faviconUrl = settings_get('site_favicon', settings_get('site.favicon', ''));
    if (isset($_POST['remove_favicon']) === true) {
        $faviconUrl = '';
    }
    if (isset($_FILES['site_favicon']) && is_array($_FILES['site_favicon']) && (int)((!empty($_FILES['site_favicon']['error']) ? $_FILES['site_favicon']['error'] : UPLOAD_ERR_NO_FILE)) !== UPLOAD_ERR_NO_FILE) {
        if ((int)$_FILES['site_favicon']['error'] === UPLOAD_ERR_OK) {
            $tmp = (string)($_FILES['site_favicon']['tmp_name'] ?? '');
            $name = (string)($_FILES['site_favicon']['name'] ?? 'favicon');
            $size = (int)($_FILES['site_favicon']['size'] ?? 0);
            if ($size > 1024 * 1024) {
                throw new RuntimeException('حجم الأيقونة أكبر من 1MB.');
            }
            $mime = '';
            if (function_exists('finfo_open')) {
                $fi = gdy_finfo_open(FILEINFO_MIME_TYPE);
                if ($fi) {
                    $mime = (string)gdy_finfo_file($fi, $tmp);
                    gdy_finfo_close($fi);
                }
            }
            $allowed = ['image/png'=>'png','image/x-icon'=>'ico','image/vnd.microsoft.icon'=>'ico','image/svg+xml'=>'svg'];
            if ((isset($allowed[$mime]) === false)) {
                throw new RuntimeException('صيغة الأيقونة غير مدعومة.');
            }
            $root = defined('ROOT_PATH') ? rtrim((string)ROOT_PATH, '/\\') : rtrim(dirname(__DIR__, 2), '/\\');
            $dir = $root . '/assets/uploads/site/';
            if (!is_dir($dir)) { gdy_mkdir($dir, 0755, true); }
            $ext = $allowed[$mime];
            $fn = 'favicon_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            $dest = $dir . $fn;
            if (!gdy_move_uploaded_file($tmp, $dest)) {
                throw new RuntimeException('تعذر حفظ الأيقونة على السيرفر.');
            }
            gdy_chmod($dest, 0644);
            $faviconUrl = rtrim((string)base_url(), '/') . '/assets/uploads/site/' . $fn;
        } else {
            throw new RuntimeException('فشل رفع الأيقونة.');
        }
    }

    $themeColor = trim((string)($_POST['theme_color'] ?? ''));
settings_save([
            'site.name'    => trim((string)($_POST['site_name'] ?? '')),
            'site.desc'    => trim((string)($_POST['site_desc'] ?? '')),
            'site.url'     => trim((string)($_POST['site_url'] ?? '')),
            'site.email'   => trim((string)($_POST['site_email'] ?? '')),
            'site.phone'   => trim((string)($_POST['site_phone'] ?? '')),
            'site.address' => trim((string)($_POST['site_address'] ?? '')),
            'site.logo'    => (string)$logoUrl,
            'site.favicon' => (string)$faviconUrl,
            'site.theme_color' => (string)$themeColor,

            'media.compress.enabled' => isset($_POST['media_compress_enabled']) ? 1 : 0,
            'media.compress.max_width' => (int)($_POST['media_compress_max_width'] ?? 1920),
            'media.compress.quality' => (int)($_POST['media_compress_quality'] ?? 82),
            'media.watermark.enabled' => isset($_POST['media_watermark_enabled']) ? 1 : 0,
            'media.watermark.opacity' => (int)($_POST['media_watermark_opacity'] ?? 35),
        ]);
        $notice = __('t_36112f9024', 'تم حفظ الإعدادات العامة بنجاح.');
    } catch (Exception $e) {
        $error = __('t_4fa410044f', 'حدث خطأ أثناء الحفظ.');
        error_log('[settings_general] ' . $e->getMessage());
    }
}

$site_name    = settings_get('site.name', '');
$site_desc    = settings_get('site.desc', '');
$site_url     = settings_get('site.url', '');
$site_email   = settings_get('site.email', '');
$site_phone   = settings_get('site.phone', '');
$site_address = settings_get('site.address', '');
$site_logo    = settings_get('site_logo', settings_get('site.logo', ''));
$site_favicon = settings_get('site_favicon', settings_get('site.favicon', ''));
$theme_color  = settings_get('site.theme_color', '#0ea5e9');
$media_compress_enabled = (int)settings_get('media.compress.enabled', 1);
$media_compress_max_width = (int)settings_get('media.compress.max_width', 1920);
$media_compress_quality = (int)settings_get('media.compress.quality', 82);
$media_watermark_enabled = (int)settings_get('media.watermark.enabled', 0);
$media_watermark_opacity = (int)settings_get('media.watermark.opacity', 35);
?>

<div class="row g-3">
    <div class="col-md-3">
      <?php include __DIR__ . '/_settings_nav.php'; ?>
    </div>

    <div class="col-md-9">
      <div class="card p-4">
<?php if ($notice): ?>
          <div class="alert alert-success"><?php echo h($notice); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
          <div class="alert alert-danger"><?php echo h($error); ?></div>
        <?php endif; ?>

        <form method="post" enctype="multipart/form-data">
          <?php if (function_exists('csrf_token')): ?>
            <input type="hidden" name="csrf_token" value="<?php echo h(csrf_token()); ?>">
          <?php endif; ?>

          <div class="mb-3">
            <label class="form-label" for="site_name"><?php echo h(__('t_2bd584d5c7', 'اسم الموقع')); ?></label>
            <input id="site_name" class="form-control" name="site_name" value="<?php echo h($site_name); ?>">
          </div>

          <div class="mb-3">
            <label class="form-label" for="site_desc"><?php echo h(__('t_81edd198f5', 'وصف مختصر')); ?></label>
            <textarea id="site_desc" class="form-control" name="site_desc" rows="3"><?php echo h($site_desc); ?></textarea>
          </div>

          <div class="mb-3">
            <label class="form-label" for="site_url"><?php echo h(__('t_cccf89f08f', 'رابط الموقع')); ?></label>
            <input id="site_url" class="form-control" name="site_url" value="<?php echo h($site_url); ?>">
          </div>

          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="form-label" for="site_logo">شعار الموقع</label>
              <?php if (!empty($site_logo)): ?>
                <div class="mb-2">
                  <img src="<?php echo h($site_logo); ?>" alt="Logo" style="max-height:64px;max-width:100%;background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:6px">
                </div>
                <div class="form-check mb-2">
                  <input class="form-check-input" type="checkbox" name="remove_logo" id="remove_logo" value="1">
                  <label class="form-check-label" for="remove_logo">إزالة الشعار</label>
                </div>
              <?php endif; ?>
              <input id="site_logo" class="form-control" type="file" name="site_logo" accept="image/*">
              <div class="form-text">PNG/JPG/WebP/GIF/SVG — الحد 2MB</div>
            </div>

            <div class="col-md-6 mb-3">
              <label class="form-label" for="site_favicon">أيقونة الموقع (Favicon)</label>
              <?php if (!empty($site_favicon)): ?>
                <div class="mb-2">
                  <img src="<?php echo h($site_favicon); ?>" alt="Favicon" style="height:32px;width:32px;background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:4px">
                </div>
                <div class="form-check mb-2">
                  <input class="form-check-input" type="checkbox" name="remove_favicon" id="remove_favicon" value="1">
                  <label class="form-check-label" for="remove_favicon">إزالة الأيقونة</label>
                </div>
              <?php endif; ?>
              <input id="site_favicon" class="form-control" type="file" name="site_favicon" accept="image/png,image/x-icon,image/svg+xml">
              <div class="form-text">PNG/ICO/SVG — الحد 1MB</div>
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label" for="theme_color">لون الثيم (PWA / المتصفح)</label>
            <input id="theme_color" class="form-control" type="color" name="theme_color" value="<?php echo h($theme_color ?: '#0ea5e9'); ?>" style="max-width:180px">
          </div>

          <hr>
          <h6 class="mt-3 mb-2">إعدادات الصور (رفع المحرر)</h6>

          <div class="form-check form-switch mb-2">
            <input class="form-check-input" type="checkbox" id="media_compress_enabled" name="media_compress_enabled" <?php echo $media_compress_enabled ? 'checked' : ''; ?>>
            <label class="form-check-label" for="media_compress_enabled">ضغط/تصغير الصور تلقائيًا عند الرفع من المحرر</label>
          </div>

          <div class="row g-2 mb-3">
            <div class="col-md-4">
              <label class="form-label" for="media_compress_max_width">أقصى عرض (px)</label>
              <input id="media_compress_max_width" class="form-control" type="number" name="media_compress_max_width" value="<?php echo (int)$media_compress_max_width; ?>" min="640" max="4096">
            </div>
            <div class="col-md-4">
              <label class="form-label" for="media_compress_quality">جودة JPEG/WebP (40-95)</label>
              <input id="media_compress_quality" class="form-control" type="number" name="media_compress_quality" value="<?php echo (int)$media_compress_quality; ?>" min="40" max="95">
            </div>
          </div>

          <div class="form-check form-switch mb-2">
            <input class="form-check-input" type="checkbox" id="media_watermark_enabled" name="media_watermark_enabled" <?php echo $media_watermark_enabled ? 'checked' : ''; ?>>
            <label class="form-check-label" for="media_watermark_enabled">إضافة علامة مائية (باستخدام شعار الموقع) عند رفع الصور من المحرر</label>
          </div>

          <div class="row g-2 mb-1">
            <div class="col-md-4">
              <label class="form-label" for="media_watermark_opacity">شفافية العلامة (10-90)</label>
              <input id="media_watermark_opacity" class="form-control" type="number" name="media_watermark_opacity" value="<?php echo (int)$media_watermark_opacity; ?>" min="10" max="90">
            </div>
            <div class="col-md-8 d-flex align-items-end">
              <div class="form-text">يتم استخدام شعار الموقع المرفوع أعلاه كعلامة مائية. يفضل أن يكون PNG بخلفية شفافة.</div>
            </div>
          </div>

          <div class="row mt-3">
            <div class="col-md-4 mb-3">
              <label class="form-label" for="site_email"><?php echo h(__('t_2a1ce89dca', 'بريد التواصل')); ?></label>
              <input id="site_email" class="form-control" type="email" name="site_email" value="<?php echo h($site_email); ?>">
            </div>
            <div class="col-md-4 mb-3">
              <label class="form-label" for="site_phone"><?php echo h(__('t_0947ad5747', 'رقم الهاتف')); ?></label>
              <input id="site_phone" class="form-control" name="site_phone" value="<?php echo h($site_phone); ?>">
            </div>
            <div class="col-md-4 mb-3">
              <label class="form-label" for="site_address"><?php echo h(__('t_6dc6588082', 'العنوان')); ?></label>
              <input id="site_address" class="form-control" name="site_address" value="<?php echo h($site_address); ?>">
            </div>
          </div>

          <button class="btn btn-primary"><?php echo h(__('t_871a087a1d', 'حفظ')); ?></button>
        </form>

      </div>
    </div>
  </div>

<?php require_once __DIR__ . '/../layout/app_end.php'; ?>
