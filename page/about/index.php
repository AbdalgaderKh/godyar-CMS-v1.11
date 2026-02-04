<?php
declare(strict_types=1);

// صفحة ثابتة: تقرير عن السكربت
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../header.php';
?>

<main class="container" style="max-width: 980px; padding: 28px 12px;">
  <div class="mb-4">
    <h1 class="h3 mb-2">حول السكربت</h1>
    <p class="text-muted mb-0">تقرير مختصر عن Godyar CMS ومكوناته وهدفه.</p>
  </div>

  <div class="card border-0 shadow-sm">
    <div class="card-body p-4">

      <h2 class="h5">ما هو Godyar CMS؟</h2>
      <p>
        Godyar CMS سكربت إدارة محتوى خفيف يركز على نشر الأخبار والمقالات مع دعم تعدد اللغات ولوحة تحكم
        لإدارة المحتوى، التصنيفات، الوسوم، المستخدمين، والإعدادات الأساسية.
      </p>

      <hr class="my-4">

      <h2 class="h5">أهم الميزات</h2>
      <ul class="mb-0">
        <li>نظام أخبار/مقالات مع تصنيفات ووسوم.</li>
        <li>دعم لغات متعددة (مثل AR / EN / FR) حسب إعدادات الموقع.</li>
        <li>لوحة تحكم لإدارة المحتوى والمستخدمين والأدوار.</li>
        <li>مكتبة وسائط لرفع الصور والملفات.</li>
        <li>صفحات ثابتة مثل (حول / سياسة الخصوصية) ضمن هوية الموقع.</li>
        <li>إعدادات عامة للموقع (الاسم، الشعار، روابط التواصل، …).</li>
      </ul>

      <hr class="my-4">

      <h2 class="h5">ملاحظات تشغيل مهمة</h2>
      <ul>
        <li>تأكد من اكتمال الجداول في قاعدة البيانات بعد التثبيت.</li>
        <li>اضبط صلاحيات المجلدات القابلة للكتابة (مثل: uploads / cache إن وجدت).</li>
        <li>فعّل HTTPS وطبّق ترويسات الأمان عبر إعدادات الخادم / .htaccess.</li>
      </ul>

      <div class="alert alert-info mb-0" role="alert">
        هذه الصفحة ثابتة (لا تعتمد على قاعدة البيانات) لتقليل الأعطال وتعزيز الاستقرار.
      </div>

    </div>
  </div>
</main>

<?php require_once __DIR__ . '/../../footer.php'; ?>
