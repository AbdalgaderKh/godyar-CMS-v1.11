<?php
declare(strict_types=1);

namespace Godyar\Services;

use PDO;
use PDOException;
use Throwable;

/**
 * NewsService
 * ----------
 * خدمة موحّدة لجلب الأخبار (Published/Draft) مع دعم الأرشيف والبحث.
 *
 * أهداف هذا الملف:
 * - إزالة الاعتماد على ملفات متضررة/مكررة
 * - توفير واجهة ثابتة تستخدمها Controllers (app.php) بدون أخطاء Parse/Fatal
 * - حماية SQL عبر Prepared Statements + White-listing
 */
final class NewsService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * @return array<string,mixed>|null
     */
    public function findById(int $id, bool $includeDrafts = false): ?array
    {
        $id = (int)$id;
        if ($id <= 0 || !$this->tableExists('news')) {
            return null;
        }

        try {
            $where = 'id = :id';
            if (!$includeDrafts) {
                $where .= ' AND ' . $this->publishedWhere();
            }

            $stmt = $this->pdo->prepare('SELECT * FROM news WHERE ' . $where . ' LIMIT 1');
            $stmt->execute([':id' => $id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
            return $row ?: null;
        } catch (Throwable $e) {
            error_log('[NewsService] findById error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * @return array<string,mixed>|null
     */
    public function findBySlug(string $slug, bool $includeDrafts = false): ?array
    {
        $slug = trim($slug);
        if ($slug === '' || !$this->tableExists('news')) {
            return null;
        }

        $slugCol = $this->hasColumn('news', 'slug') ? 'slug' : null;
        if ($slugCol === null) {
            return null;
        }

        try {
            $where = "$slugCol = :slug";
            if (!$includeDrafts) {
                $where .= ' AND ' . $this->publishedWhere();
            }

            $stmt = $this->pdo->prepare('SELECT * FROM news WHERE ' . $where . ' LIMIT 1');
            $stmt->execute([':slug' => $slug]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
            return $row ?: null;
        } catch (Throwable $e) {
            error_log('[NewsService] findBySlug error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * @return array<string,mixed>|null
     */
    public function findBySlugOrId(string $param, bool $includeDrafts = false): ?array
    {
        $param = trim($param);
        if ($param === '') return null;
        if (ctype_digit($param)) {
            return $this->findById((int)$param, $includeDrafts);
        }
        return $this->findBySlug($param, $includeDrafts);
    }

    public function idBySlug(string $slug): ?int
    {
        $slug = trim($slug);
        if ($slug === '' || !$this->tableExists('news') || !$this->hasColumn('news', 'slug')) {
            return null;
        }
        try {
            $stmt = $this->pdo->prepare('SELECT id FROM news WHERE slug = :s LIMIT 1');
            $stmt->execute([':s' => $slug]);
            $id = $stmt->fetchColumn();
            return $id !== false ? (int)$id : null;
        } catch (Throwable $e) {
            error_log('[NewsService] idBySlug error: ' . $e->getMessage());
            return null;
        }
    }

    public function slugById(int $id): ?string
    {
        $id = (int)$id;
        if ($id <= 0 || !$this->tableExists('news') || !$this->hasColumn('news', 'slug')) {
            return null;
        }
        try {
            $stmt = $this->pdo->prepare('SELECT slug FROM news WHERE id = :id LIMIT 1');
            $stmt->execute([':id' => $id]);
            $slug = $stmt->fetchColumn();
            $slug = $slug !== false ? (string)$slug : '';
            return $slug !== '' ? $slug : null;
        } catch (Throwable $e) {
            error_log('[NewsService] slugById error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function latest(int $limit = 10, bool $includeDrafts = false): array
    {
        $limit = max(1, min(50, (int)$limit));
        if (!$this->tableExists('news')) return [];

        $dateCol = $this->dateColumn();

        $where = $includeDrafts ? '1=1' : $this->publishedWhere();
        $sql = 'SELECT * FROM news WHERE ' . $where . ' ORDER BY ' . $dateCol . ' DESC, id DESC LIMIT ' . $limit;

        try {
            $stmt = $this->pdo->query($sql);
            return $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
        } catch (Throwable $e) {
            error_log('[NewsService] latest error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function mostRead(int $limit = 10): array
    {
        $limit = max(1, min(50, (int)$limit));
        if (!$this->tableExists('news')) return [];

        $viewsCol = $this->hasColumn('news', 'views') ? 'views' : ($this->hasColumn('news', 'view_count') ? 'view_count' : null);
        if ($viewsCol === null) {
            return $this->latest($limit);
        }

        $dateCol = $this->dateColumn();
        $sql = 'SELECT * FROM news WHERE ' . $this->publishedWhere() . ' ORDER BY ' . $viewsCol . ' DESC, ' . $dateCol . ' DESC, id DESC LIMIT ' . $limit;

        try {
            $stmt = $this->pdo->query($sql);
            return $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
        } catch (Throwable $e) {
            error_log('[NewsService] mostRead error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function relatedByCategory(int $categoryId, int $excludeNewsId, int $limit = 6): array
    {
        $categoryId = (int)$categoryId;
        $excludeNewsId = (int)$excludeNewsId;
        $limit = max(1, min(20, (int)$limit));
        if ($categoryId <= 0 || !$this->tableExists('news') || !$this->hasColumn('news', 'category_id')) {
            return [];
        }

        $dateCol = $this->dateColumn();
        $sql = 'SELECT * FROM news WHERE category_id = :cid AND id <> :nid AND ' . $this->publishedWhere() . ' ORDER BY ' . $dateCol . ' DESC, id DESC LIMIT ' . $limit;
        try {
            $st = $this->pdo->prepare($sql);
            $st->bindValue(':cid', $categoryId, PDO::PARAM_INT);
            $st->bindValue(':nid', $excludeNewsId, PDO::PARAM_INT);
            $st->execute();
            return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            error_log('[NewsService] relatedByCategory error: ' . $e->getMessage());
            return [];
        }
    }

    public function incrementViews(int $newsId): void
    {
        $newsId = (int)$newsId;
        if ($newsId <= 0 || !$this->tableExists('news')) return;

        $viewsCol = $this->hasColumn('news', 'views') ? 'views' : ($this->hasColumn('news', 'view_count') ? 'view_count' : null);
        if ($viewsCol === null) return;

        try {
            $sql = 'UPDATE news SET ' . $viewsCol . ' = COALESCE(' . $viewsCol . ',0) + 1 WHERE id = :id';
            $st = $this->pdo->prepare($sql);
            $st->execute([':id' => $newsId]);
        } catch (Throwable $e) {
            // ignore
        }
    }

    /**
     * Archive listing
     *
     * @return array{items:array<int,array<string,mixed>>, total:int, total_pages:int, page:int, per_page:int}
     */
    public function archive(int $page = 1, int $perPage = 12, ?int $year = null, ?int $month = null): array
    {
        $page = max(1, (int)$page);
        $perPage = max(1, min(60, (int)$perPage));
        if (!$this->tableExists('news')) {
            return ['items' => [], 'total' => 0, 'total_pages' => 1, 'page' => $page, 'per_page' => $perPage];
        }

        $dateCol = $this->dateColumn();
        $where = $this->publishedWhere();
        $params = [];

        if ($year !== null && $year > 0 && $dateCol !== 'id') {
            $where .= ' AND YEAR(' . $dateCol . ') = :y';
            $params[':y'] = (int)$year;
        }
        if ($month !== null && $month > 0 && $month <= 12 && $dateCol !== 'id') {
            $where .= ' AND MONTH(' . $dateCol . ') = :m';
            $params[':m'] = (int)$month;
        }

        $offset = ($page - 1) * $perPage;

        try {
            $cnt = $this->pdo->prepare('SELECT COUNT(*) FROM news WHERE ' . $where);
            foreach ($params as $k => $v) {
                $cnt->bindValue($k, (int)$v, PDO::PARAM_INT);
            }
            $cnt->execute();
            $total = (int)($cnt->fetchColumn() ?: 0);
            $pages = max(1, (int)ceil($total / $perPage));

            $sql = 'SELECT * FROM news WHERE ' . $where . ' ORDER BY ' . $dateCol . ' DESC, id DESC LIMIT :lim OFFSET :off';
            $st = $this->pdo->prepare($sql);
            foreach ($params as $k => $v) {
                $st->bindValue($k, (int)$v, PDO::PARAM_INT);
            }
            $st->bindValue(':lim', $perPage, PDO::PARAM_INT);
            $st->bindValue(':off', $offset, PDO::PARAM_INT);
            $st->execute();
            $items = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

            return ['items' => $items, 'total' => $total, 'total_pages' => $pages, 'page' => $page, 'per_page' => $perPage];
        } catch (Throwable $e) {
            error_log('[NewsService] archive error: ' . $e->getMessage());
            return ['items' => [], 'total' => 0, 'total_pages' => 1, 'page' => $page, 'per_page' => $perPage];
        }
    }

    /**
     * Search (with pagination + optional filters)
     *
     * @param array{type?:string,category_id?:int,date_from?:string,date_to?:string,match?:string} $filters
     * @return array{items:array<int,array<string,mixed>>, total:int, total_pages:int, page:int, per_page:int, counts:array<string,int>}
     */
    public function search(string $q, int $page = 1, int $perPage = 12, array $filters = []): array
    {
        $q = trim($q);
        $page = max(1, (int)$page);
        $perPage = max(1, min(60, (int)$perPage));

        if ($q === '' || !$this->tableExists('news')) {
            return ['items' => [], 'total' => 0, 'total_pages' => 1, 'page' => $page, 'per_page' => $perPage, 'counts' => []];
        }

        $titleCol = $this->hasColumn('news', 'title') ? 'title' : ($this->hasColumn('news', 'name') ? 'name' : null);
        $bodyCol  = $this->hasColumn('news', 'content') ? 'content' : ($this->hasColumn('news', 'body') ? 'body' : null);
        $typeCol  = $this->hasColumn('news', 'type') ? 'type' : null;
        $catCol   = $this->hasColumn('news', 'category_id') ? 'category_id' : null;
        $dateCol  = $this->dateColumn();

        if ($titleCol === null && $bodyCol === null) {
            return ['items' => [], 'total' => 0, 'total_pages' => 1, 'page' => $page, 'per_page' => $perPage, 'counts' => []];
        }

        $terms = preg_split('/\s+/u', $q, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $match = (string)($filters['match'] ?? 'all');
        $matchAll = ($match !== 'any');

        $likeClauses = [];
        $params = [];

        $i = 0;
        foreach ($terms as $t) {
            $i++;
            $p = ':q' . $i;
            $params[$p] = '%' . $t . '%';
            $cols = [];
            if ($titleCol !== null) $cols[] = "$titleCol LIKE $p";
            if ($bodyCol !== null)  $cols[] = "$bodyCol LIKE $p";
            $likeClauses[] = '(' . implode(' OR ', $cols) . ')';
        }

        $where = $this->publishedWhere();
        if ($likeClauses) {
            $where .= ' AND (' . implode($matchAll ? ' AND ' : ' OR ', $likeClauses) . ')';
        }

        if ($typeCol !== null) {
            $type = (string)($filters['type'] ?? 'all');
            if ($type !== 'all' && $type !== '') {
                $where .= ' AND ' . $typeCol . ' = :type';
                $params[':type'] = $type;
            }
        }

        if ($catCol !== null) {
            $cid = (int)($filters['category_id'] ?? 0);
            if ($cid > 0) {
                $where .= ' AND ' . $catCol . ' = :cid';
                $params[':cid'] = $cid;
            }
        }

        $df = (string)($filters['date_from'] ?? '');
        $dt = (string)($filters['date_to'] ?? '');
        if ($dateCol !== 'id' && $df !== '' && $dt !== '') {
            $where .= ' AND DATE(' . $dateCol . ') BETWEEN :df AND :dt';
            $params[':df'] = $df;
            $params[':dt'] = $dt;
        }

        $offset = ($page - 1) * $perPage;

        try {
            $cnt = $this->pdo->prepare('SELECT COUNT(*) FROM news WHERE ' . $where);
            foreach ($params as $k => $v) {
                if ($k === ':cid') {
                    $cnt->bindValue($k, (int)$v, PDO::PARAM_INT);
                } else {
                    $cnt->bindValue($k, $v);
                }
            }
            $cnt->execute();
            $total = (int)($cnt->fetchColumn() ?: 0);
            $pages = max(1, (int)ceil($total / $perPage));

            $sql = 'SELECT * FROM news WHERE ' . $where . ' ORDER BY ' . $dateCol . ' DESC, id DESC LIMIT :lim OFFSET :off';
            $st = $this->pdo->prepare($sql);
            foreach ($params as $k => $v) {
                if ($k === ':cid') {
                    $st->bindValue($k, (int)$v, PDO::PARAM_INT);
                } else {
                    $st->bindValue($k, $v);
                }
            }
            $st->bindValue(':lim', $perPage, PDO::PARAM_INT);
            $st->bindValue(':off', $offset, PDO::PARAM_INT);
            $st->execute();
            $items = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

            // counts (optional UI)
            $counts = [];
            if ($typeCol !== null) {
                try {
                    $c2 = $this->pdo->prepare('SELECT ' . $typeCol . ' AS t, COUNT(*) AS c FROM news WHERE ' . $this->publishedWhere() . ' GROUP BY ' . $typeCol);
                    $c2->execute();
                    $rows = $c2->fetchAll(PDO::FETCH_ASSOC) ?: [];
                    foreach ($rows as $r) {
                        $k = (string)($r['t'] ?? '');
                        if ($k !== '') $counts[$k] = (int)($r['c'] ?? 0);
                    }
                } catch (Throwable $e) {
                    // ignore
                }
            }

            return ['items' => $items, 'total' => $total, 'total_pages' => $pages, 'page' => $page, 'per_page' => $perPage, 'counts' => $counts];
        } catch (Throwable $e) {
            error_log('[NewsService] search error: ' . $e->getMessage());
            return ['items' => [], 'total' => 0, 'total_pages' => 1, 'page' => $page, 'per_page' => $perPage, 'counts' => []];
        }
    }

    // -------------------------
    // Internals
    // -------------------------

    private function tableExists(string $table): bool
    {
        try {
            $stmt = $this->pdo->prepare('SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :t LIMIT 1');
            $stmt->execute([':t' => $table]);
            return (bool)$stmt->fetchColumn();
        } catch (Throwable $e) {
            // On some DBs (or restricted permissions), information_schema may be unavailable.
            // Fall back to a lightweight query.
            try {
                $qt = \Godyar\DB::quoteIdent($table);
                $this->pdo->query('SELECT 1 FROM ' . $qt . ' LIMIT 1');
                return true;
            } catch (Throwable $e2) {
                return false;
            }
        }
    }

    private function hasColumn(string $table, string $col): bool
    {
        try {
            $stmt = $this->pdo->prepare('SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = :t AND column_name = :c LIMIT 1');
            $stmt->execute([':t' => $table, ':c' => $col]);
            return (bool)$stmt->fetchColumn();
        } catch (Throwable $e) {
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

        // Common patterns
        if ($this->hasColumn('news', 'status')) {
            return $p . "status = 'published'";
        }
        if ($this->hasColumn('news', 'is_published')) {
            return $p . 'is_published = 1';
        }
        if ($this->hasColumn('news', 'published')) {
            return $p . 'published = 1';
        }

        // If unknown schema: don't hide rows (better than 500).
        return '1=1';
    }
}
