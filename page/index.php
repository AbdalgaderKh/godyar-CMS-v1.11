<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    gdy_session_start();
}

// -----------------------------
// Helpers
// -----------------------------
if (!function_exists('h')) {
    function h($v): string {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}

/** Safe get of current request path relative to /page */
function gdy_page_slug(): string {
    $uri  = (string)($_SERVER['REQUEST_URI'] ?? '');
    $path = (string)(parse_url($uri, PHP_URL_PATH) ?? '');
    $path = trim($path);

    // Accept: /page/about OR /page/about/
    $path = rtrim($path, '/');
    $path = preg_replace('~^/page~', '', $path);
    $slug = trim((string)$path, '/');

    // fallback: /page/index.php?slug=about
    if ($slug === '') {
        $slug = (string)($_GET['slug'] ?? '');
    }

    $slug = strtolower(trim($slug));
    // whitelist slug characters
    $slug = preg_replace('~[^a-z0-9_-]~', '', $slug);
    return $slug !== '' ? $slug : 'about';
}

/** Load site settings (best effort) into $GLOBALS['site_settings'] */
function gdy_load_site_settings(PDO $pdo): void {
    try {
        $stmt = $pdo->query("SELECT `key`, `value` FROM site_settings");
        $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        $out  = [];
        foreach ($rows as $r) {
            $k = (string)($r['key'] ?? '');
            if ($k === '') continue;
            $out[$k] = (string)($r['value'] ?? '');
        }
        $GLOBALS['site_settings'] = $out;
    } catch (Throwable $e) {
        // ignore
    }
}

/** Default content for about/privacy */
function gdy_default_page(string $slug): array {
    if ($slug === 'privacy') {
        return [
            'title' => 'شروط الاستخدام والخصوصية',
            'html'  => <<<HTML
<h1 class="h3 mb-3">شروط الاستخدام والخصوصية</h1>
<p class="text-muted">هذه الصفحة تُعرّف شروط استخدام سكربت <strong>Godyar CMS</strong> وسياسة الخصوصية الخاصة بالموقع.</p>

<h2 class="h5 mt-4">1) الاستخدام</h2>
<ul>
  <li>يُسمح باستخدام السكربت لإدارة المحتوى ونشر الأخبار ضمن نطاق ترخيصك/اتفاقك.</li>
  <li>تتحمل مسؤولية المحتوى المنشور والالتزام بالأنظمة واللوائح المحلية.</li>
</ul>

<h2 class="h5 mt-4">2) البيانات والخصوصية</h2>
<ul>
  <li>قد نجمع بيانات تشغيلية (مثل سجلات الأخطاء) لتحسين الأداء ومعالجة الأعطال.</li>
  <li>بيانات الحساب (مثل البريد وكلمة المرور المشفرة) تُخزَّن في قاعدة البيانات.</li>
  <li>ننصح بتفعيل HTTPS وتطبيق رؤوس الأمان (HSTS وغيرها) وتحديثات الأمان بشكل دوري.</li>
</ul>

<h2 class="h5 mt-4">3) ملفات تعريف الارتباط</h2>
<p>قد تُستخدم ملفات تعريف الارتباط لإدارة الجلسات وتسجيل الدخول وتحسين التجربة.</p>

<h2 class="h5 mt-4">4) إخلاء المسؤولية</h2>
<p>يُقدَّم السكربت كما هو. ننصح دائمًا بأخذ نسخ احتياطية منتظمة، ومراجعة إعدادات الأمان، وتحديث المكونات.</p>
HTML,
        ];
    }

    // about (default)
    return [
        'title' => 'حول السكربت',
        'html'  => <<<HTML
<h1 class="h3 mb-3">حول السكربت</h1>
<p class="text-muted">صفحة تعريفية تُقدم تقريرًا مختصرًا عن سكربت <strong>Godyar CMS</strong>، وأبرز المكونات والخصائص.</p>

<div class="row g-3">
  <div class="col-lg-6">
    <div class="p-3 border rounded-3 h-100">
      <h2 class="h6">المزايا الأساسية</h2>
      <ul class="mb-0">
        <li>إدارة أخبار/مقالات متعددة اللغات.</li>
        <li>لوحة تحكم حديثة مع وحدات (تصنيفات، وسوم، وسائط، كتّاب رأي...).</li>
        <li>نظام صلاحيات وأدوار قابل للتوسعة.</li>
        <li>أدوات مساعدة: RSS، مراجعة أخبار، أسئلة القرّاء، وإضافات.</li>
      </ul>
    </div>
  </div>
  <div class="col-lg-6">
    <div class="p-3 border rounded-3 h-100">
      <h2 class="h6">الأمان والجودة</h2>
      <ul class="mb-0">
        <li>حماية CSRF للنماذج.</li>
        <li>تصفية وإسكات XSS عبر دوال هروب (escape) في المخرجات.</li>
        <li>توافق أفضل مع إعدادات الخادم وملف التثبيت.</li>
        <li>هيكل ملفات مُنظّم لتسهيل الصيانة.</li>
      </ul>
    </div>
  </div>
</div>

<h2 class="h5 mt-4">ملاحظات</h2>
<p>يمكنك تخصيص هذه الصفحة من لوحة التحكم أو عبر تعديل قالب الصفحة حسب احتياجك.</p>
HTML,
    ];
}

// -----------------------------
// Resolve page (DB first, then fallback)
// -----------------------------
$slug = gdy_page_slug();

$pdo = gdy_pdo_safe();
$pageTitle = '';
$pageHtml  = '';

if ($pdo instanceof PDO) {
    gdy_load_site_settings($pdo);

    // try DB pages table
    try {
        $stmt = $pdo->prepare("SELECT title, content FROM pages WHERE slug = :slug AND status = 'published' LIMIT 1");
        $stmt->execute([':slug' => $slug]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        if (is_array($row)) {
            $pageTitle = (string)($row['title'] ?? '');
            $pageHtml  = (string)($row['content'] ?? '');
        }
    } catch (Throwable $e) {
        // ignore DB page errors
    }
}

if ($pageTitle === '' && $pageHtml === '') {
    $fallback  = gdy_default_page($slug);
    $pageTitle = (string)($fallback['title'] ?? '');
    $pageHtml  = (string)($fallback['html'] ?? '');
}

// -----------------------------
// Render
// -----------------------------
$siteName = (string)($GLOBALS['site_settings']['site_name'] ?? 'Godyar');
$fullTitle = $pageTitle !== '' ? ($pageTitle . ' - ' . $siteName) : $siteName;

?><!doctype html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= h($fullTitle) ?></title>

  <link rel="stylesheet" href="/assets/vendor/bootstrap/css/bootstrap.rtl.min.css">
  <link rel="stylesheet" href="/assets/css/app.css?v=<?= h((string)time()) ?>">
</head>
<body>

<?php require __DIR__ . '/../frontend/views/partials/header.php'; ?>

<main class="container py-4">
  <div class="card border-0 shadow-sm">
    <div class="card-body">
      <?= $pageHtml ?>
    </div>
  </div>
</main>

<?php require __DIR__ . '/../frontend/views/partials/footer.php'; ?>

<script src="/assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
</body>
</html>
