<?php
declare(strict_types=1);

/**
 * side-toc-only.php
 * Safe/optional WordPress helper plugin.
 *
 * This file is shipped with Godyar CMS distributions by some installers,
 * but it is only relevant when the codebase is used inside WordPress.
 *
 * Behavior:
 * - If WordPress is not present (no add_filter/add_action), the file exits silently.
 * - If WordPress is present, it removes common TOC containers from post content only,
 *   keeping sidebar widgets intact.
 */

if (!function_exists('add_filter') || !function_exists('add_action')) {
    return;
}

/**
 * Remove TOC containers from the_content only.
 * Keeps TOC widgets/sidebars intact because it only touches the post content HTML.
 */
function stoc_remove_toc_from_content($content) {
    if (function_exists('is_admin') && is_admin()) { return $content; }
    if (!is_string($content) || trim($content) === '') { return $content; }

    // Run only on singular posts/pages (optional)
    if (function_exists('is_singular') && !is_singular()) { return $content; }

    // Quick check to avoid DOM parsing when not needed
    $needles = array('ez-toc-container', 'lwptoc', 'toc_container', 'toc-container', 'rank-math-toc', 'wp-block-lwptoc');
    $found = false;
    foreach ($needles as $n) {
        if (stripos($content, $n) !== false) { $found = true; break; }
    }
    if (!$found) { return $content; }

    // Use DOMDocument for safer HTML manipulation when available
    if (class_exists('DOMDocument')) {
        $html = '<!doctype html><html><head><meta charset="utf-8"></head><body>' . $content . '</body></html>';
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML($html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        $xpath = new DOMXPath($dom);
        $queries = array(
            "//*[@class and contains(concat(' ', normalize-space(@class), ' '), ' ez-toc-container ')]",
            "//*[@id='toc_container']",
            "//*[@class and contains(concat(' ', normalize-space(@class), ' '), ' lwptoc ')]",
            "//*[@class and contains(concat(' ', normalize-space(@class), ' '), ' toc-container ')]",
            "//*[@class and contains(concat(' ', normalize-space(@class), ' '), ' rank-math-toc ')]",
            "//*[contains(concat(' ', normalize-space(@class), ' '), ' wp-block-lwptoc ')]",
        );
        foreach ($queries as $q) {
            $nodes = $xpath->query($q);
            if ($nodes) {
                // NodeList is live; iterate backwards
                for ($i = $nodes->length - 1; $i >= 0; $i--) {
                    $node = $nodes->item($i);
                    if ($node && $node->parentNode) {
                        $node->parentNode->removeChild($node);
                    }
                }
            }
        }

        // Extract body innerHTML
        $body = $dom->getElementsByTagName('body')->item(0);
        if ($body) {
            $out = '';
            foreach ($body->childNodes as $child) {
                $out .= $dom->saveHTML($child);
            }
            return $out;
        }
        return $content;
    }

    // Fallback: regex remove common containers (best-effort)
    $content = preg_replace('~<div[^>]*(?:id="toc_container"|class="[^"]*(?:ez-toc-container|lwptoc|toc-container|rank-math-toc)[^"]*")[^>]*>.*?</div>~is', '', $content);
    return is_string($content) ? $content : '';
}

/**
 * Optional CSS fallback to hide TOC blocks inside post content.
 */
function stoc_inline_css_fallback() {
    if (function_exists('is_admin') && is_admin()) { return; }
    if (function_exists('is_singular') && !is_singular()) { return; }
    if (!function_exists('wp_add_inline_style')) { return; }

    // Attach to any existing stylesheet handle, otherwise do nothing.
    $css = '.entry-content .ez-toc-container,.entry-content #toc_container,.entry-content .lwptoc,.entry-content .toc-container,.entry-content .rank-math-toc{display:none!important;}';
    // Many themes use 'wp-block-library' or 'classic-theme-styles'
    if (function_exists('wp_style_is') && wp_style_is('wp-block-library', 'enqueued')) {
        wp_add_inline_style('wp-block-library', $css);
    } elseif (function_exists('wp_style_is') && wp_style_is('classic-theme-styles', 'enqueued')) {
        wp_add_inline_style('classic-theme-styles', $css);
    }
}

add_filter('the_content', 'stoc_remove_toc_from_content', 999);
add_action('wp_enqueue_scripts', 'stoc_inline_css_fallback', 999);
