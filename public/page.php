<?php
declare(strict_types=1);

// Legacy / fallback page endpoint (querystring based).
// Ensures pages render with the SAME site header/footer.

require_once __DIR__ . '/../includes/bootstrap.php';

if (!function_exists('h')) {
    function h($v): string
    {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}

/** @var PDO|null $pdo */
$pdo = function_exists('gdy_pdo_safe') ? gdy_pdo_safe() : null;

$slugRaw = $_GET['slug'] ?? '';
$slug    = is_string($slugRaw) ? trim($slugRaw) : '';
$slug    = preg_replace('~[^a-z0-9\-_/]~i', '', $slug) ?: '';
$slug    = trim($slug, '/');

// Language (optional)
$langRaw = $_GET['lang'] ?? '';
$currentLang = is_string($langRaw) && $langRaw !== ''
    ? preg_replace('~[^a-z]~i', '', strtolower($langRaw))
    : (function_exists('current_lang') ? (string)current_lang() : 'ar');

$page = null;

if (($pdo instanceof PDO) && $slug !== '') {
    try {
        // Support either a simple pages schema (slug/title/content)
        // or a multilingual schema (slug/lang/title/content ...).
        $stmt = $pdo->prepare(
            "SELECT * FROM pages \
             WHERE slug = :slug \
             AND (lang = :lang OR lang IS NULL OR lang = '') \
             ORDER BY (lang = :lang) DESC, id DESC \
             LIMIT 1"
        );
        $stmt->execute([
            ':slug' => $slug,
            ':lang' => $currentLang,
        ]);
        $page = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

        // Fallback if lang column does not exist
        if ($page === null) {
            $stmt = $pdo->prepare("SELECT * FROM pages WHERE slug = :slug ORDER BY id DESC LIMIT 1");
            $stmt->execute([':slug' => $slug]);
            $page = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        }
    } catch (Throwable $e) {
        // If query fails (different schema), try the simplest possible one
        try {
            $stmt = $pdo->prepare("SELECT * FROM pages WHERE slug = :slug ORDER BY id DESC LIMIT 1");
            $stmt->execute([':slug' => $slug]);
            $page = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (Throwable $e2) {
            error_log('[public/page.php] fetch error: ' . $e2->getMessage());
            $page = null;
        }
    }
}

// Variables used by the global header
$pageTitle = (string)($page['title'] ?? ($page['page_title'] ?? ''));
$metaTitle = (string)($page['meta_title'] ?? $pageTitle);
$metaDescription = (string)($page['meta_description'] ?? '');

// Render with site identity
$__view = __DIR__ . '/../frontend/views/partials/header.php';
$__footer = __DIR__ . '/../frontend/views/partials/footer.php';

if (is_file($__view)) {
    require $__view;
} else {
    // Absolute fallback so the page isn't blank
    echo "<!doctype html><html lang=\"" . h($currentLang) . "\"><head><meta charset=\"utf-8\"><meta name=\"viewport\" content=\"width=device-width, initial-scale=1\"><title>" . h($metaTitle) . "</title></head><body>";
}
?>

<main class="gdy-page" role="main">
  <div class="container py-4">

    <?php if (!is_array($page)) : ?>
      <div class="alert alert-warning" role="alert" style="border-radius:12px;">
        <?php echo h(function_exists('__') ? __('t_404_page', 'الصفحة غير موجودة.') : 'الصفحة غير موجودة.'); ?>
      </div>

    <?php else : ?>
      <article class="card border-0 shadow-sm" style="border-radius:16px;">
        <div class="card-body">
          <h1 class="h4 mb-3"><?php echo h($pageTitle); ?></h1>
          <div class="page-content">
            <?php
              // Content is authored by admins; allow HTML.
              echo (string)($page['content'] ?? $page['page_content'] ?? '');
            ?>
          </div>
        </div>
      </article>
    <?php endif; ?>

  </div>
</main>

<?php
if (is_file($__footer)) {
    require $__footer;
} else {
    echo "</body></html>";
}
