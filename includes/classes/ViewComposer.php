<?php
namespace Godyar\View;

/**
 * ViewComposer (legacy helper)
 *
 * Some builds shipped with a malformed/duplicated file here. Even if not used,
 * keeping it valid avoids parse errors if it is ever included/autoloaded.
 */
class ViewComposer
{
    public static function compose(\PDO $pdo): array
    {
        // Settings column compatibility (value vs setting_value)
        $col = 'setting_value';
        try {
            if (function_exists('gdy_settings_value_column')) {
                $col = (string) gdy_settings_value_column($pdo);
            } else {
                // Lightweight fallback without requiring extra files
                $cols = [];
	                foreach ($pdo->query("SHOW COLUMNS FROM settings")?->fetchAll(\PDO::FETCH_ASSOC) ?? [] as $r) {
	                    if (isset($r['Field']) && (string)$r['Field'] !== '') {
	                        $cols[] = (string)$r['Field'];
	                    }
	                }
	                if (in_array('setting_value', $cols, true)) {
	                    $col = 'setting_value';
	                } elseif (in_array('value', $cols, true)) {
	                    $col = 'value';
	                }
            }
        } catch (\Throwable $e) {
            // ignore
        }

        $settings = [];
        try {
            if (class_exists('\\Cache')) {
                $settings = \Cache::remember('settings_all', 300, function () use ($pdo, $col) {
                    $out = [];
                    $stmt = $pdo->query("SELECT setting_key, {$col} AS value FROM settings");
                    foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                        $k = (string)($row['setting_key'] ?? '');
                        if ($k !== '') $out[$k] = (string)($row['value'] ?? '');
                    }
                    return $out;
                });
            } else {
                $stmt = $pdo->query("SELECT setting_key, {$col} AS value FROM settings");
                foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                    $k = (string)($row['setting_key'] ?? '');
                    if ($k !== '') $settings[$k] = (string)($row['value'] ?? '');
                }
            }
        } catch (\Throwable $e) {}

        $decode = function (string $key) use ($settings): array {
            if (!isset($settings[$key])) return [];
            $arr = json_decode((string)$settings[$key], true);
            return is_array($arr) ? $arr : [];
        };

        $site_name    = $settings['site_name'] ?? ($settings['site_title'] ?? 'Godyar');
        $main_menu    = $decode('menu_main');
        $footer_links = $decode('menu_footer');
        $social_links = $decode('social_links');
        $footer_about = $settings['footer_about'] ?? '';

        // Optional: allow Menus class override (if present)
        if (class_exists('\\Menus')) {
            try {
                $menus = new \Menus();
                $maybe = $menus->get('main');
                if (is_array($maybe) && $maybe) $main_menu = $maybe;
                $maybe = $menus->get('footer');
                if (is_array($maybe) && $maybe) $footer_links = $maybe;
            } catch (\Throwable $e) {}
        }

        // Optional: home sections
        $sections = [];
        if (class_exists('\\Categories') && method_exists('\\Categories', 'activeWithArticles')) {
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

        // Optional: ads between posts
        $ads_between_posts = [];
        if (class_exists('\\Ads') && method_exists('\\Ads', 'active')) {
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

        return compact('site_name', 'main_menu', 'sections', 'ads_between_posts', 'footer_links', 'social_links', 'footer_about');
    }
}
