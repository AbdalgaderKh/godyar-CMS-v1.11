<!doctype html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>حول السكربت — Godyar</title>
  <link rel="stylesheet" href="/assets/vendor/bootstrap/css/bootstrap.rtl.min.css">
  <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
<header class="border-bottom bg-white">
  <div class="container py-3 d-flex flex-wrap align-items-center justify-content-between gap-2">
    <a class="text-decoration-none fw-bold" href="/">Godyar</a>
    <nav class="d-flex flex-wrap gap-3">
      <a class="text-decoration-none" href="/">الرئيسية</a>
      <a class="text-decoration-none" href="/page/about/">حول</a>
      <a class="text-decoration-none" href="/page/privacy/">الخصوصية</a>
      <a class="text-decoration-none" href="/login">دخول</a>
      <a class="text-decoration-none" href="/register">تسجيل</a>
    </nav>
  </div>
</header>

<main class="container py-4">
  <div class="row justify-content-center">
    <div class="col-lg-9">
      <div class="card border-0 shadow-sm">
        <div class="card-body p-4">
          <h1 class="h4 mb-3">تقرير عن السكربت</h1>
          <p class="text-muted mb-4">هذه الصفحة تعرض نبذة تعريفية وملاحظات تشغيلية حول سكربت <strong>Godyar CMS</strong> وكيفية استخدامه بأفضل شكل.</p>

          <h2 class="h6">ما هو Godyar CMS؟</h2>
          <ul>
            <li>نظام إدارة محتوى للأخبار والمقالات مع لوحة تحكم لإدارة المحتوى.</li>
            <li>يدعم تعدد اللغات (AR / EN / FR) ومسارات صديقة لمحركات البحث.</li>
            <li>يوفر وحدات مثل التصنيفات، الوسوم، الوسائط، وإضافات قابلة للتفعيل.</li>
          </ul>

          <h2 class="h6 mt-4">التثبيت والمتطلبات</h2>
          <ul>
            <li>PHP حديث (يفضل 8.0+).</li>
            <li>MySQL/MariaDB مع صلاحيات إنشاء الجداول.</li>
            <li>تفعيل mod_rewrite (أو ما يعادله) لعمل الروابط اللطيفة.</li>
          </ul>

          <h2 class="h6 mt-4">ملاحظات الجودة والأمان</h2>
          <ul>
            <li>استخدم HTTPS دائماً.</li>
            <li>حدّث كلمات المرور وأعد توليد مفاتيح CSRF عند النقل أو الاستنساخ.</li>
            <li>فعّل رؤوس الأمان (Security Headers) على مستوى السيرفر/‏.htaccess.</li>
          </ul>

          <div class="alert alert-info mt-4 mb-0">
            إذا رغبت بإضافة صفحات أخرى (مثل شروط النشر، سياسة التعليقات، …) يمكن بناؤها بنفس هذا القالب لتأخذ هوية الموقع.
          </div>
        </div>
      </div>
    </div>
  </div>
</main>

<footer class="border-top bg-white">
  <div class="container py-3 d-flex flex-wrap justify-content-between gap-2">
    <div class="text-muted">© 2026 Godyar CMS</div>
    <div class="d-flex gap-3">
      <a class="text-decoration-none" href="/page/privacy/">الخصوصية</a>
      <a class="text-decoration-none" href="/page/about/">حول</a>
    </div>
  </div>
</footer>

<script src="/assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
</body>
</html>
