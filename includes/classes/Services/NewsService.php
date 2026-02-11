<?php
declare(strict_types=1);

namespace Godyar\Services;

use PDO;
use Throwable;

/**
 * NewsService (compat + strict-PDO safe)
 * ------------------------------------
 * إصلاح جذري لخطأ:
 *   SQLSTATE[HY093]: Invalid parameter number
 *
 * الفكرة:
 * - تجنّب named placeholders بالكامل داخل هذا الملف (بعض بيئات PDO/Drivers صارمة).
 * - تجنّب bindValue/bindParam.
 * - الاعتماد على positional placeholders (?) + execute($params).
 * - التحقق من وجود الجداول/الأعمدة بدون information_schema (قد يكون محجوباً) وبدون باراميترات.
 */
final class NewsService
{
    private PDO $pdo;

    private const VERSION = 'v7-2026-01-31';
    private static bool $versionLogged = false;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        // لا تُسجّل نسخة الخدمة إلا عند تفعيل وضع التصحيح (Debug)
        // لتجنب إغراق سجل الأخطاء برسائل متكررة في كل طلب.
        if (!self::$versionLogged && $this->isDebugEnabled()) {
            self::$versionLogged = true;
            error_log('[NewsService] loaded ' . self::VERSION);
        }
    }

    /**
     * تفعيل الـ Debug عبر:
     * - define('GDY_DEBUG', true);
     * - أو متغير بيئة GDY_DEBUG=1
     */
    private function isDebugEnabled(): bool
    {
        if (defined('GDY_DEBUG') && GDY_DEBUG) return true;
        $env = getenv('GDY_DEBUG');
        return ($env === '1' || strtolower((string)$env) === 'true');
    }

/**
 * Increment views counter for a news item.
 * - Prepared statement (safe).
 * - Schema-tolerant: no-op if "views" column missing.
 */
public function incrementViews(int $newsId): bool
{
    $newsId = (int)$newsId;
    if ($newsId <= 0) return false;
    if (!$this->tableExists('news')) return false;
    if (!$this->hasColumn('news', 'views')) return false;

    try {
        $sql = 'UPDATE news SET views = COALESCE(views, 0) + 1 WHERE id = ?';
        $st = $this->pdo->prepare($sql);
        $ok = $st->execute([$newsId]);
        return ($ok === TRUE);
    } catch (Exception $e) {
        $this->log('[NewsService] incrementViews error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Fetch related news by category (excluding current post).
 *
 * @return array<int, array<string,mixed>>
 */
public function relatedByCategory(int $categoryId, int $excludeId, int $limit = 6): array
{
    $categoryId = (int)$categoryId;
    $excludeId = (int)$excludeId;
    $limit = (int)$limit;

    if ($categoryId <= 0 || $limit <= 0) return [];
    if (!$this->tableExists('news')) return [];
    if (!$this->hasColumn('news', 'category_id')) return [];

    try {
        $where = 'category_id = ? AND id <> ?';
        $params = [$categoryId, $excludeId];

        // Only published
        $where .= ' AND ' . $this->publishedWhere();

        $order = $this->dateColumn();
        $sql = 'SELECT * FROM news WHERE ' . $where . ' ORDER BY ' . $this->qi($order) . ' DESC LIMIT ' . $limit;

        $st = $this->pdo->prepare($sql);
        $st->execute($params);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return $rows;
    } catch (Exception $e) {
        $this->log('[NewsService] relatedByCategory error: ' . $e->getMessage());
        return [];
    }
}

/**
 * Latest news list.
 *
 * @return array<int, array<string,mixed>>
 */
public function latest(int $limit = 10, bool $includeDrafts = false): array
{
    $limit = (int)$limit;
    if ($limit <= 0) $limit = 10;
    if (!$this->tableExists('news')) return [];

    try {
        $where = '1=1';
        if (!$includeDrafts) {
            $where = $this->publishedWhere();
        }

        $order = $this->dateColumn();
        $sql = 'SELECT * FROM news WHERE ' . $where . ' ORDER BY ' . $this->qi($order) . ' DESC LIMIT ' . $limit;
        $st = $this->pdo->prepare($sql);
        $st->execute([]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        return $rows;
    } catch (Exception $e) {
        $this->log('[NewsService] latest error: ' . $e->getMessage());
        return [];
    }
}

/**
 * Most read list (falls back to latest if "views" column missing).
 *
 * @return array<int, array<string,mixed>>
 */
public function mostRead(int $limit = 10): array
{
    $limit = (int)$limit;
    if ($limit <= 0) $limit = 10;
    if (!$this->tableExists('news')) return [];

    try {
        $orderCol = $this->hasColumn('news', 'views') ? 'views' : $this->dateColumn();
        $where = $this->publishedWhere();

        $sql = 'SELECT * FROM news WHERE ' . $where . ' ORDER BY ' . $this->qi($orderCol) . ' DESC LIMIT ' . $limit;
        $st = $this->pdo->prepare($sql);
        $st->execute([]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        return $rows;
    } catch (Exception $e) {
        $this->log('[NewsService] mostRead error: ' . $e->getMessage());
        return [];
    }
}

    /** @return array<string,mixed>|null */
    public function findById(int $id, bool $includeDrafts = false): ?array
    {
        $id = (int)$id;
        if ($id <= 0 || !$this->tableExists('news')) return null;

        try {
            $where = 'id = ?';
            $params = [$id];

            if (!$includeDrafts) {
                $where .= ' AND ' . $this->publishedWhere();
            }

            $sql = 'SELECT * FROM news WHERE ' . $where . ' LIMIT 1';
            $st = $this->pdo->prepare($sql);
            $ph2 = substr_count($sql, '?');
            if ($ph2 !== count($params)) {
                $this->log('[NewsService] placeholder mismatch (select) ph=' . $ph2 . ' params=' . count($params));
                if (count($params) > $ph2) $params = array_slice($params, 0, $ph2);
            }
            $st->execute($params);
            $row = $st->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (Exception $e) {
            error_log('[NewsService] findById error: ' . $e->getMessage());
            return null;
        }
    }

    /** @return array<string,mixed>|null */
    public function findBySlug(string $slug, bool $includeDrafts = false): ?array
    {
        $slug = trim($slug);
        if ($slug === '' || !$this->tableExists('news') || !$this->hasColumn('news', 'slug')) return null;

        try {
            $where = 'slug = ?';
            $params = [$slug];

            if (!$includeDrafts) {
                $where .= ' AND ' . $this->publishedWhere();
            }

            $sql = 'SELECT * FROM news WHERE ' . $where . ' LIMIT 1';
            $st = $this->pdo->prepare($sql);
            $ph2 = substr_count($sql, '?');
            if ($ph2 !== count($params)) {
                $this->log('[NewsService] placeholder mismatch (select) ph=' . $ph2 . ' params=' . count($params));
                if (count($params) > $ph2) $params = array_slice($params, 0, $ph2);
            }
            $st->execute($params);
            $row = $st->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (Exception $e) {
            error_log('[NewsService] findBySlug error: ' . $e->getMessage());
            return null;
        }
    }

    /** @return array<string,mixed>|null */
    public function findBySlugOrId(string $param, bool $includeDrafts = false): ?array
    {
        $param = trim($param);
        if ($param === '') return null;
        if (ctype_digit($param)) return $this->findById((int)$param, $includeDrafts);
        return $this->findBySlug($param, $includeDrafts);
    }

    /**
     * Compatibility helper used by some legacy routes.
     * Returns the numeric news id for a slug (or 0 if not found).
     */
    public function idBySlug(string $slug, bool $includeDrafts = false): int
    {
        $row = $this->findBySlug($slug, $includeDrafts);
        return (int)($row['id'] ?? 0);
    }

    /**
     * البحث في جدول news فقط (يدعم title/content/excerpt إن وجدت)
     * @return array{items: array<int,array<string,mixed>>, total:int, total_pages:int, page:int, per_page:int, counts: array<string,int>}
     */
    public function search(string $q, int $page = 1, int $perPage = 12, array $filters = []): array
    {
        $q = trim($q);
        $page = max(1, (int)$page);
        $perPage = max(1, min(60, (int)$perPage));

        if ($q === '' || !$this->tableExists('news')) {
            return ['items'=>[], 'total'=>0, 'total_pages'=>1, 'page'=>$page, 'per_page'=>$perPage, 'counts'=>[]];
        }

        $titleCol   = $this->hasColumn('news', 'title') ? 'title' : ($this->hasColumn('news', 'name') ? 'name' : null);
        $bodyCol    = $this->hasColumn('news', 'content') ? 'content' : ($this->hasColumn('news', 'body') ? 'body' : null);
        $excerptCol = $this->hasColumn('news', 'excerpt') ? 'excerpt' : null;

        $typeCol = $this->hasColumn('news', 'type') ? 'type' : null;
        $catCol  = $this->hasColumn('news', 'category_id') ? 'category_id' : null;
        $dateCol = $this->dateColumn();

        if ($titleCol === null && $bodyCol === null && $excerptCol === null) {
            return ['items'=>[], 'total'=>0, 'total_pages'=>1, 'page'=>$page, 'per_page'=>$perPage, 'counts'=>[]];
        }

        // split terms (Arabic/English) safely
        $terms = preg_split('/\s+/u', $q, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if (!$terms) $terms = [$q];

        $match = (string)($filters['match'] ?? 'all');
        $matchAll = ($match !== 'any');

        $params = [];
        $likeClauses = [];

        foreach ($terms as $t) {
            $t = trim((string)$t);
            if ($t === '') continue;

            $value = '%' . $t . '%';
            $cols = [];

            if ($titleCol !== null)  { $cols[] = "$titleCol LIKE ?";   $params[] = $value; }
            if ($bodyCol !== null)   { $cols[] = "$bodyCol LIKE ?";    $params[] = $value; }
            if ($excerptCol !== null){ $cols[] = "$excerptCol LIKE ?"; $params[] = $value; }

            if ($cols) $likeClauses[] = '(' . implode(' OR ', $cols) . ')';
        }

        $where = $this->publishedWhere();
        if ($likeClauses) {
            $where .= ' AND (' . implode($matchAll ? ' AND ' : ' OR ', $likeClauses) . ')';
        }

        // type filter (news/opinion/..)
        if ($typeCol !== null) {
            $type = (string)($filters['type'] ?? 'all');
            if ($type !== '' && $type !== 'all') {
                $where .= ' AND ' . $typeCol . ' = ?';
                $params[] = $type;
            }
        }

        // category filter
        if ($catCol !== null) {
            $cid = (int)($filters['category_id'] ?? 0);
            if ($cid > 0) {
                $where .= ' AND ' . $catCol . ' = ?';
                $params[] = $cid;
            }
        }

        // date filter (if controller/service passes date_from/date_to)
        $df = (string)($filters['date_from'] ?? '');
        $dt = (string)($filters['date_to'] ?? '');
        if ($dateCol !== 'id' && $df !== '' && $dt !== '') {
            $where .= ' AND DATE(' . $dateCol . ') BETWEEN ? AND ?';
            $params[] = $df;
            $params[] = $dt;
        }

        $offset = ($page - 1) * $perPage;

        try {
            $cntSql = 'SELECT COUNT(*) FROM news WHERE ' . $where;
            $cnt = $this->pdo->prepare($cntSql);
            $ph = substr_count($cntSql, '?');
            if ($ph !== count($params)) {
                $this->log('[NewsService] placeholder mismatch (count) ph=' . $ph . ' params=' . count($params));
                // Trim or pad defensively to avoid HY093 on some hosts
                if (count($params) > $ph) $params = array_slice($params, 0, $ph);
            }
            $cnt->execute($params);
            $total = (int)($cnt->fetchColumn() ?: 0);
            $pages = max(1, (int)ceil($total / $perPage));

            $sql = 'SELECT * FROM news WHERE ' . $where
                . ' ORDER BY ' . $dateCol . ' DESC, id DESC'
                . ' LIMIT ' . (int)$perPage . ' OFFSET ' . (int)$offset;

            $st = $this->pdo->prepare($sql);
            $ph2 = substr_count($sql, '?');
            if ($ph2 !== count($params)) {
                $this->log('[NewsService] placeholder mismatch (select) ph=' . $ph2 . ' params=' . count($params));
                if (count($params) > $ph2) $params = array_slice($params, 0, $ph2);
            }
            $st->execute($params);
            $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

            // counts by type (optional)
            $counts = [];
            if ($typeCol !== null) {
                try {
                    $c2 = $this->pdo->query('SELECT ' . $typeCol . ' AS t, COUNT(*) AS c FROM news WHERE ' . $this->publishedWhere() . ' GROUP BY ' . $typeCol);
                    $r2 = $c2 ? ($c2->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
                    foreach ($r2 as $r) {
                        $k = (string)($r['t'] ?? '');
                        if ($k === '') continue;
                        $counts[$k] = (int)($r['c'] ?? 0);
                    }
                } catch (Exception $e2) {
                    // ignore
                }
            }

            $items = [];
            foreach ($rows as $row) {
                $items[] = $this->normalizeRow($row);
            }

            return ['items'=>$items, 'total'=>$total, 'total_pages'=>$pages, 'page'=>$page, 'per_page'=>$perPage, 'counts'=>$counts];
        } catch (Exception $e) {
            // Diagnostic: count placeholders vs params (helps if hosting still throws HY093)
            $this->log('[NewsService] search error: ' . $e->getMessage() . ' | params=' . count($params) . ' | where=' . $where);
            return ['items'=>[], 'total'=>0, 'total_pages'=>1, 'page'=>$page, 'per_page'=>$perPage, 'counts'=>[]];
        }
    }

    // -------------------------
    // Helpers / internals
    // -------------------------

    private function tableExists(string $table): bool
    {
        $table = trim($table);
        if ($table === '') return false;

        // No placeholders: avoid HY093 entirely
        try {
            $qt = $this->qi($table);
            $this->pdo->query('SELECT 1 FROM ' . $qt . ' LIMIT 1');
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    private function hasColumn(string $table, string $col): bool
    {
        $table = trim($table);
        $col = trim($col);
        if ($table === '' || $col === '') return false;

        try {
            $qt = $this->qi($table);
            $qc = $this->qi($col);
            $this->pdo->query('SELECT ' . $qc . ' FROM ' . $qt . ' LIMIT 1');
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    private function dateColumn(): string
    {
        if ($this->hasColumn('news', 'published_at')) return 'published_at';
        if ($this->hasColumn('news', 'publish_at')) return 'publish_at';
        if ($this->hasColumn('news', 'created_at')) return 'created_at';
        return 'id';
    }

    /**
     * Published predicate with schema tolerance.
     */
    private function publishedWhere(string $alias = ''): string
    {
        $p = $alias !== '' ? ($alias . '.') : '';

        if ($this->hasColumn('news', 'status')) return $p . "status = 'published'";
        if ($this->hasColumn('news', 'is_published')) return $p . 'is_published = 1';
        if ($this->hasColumn('news', 'published')) return $p . 'published = 1';

        return '1=1';
    }

    /** @return array<string,mixed> */
    private function normalizeRow(array $row): array
    {
        $id = isset($row['id']) ? (int)$row['id'] : 0;
        $slug = (string)($row['slug'] ?? '');
        $title = (string)($row['title'] ?? ($row['name'] ?? ''));
        $content = (string)($row['excerpt'] ?? ($row['content'] ?? ($row['body'] ?? '')));

        // kind: keep "news" for view
        $kind = 'news';
        $type = (string)($row['type'] ?? '');
        if ($type === 'opinion') $kind = 'news'; // view badge remains "خبر" anyway

        // URL: relative only (view will prefix baseUrl)
        // prefer slug if exists else id
        if ($slug !== '') {
            $url = '/ar/news?slug=' . rawurlencode($slug);
        } elseif ($id > 0) {
            $url = '/ar/news?id=' . $id;
        } else {
            $url = '';
        }

        // image: keep absolute if stored as absolute, else return as-is
        $img = (string)($row['image'] ?? ($row['thumb'] ?? ($row['thumbnail'] ?? '')));

        $created = (string)($row['published_at'] ?? ($row['created_at'] ?? ''));

        return [
            'kind' => $kind,
            'title' => $title,
            'url' => $url,
            'image' => $img,
            'excerpt' => $content,
            'created_at' => $created,
            'category_slug' => (string)($row['category_slug'] ?? ''),
        ];
    }

    private function qi(string $ident): string
    {
        // allow only [A-Za-z0-9_], fallback to backtick-escape safe subset
        if (!preg_match('/^[A-Za-z0-9_]+$/', $ident)) {
            // remove unsafe chars
            $ident = preg_replace('/[^A-Za-z0-9_]/', '', $ident) ?: '';
        }
        return '`' . str_replace('`', '``', $ident) . '`';
    }

    private function log(string $msg): void
    {
        error_log($msg);
    }
}
