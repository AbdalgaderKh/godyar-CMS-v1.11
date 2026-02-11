<?php
// Admin footer (no additional file includes here)
// If site_setting() exists (loaded by bootstrap), we can show the site logo.
$__siteLogo = '';
if (function_exists('site_setting')) {
  // site_setting() signature changed in newer builds to: site_setting(PDO $pdo, string $key, mixed $default = null)
  // To avoid fatal errors across versions, detect parameter count and call appropriately.
  $__pc = 0;
  try {
    $__rf = new ReflectionFunction('site_setting');
    $__pc = $__rf->getNumberOfParameters();
  } catch (Exception $__e) {
    $__pc = 0;
  }

  if ($__pc >= 2) {
    // New signature: needs PDO as first arg
    global $pdo;
    if (isset($pdo) && ($pdo instanceof PDO)) {
      $__siteLogo = (string)site_setting($pdo, 'site_logo', '');
      if ($__siteLogo === '') { $__siteLogo = (string)site_setting($pdo, 'site.logo', ''); } // legacy fallback
    }
  } else {
    // Old signature: site_setting(string $key)
    $__siteLogo = (string)site_setting('site_logo');
    if ($__siteLogo === '') { $__siteLogo = (string)site_setting('site.logo'); } // legacy fallback
  }
}
?>
<footer class="gdy-admin-footer" aria-label="footer">
  <style>
    .gdy-admin-footer{
      border-top: 1px solid rgba(0,0,0,.08);
      padding: 12px 14px;
      margin-top: 18px;
      font-size: .85rem;
      color: rgba(0,0,0,.62);
      display:flex;
      justify-content: space-between;
      align-items:center;
      flex-wrap: wrap;
      gap: 10px;
      background: linear-gradient(180deg, rgba(2,6,23,0.02), rgba(2,6,23,0.00));
    }
    .gdy-admin-footer__left{display:flex;align-items:center;gap:10px;min-width:220px;}
    .gdy-admin-footer__logo{
      width:36px;height:36px;border-radius:14px;
      display:inline-flex;align-items:center;justify-content:center;
      background: rgba(0,0,0,.04);
      border: 1px solid rgba(0,0,0,.10);
      overflow:hidden;
      position: relative;
    }
    /* "engraved" / embossed effect */
    .gdy-admin-footer__logo img{
      width:100%;height:100%;object-fit:cover;
      filter:
        drop-shadow(0 1px 0 rgba(255,255,255,.55))
        drop-shadow(0 -1px 0 rgba(0,0,0,.18))
        drop-shadow(0 8px 16px rgba(2,6,23,.18));
      transform: translateZ(0);
    }
    .gdy-admin-footer__logo::after{
      content:"";
      position:absolute;inset:0;
      background: radial-gradient(circle at top left, rgba(255,255,255,.55), rgba(255,255,255,0) 55%);
      mix-blend-mode: overlay;
      pointer-events:none;
    }
    .gdy-admin-footer__brand{font-weight:800;color:rgba(0,0,0,.78);}
    .gdy-admin-footer__muted{color:rgba(0,0,0,.55);font-size:.82rem;}
    .gdy-admin-badge{
      display:inline-flex;
      align-items:center;
      padding: .18rem .55rem;
      border-radius: 999px;
      border: 1px solid rgba(0,0,0,.10);
      background: rgba(0,0,0,.03);
      font-weight: 800;
      letter-spacing:.2px;
    }
  </style>

  <div class="gdy-admin-footer__left">
    <span class="gdy-admin-footer__logo" aria-hidden="true">
      <?php if ($__siteLogo !== ''): ?>
        <img src="<?php echo h($__siteLogo); ?>" alt="">
      <?php else: ?>
        <span style="font-weight:900;font-size:.95rem;opacity:.7;">G</span>
      <?php endif; ?>
    </span>
    <div style="display:flex;flex-direction:column;line-height:1.1;">
      <span class="gdy-admin-footer__brand">Godyar CMS</span>
      <span class="gdy-admin-footer__muted">© <?php echo date('Y'); ?> جميع الحقوق محفوظة</span>
    </div>
  </div>

  <div class="gdy-admin-badge">Godyar CMS <?php echo defined('GDY_VERSION') ? h((string)GDY_VERSION) : 'v1.11'; ?></div>
</footer>
