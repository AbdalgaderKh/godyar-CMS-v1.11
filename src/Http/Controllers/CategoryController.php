<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use Godyar\Services\CategoryService;

final class CategoryController
{
    private CategoryService $categories;
    private string $basePrefix;

    public function __construct(CategoryService $categories, string $basePrefix = '')
    {
        $this->categories = $categories;
        $this->basePrefix = rtrim($basePrefix, '/');
    }

    /**
     * عرض صفحة قسم.
     *
     * ملاحظة: هذه الدالة كانت متضررة (Parse Error) بسبب وجود منطق خارج أي دالة.
     */
    public function show(string $slug, int $page = 1, string $sort = 'latest', string $period = 'all'): void
    {
        $slug = trim((string)$slug);
        $slug = trim($slug, "/ " . "\t\n\r\0\x0B");
        // منع أي محاولات لتمرير مسارات متعددة
        if ($slug === '' || strpos($slug, '/') !== false) {
            $this->renderMessage(404, 'القسم غير موجود', 'لم يتم تحديد اسم القسم في الرابط.');
        }

        // بدء الجلسة لأن بعض التحقق يعتمد على session
        if (function_exists('gdy_session_start')) {
            @gdy_session_start();
        } elseif (session_status() !== PHP_SESSION_ACTIVE && !headers_sent()) {
            @session_start();
        }

        // كاش اختياري إن وُجد Cache class
        $usePageCache = class_exists('Cache');
        $pageCacheKey = 'cat:' . md5($slug . '|' . $page . '|' . $sort . '|' . $period);
        if ($usePageCache) {
            try {
                $cached = \Cache::get($pageCacheKey);
                if (is_string($cached) && $cached !== '') {
                    header('X-Godyar-Cache: HIT');
                    echo $cached;
                    exit;
                }
            } catch (\Throwable $e) {
                // ignore cache failures
            }
        }

        $category = $this->categories->findBySlug($slug);
        if (!$category) {
            $this->renderMessage(404, 'القسم غير موجود', 'لم يتم العثور على القسم المطلوب.');
        }

        $categoryId = (int)($category['id'] ?? 0);
        if ($categoryId <= 0) {
            $this->renderMessage(404, 'القسم غير موجود', 'القسم غير صالح.');
        }

        $page = max(1, (int)$page);
        $perPage = 12;

        $sort = strtolower(trim((string)$sort));
        if (!in_array($sort, ['latest','popular','views'], true)) {
            $sort = 'latest';
        }

        $period = strtolower(trim((string)$period));
        if (!in_array($period, ['all','day','week','month'], true)) {
            $period = 'all';
        }

		// توافق: بعض الإصدارات تستخدم listPublishedNews كاسم موحد.
		if (method_exists($this->categories, 'listPublishedNews')) {
		    $result = $this->categories->listPublishedNews($categoryId, $page, $perPage, $sort, $period);
		} else {
		    $result = $this->categories->listNews($categoryId, $page, $perPage, $sort, $period);
		}
        $rows = (array)($result['items'] ?? []);

        $baseUrl = $this->baseUrl();
        $items = [];

        foreach ($rows as $row) {
            if (!is_array($row)) continue;

            $id = (int)($row['id'] ?? 0);
            $newsSlug = (string)($row['slug'] ?? '');

            // توحيد URL الخبر على /news/id/{id}
            $url = $id > 0
                ? ($baseUrl . '/news/id/' . $id)
                : ($baseUrl . '/news/' . rawurlencode($newsSlug));

            $catMembersOnly = (int)($category['is_members_only'] ?? 0) === 1;
            $rowMembersOnly = (int)($row['is_members_only'] ?? 0) === 1;
            $isLocked = $catMembersOnly || $rowMembersOnly;

            $items[] = array_merge($row, [
                'url' => $url,
                'is_locked' => $isLocked ? 1 : 0,
            ]);
        }

        $subcategories = $this->categories->subcategories($categoryId, 10);
        $parentId = isset($category['parent_id']) ? (int)$category['parent_id'] : null;
        if ($parentId === 0) {
            $parentId = null;
        }
        $siblingCategories = $this->categories->siblingCategories($parentId, $categoryId, 8);

        $categoryName = (string)($category['name'] ?? '');
        $categoryDescription = (string)($category['description'] ?? '');

        $currentCategoryUrl = $baseUrl . '/category' . rawurlencode($slug);
        $canonicalUrl = $currentCategoryUrl . $this->canonicalQuery($page, $sort, $period);

        $viewData = [
            'category' => $category,
            'items' => $items,
            'subcategories' => $subcategories,
            'siblingCategories' => $siblingCategories,
            'totalItems' => (int)($result['total'] ?? 0),
            'itemsPerPage' => $perPage,
            'currentPage' => $page,
            'pages' => (int)($result['total_pages'] ?? 1),
            'baseUrl' => $baseUrl,
            'homeUrl' => $baseUrl . '/',
            'currentCategoryUrl' => $currentCategoryUrl,
            'breadcrumbs' => [
                ['label' => 'الرئيسية', 'url' => $baseUrl . '/'],
                ['label' => $categoryName ?: $slug, 'url' => $currentCategoryUrl],
            ],
            'pageTitle' => $category['meta_title'] ?? (($categoryName ?: $slug) . ' - أخبار'),
            'metaDescription' => $category['meta_description']
                ?? ($categoryDescription !== '' ? $categoryDescription : 'أحدث الأخبار في قسم ' . ($categoryName ?: $slug)),
            'canonicalUrl' => $canonicalUrl,
            'rss' => $baseUrl . '/rss/category' . rawurlencode((string)($category['slug'] ?? $slug)) . '.xml',
        ];

        $viewPath = dirname(__DIR__, 3) . '/frontend/views/category.php';
        if (is_file($viewPath)) {
            if ($usePageCache) {
                ob_start();
                extract($viewData, EXTR_SKIP);
                require $viewPath;
                $html = ob_get_clean();
                if (is_string($html) && $html !== '') {
                    try { \Cache::put($pageCacheKey, $html, 300); } catch (\Throwable) {}
                    header('X-Godyar-Cache: MISS');
                    echo $html;
                    exit;
                }
            }

            extract($viewData, EXTR_SKIP);
            require $viewPath;
            exit;
        }

        $this->renderMessage(500, 'خطأ', 'ملف العرض غير موجود.');
    }

    private function canonicalQuery(int $page, string $sort, string $period): string
    {
        $q = [];
        if ($page > 1) {
            $q['page'] = $page;
        }
        if ($sort !== 'latest') {
            $q['sort'] = $sort;
        }
        if ($period !== 'all') {
            $q['period'] = $period;
        }
        return $q ? ('?' . http_build_query($q)) : '';
    }

    private function baseUrl(): string
    {
        if (function_exists('base_url')) {
            $b = rtrim((string)base_url(), '/');
            if ($b !== '') {
                return $b;
            }
        }

        if (defined('BASE_URL')) {
            $b = rtrim((string)BASE_URL, '/');
            if ($b !== '') {
                return $b;
            }
        }

        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return rtrim($scheme . '://' . $host . $this->basePrefix, '/');
    }

    private function renderMessage(int $code, string $title, string $message): void
    {
        http_response_code($code);
        echo '<!doctype html><html lang="ar" dir="rtl"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<title>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</title>'
            . '<link href="/assets/vendor/bootstrap/css/bootstrap.rtl.min.css" rel="stylesheet">'
            . '</head><body class="bg-dark text-light"><main class="container py-5">'
            . '<div class="alert alert-info rounded-3 shadow-sm bg-opacity-75">'
            . '<h1 class="h4 mb-2">' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h1>'
            . '<p class="mb-0">' . nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8')) . '</p>'
            . '</div></main></body></html>';
        exit;
    }
}
