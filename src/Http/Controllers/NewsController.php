<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\FrontendRenderer;
use Godyar\Services\AdService;
use Godyar\Services\CategoryService;
use Godyar\Services\NewsService;
use Godyar\Services\TagService;
use PDO;
use Throwable;

/**
 * NewsController
 * --------------
 * Handles:
 * - /news/id/{id}
 * - /news/{slug} (legacy fallback)
 * - /preview/news/{id}
 * - /news/print/{id}
 */
final class NewsController
{
    private PDO $pdo;
    private NewsService $news;
    private CategoryService $categories;
    private TagService $tags;
    private AdService $ads;
    private string $basePrefix;

    public function __construct(PDO $pdo, NewsService $news, CategoryService $categories, TagService $tags, AdService $ads, string $basePrefix = '')
    {
        $this->pdo = $pdo;
        $this->news = $news;
        $this->categories = $categories;
        $this->tags = $tags;
        $this->ads = $ads;
        $this->basePrefix = rtrim($basePrefix, '/');
    }

    public function preview(int $id): void
    {
        $this->show((string)$id, true);
    }

    public function print(int $id): void
    {
        $id = (int)$id;
        if ($id <= 0) {
            $this->renderMessage(404, 'غير موجود', 'لم يتم تحديد الخبر.');
        }

        $post = $this->news->findById($id, false);
        if (!$post) {
            $this->renderMessage(404, 'غير موجود', 'الخبر غير موجود.');
        }

        $baseUrl = $this->absoluteBaseUrl();
        $articleUrlFull = rtrim($baseUrl, '/') . $this->basePrefix . '/news/id/' . $id;

        $root = dirname(__DIR__, 3);
        $renderer = new FrontendRenderer($root, $this->basePrefix);
        $renderer->render('frontend/views/news_print.php', [
            'post' => $post,
            'baseUrl' => $baseUrl,
            'articleUrlFull' => $articleUrlFull,
        ]);
    }

    /**
     * @param string $slugOrId Either numeric id or slug.
     */
    public function show(string $slugOrId, bool $forcePreview = false): void
    {
        $slugOrId = trim($slugOrId);
        if ($slugOrId === '') {
            $this->renderMessage(404, 'غير موجود', 'لم يتم تحديد الخبر.');
        }

        // Support legacy ?preview=1 as well.
        $isPreview = $forcePreview || ((string)($_GET['preview'] ?? '') === '1');

        // Admin-only preview
        if ($isPreview && !$this->isAdmin()) {
            http_response_code(403);
            echo 'Forbidden';
            exit;
        }

        $post = $this->news->findBySlugOrId($slugOrId, $isPreview);
        if (!$post) {
            $this->renderMessage(404, 'غير موجود', 'الخبر غير موجود.');
        }

        $id = (int)($post['id'] ?? 0);
        if ($id > 0 && !$isPreview) {
            $this->news->incrementViews($id);
        }

        $categoryId = (int)($post['category_id'] ?? 0);
        $related = ($categoryId > 0 && $id > 0) ? $this->news->relatedByCategory($categoryId, $id, 6) : [];
        $tags = ($id > 0) ? $this->tags->forNews($id) : [];
        $latest = $this->news->latest(10, false);
        $mostRead = $this->news->mostRead(10);

        $root = dirname(__DIR__, 3);
        $renderer = new FrontendRenderer($root, $this->basePrefix);
        $renderer->render('frontend/views/news_single_legacy.php', [
            'news' => $post,
            'related' => $related,
            'tags' => $tags,
            'latest' => $latest,
            'mostRead' => $mostRead,
            'isPreview' => $isPreview,
        ]);
    }

    private function isAdmin(): bool
    {
        try {
            if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
                session_start();
            }
            $role = (string)($_SESSION['user']['role'] ?? ($_SESSION['user_role'] ?? ''));
            return $role !== '' && in_array($role, ['admin', 'superadmin', 'manager'], true);
        } catch (Throwable) {
            return false;
        }
    }

    private function absoluteBaseUrl(): string
    {
        // If the project provides base_url(), prefer it.
        if (function_exists('base_url')) {
            $u = (string)base_url();
            return rtrim($u, '/');
        }

        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return $scheme . '://' . $host;
    }

    private function renderMessage(int $status, string $title, string $message): void
    {
        http_response_code($status);
        echo '<!doctype html><html lang="ar"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<title>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</title>'
            . '<style>body{font-family:system-ui,Segoe UI,Arial;display:flex;min-height:100vh;align-items:center;justify-content:center;margin:0;background:#f8fafc;color:#0f172a} .box{max-width:720px;padding:28px 22px;background:#fff;border:1px solid #e2e8f0;border-radius:14px;box-shadow:0 10px 30px rgba(2,6,23,.06)} h1{margin:0 0 10px;font-size:22px} p{margin:0;color:#475569;line-height:1.7}</style>'
            . '</head><body><div class="box"><h1>'
            . htmlspecialchars($title, ENT_QUOTES, 'UTF-8')
            . '</h1><p>'
            . htmlspecialchars($message, ENT_QUOTES, 'UTF-8')
            . '</p></div></body></html>';
        exit;
    }
}
