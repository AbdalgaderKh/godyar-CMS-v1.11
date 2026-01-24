<?php
// /frontend/views/trending/content.php
/**
 * @var string   $baseUrl
 * @var callable $newsUrl
 * @var array    $trendingNews
 */

$baseUrl = (string)($baseUrl ?? '');
$newsUrl = $newsUrl ?? static function (array $row) use ($baseUrl): string {
    // Fallback: لو لم تمرر دالة بناء الروابط
    return rtrim($baseUrl, '/') . '/news/' . rawurlencode((string)($row['slug'] ?? ''));
};

// Normalize image paths so they work on nested routes.
if (function_exists('gdy_img_src') === false) {
    function gdy_img_src(?string $src): string
    {
        $src = trim((string)$src);
        if ($src === '') return '';
        if (preg_match('~^(https?:)?//~i', $src)) return $src;
        if (function_exists('str_starts_with') && str_starts_with($src, 'data:')) return $src;
        if ($src[0] === '/') return $src;
        return '/' . ltrim($src, '/');
    }
}
?>

<section aria-label="الأخبار الأكثر تداولاً">
    <div class="section-header">
        <div>
            <div class="section-title">الأخبار الأكثر تداولاً</div>
            <div class="section-sub">أكثر الأخبار مشاهدة وقراءة من قبل الزوار</div>
        </div>
        <a href="<?php echo h($baseUrl); ?>" class="section-sub">
            العودة للرئيسية
        </a>
    </div>

    <?php if (!empty($trendingNews)): ?>
        <div class="news-grid">
            <?php foreach ($trendingNews as $row): ?>
                <article class="news-card fade-in">
                    <?php if (!empty($row['featured_image'])): ?>
                        <a href="<?php echo h($newsUrl($row)); ?>" class="news-thumb">
                            <img src="<?php echo htmlspecialchars(gdy_img_src($row['featured_image'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                 alt="<?php echo htmlspecialchars((string)($row['title'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                        </a>
                    <?php endif; ?>
                    <div class="news-body">
                        <a href="<?php echo h($newsUrl($row)); ?>">
                            <h3><?php echo htmlspecialchars((string)($row['title'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></h3>
                        </a>
                        <?php if (!empty($row['excerpt'])): ?>
                            <p class="news-excerpt"><?php echo htmlspecialchars((string)$row['excerpt'], ENT_QUOTES, 'UTF-8'); ?></p>
                        <?php endif; ?>
                        <div class="news-meta">
                            <span><?php echo htmlspecialchars((string)($row['views'] ?? 0), ENT_QUOTES, 'UTF-8'); ?> مشاهدة</span>
                            <?php if (!empty($row['publish_at'])): ?>
                                <span><?php echo htmlspecialchars((string)$row['publish_at'], ENT_QUOTES, 'UTF-8'); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="side-widget" style="text-align: center; padding: 40px 20px;">
            <div class="side-widget-title">
                <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="#more-h"></use></svg>
                <span>لا توجد أخبار شائعة بعد</span>
            </div>
            <p style="color: var(--text-muted); margin-top: 10px;">
                سيتم عرض الأخبار الأكثر مشاهدة هنا تلقائياً بعد وجود زيارات كافية.
            </p>
            <a href="<?php echo h($baseUrl); ?>" class="btn-primary" style="margin-top: 15px;">
                <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="#more-h"></use></svg>
                <span>العودة للرئيسية</span>
            </a>
        </div>
    <?php endif; ?>
</section>
