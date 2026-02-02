<?php
declare(strict_types=1);

// cron/update_feeds.php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/classes/FeedParser.php';

$pdo = $pdo ?? (function_exists('gdy_pdo_safe') ? gdy_pdo_safe() : null);
if (!($pdo instanceof PDO)) {
    fwrite(STDERR, "No PDO\n");
    exit(1);
}
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$feeds = $pdo->query("SELECT * FROM feeds WHERE is_active = 1")->fetchAll(PDO::FETCH_ASSOC) ?: [];
if (!$feeds) {
    exit(0);
}

function gdy_slugify(string $s): string
{
    $s = trim($s);
    $s = mb_strtolower($s, 'UTF-8');
    $s = preg_replace('~[\s\-]+~u', '-', $s) ?? '';
    $s = preg_replace('~[^\p{L}\p{N}\-]+~u', '', $s) ?? '';
    $s = trim($s, '-');
    return substr($s, 0, 180);
}

foreach ($feeds as $feed) {
    $url = (string)($feed['url'] ?? '');
    if ($url === '') continue;

    $items = FeedParser::parse($url, 20);
    if (!$items) {
        // Update last_fetched_at حتى لو فشل القراءة (لتجنب hammering)
        $upd = $pdo->prepare("UPDATE feeds SET last_fetched_at = NOW() WHERE id = :id");
        $upd->execute([':id' => (int)$feed['id']]);
        continue;
    }

    $insert = $pdo->prepare(
        "INSERT INTO news (title, slug, content, featured_image, category_id, status, publish_at, created_at, updated_at)
         VALUES (:title, :slug, :content, :img, :cat, 'draft', NOW(), NOW(), NOW())"
    );

    foreach ($items as $it) {
        $title = (string)($it['title'] ?? '');
        if ($title === '') continue;

        $slug = gdy_slugify($title);
        if ($slug === '') continue;

        $content = (string)($it['link'] ?? '');
        $img = null;

        try {
            $insert->execute([
                ':title'   => $title,
                ':slug'    => $slug,
                ':content' => $content,
                ':img'     => $img,
                ':cat'     => (int)($feed['category_id'] ?? 0),
            ]);
        } catch (PDOException $e) {
            // Duplicate (slug/unique): ignore
            if (function_exists('gdy_db_is_duplicate_exception') && gdy_db_is_duplicate_exception($e, $pdo)) {
                continue;
            }
            // Otherwise log and continue
            error_log('[update_feeds] insert failed: ' . $e->getMessage());
        }
    }

    $upd = $pdo->prepare("UPDATE feeds SET last_fetched_at = NOW() WHERE id = :id");
    $upd->execute([':id' => (int)$feed['id']]);
}

exit(0);
