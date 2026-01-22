<?php
declare(strict_types=1);

require_once __DIR__ . '/../_admin_guard.php';
require_once __DIR__ . '/../../includes/bootstrap.php';

$pageTitle   = __('t_82bacb8bca', 'مركز الأمان');
$currentPage = 'system_health';

if (!function_exists('h')) {
    function h($v): string
    {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}

require_once __DIR__ . '/../layout/app_start.php';

$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
$displayErrors = (bool)ini_get('display_errors');
$logErrors     = (bool)ini_get('log_errors');
$sessionSettings = [
    'httponly' => (bool)ini_get('session.cookie_httponly'),
    'samesite' => (string)ini_get('session.cookie_samesite'),
    'secure'   => (bool)ini_get('session.cookie_secure'),
];

?>

<div class="alert alert-info">
  <?= h(__('t_131af90b58', 'هذه الصفحة مخصصة لفحص إعدادات الأمان العامة للنظام.')) ?>
  <br>
  <small class="text-muted"><?= h(__('t_c8a1d5ae64', 'النسخة الحالية مبسطة، ويمكن تطويرها لاحقًا لإظهار المزيد من التفاصيل.')) ?></small>
</div>

<div class="row g-3">
  <div class="col-lg-6">
    <div class="card">
      <div class="card-header">
        <?= h(__('t_afd4e6edb4', 'إعدادات الجلسات والكويكز')) ?>
      </div>
      <div class="card-body small">
        <ul class="mb-0">
          <li>HTTPOnly: <strong><?= $sessionSettings['httponly'] ? 'ON' : 'OFF' ?></strong></li>
          <li>SameSite: <strong><?= h($sessionSettings['samesite'] ?: 'Lax') ?></strong></li>
          <li>Secure cookie: <strong><?= $sessionSettings['secure'] ? 'ON' : 'OFF' ?></strong></li>
          <li>HTTPS: <strong><?= $https ? __('t_e1dadf4c7c', 'نعم') : __('t_b27ea934ef', 'لا') ?></strong></li>
        </ul>
      </div>
    </div>
  </div>

  <div class="col-lg-6">
    <div class="card">
      <div class="card-header">
        <?= h(__('t_84d1dd88a6', 'إعدادات PHP المهمة')) ?>
      </div>
      <div class="card-body small">
        <ul class="mb-0">
          <li><?= h(__('t_1425cbc31c', 'الإصدار:')) ?> <strong><?= h(PHP_VERSION) ?></strong></li>
          <li>display_errors: <strong><?= $displayErrors ? 'ON' : 'OFF' ?></strong></li>
          <li>log_errors: <strong><?= $logErrors ? 'ON' : 'OFF' ?></strong></li>
          <li>error_reporting: <strong><?= h((string)error_reporting()) ?></strong></li>
        </ul>
      </div>
    </div>
  </div>
</div>

<?php
require_once __DIR__ . '/../layout/app_end.php';
