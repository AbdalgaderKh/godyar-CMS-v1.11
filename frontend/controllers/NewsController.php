<?php
declare(strict_types=1);

// frontend/controllers/NewsController.php
require_once __DIR__ . '/../../includes/bootstrap.php';

if (!function_exists('h')) {
    function h($v): string {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}

$pdo = $pdo ?? (function_exists('gdy_pdo_safe') ? gdy_pdo_safe() : null);
if (!($pdo instanceof PDO)) {
    http_response_code(500);
    exit('Database connection not available');
}

$slug = isset($_GET['slug']) ? trim((string)$_GET['slug']) : '';
if ($slug === '') {
    http_response_code(404);
    exit('Not found');
}

$news = null;
try {
    $st = $pdo->prepare("SELECT * FROM news WHERE slug = :s AND status = 'published' LIMIT 1");
    $st->execute([':s' => $slug]);
    $news = $st->fetch(PDO::FETCH_ASSOC) ?: null;
} catch (Exception $e) {
    error_log('[NewsController] fetch failed: ' . $e->getMessage());
}

if (!$news) {
    http_response_code(404);
    exit('Not found');
}

// Update views (best-effort)
try {
    $updateStmt = $pdo->prepare("UPDATE news SET views = COALESCE(views, 0) + 1 WHERE slug = :slug");
    $updateStmt->execute([':slug' => $slug]);
} catch (Exception $e) {
    error_log('[NewsController] views update failed: ' . $e->getMessage());
}

// Hook for plugins
if (function_exists('g_do_hook')) {
    try {
        g_do_hook('frontend_news_before_render', $news, $pdo);
    } catch (Exception $e) {
        // do not break rendering
        error_log('[NewsController] hook failed: ' . $e->getMessage());
    }
}

// Some views expect $baseUrl
$baseUrl = function_exists('gdy_base_url') ? rtrim((string)gdy_base_url(), '/') : '';

// Render
$view = __DIR__ . '/../views/news_single.php';
if (!is_file($view)) {
    http_response_code(500);
    exit('View missing');
}
require $view;
