<?php
declare(strict_types=1);

/**
 * frontend/controllers/TrendingController.php (safe)
 *
 * Fixes:
 * - Undefined array key "key" warnings originating from legacy settings handling.
 *
 * Behavior:
 * - Renders a simple "Most Read" page using NewsService::mostRead() if available.
 * - Uses existing header/footer templates when present.
 */
require_once __DIR__ . '/../../includes/bootstrap.php';



// output cache (anonymous GET only)
$__didOutputCache = false;
$__pageCacheKey = '';
$__ttl = function_exists('gdy_output_cache_ttl') ? gdy_output_cache_ttl() : 0;
if ($__ttl > 0 && function_exists('gdy_should_output_cache') && gdy_should_output_cache() && class_exists('PageCache')) {
    $__pageCacheKey = 'trending_' . gdy_page_cache_key('trending', ['page' => 1]);
    if (PageCache::serveIfCached($__pageCacheKey)) {
        exit;
    }
    ob_start();
    $__didOutputCache = true;
}

$container = $GLOBALS['container'] ?? null;
if (($container instanceof \Godyar\Container) === false) {
    try { $container = new \Godyar\Container(\Godyar\DB::pdo()); } catch (\Throwable $e) { $container = null; }
}

$items = [];
try {
    if ($container && method_exists($container, 'news')) {
        $svc = $container->news();
        if ($svc && method_exists($svc, 'mostRead')) {
            $period = isset($_GET['period']) ? (string)$_GET['period'] : 'week';
            $items = $svc->mostRead(20, $period);
        }
    }
} catch (\Throwable $e) {
    error_log('[TrendingController] ' . $e->getMessage());
}

$header = __DIR__ . '/../templates/header.php';
$footer = __DIR__ . '/../templates/footer.php';

$siteTitle = 'الأكثر قراءة';
$siteDescription = '';

if (is_file($header)) require $header;

echo '<main class="container my-5">';
echo '<h1 style="margin-bottom:12px;">الأكثر قراءة</h1>';

if (!$items) {
    echo '<p>لا توجد عناصر حالياً.</p>';
} else {
    echo '<ul>';
    foreach ($items as $r) {
        $id = (int)($r['id'] ?? 0);
        $title = (string)($r['title'] ?? '');
        $url = ($id > 0) ? ('/news/id/' . $id) : '#';
        echo '<li><a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '">'
            . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</a></li>';
    }
    echo '</ul>';
}

echo '</main>';

if (is_file($footer)) require $footer;

if ($__didOutputCache && $__pageCacheKey !== '') {
    PageCache::store($__pageCacheKey, $__ttl);
    @ob_end_flush();
}

