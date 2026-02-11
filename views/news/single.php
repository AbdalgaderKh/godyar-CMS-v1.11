<?php
declare(strict_types=1);
?>
<!DOCTYPE html>
<html>
<head>
    <title><?= htmlspecialchars($meta_title, ENT_QUOTES, 'UTF-8') ?></title>
    <meta name="description" content="<?= htmlspecialchars($meta_description, ENT_QUOTES, 'UTF-8') ?>">
    <link rel="canonical" href="<?= htmlspecialchars($canonical_url, ENT_QUOTES, 'UTF-8') ?>">
    <meta property="og:title" content="<?= htmlspecialchars($article['title'], ENT_QUOTES, 'UTF-8') ?>">
    <meta property="og:description" content="<?= htmlspecialchars($meta_description, ENT_QUOTES, 'UTF-8') ?>">
    <meta property="og:image" content="<?= htmlspecialchars($og_image, ENT_QUOTES, 'UTF-8') ?>">
    <link rel="stylesheet" href="/assets/css/article-single.css?v=1">
</head>
<body>
    <article class="article-single">

        <header class="article-header">
            <h1><?= htmlspecialchars($article['title'], ENT_QUOTES, 'UTF-8') ?></h1>

            <div class="article-meta">
                <span class="author">
                    <img src="<?= htmlspecialchars($article['avatar'], ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($article['username'], ENT_QUOTES, 'UTF-8') ?>">
                    <?= htmlspecialchars($article['username'], ENT_QUOTES, 'UTF-8') ?>
                </span>

                <span class="date">
                    <?= date('Y-m-d', strtotime($article['published_at'])) ?>
                </span>

                <span class="category">
                    <a href="/category/<?= htmlspecialchars($article['category_slug'], ENT_QUOTES, 'UTF-8') ?>">
                        <?= htmlspecialchars($article['category_name'], ENT_QUOTES, 'UTF-8') ?>
                    </a>
                </span>

                <span class="views">
                    👁️ <?= number_format($article['views'] + 1) ?> مشاهدة
                </span>
            </div>

            <?php if (!empty($article['image'])): ?>
            <div class="article-image">
                <img 
                    src="<?= htmlspecialchars($article['image'], ENT_QUOTES, 'UTF-8') ?>" 
                    alt="<?= htmlspecialchars($article['title'], ENT_QUOTES, 'UTF-8') ?>"
                    loading="lazy"
                >
            </div>
            <?php endif; ?>
        </header>

        <div class="article-content">
            <?= $article['content'] ?>
        </div>

    </article>
</body>
</html>
