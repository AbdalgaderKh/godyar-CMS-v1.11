<?php
declare(strict_types=1);

// Portable deployment self-check page (safe by default)
// - Enabled only when GDY_DEPLOY_CHECK=1 (or GDY_DIAGNOSTICS=1)
// - Requires token (GDY_DEPLOY_CHECK_TOKEN) OR localhost
// - Does NOT reveal secrets (no DB creds, no paths outside ROOT)

require_once __DIR__ . '/includes/bootstrap.php';

if (!headers_sent()) {
    header('Content-Type: text/html; charset=UTF-8');
    header('Cache-Control: no-store, max-age=0, must-revalidate');
    header('Pragma: no-cache');
}

$enabled = getenv('GDY_DEPLOY_CHECK');
$diag = getenv('GDY_DIAGNOSTICS');
if (!((is_string($enabled) && $enabled === '1') || (is_string($diag) && $diag === '1'))) {
    http_response_code(404);
    echo 'Not Found';
    exit;
}

function _gdy_is_localhost(): bool {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    return ($ip === '127.0.0.1' || $ip === '::1');
}

$token = getenv('GDY_DEPLOY_CHECK_TOKEN');
$provided = $_GET['token'] ?? ($_SERVER['HTTP_X_DEPLOY_TOKEN'] ?? '');

if (is_string($token) && $token !== '') {
    if (!is_string($provided) || !hash_equals($token, $provided)) {
        http_response_code(403);
        echo 'Forbidden';
        exit;
    }
} else {
    if (!_gdy_is_localhost()) {
        http_response_code(403);
        echo 'Forbidden';
        exit;
    }
}

// ---------- Checks ----------
$results = [];
$okAll = true;

function add_result(array &$results, bool &$okAll, string $name, bool $ok, string $detail = ''): void {
    $results[] = ['name' => $name, 'ok' => $ok, 'detail' => $detail];
    if (!$ok) $okAll = false;
}

// Storage checks
$storage = defined('GODYAR_STORAGE') ? GODYAR_STORAGE : (dirname(__DIR__) . '/storage');
$dirs = [
    'storage' => $storage,
    'storage/cache' => $storage . '/cache',
    'storage/logs' => $storage . '/logs',
    'storage/ratelimit' => $storage . '/ratelimit',
    'storage/uploads' => $storage . '/uploads',
    'storage/queue' => $storage . '/queue',
];

foreach ($dirs as $label => $path) {
    $exists = is_dir($path);
    $writable = $exists ? is_writable($path) : false;
    add_result($results, $okAll, "Dir: {$label}", ($exists && $writable), ($exists ? ($writable ? 'OK' : 'Not writable') : 'Missing'));
}

// Cache read/write test
$cacheOk = false;
$cacheDetail = 'Cache disabled or unavailable';
try {
    if (class_exists('Cache')) {
        $key = 'deploy_check_' . bin2hex(random_bytes(8));
        $val = 'ok_' . time();
        // Prefer remember to avoid needing set() API
        $got = Cache::remember($key, 30, function () use ($val) { return $val; });
        $got2 = Cache::remember($key, 30, function () { return 'changed'; });
        $cacheOk = ($got === $val) && ($got2 === $val);
        $cacheDetail = $cacheOk ? 'OK (file cache)' : 'Unexpected cache behavior';
        // Best-effort cleanup
        if (method_exists('Cache','forget')) { Cache::forget($key); }
    }
} catch (Throwable $e) {
    $cacheOk = false;
    $cacheDetail = 'Exception: ' . get_class($e);
}
add_result($results, $okAll, 'Cache: cross-request layer', $cacheOk, $cacheDetail);

// Security headers visibility (what PHP queued)
$hdrs = function_exists('headers_list') ? headers_list() : [];
$hasNosniff = false;
$hasFrame = false;
$hasRef = false;
$hasCsp = false;
foreach ($hdrs as $h) {
    $lh = strtolower($h);
    if (str_starts_with($lh, 'x-content-type-options:')) $hasNosniff = true;
    if (str_starts_with($lh, 'x-frame-options:')) $hasFrame = true;
    if (str_starts_with($lh, 'referrer-policy:')) $hasRef = true;
    if (str_starts_with($lh, 'content-security-policy:')) $hasCsp = true;
}
add_result($results, $okAll, 'Headers: nosniff', $hasNosniff, $hasNosniff ? 'OK' : 'Missing');
add_result($results, $okAll, 'Headers: frame protection', $hasFrame, $hasFrame ? 'OK' : 'Missing');
add_result($results, $okAll, 'Headers: referrer-policy', $hasRef, $hasRef ? 'OK' : 'Missing');
add_result($results, $okAll, 'Headers: CSP', $hasCsp, $hasCsp ? 'OK' : 'Missing (can be disabled by env)');

// Admin login route existence (portable check)
$adminLoginIndex = dirname(__DIR__) . '/admin/login/index.php';
$adminLoginPhp = dirname(__DIR__) . '/admin/login.php';
$adminOk = is_file($adminLoginIndex) || is_file($adminLoginPhp);
add_result($results, $okAll, 'Admin login route files', $adminOk, $adminOk ? 'OK' : 'Missing admin/login or login.php');

?><!doctype html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Godyar Deploy Check</title>
  <style>
    body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial;max-width:980px;margin:20px auto;padding:0 12px;line-height:1.5}
    .ok{color:#0a7a2f}
    .bad{color:#b00020}
    table{border-collapse:collapse;width:100%;margin-top:12px}
    th,td{border:1px solid #ddd;padding:8px;text-align:right;vertical-align:top}
    th{background:#f7f7f7}
    .pill{display:inline-block;padding:2px 8px;border-radius:999px;font-size:12px}
    .pill.ok{background:#e7f7ec}
    .pill.bad{background:#fde7ea}
    code{background:#f2f2f2;padding:1px 4px;border-radius:4px}
  </style>
</head>
<body>
  <h1>فحص النشر — Godyar CMS</h1>
  <p>هذه الصفحة تُستخدم للتأكد من جاهزية البيئة. لا تُفعّل إلا عند <code>GDY_DEPLOY_CHECK=1</code> (أو <code>GDY_DIAGNOSTICS=1</code>).</p>

  <h2>النتيجة العامة: <?php if ($okAll): ?><span class="pill ok">PASS</span><?php else: ?><span class="pill bad">NEEDS ATTENTION</span><?php endif; ?></h2>

  <table>
    <thead><tr><th>التحقق</th><th>الحالة</th><th>تفاصيل</th></tr></thead>
    <tbody>
      <?php foreach ($results as $r): ?>
        <tr>
          <td><?= htmlspecialchars($r['name'], ENT_QUOTES, 'UTF-8') ?></td>
          <td><?php if ($r['ok']): ?><span class="ok">✅ OK</span><?php else: ?><span class="bad">❌ FAIL</span><?php endif; ?></td>
          <td><?= htmlspecialchars($r['detail'], ENT_QUOTES, 'UTF-8') ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <h2>روابط سريعة</h2>
  <ul>
    <li><a href="/admin/login">/admin/login</a> (يجب أن يعمل على أغلب البيئات حتى بدون Rewrite)</li>
    <li><a href="/health.php">/health.php</a> (مقفل بتوكن/localhost حسب الإعداد)</li>
  </ul>

  <h2>ملاحظات</h2>
  <ul>
    <li>إذا فشل “Dir: storage/*” تأكد من الصلاحيات (يفضّل 0755/0775 حسب المستخدم/المجموعة).</li>
    <li>إذا فشل “Headers”، قد تكون البيئة ترسل هيدرزها أو تم تعطيلها عبر env (مثل <code>GDY_SECURITY_HEADERS=0</code> أو <code>GDY_CSP=0</code>).</li>
  </ul>
</body>
</html>
