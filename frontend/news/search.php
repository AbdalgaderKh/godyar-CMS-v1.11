<?php
/**
 * Unified search handler for the Arabic frontend.
 *
 * This powers /ar/search and supports the UI filters:
 * - q: query
 * - mode: any|all|exact
 * - type: all|news|opinion|page|author
 * - section: category slug (news/opinion only)
 */

declare(strict_types=1);

require_once __DIR__ . '/../../includes/app.php';

// -------------------------
// Schema compatibility
// -------------------------

/**
 * Check if a table has a given column (cached per request).
 */
function gdy_table_has_column(PDO $pdo, string $table, string $column): bool
{
    static $cache = [];
    $key = $table . '.' . $column;
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }
    try {
        $stmt = $pdo->prepare("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c LIMIT 1");
        $stmt->execute([':t' => $table, ':c' => $column]);
        $cache[$key] = (bool)$stmt->fetchColumn();
    } catch (Exception $e) {
        // If introspection fails for any reason, assume the column is missing.
        $cache[$key] = false;
    }
    return $cache[$key];
}

/**
 * Opinion-author select list that works across older/newer schemas.
 * Returns a tuple: [photoSelect, specializationSelect]
 */
function gdy_author_select_fields(PDO $pdo): array
{
    $photoCol = gdy_table_has_column($pdo, 'opinion_authors', 'photo') ? 'photo'
             : (gdy_table_has_column($pdo, 'opinion_authors', 'avatar') ? 'avatar' : null);

    $specCol  = gdy_table_has_column($pdo, 'opinion_authors', 'specialization') ? 'specialization' : null;

    $photoExpr = $photoCol ? "oa.`{$photoCol}`" : "NULL";
    $specExpr  = $specCol  ? "oa.`{$specCol}`"  : "NULL";

    return [$photoExpr, $specExpr];
}


// -------------------------
// Input
// -------------------------

$q = trim((string)($_GET['q'] ?? ''));
$mode = (string)($_GET['mode'] ?? 'any');
$type = (string)($_GET['type'] ?? 'all');
$section = (string)($_GET['section'] ?? 'all');

$allowedModes = ['any', 'all', 'exact'];
if (!in_array($mode, $allowedModes, true)) {
    $mode = 'any';
}

$allowedTypes = ['all', 'news', 'opinion', 'page', 'author'];
if (!in_array($type, $allowedTypes, true)) {
    $type = 'all';
}

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;
$offset = ($page - 1) * $perPage;

// output cache (anonymous GET only)
$__didOutputCache = false;
$__pageCacheKey = '';
$__ttl = function_exists('gdy_output_cache_ttl') ? gdy_output_cache_ttl() : 0;
if ($__ttl > 0 && function_exists('gdy_should_output_cache') && gdy_should_output_cache() && class_exists('PageCache')) {
    $__pageCacheKey = 'search_' . gdy_page_cache_key('search', [$q, $type, $mode, $page, $perPage]);
    if (PageCache::serveIfCached($__pageCacheKey)) {
        exit;
    }
    ob_start();
    $__didOutputCache = true;
}

// Some MySQL/PDO configurations (especially when native prepared statements
// are used) may fail when LIMIT/OFFSET are bound as parameters. We embed
// them after forcing safe integer values.
$limitSafe  = max(1, min(50, (int)$perPage));
$offsetSafe = max(0, (int)$offset);

$baseUrl = rtrim((string)gdy_base_url(), '/');

// -------------------------
// Helpers
// -------------------------

/**
 * Build a (WHERE ...) fragment + bindings for text search.
 *
 * @param string[] $columns
 * @param string   $paramPrefix
 * @return array{0:string,1:array<string,string>}
 */
function gdy_build_text_where(string $query, string $mode, array $columns, string $paramPrefix): array
{
    $query = trim($query);
    if ($query === '') {
        return ['1=1', []];
    }

    // Tokenize by whitespace (Unicode aware).
    $terms = preg_split('/\s+/u', $query, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $terms = array_values(array_filter(array_map('trim', $terms), static fn($t) => $t !== ''));

    // Exact phrase: single LIKE across all columns.
    if ($mode === 'exact' || count($terms) <= 1) {
        $bindings = [];
        $needle = '%' . $query . '%';
        $ors = [];
        foreach ($columns as $i => $col) {
            $p = ":{$paramPrefix}p{$i}";
            $ors[] = "{$col} LIKE {$p}";
            $bindings[$p] = $needle;
        }
        return ['(' . implode(' OR ', $ors) . ')', $bindings];
    }

    // any: OR between terms. all: AND between terms.
    $join = ($mode === 'all') ? ' AND ' : ' OR ';
    $clauses = [];
    $bindings = [];
    foreach ($terms as $tIdx => $term) {
        $ors = [];
        $needle = '%' . $term . '%';
        foreach ($columns as $cIdx => $col) {
            $p = ":{$paramPrefix}t{$tIdx}_{$cIdx}";
            $ors[] = "{$col} LIKE {$p}";
            $bindings[$p] = $needle;
        }
        $clauses[] = '(' . implode(' OR ', $ors) . ')';
    }

    return ['(' . implode($join, $clauses) . ')', $bindings];
}


/**
 * Make a safe, short excerpt for UI.
 */
function gdy_excerpt(?string $html, int $max = 180): string
{
    $txt = (string)$html;
    $txt = html_entity_decode($txt, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $txt = strip_tags($txt);
    $txt = preg_replace('/\s+/u', ' ', $txt) ?? $txt;
    $txt = trim($txt);

    if (function_exists('mb_strlen') && mb_strlen($txt, 'UTF-8') > $max) {
        $txt = mb_substr($txt, 0, $max, 'UTF-8') . '…';
    } elseif (strlen($txt) > $max) {
        $txt = substr($txt, 0, $max) . '…';
    }
    return $txt;
}

/**
 * Fetch categories for section filter.
 */
function gdy_fetch_categories(PDO $pdo): array
{
    try {
        $stmt = $pdo->query("SELECT slug, name FROM categories WHERE status='active' ORDER BY sort_order ASC, id ASC");
        return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    } catch (Exception $e) {
        return [];
    }
}

// -------------------------
// Data fetch (counts + results)
// -------------------------

$categories = gdy_fetch_categories($pdo);

// Count helpers
$counts = [
    'news'    => 0,
    'opinion' => 0, // optional filter via ?type=opinion
    'pages'   => 0,
    'authors' => 0,
];



// -----------------------------------------------------------------------------
// Short-lived cache for search results (portable, cross-env)
// Caches DB-heavy count + results for a short TTL to reduce load during spikes.
// Control via env: GDY_LIST_CACHE_TTL (seconds). Disable with 0. Bypass with ?nocache=1
// -----------------------------------------------------------------------------
$__listTtl = function_exists('gdy_list_cache_ttl') ? gdy_list_cache_ttl() : 120;
$__listKey = function_exists('gdy_cache_key')
    ? gdy_cache_key('list:search', [$q, $mode, $type, $page, $perPage, $_SERVER['HTTP_HOST'] ?? ''])
    : ('list:search:' . sha1($q . '|' . $type . '|' . $page));

$__cached = null;
if ($__listTtl > 0 && function_exists('gdy_should_bypass_list_cache') && !gdy_should_bypass_list_cache() && class_exists('Cache') && method_exists('Cache', 'get')) {
    $__cached = Cache::get($__listKey);
}

if (is_array($__cached)) {
    $counts   = (array)($__cached['counts'] ?? $counts);
    $total    = (int)($__cached['total'] ?? 0);
    $results  = (array)($__cached['results'] ?? []);
    $typeTotal = (int)($__cached['typeTotal'] ?? $total);
    $pages = max(1, (int)ceil(($typeTotal ?: 0) / $perPage));
    if ($page > $pages) $page = $pages;
    goto render_search;
}

// Build WHERE fragments (fix: undefined vars caused empty results/SQL errors).
$now = date('Y-m-d H:i:s');

// Base filter for published content
$newsWhere = "n.status = 'published' AND n.deleted_at IS NULL AND (n.published_at IS NULL OR n.published_at <= :now)";
$newsBinds = [':now' => $now];

// Text filter (Arabic-safe; uses bound LIKE)
// FIX: كان يتم تمرير الأعمدة كأول باراميتر بدل نص البحث، مما يجعل الشرط يُبنى على قيمة خاطئة.
list($newsExtra, $newsBindsText) = gdy_build_text_where(
    $q,
    $mode,
    ['n.title', 'n.excerpt', 'n.content', 'n.seo_title', 'n.seo_description', 'n.seo_keywords'],
    'n_'
);
$newsBinds = array_merge($newsBinds, $newsBindsText);

// Section filter (category slug)
if ($section !== 'all' && $section !== '') {
    $newsExtra .= " AND c.slug = :section";
    $newsBinds[':section'] = $section;
}

// Flags used later
// "all" يشمل أيضًا الصفحات + كتّاب الرأي كما توضح واجهة البحث
$searchPages   = ($type === 'all' || $type === 'page');
$searchAuthors = ($type === 'all' || $type === 'author');

// Build opinion-author field list in a schema-safe way (older DBs may not have all columns)
[$authorPhotoExpr, $authorSpecExpr] = gdy_author_select_fields($pdo);


try {

    // Articles live in the `news` table. Some installs do NOT have a `type` column,
    // so we derive "opinion" from `opinion_author_id` when present.
    $sqlNewsAll = "SELECT COUNT(*) FROM news n LEFT JOIN categories c ON c.id = n.category_id WHERE {$newsExtra} AND {$newsWhere}";
    $stmt = $pdo->prepare($sqlNewsAll);
    foreach ($newsBinds as $k => $v) { $stmt->bindValue($k, $v); }
    $stmt->execute();
    $counts['news'] = (int)$stmt->fetchColumn();

    $sqlOpinion = "SELECT COUNT(*) FROM news n LEFT JOIN categories c ON c.id = n.category_id WHERE {$newsExtra} AND n.opinion_author_id IS NOT NULL AND {$newsWhere}";
    $stmt = $pdo->prepare($sqlOpinion);
    foreach ($newsBinds as $k => $v) { $stmt->bindValue($k, $v); }
    $stmt->execute();
    $counts['opinion'] = (int)$stmt->fetchColumn();
} catch (Exception $e) {
    // keep zeros
}

// PAGES
[$pageWhere, $pageBinds] = gdy_build_text_where($q, $mode, ['p.title', 'p.content'], 'p_');
try {
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) AS cnt\n" .
        "FROM pages p\n" .
        "WHERE p.status='published' AND {$pageWhere}"
    );
    foreach ($pageBinds as $k => $v) {
        $stmt->bindValue($k, $v, PDO::PARAM_STR);
    }
    $stmt->execute();
    $counts['pages'] = (int)($stmt->fetchColumn() ?: 0);
} catch (Exception $e) {
    // keep zero
}

// AUTHORS (opinion writers)
[$authorWhere, $authorBinds] = gdy_build_text_where($q, $mode, ['oa.name', 'oa.bio'], 'a_');
try {
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) AS cnt\n" .
        "FROM opinion_authors oa\n" .
        "WHERE oa.is_active = 1 AND {$authorWhere}"
    );
    foreach ($authorBinds as $k => $v) {
        $stmt->bindValue($k, $v, PDO::PARAM_STR);
    }
    $stmt->execute();
    $counts['authors'] = (int)($stmt->fetchColumn() ?: 0);
} catch (Exception $e) {
    // keep zero
}

$total = (int)($counts['news'] + $counts['opinion'] + $counts['pages'] + $counts['authors']);

// -------------------------
// Fetch results (respect type filter)
// -------------------------

$results = [];

/**
 * Normalize result rows for the UI.
 *
 * @param array<string,mixed> $row
 * @return array{kind:string,title:string,url:string,image:?string,excerpt:string,created_at:?string,category_slug:?string,category_name:?string,news_id:?int,comments_count:?int}
 */
function gdy_normalize_row(array $row, string $baseUrl): array
{
    $kind = (string)($row['kind'] ?? 'news');
    $slug = (string)($row['slug'] ?? '');
    $title = (string)($row['title'] ?? '');
    $image = $row['image'] ?? null;
    $createdAt = $row['created_at'] ?? null;
    $categorySlug = $row['category_slug'] ?? null;
    $categoryName = $row['category_name'] ?? null;

    if ($kind === 'page') {
        $url = $baseUrl . '/ar/page?slug=' . rawurlencode($slug);
    } elseif ($kind === 'author') {
        $url = $baseUrl . '/ar/author?slug=' . rawurlencode($slug);
    } elseif ($kind === 'opinion') {
        $url = $baseUrl . '/ar/news?slug=' . rawurlencode($slug);
    } else {
        $url = $baseUrl . '/ar/news?slug=' . rawurlencode($slug);
    }

    return [
        'kind' => $kind,
        'title' => $title,
        'url' => $url,
        'image' => $image ? (string)$image : null,
        'excerpt' => gdy_excerpt((string)($row['excerpt'] ?? '')),
        'created_at' => $createdAt ? (string)$createdAt : null,
        'category_slug' => $categorySlug ? (string)$categorySlug : null,
        'category_name' => $categoryName ? (string)$categoryName : null,
        'news_id' => isset($row['news_id']) ? (int)$row['news_id'] : null,
        'comments_count' => isset($row['comments_count']) ? (int)$row['comments_count'] : null,
    ];
}

try {
    if ($type === 'page') {
        $sql =
            "SELECT 'page' AS kind, p.title, p.slug, NULL AS image, p.content AS excerpt, p.updated_at AS created_at, NULL AS category_slug, NULL AS category_name\n" .
            "FROM pages p\n" .
            "WHERE p.status='published' AND {$pageWhere}\n" .
            "ORDER BY p.updated_at DESC\n" .
            "LIMIT {$limitSafe} OFFSET {$offsetSafe}";

        $stmt = $pdo->prepare($sql);
        foreach ($pageBinds as $k => $v) {
            $stmt->bindValue($k, $v, PDO::PARAM_STR);
        }
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($rows as $row) {
            $results[] = gdy_normalize_row($row, $baseUrl);
        }
    } elseif ($type === 'author') {
        $sql =
            "SELECT 'author' AS kind, oa.name AS title, oa.slug, {$authorPhotoExpr} AS image, oa.bio AS excerpt, oa.created_at, NULL AS category_slug, NULL AS category_name\n" .
            "FROM opinion_authors oa\n" .
            "WHERE oa.is_active = 1 AND {$authorWhere}\n" .
            "ORDER BY oa.created_at DESC\n" .
            "LIMIT {$limitSafe} OFFSET {$offsetSafe}";

        $stmt = $pdo->prepare($sql);
        foreach ($authorBinds as $k => $v) {
            $stmt->bindValue($k, $v, PDO::PARAM_STR);
        }
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($rows as $row) {
            $results[] = gdy_normalize_row($row, $baseUrl);
        }
    } elseif ($type === 'news' || $type === 'opinion') {
        $tBinds = $newsBinds;
        $extra = $newsExtra;
        if ($type === 'opinion') {
            $extra .= ' AND n.opinion_author_id IS NOT NULL';
        }

        $sql =
            "SELECT CASE WHEN n.opinion_author_id IS NOT NULL THEN 'opinion' ELSE 'news' END AS kind, n.id AS news_id, n.title, n.slug, n.image, n.excerpt, n.created_at, c.slug AS category_slug, c.name AS category_name\n" .
            "FROM news n\n" .
            "LEFT JOIN categories c ON c.id = n.category_id\n" .
            "WHERE {$extra} AND {$newsWhere}\n" .
            "ORDER BY n.created_at DESC\n" .
            "LIMIT {$limitSafe} OFFSET {$offsetSafe}";

        $stmt = $pdo->prepare($sql);
        foreach ($tBinds as $k => $v) {
            $stmt->bindValue($k, $v, PDO::PARAM_STR);
        }
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($rows as $row) {
            $results[] = gdy_normalize_row($row, $baseUrl);
        }
    } else {
        // all: UNION across news/opinion + pages + authors
        $unionSql =
            "(\n" .
            "  SELECT CASE WHEN n.opinion_author_id IS NOT NULL THEN 'opinion' ELSE 'news' END AS kind, n.id AS news_id, n.title, n.slug, n.image, n.excerpt, n.created_at, c.slug AS category_slug, c.name AS category_name\n" .
            "  FROM news n\n" .
            "  LEFT JOIN categories c ON c.id = n.category_id\n" .
            "  WHERE {$newsExtra} AND {$newsWhere}\n" .
            ")\n" .
            "UNION ALL\n" .
            "(\n" .
            "  SELECT 'page' AS kind, p.title, p.slug, NULL AS image, p.content AS excerpt, p.updated_at AS created_at, NULL AS category_slug, NULL AS category_name\n" .
            "  FROM pages p\n" .
            "  WHERE p.status='published' AND {$pageWhere}\n" .
            ")\n" .
            "UNION ALL\n" .
            "(\n" .
            "  SELECT 'author' AS kind, oa.name AS title, oa.slug, {$authorPhotoExpr} AS image, oa.bio AS excerpt, oa.created_at AS created_at, NULL AS category_slug, NULL AS category_name\n" .
            "  FROM opinion_authors oa\n" .
            "  WHERE oa.is_active = 1 AND {$authorWhere}\n" .
            ")";

        $sql = "SELECT * FROM (\n{$unionSql}\n) u ORDER BY u.created_at DESC LIMIT {$limitSafe} OFFSET {$offsetSafe}";

        $stmt = $pdo->prepare($sql);
        // Merge bindings (param names are unique due to prefixes)
        foreach ([$newsBinds, $pageBinds, $authorBinds] as $binds) {
            foreach ($binds as $k => $v) {
                $stmt->bindValue($k, $v, PDO::PARAM_STR);
            }
        }
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($rows as $row) {
            $results[] = gdy_normalize_row($row, $baseUrl);
        }
    }
} catch (Exception $e) {
    // In case of any DB errors, show empty results gracefully.
    // Also log the exception so the hosting error log reveals the actual root cause.
    error_log('[GodyarSearch] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    $results = [];
}

// Performance: attach comment counts for news/opinion results (single query)
if (function_exists('gdy_comment_counts_for_news') && $pdo instanceof \PDO && !empty($results)) {
    try {
        $ids = [];
        foreach ($results as $r) {
            if (($r['kind'] ?? '') === 'news' || ($r['kind'] ?? '') === 'opinion') {
                $nid = (int)($r['news_id'] ?? 0);
                if ($nid > 0) $ids[] = $nid;
            }
        }
        $ids = array_values(array_unique($ids));
        if ($ids) {
            $map = gdy_comment_counts_for_news($pdo, $ids);
            foreach ($results as &$r) {
                if (($r['kind'] ?? '') === 'news' || ($r['kind'] ?? '') === 'opinion') {
                    $nid = (int)($r['news_id'] ?? 0);
                    $r['comments_count'] = (int)($map[$nid] ?? 0);
                }
            }
            unset($r);
        }
    } catch (Exception $e) {
        // ignore
    }
}

// Pagination: total depends on type
if ($type === 'page') {
    $typeTotal = $counts['pages'];
} elseif ($type === 'author') {
    $typeTotal = $counts['authors'];
} elseif ($type === 'opinion') {
    $typeTotal = $counts['opinion'];
} elseif ($type === 'news') {
    // news bucket includes non-opinion rows; still OK.
    $typeTotal = $counts['news'];
} else {
    $typeTotal = $total;
}

$pages = max(1, (int)ceil(($typeTotal ?: 0) / $perPage));
if ($page > $pages) {
    $page = $pages;
}

// Render

// Save to cache (best-effort)
if ($__listTtl > 0 && class_exists('Cache') && method_exists('Cache', 'put')) {
    try {
        Cache::put($__listKey, [
            'counts'    => $counts,
            'total'     => (int)($total ?? 0),
            'results'   => $results,
            'typeTotal' => (int)($typeTotal ?? 0),
        ], (int)$__listTtl);
    } catch (Exception $e) {
        // ignore
    }
}

render_search:
require __DIR__ . '/../views/search.php';

if ($__didOutputCache && $__pageCacheKey !== '') {
    PageCache::store($__pageCacheKey, $__ttl);
    @ob_end_flush();
}
