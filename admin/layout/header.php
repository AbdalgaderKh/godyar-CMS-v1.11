<?php
declare(strict_types=1);

// admin/layout/header.php
// Unified, production-safe admin header.

require_once __DIR__ . '/../_admin_guard.php';
require_once __DIR__ . '/../../includes/lang.php';

if (!function_exists('h')) {
    function h($v): string {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}

$lang = function_exists('gdy_lang') ? (string)gdy_lang() : (string)($_SESSION['lang'] ?? 'ar');
$lang = strtolower(trim($lang));
if (!in_array($lang, ['ar', 'en', 'fr'], true)) {
    $lang = 'ar';
}
$dir = ($lang === 'ar') ? 'rtl' : 'ltr';

$base = '';
if (defined('ROOT_URL')) {
    $base = rtrim((string)ROOT_URL, '/');
} elseif (defined('BASE_URL')) {
    $base = rtrim((string)BASE_URL, '/');
} elseif (defined('GODYAR_BASE_URL')) {
    $base = rtrim((string)GODYAR_BASE_URL, '/');
}

// Derive base from server if constants are empty
if ($base === '') {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = (string)($_SERVER['HTTP_HOST'] ?? '');
    if ($host !== '') {
        $base = $scheme . '://' . $host;
    }
}

/**
 * Normalize base:
 * - Remove trailing /admin if present (prevents /admin/admin assets).
 * - Remove trailing language prefix (e.g. /ar, /en, /fr) if base was built from a prefixed URL.
 */
if ($base !== '') {
    $scheme = parse_url($base, PHP_URL_SCHEME);
    $host   = parse_url($base, PHP_URL_HOST);
    $port   = parse_url($base, PHP_URL_PORT);
    $path   = (string)(parse_url($base, PHP_URL_PATH) ?? '');
    $path   = rtrim($path, '/');

    // strip /admin
    if (preg_match('~/(admin)$~i', $path)) {
        $path = preg_replace('~/admin$~i', '', $path);
    }
    // strip trailing language code (2 letters)
    if (preg_match('~/(ar|en|fr)$~i', $path)) {
        $path = preg_replace('~/(ar|en|fr)$~i', '', $path);
    }

    if ($scheme && $host) {
        $base = $scheme . '://' . $host . ($port ? ':' . $port : '') . $path;
    } else {
        $base = $path;
    }
}

$adminBase = ($base !== '' ? $base : '') . '/admin';

$pageTitle = $pageTitle ?? (function_exists('__') ? __('dashboard', [], 'لوحة التحكم') : 'لوحة التحكم');

$root = defined('ROOT_PATH') ? (string)ROOT_PATH : (string)dirname(__DIR__, 2);
$cssFileName = ($dir === 'rtl') ? 'bootstrap.rtl.min.css' : 'bootstrap.min.css';
$jsFileName  = 'bootstrap.bundle.min.js';

$localCssFile = rtrim($root, '/\\') . '/assets/vendor/bootstrap/css/' . $cssFileName;
$localJsFile  = rtrim($root, '/\\') . '/assets/vendor/bootstrap/js/' . $jsFileName;

$bootstrapCss = is_file($localCssFile)
    ? ($base . '/assets/vendor/bootstrap/css/' . $cssFileName)
    : ('/assets/vendor/bootstrap/css/' . $cssFileName);
$bootstrapJs = is_file($localJsFile)
    ? ($base . '/assets/vendor/bootstrap/js/' . $jsFileName)
    : ('/assets/vendor/bootstrap/js/' . $jsFileName);

$uiCssPath    = __DIR__ . '/../assets/css/admin-ui.css';
$shellCssPath = __DIR__ . '/../assets/css/admin-shell.css';
$uiVer        = is_file($uiCssPath) ? (string)filemtime($uiCssPath) : (string)time();
$shellVer     = is_file($shellCssPath) ? (string)filemtime($shellCssPath) : (string)time();

$pageHead = $pageHead ?? '';

$jsonFlags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
?>
<!doctype html>
<html lang="<?php echo h($lang); ?>" dir="<?php echo h($dir); ?>">
<head>
    <meta charset="utf-8">
    <title><?php echo h($pageTitle); ?> — <?php echo h(function_exists('__') ? __('dashboard', [], 'لوحة التحكم') : 'لوحة التحكم'); ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <script>
        window.GODYAR_BASE_URL = <?php echo json_encode($base, $jsonFlags); ?>;
        window.GDY_ADMIN_URL   = <?php echo json_encode($adminBase, $jsonFlags); ?>;
    </script>

    <link rel="stylesheet" href="<?php echo h($bootstrapCss); ?>">
    <link rel="stylesheet" href="<?php echo h($adminBase . '/assets/css/admin-ui.css?v=' . $uiVer); ?>">
    <link rel="stylesheet" href="<?php echo h($adminBase . '/assets/css/admin-shell.css?v=' . $shellVer); ?>">

    <?php if ($pageHead !== '') { echo $pageHead; } ?>
</head>
<body>

<?php if (function_exists('csrf_token')): ?>
  <input type="hidden" id="gdyGlobalCsrfToken" value="<?php echo h(csrf_token()); ?>" style="display:none">
<?php endif; ?>

<?php
// Inline SVG sprite for admin icons (best effort)
try {
    $sprite = rtrim($root, '/\\') . '/assets/icons/gdy-icons.svg';
    if (is_file($sprite) && function_exists('gdy_readfile')) {
        gdy_readfile($sprite);
    }
} catch (Throwable $e) {
    // ignore
}
?>

<script src="<?php echo h($bootstrapJs); ?>"></script>
