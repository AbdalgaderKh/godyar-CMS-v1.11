<?php
declare(strict_types=1);

/**
 * saved.php (legacy bookmarks page) - compatibility hotfix
 *
 * Fixes fatal:
 * - Call to undefined function gdy_load_settings()
 */
require_once __DIR__ . '/includes/bootstrap.php';

// Ensure settings helpers exist even if bootstrap order changes
if (function_exists('gdy_load_settings') === false) {
    require_once __DIR__ . '/includes/site_settings.php';
}

$pdo = function_exists('gdy_pdo_safe') ? gdy_pdo_safe() : null;
$settings = ($pdo instanceof PDO) ? gdy_load_settings($pdo, false) : [];
$frontendOptions = function_exists('gdy_prepare_frontend_options') ? gdy_prepare_frontend_options($settings) : [];
// Extract a few common vars for templates (safe defaults)
$siteName = (string)($frontendOptions['siteName'] ?? 'Godyar');
$siteTagline = (string)($frontendOptions['siteTagline'] ?? '');
$siteLogo = (string)($frontendOptions['siteLogo'] ?? '');
$primaryColor = (string)($frontendOptions['primaryColor'] ?? '#111111');
$primaryDark = (string)($frontendOptions['primaryDark'] ?? '#000000');
$baseUrl = (string)($frontendOptions['baseUrl'] ?? '/');
$themeClass = (string)($frontendOptions['themeClass'] ?? 'theme-default');
$searchPlaceholder = (string)($frontendOptions['searchPlaceholder'] ?? 'ابحث...');
$header = __DIR__ . '/frontend/templates/header.php';
$footer = __DIR__ . '/frontend/templates/footer.php';

$siteTitle = 'المحفوظات';
$siteDescription = '';

if (is_file($header)) require $header;

echo '<main class="container my-5">';
echo '<h1 style="margin-bottom:12px;">المحفوظات</h1>';
echo '<p>هذه صفحة توافق. سيتم لاحقاً ربطها بنظام الإشارات المرجعية (Bookmarks).</p>';
echo '</main>';

if (is_file($footer)) require $footer;
