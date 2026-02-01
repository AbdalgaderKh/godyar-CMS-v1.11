<?php
declare(strict_types=1);

// /frontend/controllers/CategoryController.php
$bootstrap = dirname(__DIR__, 2) . '/includes/bootstrap.php';
if (!is_file($bootstrap)) {
    http_response_code(500);
    exit('Bootstrap missing: ' . $bootstrap);
}
require_once $bootstrap;

if (!function_exists('h')) {
    function h($v): string {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}

/**
 * Base URL resolver (prefers unified helper).
 */
function gdy_get_base_url(): string
{
    if (function_exists('gdy_base_url')) {
        return rtrim((string)gdy_base_url(), '/');
    }
    if (defined('GODYAR_BASE_URL')) {
        return rtrim((string)GODYAR_BASE_URL, '/');
    }
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return rtrim($scheme . '://' . $host, '/');
}

function gdy_render_message_page(string $title, string $message, int $code = 200): void
{
    http_response_code($code);
    echo '<!doctype html><html lang="ar" dir="rtl"><head><meta charset="utf-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>' . h($title) . '</title></head><body style="font-family:system-ui;max-width:720px;margin:40px auto;padding:0 16px;">';
    echo '<h1>' . h($title) . '</h1><p>' . h($message) . '</p></body></html>';
    exit;
}

function gdy_render_not_found_page(string $title, string $message): void
{
    gdy_render_message_page($title, $message, 404);
}

function gdy_render_error_page(string $title, string $message): void
{
    gdy_render_message_page($title, $message, 500);
}

// PDO
$pdo = $pdo ?? (function_exists('gdy_pdo_safe') ? gdy_pdo_safe() : null);
if (!($pdo instanceof PDO)) {
    gdy_render_error_page('خطأ', 'تعذر الاتصال بقاعدة البيانات.');
}

// slug
$slug = isset($_GET['slug']) ? trim((string)$_GET['slug']) : '';
if ($slug === '') {
    gdy_render_not_found_page('القسم غير موجود', 'لم يتم تحديد اسم القسم في الرابط.');
}
$slug = preg_replace('~[^a-zA-Z0-9\-]~', '', $slug) ?? '';
if ($slug === '') {
    gdy_render_not_found_page('القسم غير موجود', 'صيغة الرابط غير صحيحة.');
}

// pagination
$perPage = 12;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;

// fetch category
try {
    $st = $pdo->prepare("SELECT * FROM categories WHERE slug = :slug AND (is_active = 1 OR is_active IS NULL) LIMIT 1");
    $st->execute([':slug' => $slug]);
    $category = $st->fetch(PDO::FETCH_ASSOC) ?: null;
} catch (Throwable $e) {
    error_log('[CategoryController] category fetch failed: ' . $e->getMessage());
    $category = null;
}
if (!$category) {
    gdy_render_not_found_page('القسم غير موجود', 'لم يتم العثور على القسم المطلوب.');
}

// fetch items + total (short-lived cache to reduce DB load)
$totalItems = 0;
$items = [];
$ttl = function_exists('gdy_list_cache_ttl') ? gdy_list_cache_ttl() : 120;
$cacheKey = function_exists('gdy_cache_key')
    ? gdy_cache_key('list:cat', [$slug, (int)$category['id'], $page, $perPage, $_SERVER['HTTP_HOST'] ?? ''])
    : ('list:cat:' . $slug . ':' . $page);

try {
    $payload = function_exists('gdy_cache_remember')
        ? gdy_cache_remember($cacheKey, (int)$ttl, function () use ($pdo, $category, $perPage, $offset) {
            $out = ['total' => 0, 'items' => []];

            $st = $pdo->prepare("SELECT COUNT(*) FROM news WHERE status='published' AND category_id = :cid");
            $st->execute([':cid' => (int)$category['id']]);
            $out['total'] = (int)$st->fetchColumn();

            $sql = "SELECT *
                    FROM news
                    WHERE status='published' AND category_id = :cid
                    ORDER BY publish_at DESC
                    LIMIT :lim OFFSET :off";

            $prevEmulate = (bool)$pdo->getAttribute(PDO::ATTR_EMULATE_PREPARES);
            $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);

            $st = $pdo->prepare($sql);
            $st->bindValue(':cid', (int)$category['id'], PDO::PARAM_INT);
            $st->bindValue(':lim', (int)$perPage, PDO::PARAM_INT);
            $st->bindValue(':off', (int)$offset, PDO::PARAM_INT);
            $st->execute();
            $out['items'] = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

            $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, $prevEmulate);

            return $out;
        })
        : null;

    if (is_array($payload)) {
        $totalItems = (int)($payload['total'] ?? 0);
        $items      = (array)($payload['items'] ?? []);
    }

    // Performance: attach comment counts in one query (avoid N+1 in views)
    if (function_exists('gdy_attach_comment_counts_to_news_rows') && $pdo instanceof PDO) {
        try { $items = gdy_attach_comment_counts_to_news_rows($pdo, $items); } catch (Throwable $e) { /* ignore */ }
    }

} catch (Throwable $e) {
    error_log('[CategoryController] news fetch failed: ' . $e->getMessage());
}
$pages = max(1, (int)ceil($totalItems / $perPage));
$pagination = [
    'total_items' => $totalItems,
    'per_page'    => $perPage,
    'current'     => $page,
    'pages'       => $pages,
];

// values expected by the view
$baseUrl = gdy_get_base_url();
$newsItems = $items;
$currentPage = $page;

$view = dirname(__DIR__) . '/views/category.php';
if (!is_file($view)) {
    gdy_render_error_page('خطأ', 'ملف العرض غير موجود: ' . $view);
}
require $view;
