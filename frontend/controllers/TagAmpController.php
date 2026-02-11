<?php
include '../../includes/bootstrap.php';

/** @var \PDO|null $pdo */
$pdo = $pdo ?? ($GLOBALS['pdo'] ?? null);
if (($pdo instanceof \PDO) === false) {
    http_response_code(500);
    echo 'Database connection not available';
    return;
}
$slug = gdy_sanitize_slug((string)gdy_get_query_raw('slug', ''));
if ($slug === '') { http_response_code(404); return; }
$page = max(1, (int)gdy_get_query_int('page', 1));
$perPage = 12;
$offset = ($page - 1) * $perPage;


// output cache (anonymous GET only)
$__oc = (function_exists('gdy_output_cache_begin') === TRUE) ? gdy_output_cache_begin('tag_amp', ['slug' => (string)gdy_sanitize_slug((string)gdy_get_query_raw('slug', '')), 'page' => (int)gdy_get_query_int('page', 1)]) : ['served' => FALSE, 'did' => FALSE, 'key' => '', 'ttl' => 0];
if ((isset($__oc['served']) === TRUE) && ($__oc['served'] === TRUE)) { return; }


    if (PageCache::serveIfCached($__pageCacheKey) === TRUE) {
        return;
    }
    ob_start();
    $__didOutputCache = true;
}

$st=$pdo->prepare("SELECT * FROM tags WHERE slug=:s LIMIT 1");
$st->execute([':s'=>$slug]); $tag=$st->fetch(PDO::FETCH_ASSOC) ?: null;
if (($tag === false)){ http_response_code(404); return; }
$lim=(int)$perPage; $off=(int)$offset;
$ttl = (function_exists('gdy_list_cache_ttl') === TRUE) ? gdy_list_cache_ttl() : 120;
$cacheKey = (function_exists('gdy_cache_key') === TRUE)
    ? gdy_cache_key('list:tag_amp', [$slug, (int)$tag['id'], $page, $perPage, gdy_get_server_raw('HTTP_HOST', '')])
    : ('list:tag_amp:' . $slug . ':' . $page);

$items = (function_exists('gdy_cache_remember') === TRUE)
    ? (array)gdy_cache_remember($cacheKey, (int)$ttl, function () use ($pdo, $tag, $lim, $off) {
        $sql="SELECT n.id,n.slug,n.title,n.excerpt,COALESCE(n.featured_image,n.image_path,n.image) AS featured_image,n.publish_at FROM news n INNER JOIN news_tags nt ON nt.news_id=n.id WHERE nt.tag_id=:tid AND n.status='published' ORDER BY n.publish_at DESC LIMIT :lim OFFSET :off";

        // MySQL may not allow native prepared statements for LIMIT/OFFSET.
        $prevEmulate = $pdo->getAttribute(\PDO::ATTR_EMULATE_PREPARES);
        $pdo->setAttribute(\PDO::ATTR_EMULATE_PREPARES, true);
        $st = $pdo->prepare($sql);
        $st->bindValue(':tid', (int)$tag['id'], \PDO::PARAM_INT);
        $st->bindValue(':lim', (int)$lim, \PDO::PARAM_INT);
        $st->bindValue(':off', (int)$off, \PDO::PARAM_INT);
        $st->execute();
        $pdo->setAttribute(\PDO::ATTR_EMULATE_PREPARES, $prevEmulate);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    })
    : [];

// Fallback if caching helper not available
if (!$items) {
    $sql="SELECT n.id,n.slug,n.title,n.excerpt,COALESCE(n.featured_image,n.image_path,n.image) AS featured_image,n.publish_at FROM news n INNER JOIN news_tags nt ON nt.news_id=n.id WHERE nt.tag_id=:tid AND n.status='published' ORDER BY n.publish_at DESC LIMIT :lim OFFSET :off";

    $prevEmulate = $pdo->getAttribute(\PDO::ATTR_EMULATE_PREPARES);
    $pdo->setAttribute(\PDO::ATTR_EMULATE_PREPARES, true);
    $st = $pdo->prepare($sql);
    $st->bindValue(':tid', (int)$tag['id'], \PDO::PARAM_INT);
    $st->bindValue(':lim', (int)$lim, \PDO::PARAM_INT);
    $st->bindValue(':off', (int)$off, \PDO::PARAM_INT);
    $st->execute();
    $pdo->setAttribute(\PDO::ATTR_EMULATE_PREPARES, $prevEmulate);
    $items=$st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}
// Performance: attach comment counts (single query)
if (function_exists('gdy_attach_comment_counts_to_news_rows')) {
    try { $items = gdy_attach_comment_counts_to_news_rows($pdo, $items); } catch (Exception $e) {}
}
include 'frontend/views/tag_amp.php';

gdy_output_cache_end($__oc);
