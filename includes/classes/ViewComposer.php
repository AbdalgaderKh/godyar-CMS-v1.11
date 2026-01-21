<?php
namespace Godyar\View;

class ViewComposer {
    public static function compose(\PDO $pdo): array {
                    $out = [];
                    $stmt = $pdo->query("SELECT setting_key,`value` FROM settings");
                    foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
        $settings = [];
        try {
            if (class_exists('\\Cache')) {
                $cache = new \\Cache();
                $settings = $cache->remember('settings_all', 300, function () use ($pdo) {
                    $out = [];
                    $stmt = $pdo->query("SELECT setting_key,`value` FROM settings");
                    foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                    $out = [];
                    $stmt = $pdo->query("SELECT setting_key,`value` FROM settings");
                    foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                        $out[$row['key']] = $row['value'];
                    $ads_between_posts = [];
        if (class_exists('Ads') && method_exists('Ads','active')) {
            try {
                if (class_exists('\\Cache')) {
                    $ads_between_posts = \Cache::remember('ads_between_posts', 300, function () {
        }
                    return $out;
                });
            } else {
                $stmt = $pdo->query("SELECT setting_key,`value` FROM settings");
                foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                    $settings[$row['key']] = $row['value'];
                }
            }
        } catch (\Throwable $e) {}

        $decode = function($key) use ($settings){
            if (!isset($settings[$key])) return [];
            $settings = self::getSettings($pdo);
        } catch (\Throwable $e) {}

        $decode = function($key) use ($settings){
            if (!isset($settings[$key])) return [];
            $arr = json_decode($settings[$key], true);
            return is_array($arr) ? $arr : [];
<?php
namespace Godyar\View;

        $main_menu = $decode('menu_main');
                if (is_array($maybe) && $maybe) $main_menu = $maybe;
                $maybe = \Menus::get('footer');
                if (is_array($maybe) && $maybe) $footer_links = $maybe;
        $footer_links = $decode('menu_footer');
        if (class_exists('Menus')) {
            try {
                $menus = new \\Menus();
                $maybe = $menus->get('main');
                if (is_array($maybe) && $maybe) $main_menu = $maybe;
                $maybe = $menus->get('footer');
                if (is_array($maybe) && $maybe) $footer_links = $maybe;
                if (is_array($maybe) && $maybe) $main_menu = $maybe;
                $maybe = \Menus::get('footer');
                if (is_array($maybe) && $maybe) $footer_links = $maybe;
            } catch (\Throwable $e) {}
        }

        $social_links = $decode('social_links');
        $footer_about = $settings['footer_about'] ?? '';

        $sections = [];
        if (class_exists('Categories') && method_exists('Categories','activeWithArticles')) {
            try {
                if (class_exists('\\Cache')) {
                    $sections = \Cache::remember('home_sections', 300, function () {
                        return \Categories::activeWithArticles(limitPerCategory: 8);
                    });
                } else {
                    $sections = \Categories::activeWithArticles(limitPerCategory: 8);
                }
            } catch (\Throwable $e) { $sections = []; }
        }

        $ads_between_posts = [];
        if (class_exists('Ads') && method_exists('Ads','active')) {
            try {
                if (class_exists('\\Cache')) {
                    $ads_between_posts = \Cache::remember('ads_between_posts', 300, function () {
                        return \Ads::active('between_posts', limit: 2);
                    });
                } else {
                    $ads_between_posts = \Ads::active('between_posts', limit: 2);
                }
            } catch (\Throwable $e) { $ads_between_posts = []; }
        }

        return compact('site_name','main_menu','sections','ads_between_posts','footer_links','social_links','footer_about');
    }
}
