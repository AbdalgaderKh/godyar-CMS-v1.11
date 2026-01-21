<?php
declare(strict_types=1);

if (!function_exists('h')) {
    function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
}

$baseUrl = function_exists('base_url') ? rtrim((string)base_url(), '/') : '';
$basePath = '';
if ($baseUrl !== '') {
    $basePath = (string)(parse_url($baseUrl, PHP_URL_PATH) ?: '');
    $basePath = rtrim($basePath, '/');
}

$slug = isset($slug) ? trim((string)$slug) : (isset($_GET['slug']) ? trim((string)$_GET['slug']) : '');
$slug = trim($slug, "/ \t\n\r\0\x0B");

// Pagination
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 12;
$offset = ($page - 1) * $perPage;

$author = null;
$items = [];
$total = 0;

if (!isset($pdo) || !($pdo instanceof PDO)) {
    require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
}

if ($slug !== '' && isset($pdo) && $pdo instanceof PDO) {
    // Fetch author
    $stmt = $pdo->prepare('SELECT * FROM opinion_authors WHERE slug = :slug AND (is_active = 1 OR is_active = TRUE) LIMIT 1');
    $stmt->execute([':slug' => $slug]);
    $author = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

    if ($author) {
        $aid = (int)($author['id'] ?? 0);
        // Count
        $stc = $pdo->prepare('SELECT COUNT(*) FROM news WHERE opinion_author_id = :id AND status = :st');
        $stc->execute([':id' => $aid, ':st' => 'published']);
        $total = (int)($stc->fetchColumn() ?: 0);

        // List
        $st = $pdo->prepare('SELECT id,title,slug,excerpt,image,image_path,featured_image,published_at,created_at FROM news WHERE opinion_author_id = :id AND status = :st ORDER BY COALESCE(published_at, created_at) DESC LIMIT :lim OFFSET :off');
        $st->bindValue(':id', $aid, PDO::PARAM_INT);
        $st->bindValue(':st', 'published', PDO::PARAM_STR);
        $st->bindValue(':lim', $perPage, PDO::PARAM_INT);
        $st->bindValue(':off', $offset, PDO::PARAM_INT);
        $st->execute();
        $items = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}

$pageTitle = $author ? ((string)($author['page_title'] ?? $author['name'] ?? 'كاتب')) : 'كاتب غير موجود';

require __DIR__ . '/partials/header.php';
?>

<div class="gdy-page">
  <div class="gdy-author-head card shadow-sm mb-4">
    <div class="card-body d-flex flex-column flex-md-row gap-3 align-items-start align-items-md-center">
      <?php
        $avatar = $author['avatar'] ?? '';
        if (is_string($avatar) && $avatar !== '' && !preg_match('~^https?://~i', $avatar)) {
            $avatar = rtrim($baseUrl, '/') . '/' . ltrim($avatar, '/');
        }
      ?>
      <div class="author-avatar" style="width:84px;height:84px;border-radius:18px;overflow:hidden;background:#111827;flex:0 0 auto;">
        <?php if (!empty($avatar)): ?>
          <img src="<?= h($avatar) ?>" alt="<?= h($author['name'] ?? '') ?>" style="width:100%;height:100%;object-fit:cover;" loading="lazy">
        <?php else: ?>
          <div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;color:#9ca3af;font-weight:700;">AC</div>
        <?php endif; ?>
      </div>
      <div class="flex-grow-1">
        <h1 class="h4 mb-1"><?= h($author['name'] ?? 'غير موجود') ?></h1>
        <?php if (!empty($author['specialization'])): ?>
          <div class="text-muted mb-2"><?= h($author['specialization']) ?></div>
        <?php endif; ?>
        <?php if (!empty($author['bio'])): ?>
          <div class="small" style="line-height:1.8;opacity:.92"><?= nl2br(h($author['bio'])) ?></div>
        <?php endif; ?>
      </div>
      <?php if ($author): ?>
      <div class="text-muted small" style="white-space:nowrap">
        <span><?= h('عدد المقالات: ') ?></span><strong><?= (int)$total ?></strong>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <?php if (!$author): ?>
    <div class="alert alert-warning">الكاتب غير موجود أو غير مفعل.</div>
  <?php else: ?>

    <?php if (empty($items)): ?>
      <div class="alert alert-info">لا توجد مقالات منشورة لهذا الكاتب حالياً.</div>
    <?php else: ?>
      <div class="row g-3">
        <?php foreach ($items as $row):
          $slug2 = (string)($row['slug'] ?? '');
          $url = $slug2 !== '' ? ($baseUrl . '/news/' . rawurlencode($slug2)) : ($baseUrl . '/news.php?id=' . (int)($row['id'] ?? 0));
          $img = (string)($row['image'] ?? $row['image_path'] ?? $row['featured_image'] ?? '');
          if ($img !== '' && !preg_match('~^https?://~i', $img)) {
              $img = rtrim($baseUrl, '/') . '/' . ltrim($img, '/');
          }
          $date = (string)($row['published_at'] ?? $row['created_at'] ?? '');
        ?>
        <div class="col-12 col-md-6 col-lg-4">
          <article class="card h-100 shadow-sm">
            <?php if ($img !== ''): ?>
              <a href="<?= h($url) ?>" class="ratio ratio-16x9" style="border-radius:14px;overflow:hidden;">
                <img src="<?= h($img) ?>" alt="<?= h($row['title'] ?? '') ?>" loading="lazy" style="width:100%;height:100%;object-fit:cover;">
              </a>
            <?php endif; ?>
            <div class="card-body">
              <h2 class="h6 mb-2"><a href="<?= h($url) ?>" class="text-decoration-none"><?= h($row['title'] ?? '') ?></a></h2>
              <?php if (!empty($row['excerpt'])): ?>
                <p class="text-muted small mb-2" style="line-height:1.7"><?= h(mb_strimwidth((string)$row['excerpt'], 0, 140, '…', 'UTF-8')) ?></p>
              <?php endif; ?>
              <?php if ($date !== ''): ?>
                <div class="text-muted small"><?= h($date) ?></div>
              <?php endif; ?>
            </div>
          </article>
        </div>
        <?php endforeach; ?>
      </div>

      <?php
        $pages = (int)ceil(max(1, $total) / $perPage);
        if ($pages > 1):
          $base = $baseUrl . '/author?slug=' . rawurlencode($slug);
      ?>
      <nav class="mt-4" aria-label="pagination">
        <ul class="pagination justify-content-center">
          <?php for ($i=1; $i<=$pages; $i++): ?>
            <li class="page-item <?= ($i === $page ? 'active' : '') ?>">
              <a class="page-link" href="<?= h($base . '&page=' . $i) ?>"><?= (int)$i ?></a>
            </li>
          <?php endfor; ?>
        </ul>
      </nav>
      <?php endif; ?>

    <?php endif; ?>
  <?php endif; ?>
</div>

<?php
if (!defined('GDY_TPL_WRAPPED')) {
    require __DIR__ . '/partials/footer.php';
}
