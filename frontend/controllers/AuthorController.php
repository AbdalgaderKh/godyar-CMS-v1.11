<?php
// UTF-8 no BOM
require_once __DIR__ . '/../../includes/bootstrap.php';
if (session_status() !== PHP_SESSION_ACTIVE) {
    if (function_exists('gdy_session_start')) {
        gdy_session_start();
    } elseif (!headers_sent()) {
        session_start();
    }
}

// Load settings (class or PDO fallback)
$settings = [];
try {
  if (class_exists('Settings') === true) { $settings = Settings::getAll(); }
  elseif (isset($pdo) === true) {
    $st = $pdo->query("SELECT setting_key, " . (function_exists('gdy_settings_value_column') ? gdy_settings_value_column($pdo) : 'setting_value') . " AS value FROM settings");
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

$slug = isset($_GET['slug']) ? trim((string)$_GET['slug']) : '';
require __DIR__ . '/../views/author.php';
