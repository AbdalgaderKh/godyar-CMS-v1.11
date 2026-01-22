        return '/' . ltrim($src, '/');
    }
}
<?php
// /godyar/frontend/views/trending/content.php
/**
 * @var string $baseUrl
 * @var callable $newsUrl
 * @var array $trendingNews
 */
$baseUrl = $baseUrl ?? '';
$newsUrl = $newsUrl ?? function(array $row): string {
    return $baseUrl;
};

// Normalize image paths so they work on nested routes.
if (function_exists('gdy_img_src') === false) {
    function gdy_img_src(?string $src): string {
        $src = trim((string)$src);
        if ($src === '') return '';
        if (preg_match('~^(https?:)?//~i', $src)) return $src;
        if (str_starts_with($src, 'data:')) return $src;
        if ($src[0] === '/') return $src;
        return '/' . ltrim($src, '/');
    }
}
<section aria-label="الأخبار الأكثر تداولاً">
        return '/' . ltrim($src, '/');
    }
}
?>
<?php
/** @var string $baseUrl */
/** @var callable $newsUrl */
$baseUrl = $baseUrl ?? '';
$newsUrl = $newsUrl ?? fn($row): string => '';
?>

<section aria-label="الأخبار الأكثر تداولاً">
    <div class="section-header">
        <div>
            <div class="section-title">الأخبار الأكثر تداولاً</div>
            <div class="section-sub">أكثر الأخبار مشاهدة وقراءة من قبل الزوار</div>
        </div>
        <a href="<?= h($baseUrl) ?>" class="section-sub">
            العودة للرئيسية
        </a>
    </div>

    <?php if (!empty($trendingNews)): ?>
        <div class="news-grid">
            <?php foreach ($trendingNews as $row): ?>
                <article class="news-card fade-in">
                    <?php if (!empty($row['featured_image'])): ?>
                        <a href="<?= h($newsUrl($row)) ?>" class="news-thumb">
	                            <img src="<?= htmlspecialchars(gdy_img_src($row['featured_image'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars((string)($row['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                        </a>
                    <?php endif; ?>
                    <div class="news-body">
                        <a href="<?= h($newsUrl($row)) ?>">
                            <h2 class="news-title">
                                <?php
                                    $t = (string)$row['title'];
                                    $cut = mb_substr($t, 0, 90, 'UTF-8');
                                    echo h($cut) . (mb_strlen($t, 'UTF-8') > 90 ? '…' : '');
                                ?>
                            </h2>
                        </a>
                        <?php if (!empty($row['excerpt'])): ?>
                            <p class="news-excerpt">
                                <?php
                                    $ex = (string)$row['excerpt'];
                                    $cut = mb_substr($ex, 0, 120, 'UTF-8');
                                    echo h($cut) . (mb_strlen($ex, 'UTF-8') > 120 ? '…' : '');
                                ?>
                            </p>
                        <?php endif; ?>
                        <div class="news-meta">
                            <span>
                                <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="#more-h"></use></svg>
                                <?= !empty($row['published_at']) ? h(date('Y-m-d', strtotime($row['published_at']))) : '' ?>
                            </span>
                            <span>
                                <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="#more-h"></use></svg>
                                <?= (int)($row['views'] ?? 0) ?> مشاهدة
                            </span>
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
            <a href="<?= h($baseUrl) ?>" class="btn-primary" style="margin-top: 15px;">
                <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="#more-h"></use></svg>
                <span>العودة للرئيسية</span>
            </a>
        </div>
    <?php endif; ?>
</section>
