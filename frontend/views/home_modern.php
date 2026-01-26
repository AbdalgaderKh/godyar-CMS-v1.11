<?php
declare(strict_types=1);

// هذا الملف يُستدعى من frontend/home.php بعد تجهيز المتغيرات (مثل $latestNews, $featuredNews).
// يعتمد على partials/header.php و partials/footer.php لهيكل الصفحة.

if (function_exists('h') === false) {
    function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
}

$baseUrl = function_exists('base_url') ? rtrim((string)base_url(), '/') : '';

$newsUrl = function(array $row) use ($baseUrl): string {
    $slug = (string)($row['slug'] ?? '');
    if ($slug !== '') {
        return $baseUrl . '/news/' . rawurlencode($slug);
    }
    $id = (int)($row['id'] ?? 0);
    return $id > 0 ? ($baseUrl . '/news.php?id=' . $id) : ($baseUrl . '/');
};

// اختيارات الصور: featured_image / image_path / image
$newsImage = function(array $row) use ($baseUrl): string {
    $img = (string)($row['featured_image'] ?? ($row['image_path'] ?? ($row['image'] ?? '')));
    $img = trim($img);
    if ($img === '') return '';
    if (preg_match('~^https?://~i', $img)) return $img;
    // اجعلها مطلقة على مستوى التثبيت (لتعمل داخل subdir)
    return rtrim($baseUrl, '/') . '/' . ltrim($img, '/');
};

	// ملاحظة: لا نقوم بتحميل header/footer من هنا.
	// الواجهة الرئيسية (frontend/index.php) هي المسؤولة عن تضمين الـ layout مرة واحدة.
	?>

<section class="mb-4">
  <div class="p-4 rounded-4 shadow-sm" style="background: rgba(var(--primary-rgb), .06); border: 1px solid rgba(var(--primary-rgb), .14);">
    <div class="d-flex flex-column flex-md-row align-items-start align-items-md-center justify-content-between gap-3">
      <div>
        <h1 class="h4 m-0"><?php echo h($siteName ?? 'Godyar CMS'); ?></h1>
        <div class="text-muted small mt-1"><?php echo h($siteTagline ?? 'منصة إخبارية متكاملة'); ?></div>
      </div>
      <?php if (!empty($breakingNews) && is_array($breakingNews)): ?>
        <a class="btn btn-outline-primary btn-sm" href="<?php echo h($newsUrl($breakingNews[0])); ?>"><?php echo h(__('t_breaking', 'عاجل')); ?>: <?php echo h((string)($breakingNews[0]['title'] ?? '')); ?></a>
      <?php endif; ?>
    </div>
  </div>
</section>

<?php if (!empty($featuredNews) && is_array($featuredNews)): ?>
<section class="mb-4">
  <div class="d-flex align-items-center justify-content-between mb-2">
    <h2 class="h5 m-0"><?php echo h(__('t_featured', 'الأخبار المميزة')); ?></h2>
    <a class="small" href="<?php echo h(($baseUrl ?: '') . '/news'); ?>"><?php echo h(__('t_more', 'المزيد')); ?></a>
  </div>
  <div class="row g-3">
    <?php foreach (array_slice($featuredNews, 0, 6) as $row):
      $u = $newsUrl((array)$row);
      $img = $newsImage((array)$row);
      $title = (string)($row['title'] ?? '');
      $excerpt = (string)($row['excerpt'] ?? ($row['summary'] ?? ''));
    ?>
      <div class="col-12 col-md-6 col-lg-4">
        <article class="card h-100 border-0 shadow-sm rounded-4 overflow-hidden">
          <?php if ($img !== ''): ?>
            <a href="<?php echo h($u); ?>" class="ratio ratio-16x9" style="background:#0b1220;">
              <img src="<?php echo h($img); ?>" alt="<?php echo h($title); ?>" style="object-fit:cover; width:100%; height:100%;" loading="lazy" />
            </a>
          <?php endif; ?>
          <div class="card-body">
            <h3 class="h6 mb-2"><a class="text-decoration-none" href="<?php echo h($u); ?>"><?php echo h($title); ?></a></h3>
            <?php if ($excerpt !== ''): ?>
              <p class="text-muted small mb-0"><?php echo h(mb_strimwidth(strip_tags($excerpt), 0, 140, '…', 'UTF-8')); ?></p>
            <?php endif; ?>
          </div>
        </article>
      </div>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<section class="mb-4">
  <div class="d-flex align-items-center justify-content-between mb-2">
    <h2 class="h5 m-0"><?php echo h(__('t_latest', 'آخر الأخبار')); ?></h2>
    <a class="small" href="<?php echo h(($baseUrl ?: '') . '/news'); ?>"><?php echo h(__('t_more', 'المزيد')); ?></a>
  </div>

  <?php if (empty($latestNews) || !is_array($latestNews)): ?>
    <div class="alert alert-info rounded-4 mb-0"><?php echo h(__('t_no_news', 'لا توجد أخبار للعرض حالياً.')); ?></div>
  <?php else: ?>
    <div class="row g-3">
      <?php foreach (array_slice($latestNews, 0, 12) as $row):
        $u = $newsUrl((array)$row);
        $img = $newsImage((array)$row);
        $title = (string)($row['title'] ?? '');
        $excerpt = (string)($row['excerpt'] ?? ($row['summary'] ?? ''));
      ?>
        <div class="col-12 col-md-6">
          <article class="card h-100 border-0 shadow-sm rounded-4 overflow-hidden">
            <div class="row g-0">
              <?php if ($img !== ''): ?>
                <div class="col-4">
                  <a href="<?php echo h($u); ?>" class="ratio ratio-1x1" style="background:#0b1220;">
                    <img src="<?php echo h($img); ?>" alt="<?php echo h($title); ?>" style="object-fit:cover; width:100%; height:100%;" loading="lazy" />
                  </a>
                </div>
              <?php endif; ?>
              <div class="col">
                <div class="card-body">
                  <h3 class="h6 mb-1"><a class="text-decoration-none" href="<?php echo h($u); ?>"><?php echo h($title); ?></a></h3>
                  <?php if ($excerpt !== ''): ?>
                    <p class="text-muted small mb-0"><?php echo h(mb_strimwidth(strip_tags($excerpt), 0, 160, '…', 'UTF-8')); ?></p>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          </article>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>

	<?php
	// footer يتم تضمينه من frontend/index.php
