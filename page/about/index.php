<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';

if (!function_exists('h')) {
    function h($v): string
    {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}

// Page meta (used by the shared frontend header)
$pageTitle       = "حول السكربت";
$metaTitle       = $pageTitle;
$metaDescription = "تقرير مختصر عن سكربت Godyar CMS ومكوناته وميزاته.";

// Optional helpers used by some templates
$currentLang = $currentLang ?? 'ar';
$pageDir = 'rtl';

require_once __DIR__ . '/../../frontend/views/partials/header.php';
?>
<main class="gdy-page" style="padding:18px 0 42px;">
  <div class="container">
    <?php
      $html = <<<GDYHTML
<div class="container">
        <div class="header">
            <h1>عن السكربت — <span class="cms-name">Godyar CMS</span></h1>
        </div>
        
        <div class="content-block">
            <p class="intro"><span class="cms-name">Godyar CMS</span> — نظام إدارة محتوى إخباري متعدد اللغات</p>
            
            <p>هذا السكربت مخصص لإدارة مواقع الأخبار والمقالات مع دعم تعدد اللغات (مثل العربية/الفرنسية/الإنجليزية)، ولوحة تحكم لإدارة المحتوى، والوسائط، والتصنيفات، والوسوم، والمستخدمين والصلاحيات.</p>
        </div>
        
        <div class="content-block">
            <h2>أهم المزايا</h2>
            
            <ul class="feature-list">
                <li><strong>إدارة الأخبار والمقالات:</strong> إنشاء/تعديل/حذف، مسودات، ومراجعة المحتوى.</li>
                <li><strong>تصنيفات ووسوم:</strong> تنظيم المحتوى لسهولة التصفح والبحث.</li>
                <li><strong>مكتبة وسائط:</strong> رفع الصور والملفات وإدارتها.</li>
                <li><strong>كتّاب الرأي والفريق:</strong> صفحات تعريفية قابلة للإدارة من لوحة التحكم.</li>
                <li><strong>إعدادات الموقع:</strong> التحكم في بيانات الموقع العامة من مكان واحد.</li>
                <li><strong>دعم لغات متعددة:</strong> إمكانية تقديم المحتوى بعدة لغات حسب إعدادات الموقع.</li>
            </ul>
        </div>
        
        <div class="content-block">
            <h2>ملاحظات تقنية</h2>
            
            <div class="highlight">
                <p>يعتمد السكربت على قاعدة بيانات (MySQL أو PostgreSQL حسب النسخة/الإعداد).</p>
                <p>يوصى باستخدام PHP 8.1+ وقاعدة بيانات MySQL 5.7+/8.0+.</p>
                <p>يتم تطبيق تحسينات أمنية بشكل دوري، مع الاهتمام بتحديثات الحماية والأداء.</p>
            </div>
        </div>
        
        <div class="content-block">
            <h2>الدعم والتطوير</h2>
            
            <p>يتم تطوير السكربت بهدف توفير منصة مستقرة وسهلة الاستخدام. في حال واجهت مشكلة أو رغبت في طلب ميزة جديدة يمكنك التواصل عبر صفحة "اتصل بنا" أو بريد الدعم (إن كان مضافًا داخل إعدادات الموقع).</p>
        </div>
        
        <div class="footer">
            <p>Godyar CMS - نظام إدارة محتوى متعدد اللغات</p>
        </div>
    </div>
GDYHTML;
      echo $html;
    ?>
  </div>
</main>
<?php require_once __DIR__ . '/../../frontend/views/partials/footer.php'; ?>
