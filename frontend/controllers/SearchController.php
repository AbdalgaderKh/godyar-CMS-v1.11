<?php
// UTF-8 no BOM
require_once __DIR__ . '/../../includes/bootstrap.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// ✅ إضافة علامة أن هذه صفحة بحث (للفوتر)
$GLOBALS['isSearchPage'] = true;

// PDO fallback (لتجنب متغير غير معرف في التحليلات الثابتة)
if (!isset($pdo) || !($pdo instanceof \PDO)) {
  $pdo = function_exists('gdy_pdo_safe') ? gdy_pdo_safe() : null;
}

// Load settings (class or PDO fallback)
$settings = [];
try {
  if (class_exists('Settings')) { $settings = Settings::getAll(); }
	  elseif ($pdo instanceof \PDO) {
	    $st = $pdo->query("SELECT setting_key,`value` FROM settings");
	    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
	      $k = (string)($row['setting_key'] ?? '');
	      if ($k !== '') {
	        $settings[$k] = (string)($row['value'] ?? '');
	      }
	    }
  }
} catch (Throwable $e) { error_log(get_class($e).": ".$e->getMessage()); }

$site_name    = $settings['site_name'] ?? ($settings['site_title'] ?? 'Godyar');
$main_menu    = json_decode($settings['menu_main']   ?? '[]', true) ?: [];
$footer_links = json_decode($settings['menu_footer'] ?? '[]', true) ?: [];
$social_links = json_decode($settings['social_links']?? '[]', true) ?: [];
$footer_about = $settings['footer_about'] ?? '';

// نفّذ منطق البحث الفعلي (فلترة/صفحات/عدد النتائج) ثم اعرض القالب.
// الملف التالي يجهّز المتغيرات ($q, $results, $pagination, ...) ويستدعي view/search.php.
if (!headers_sent()) {
  // Use this header to confirm which build is deployed.
  header('X-Godyar-Build: godyar_CMS_v1.11_CLEAN_SECURE_v10');
}
require __DIR__ . '/../news/search.php';