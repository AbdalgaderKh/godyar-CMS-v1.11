<?php
require_once __DIR__ . '/../../includes/bootstrap.php';

/** @var \PDO|null $pdo */
$pdo = $pdo ?? ($GLOBALS['pdo'] ?? null);
if (($pdo instanceof \PDO) === false) {
    http_response_code(500);
    exit('Database connection not available');
}
$slug = (!empty($_GET['slug']) ? $_GET['slug'] : null); if (($slug === false)){ http_response_code(404); exit; }
$page = max(1,(int)($_GET['page']??1)); $perPage=12; $offset=($page-1)*$perPage;
$st=$pdo->prepare("SELECT * FROM categories WHERE slug=:s AND is_active=1 LIMIT 1");
$st->execute([':s'=>$slug]); $category=$st->fetch(PDO::FETCH_ASSOC) ?: null;
if (($category === false)){ http_response_code(404); exit; }
$lim=(int)$perPage; $off=(int)$offset;
$ttl = function_exists('gdy_list_cache_ttl') ? gdy_list_cache_ttl() : 120;
$cacheKey = function_exists('gdy_cache_key')
    ? gdy_cache_key('list:cat_amp', [$slug, (int)$category['id'], $page, $perPage, $_SERVER['HTTP_HOST'] ?? ''])
    : ('list:cat_amp:' . $slug . ':' . $page);

$items = function_exists('gdy_cache_remember')
    ? (array)gdy_cache_remember($cacheKey, (int)$ttl, function () use ($pdo, $category, $lim, $off) {
        $sql="SELECT id,slug,title,excerpt,COALESCE(featured_image,image_path,image) AS featured_image,publish_at FROM news WHERE status='published' AND category_id=:cid ORDER BY publish_at DESC LIMIT :lim OFFSET :off";
        $prevEmulate = (bool)$pdo->getAttribute(PDO::ATTR_EMULATE_PREPARES);
        $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);
        $st = $pdo->prepare($sql);
        $st->bindValue(':cid', (int)$category['id'], PDO::PARAM_INT);
        $st->bindValue(':lim', (int)$lim, PDO::PARAM_INT);
        $st->bindValue(':off', (int)$off, PDO::PARAM_INT);
        $st->execute();
        $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, $prevEmulate);
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    })
    : [];

// Fallback if caching helper not available
if (!$items) {
    $sql="SELECT id,slug,title,excerpt,COALESCE(featured_image,image_path,image) AS featured_image,publish_at FROM news WHERE status='published' AND category_id=:cid ORDER BY publish_at DESC LIMIT :lim OFFSET :off";
    $prevEmulate = (bool)$pdo->getAttribute(PDO::ATTR_EMULATE_PREPARES);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);
    $st = $pdo->prepare($sql);
    $st->bindValue(':cid', (int)$category['id'], PDO::PARAM_INT);
    $st->bindValue(':lim', (int)$lim, PDO::PARAM_INT);
    $st->bindValue(':off', (int)$off, PDO::PARAM_INT);
    $st->execute();
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, $prevEmulate);
    $items=$st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}
// Performance: attach comment counts (single query)
if (function_exists('gdy_attach_comment_counts_to_news_rows')) {
    try { $items = gdy_attach_comment_counts_to_news_rows($pdo, $items); } catch (Throwable $e) {}
}
require __DIR__ . '/../views/category_amp.php';
