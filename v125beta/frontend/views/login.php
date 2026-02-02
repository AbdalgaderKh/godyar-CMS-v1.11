<?php
declare(strict_types=1);

// Variables expected:
// $login_error, $login_identifier, $login_csrf, $login_next, $login_wait
if (!function_exists('h')) {
    function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
}

$siteName = $siteName ?? ($GLOBALS['site_settings']['site_name'] ?? 'Godyar');
$baseUrl  = function_exists('base_url') ? rtrim((string)base_url(), '/') : '';

?><!doctype html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo h('تسجيل الدخول'); ?> — <?php echo h($siteName); ?></title>

  <!-- Core CSS (existing assets) -->
  <link rel="stylesheet" href="<?php echo h(($baseUrl ?: '')); ?>/assets/css/style.css">
  <link rel="stylesheet" href="<?php echo h(($baseUrl ?: '')); ?>/assets/css/front.css">
  <link rel="stylesheet" href="<?php echo h(($baseUrl ?: '')); ?>/assets/css/ui-enhancements.css">
  <link rel="stylesheet" href="<?php echo h(($baseUrl ?: '')); ?>/assets/css/responsive.css">

  <style>
    :root { --gdy-radius: 14px; }
    body{ background: #0b1220; min-height: 100vh; display:flex; align-items:center; justify-content:center; padding:24px; }
    .card-login{ width:100%; max-width:520px; background:#0f172a; border:1px solid rgba(255,255,255,.08); border-radius:var(--gdy-radius); box-shadow: 0 20px 60px rgba(0,0,0,.45); overflow:hidden; }
    .card-head{ padding:22px 22px 14px; border-bottom:1px solid rgba(255,255,255,.08); }
    .card-head h1{ margin:0; font-size:22px; color:#fff; }
    .card-head p{ margin:8px 0 0; color: rgba(255,255,255,.7); font-size:14px; }
    .card-body{ padding:22px; }
    .alert{ border-radius:12px; padding:12px 12px; margin-bottom:14px; font-size:14px; }
    .alert-danger{ background: rgba(220,38,38,.12); border:1px solid rgba(220,38,38,.35); color: rgba(255,255,255,.92); }
    .alert-info{ background: rgba(59,130,246,.12); border:1px solid rgba(59,130,246,.35); color: rgba(255,255,255,.92); }
    label{ color: rgba(255,255,255,.86); font-size:14px; margin-bottom:6px; display:block; }
    .field{ position:relative; }
    .input{ width:100%; padding:12px 12px; border-radius:12px; border:1px solid rgba(255,255,255,.12); background: rgba(2,6,23,.55); color:#fff; outline:none; }
    .input:focus{ border-color: rgba(59,130,246,.6); box-shadow: 0 0 0 3px rgba(59,130,246,.2); }
    .row{ display:grid; gap:12px; }
    .row-2{ display:grid; grid-template-columns: 1fr auto; gap:12px; align-items:center; }
    .btn{ display:inline-flex; align-items:center; justify-content:center; width:100%; padding:12px 12px; border-radius:12px; border:1px solid rgba(255,255,255,.12); background: rgba(59,130,246,.95); color:#fff; font-weight:700; cursor:pointer; }
    .btn:hover{ filter: brightness(1.03); }
    .muted{ color: rgba(255,255,255,.7); font-size:13px; }
    .actions{ display:flex; justify-content:space-between; align-items:center; gap:12px; margin-top:12px; }
    a{ color: rgba(147,197,253,.95); text-decoration:none; }
    a:hover{ text-decoration:underline; }
    .pw-toggle{ border:1px solid rgba(255,255,255,.14); background: rgba(2,6,23,.35); color:#fff; border-radius:12px; padding:10px 12px; cursor:pointer; }
    .caps{ display:none; margin-top:8px; }
    @media (max-width:520px){ body{ padding:16px; } .card-body{ padding:18px; } }
  </style>
</head>
<body>
  <section class="card-login" aria-label="Login">
    <header class="card-head">
      <h1>تسجيل الدخول</h1>
      <p>أدخل البريد الإلكتروني أو اسم المستخدم وكلمة المرور للوصول إلى حسابك.</p>
    </header>

    <div class="card-body">
      <?php if (!empty($login_wait)) : ?>
        <div class="alert alert-info">
          تم تقييد المحاولات مؤقتاً. الرجاء الانتظار <?php echo (int)$login_wait; ?> ثانية ثم المحاولة مرة أخرى.
        </div>
      <?php endif; ?>

      <?php if (!empty($login_error)) : ?>
        <div class="alert alert-danger" role="alert">
          <?php echo h($login_error); ?>
        </div>
      <?php endif; ?>

      <form method="post" class="row" autocomplete="on" novalidate>
        <input type="hidden" name="csrf_token" value="<?php echo h((string)$login_csrf); ?>">
        <input type="hidden" name="next" value="<?php echo h((string)$login_next); ?>">

        <div>
          <label for="login">البريد الإلكتروني أو اسم المستخدم</label>
          <div class="field">
            <input id="login" name="login" class="input" value="<?php echo h((string)$login_identifier); ?>" required autocomplete="username" inputmode="email">
          </div>
        </div>

        <div>
          <label for="password">كلمة المرور</label>
          <div class="row-2">
            <input id="password" type="password" name="password" class="input" required autocomplete="current-password">
            <button type="button" class="pw-toggle" id="pwToggle" aria-label="إظهار أو إخفاء كلمة المرور">إظهار</button>
          </div>
          <div class="alert alert-info caps" id="capsWarn">ملاحظة: زر الأحرف الكبيرة (CapsLock) مفعل.</div>
        </div>

        <div class="actions">
          <label class="muted" style="display:flex;gap:8px;align-items:center;">
            <input type="checkbox" name="remember" value="1"> تذكرني
          </label>
          <a class="muted" href="<?php echo h(($baseUrl ?: '')); ?>/register">إنشاء حساب</a>
        </div>

        <button class="btn" type="submit">دخول</button>

        <div class="muted" style="margin-top:8px;">
          بالمتابعة أنت توافق على سياسات الموقع. إذا واجهت مشكلة في الدخول، تواصل مع الدعم.
        </div>
      </form>
    </div>
  </section>

  <script>
    (function(){
      var pw = document.getElementById('password');
      var toggle = document.getElementById('pwToggle');
      var caps = document.getElementById('capsWarn');

      if (toggle && pw) {
        toggle.addEventListener('click', function(){
          var isPass = pw.getAttribute('type') === 'password';
          pw.setAttribute('type', isPass ? 'text' : 'password');
          toggle.textContent = isPass ? 'إخفاء' : 'إظهار';
        });
      }

      function capsCheck(e){
        if (!caps) return;
        var on = e.getModifierState && e.getModifierState('CapsLock');
        caps.style.display = on ? 'block' : 'none';
      }

      if (pw) {
        pw.addEventListener('keyup', capsCheck);
        pw.addEventListener('keydown', capsCheck);
      }
    })();
  </script>
</body>
</html>
