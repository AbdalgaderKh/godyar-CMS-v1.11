<?php
require_once __DIR__ . '/../../includes/bootstrap.php';

/** @var \PDO|null $pdo */
$pdo = $pdo ?? ($GLOBALS['pdo'] ?? null);
header('Content-Type: application/json; charset=UTF-8');
$q = trim($_GET['q'] ?? '');
$page = max(1,(int)($_GET['page'] ?? 1)); $perPage = max(1,min(50,(int)($_GET['limit'] ?? 12))); $offset = ($page-1)*$perPage;
if ($q === '') { echo json_encode(['ok'=>true,'items'=>[]]); exit; }
if (!($pdo instanceof \PDO)) { http_response_code(500); echo json_encode(['ok'=>false,'error'=>'db_unavailable']); exit; }
try {
  $like = "%$q%";
  $ttl = function_exists('gdy_list_cache_ttl') ? gdy_list_cache_ttl() : 120;
  $cacheKey = function_exists('gdy_cache_key')
    ? gdy_cache_key('list:api_search', [$q, $page, $perPage, $_SERVER['HTTP_HOST'] ?? ''])
    : ('list:api_search:' . sha1($q . '|' . $page . '|' . $perPage));

  $payload = function_exists('gdy_cache_remember')
    ? (array)gdy_cache_remember($cacheKey, (int)$ttl, function () use ($pdo, $like, $perPage, $offset) {
        $cnt = $pdo->prepare("SELECT COUNT(*) FROM news WHERE status='published' AND (title LIKE :q OR excerpt LIKE :q OR content LIKE :q)");
        $cnt->execute([':q'=>$like]);
        $total = (int)$cnt->fetchColumn();

        $lim=(int)$perPage; $off=(int)$offset;
        $sql = "SELECT id,slug,title,excerpt,COALESCE(featured_image,image_path,image) AS featured_image,publish_at
                FROM news
                WHERE status='published' AND (title LIKE :q OR excerpt LIKE :q OR content LIKE :q)
                ORDER BY publish_at DESC
                LIMIT :lim OFFSET :off";
        $prevEmulate = $pdo->getAttribute(PDO::ATTR_EMULATE_PREPARES);
        $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);
        $st = $pdo->prepare($sql);
        $st->bindValue(':q', $like, PDO::PARAM_STR);
        $st->bindValue(':lim', $lim, PDO::PARAM_INT);
        $st->bindValue(':off', $off, PDO::PARAM_INT);
        $st->execute();
        $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, $prevEmulate);
        $items = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

        if (function_exists('gdy_attach_comment_counts_to_news_rows')) {
            try { $items = gdy_attach_comment_counts_to_news_rows($pdo, $items); } catch (Throwable $e) {}
        }

        return ['total'=>$total,'items'=>$items];
      })
    : [];

  $total = (int)($payload['total'] ?? 0);
  $items = (array)($payload['items'] ?? []);
  echo json_encode(['ok'=>true,'total'=>$total,'items'=>$items], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
} catch (Throwable $e) { error_log('API_SEARCH: '.$e->getMessage()); http_response_code(500); echo json_encode(['ok'=>false]); }
