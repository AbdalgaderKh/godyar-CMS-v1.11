<?php
declare(strict_types=1);

// Diagnostics page (admin-only, portable)
// Safe output: no secrets printed.
// Controls:
// - Set GDY_DIAGNOSTICS=1 to allow access (recommended temporarily).
// - If unset/0, access allowed only when GODYAR_DEBUG is true.
require_once __DIR__ . '/admin/_admin_boot.php';

$allow = false;
$env = getenv('GDY_DIAGNOSTICS');
if ($env !== false && (string)$env === '1') {
    $allow = true;
} elseif (defined('GODYAR_DEBUG') && GODYAR_DEBUG) {
    $allow = true;
}

if (!$allow) {
    http_response_code(404);
    echo 'Not found';
    exit;
}

// Require admin session
$admin = $_SESSION['admin'] ?? null;
if ($admin !== true) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

$storage = defined('GODYAR_STORAGE') ? GODYAR_STORAGE : (dirname(__DIR__) . '/storage');
$paths = [
  'ROOT_PATH' => defined('ROOT_PATH') ? ROOT_PATH : __DIR__,
  'GODYAR_STORAGE' => $storage,
  'uploads' => __DIR__ . '/uploads',
  'assets/uploads' => __DIR__ . '/assets/uploads',
];

$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

$checks = [
  'HTTPS' => $https ? 'YES' : 'NO',
  'Session active' => (session_status() === PHP_SESSION_ACTIVE) ? 'YES' : 'NO',
  'Session cookie httponly' => ini_get('session.cookie_httponly'),
  'Session use_strict_mode' => ini_get('session.use_strict_mode'),
  'display_errors' => ini_get('display_errors'),
  'log_errors' => ini_get('log_errors'),
];

?><!doctype html>
<html lang="ar">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Godyar Diagnostics</title>
  <style>
    body{font-family:system-ui, -apple-system, Segoe UI, Roboto, Arial; padding:16px; line-height:1.6;}
    table{border-collapse:collapse; width:100%; max-width:980px;}
    td,th{border:1px solid #ddd; padding:8px;}
    th{text-align:left; background:#f5f5f5;}
    code{background:#f6f8fa; padding:2px 6px; border-radius:6px;}
  </style>
</head>
<body>
  <h1>Godyar Diagnostics</h1>
  <p style="color:#b00"><strong>تنبيه:</strong> هذه الصفحة مخصصة للتشخيص ويُفضّل تعطيلها بعد الانتهاء (GDY_DIAGNOSTICS=0).</p>

  <h2>Environment</h2>
  <table>
    <tr><th>PHP</th><td><?php echo h(PHP_VERSION); ?> (<?php echo h(PHP_SAPI); ?>)</td></tr>
    <tr><th>Server</th><td><?php echo h($_SERVER['SERVER_SOFTWARE'] ?? ''); ?></td></tr>
    <tr><th>Host</th><td><?php echo h($_SERVER['HTTP_HOST'] ?? ''); ?></td></tr>
  </table>

  <h2>Checks</h2>
  <table>
    <?php foreach ($checks as $k => $v): ?>
      <tr><th><?php echo h($k); ?></th><td><code><?php echo h((string)$v); ?></code></td></tr>
    <?php endforeach; ?>
  </table>

  <h2>Paths & Permissions</h2>
  <table>
    <tr><th>Path</th><th>Exists</th><th>Writable</th></tr>
    <?php foreach ($paths as $name => $p): ?>
      <tr>
        <th><?php echo h($name); ?></th>
        <td><?php echo is_dir($p) || is_file($p) ? 'YES' : 'NO'; ?></td>
        <td><?php echo (is_dir($p) && is_writable($p)) ? 'YES' : 'NO'; ?></td>
      </tr>
    <?php endforeach; ?>
  </table>

  <h2>Database</h2>
  <table>
    <tr><th>Status</th><td>
      <?php
        $ok = false; $drv = 'unknown';
        try {
          if (function_exists('gdy_pdo_safe')) {
            $pdo = gdy_pdo_safe();
            if ($pdo instanceof PDO) { $drv = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME); $pdo->query('SELECT 1'); $ok = true; }
          }
        } catch (Throwable $e) { $ok = false; }
        echo $ok ? '<strong style="color:green">OK</strong>' : '<strong style="color:red">FAIL</strong>';
      ?>
    </td></tr>
    <tr><th>Driver</th><td><code><?php echo h($drv); ?></code></td></tr>
  </table>

</body>
</html>
