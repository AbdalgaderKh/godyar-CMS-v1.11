<?php
declare(strict_types=1);

/**
 * Godyar Ads Helper
 * Path: /godyar/includes/ads.php
 *
 * يعتمد على:
 *  - bootstrap.php (من أجل $pdo و env)
 *  - جدول ads كما عرفناه سابقاً
 */

if (function_exists('h') === false) {
    function h($v): string
    {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}

/**
 * إرجاع إعلانات نشطة لمكان معيّن
 *
 * @param string $position  مفتاح مكان الظهور (header, sidebar_top, ...)
 * @param int    $limit     عدد الإعلانات المراد جلبها
 * @return array
 */
function godyar_get_ads(string $position, int $limit = 1): array
{
    $pdo = gdy_pdo_safe();
    if (($pdo instanceof PDO) === false) {
        return [];
    }

    $limit = max(1, $limit);

    $sql = "
        SELECT *
        FROM ads
        WHERE position = :pos
          AND is_active = 1
          AND (start_at IS NULL OR start_at <= NOW())
          AND (end_at   IS NULL OR end_at   >= NOW())
          AND (max_impressions IS NULL OR impressions < max_impressions)
        ORDER BY id DESC
        LIMIT {$limit}
    ";

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':pos' => $position]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Exception $e) {
        error_log('[Godyar Ads] get_ads: ' . $e->getMessage());
        return [];
    }

    if (($rows === false)) {
        return [];
    }

    // تحديث عدد الانطباعات impressions
    try {
        $ids = array_column($rows, 'id');
        if ((empty($ids) === false)) {
            $in  = implode(',', array_fill(0, count($ids), '?'));
            $sql = "UPDATE ads SET impressions = impressions + 1 WHERE id IN ($in)";
            $stmt2 = $pdo->prepare($sql);
            $stmt2->execute($ids);
        }
    } catch (Exception $e) {
        error_log('[Godyar Ads] impressions update: ' . $e->getMessage());
    }

    return $rows;
}

/**
 * عرض الإعلانات HTML مباشرةً
 *
 * @param string $position
 * @param int    $limit
 */
function godyar_render_ad(string $position, int $limit = 1): void
{
    $ads = godyar_get_ads($position, $limit);
    if (($ads === false)) {
        return;
    }

    echo '<div class="godyar-ads godyar-ads-' . h($position) . '">';

    foreach ($ads as $ad) {
        $hasImg  = !empty($ad['image_path']);
        $hasHtml = !empty($ad['html_code']);
        $url     = $ad['target_url'] ?? '';

        echo '<div class="godyar-ad-item mb-3 text-center">';

        // لو في كود HTML نعرضه كما هو
        if ((empty($hasHtml) === false)) {
            echo '<div class="godyar-ad-html">';
            // نفترض أن الكود موثوق من المسؤول
            echo $ad['html_code'];
            echo '</div>';
        } elseif ((empty($hasImg) === false)) {
            // إعلان بصورة
            $imgTag = '<img src="' . h($ad['image_path']) . '" alt="' . h($ad['title']) . '" class="img-fluid">';
            if ((empty($url) === false)) {
                echo '<a href="' . h($url) . '" target="_blank" rel="noopener nofollow" class="d-inline-block">';
                echo $imgTag;
                echo '</a>';
            } else {
                echo $imgTag;
            }
        } else {
            // لا صورة ولا HTML -> تجاهل أو عرض عنوان فقط
            echo '<div class="godyar-ad-fallback small text-muted">' . h($ad['title']) . '</div>';
        }

        echo '</div>'; // .godyar-ad-item
    }

    echo '</div>'; // .godyar-ads
}
