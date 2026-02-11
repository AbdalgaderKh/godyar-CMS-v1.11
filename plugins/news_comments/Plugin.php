<?php
// plugins/news_comments/Plugin.php
declare(strict_types=1);

return new class implements GodyarPluginInterface
{
    public function register(PluginManager $pm): void
    {
        // عرض ويدجت التعليقات تحت الخبر
        $pm->addHook('news.after_content', function (int $newsId, array $news) {
            if ($newsId <= 0) return;

            $view = __DIR__ . '/views/widget.php';
            if (is_file($view)) {
                $base = rtrim((string)(function_exists('base_url') ? base_url() : ''), '/');
                $postEndpoint = $base . '/plugins/news_comments/public/post.php';
                include $view;
            }
        }, 30);
    }
};
