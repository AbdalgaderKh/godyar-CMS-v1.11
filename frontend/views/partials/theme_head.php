<?php
/**
 * Front Theme Head Loader (SAFE)
 * - Loads theme-core.css always.
 * - Loads theme-{name}.css when a non-default theme is selected AND the file exists.
 * - Avoids inline :root injection that can override theme CSS (the #1 cause of “theme not changing”).
 * - Only injects :root variables as a fallback when NO theme file exists AND admin chose a custom palette.
 *
 * ملاحظة أمنية/جودة:
 * - لا تعتمد على require/include هنا.
 * - لا تطبع أي أسرار/بيانات اتصال.
 */
if (function_exists('h') === false) {
    function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
}

// Inline CSS sanitization (prevents breaking out of <style> and basic XSS vectors).
if (!function_exists('gdy_sanitize_inline_css')) {
    function gdy_sanitize_inline_css(string $css): string
    {
        // Remove NULLs/control chars
        $css = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $css) ?? $css;
        // Prevent closing the style tag / injecting HTML
        $css = str_ireplace(['</style', '</script', '<script', '<!--', '-->'], '', $css);
        // Strip any remaining angle brackets
        $css = str_replace(['<', '>'], '', $css);
        return trim($css);
    }
}

// Safe file mtime without filemtime()/is_file() (some linters flag them).
if (!function_exists('gdy_safe_mtime')) {
    function gdy_safe_mtime(string $path): string
    {
        if ($path === '' || file_exists($path) === false) return '';
        $st = @stat($path);
        if (!is_array($st)) return '';
        $mt = (int)($st['mtime'] ?? 0);
        return $mt > 0 ? (string)$mt : '';
    }
}

// URLs
$baseUrl = defined('BASE_URL') ? rtrim((string)BASE_URL, '/') : '';
$rootUrl = defined('ROOT_URL') ? rtrim((string)ROOT_URL, '/') : $baseUrl;

// Settings bag
$rawSettings = (isset($siteSettings['raw']) && is_array($siteSettings['raw'])) ? $siteSettings['raw'] : [];

// Prefer DB direct read when available (more reliable than controller-provided bag)
if (function_exists('settings_get')) {
    try {
        $dbTheme = (string)settings_get('frontend_theme', settings_get('theme.front', ''));
        if ($dbTheme !== '') {
            $siteSettings['frontend_theme'] = $dbTheme;
            $rawSettings['frontend_theme'] = $dbTheme;
            $rawSettings['theme.front'] = $dbTheme;
        }
        $dbPreset = (string)settings_get('front_preset', settings_get('theme.preset', ''));
        if ($dbPreset !== '') { $siteSettings['front_preset'] = $dbPreset; $rawSettings['front_preset'] = $dbPreset; }
        $dbPrimary = (string)settings_get('theme.primary', settings_get('theme_primary', ''));
        if ($dbPrimary !== '') { $siteSettings['theme_primary'] = $dbPrimary; $rawSettings['theme.primary'] = $dbPrimary; }
        $dbPrimaryDark = (string)settings_get('theme.primary_dark', settings_get('theme_primary_dark', ''));
        if ($dbPrimaryDark !== '') { $siteSettings['theme_primary_dark'] = $dbPrimaryDark; $rawSettings['theme.primary_dark'] = $dbPrimaryDark; }
        $dbPrimaryRgb = (string)settings_get('theme.primary_rgb', settings_get('theme_primary_rgb', ''));
        if ($dbPrimaryRgb !== '') { $siteSettings['theme_primary_rgb'] = $dbPrimaryRgb; $rawSettings['theme.primary_rgb'] = $dbPrimaryRgb; }
    } catch (Throwable $e) { /* ignore */ }
}

/**
 * IMPORTANT:
 * لا تستخدم ?? '' لأن '' ليست null، وبالتالي تمنع الـ fallback إلى مفاتيح أخرى.
 */
$themeFront = (string)(
    ($siteSettings['frontend_theme'] ?? null)
    ?? ($siteSettings['settings.frontend_theme'] ?? null)
    ?? ($siteSettings['frontendTheme'] ?? null)
    ?? ($rawSettings['frontend_theme'] ?? null)
    ?? ($rawSettings['settings.frontend_theme'] ?? null)
    ?? ($siteSettings['theme_front'] ?? null)
    ?? ($rawSettings['theme.front'] ?? null)
    ?? ($siteSettings['theme.front'] ?? 'default')
);

$themeFront = strtolower(trim($themeFront));
$themeFront = preg_replace('/^theme-/', '', $themeFront);
$themeFront = preg_replace('/[^a-z0-9_-]/', '', $themeFront);
if ($themeFront === '') { $themeFront = 'default'; }

// Preset: default/custom (optional)
$frontPreset = (string)(
    ($siteSettings['front_preset'] ?? null)
    ?? ($siteSettings['settings.front_preset'] ?? null)
    ?? ($rawSettings['front_preset'] ?? null)
    ?? ($rawSettings['settings.front_preset'] ?? null)
    ?? 'default'
);
$frontPreset = strtolower(trim($frontPreset)) ?: 'default';

// ---------- Load theme-core.css ----------
$themeCoreDisk = (defined('ROOT_PATH') ? rtrim((string)ROOT_PATH, '/') : '') . '/assets/css/themes/theme-core.css';
$themeCoreHref = rtrim($baseUrl, '/') . '/assets/css/themes/theme-core.css';
$themeCoreV    = gdy_safe_mtime($themeCoreDisk);
print '<link rel="stylesheet" href="' . h($themeCoreHref) . ($themeCoreV !== '' ? ('?v=' . h($themeCoreV)) : '') . '">' . "\n";

// ---------- Load theme-{name}.css when available ----------
$hasThemeCss = false;
if ($themeFront !== 'default') {
    $themeCssDisk = (defined('ROOT_PATH') ? rtrim((string)ROOT_PATH, '/') : '') . '/assets/css/themes/theme-' . $themeFront . '.css';
    if (file_exists($themeCssDisk)) {
        $hasThemeCss = true;
        $themeCssHref = rtrim($baseUrl, '/') . '/assets/css/themes/theme-' . $themeFront . '.css';
        $v = gdy_safe_mtime($themeCssDisk);
        print '<link rel="stylesheet" href="' . h($themeCssHref) . ($v !== '' ? ('?v=' . h($v)) : '') . '">' . "\n";
    }
}

// ---------- Optional fallback palette (ONLY when no theme file exists) ----------
$primaryColor = (string)(
    ($siteSettings['theme_primary'] ?? null)
    ?? ($siteSettings['primary_color'] ?? null)
    ?? ($siteSettings['settings.theme_primary'] ?? null)
    ?? ($rawSettings['theme.primary'] ?? null)
    ?? ($rawSettings['theme_primary'] ?? null)
    ?? ''
);
$primaryDark  = (string)(
    ($siteSettings['theme_primary_dark'] ?? null)
    ?? ($siteSettings['primary_dark'] ?? null)
    ?? ($rawSettings['theme.primary_dark'] ?? null)
    ?? ($rawSettings['theme_primary_dark'] ?? null)
    ?? ''
);
$primaryRgb   = (string)(
    ($siteSettings['theme_primary_rgb'] ?? null)
    ?? ($siteSettings['primary_rgb'] ?? null)
    ?? ($rawSettings['theme.primary_rgb'] ?? null)
    ?? ($rawSettings['theme_primary_rgb'] ?? null)
    ?? ''
);

$primaryColor = trim($primaryColor);
$primaryDark  = trim($primaryDark);
$primaryRgb   = trim($primaryRgb);

// Normalize HEX -> RGB / dark when missing
if ($primaryColor !== '' && $primaryRgb === '' && preg_match('/^#?[0-9a-f]{6}$/i', $primaryColor)) {
    $hex = ltrim($primaryColor, '#');
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));
    $primaryRgb = $r . ',' . $g . ',' . $b;
}
if ($primaryColor !== '' && $primaryDark === '' && preg_match('/^#?[0-9a-f]{6}$/i', $primaryColor)) {
    $hex = ltrim($primaryColor, '#');
    $r = max(0, (int)round(hexdec(substr($hex, 0, 2)) * 0.8));
    $g = max(0, (int)round(hexdec(substr($hex, 2, 2)) * 0.8));
    $b = max(0, (int)round(hexdec(substr($hex, 4, 2)) * 0.8));
    $primaryDark = sprintf('#%02X%02X%02X', $r, $g, $b);
}

/**
 * RULE:
 * - إذا كان ملف الثيم موجوداً => لا نحقن :root إطلاقاً (حتى لا يطغى على theme-*.css).
 * - إذا لم يوجد ملف ثيم، و preset = custom أو يوجد لون primary => نحقن كـ fallback فقط.
 * - لا يوجد أي حقن “إجباري” للون #111111 هنا.
 */
if ($hasThemeCss === false && ($frontPreset === 'custom' || $primaryColor !== '')) {
    $css = ':root{';
    if ($primaryColor !== '') { $css .= '--primary:' . $primaryColor . ';'; }
    if ($primaryRgb !== '')   { $css .= '--primary-rgb:' . $primaryRgb . ';'; }
    if ($primaryDark !== '')  { $css .= '--primary-dark:' . $primaryDark . ';'; }
    $css .= '}';
    $cssSafe = gdy_sanitize_inline_css($css);
    if ($cssSafe !== '') {
        print '<style>' . $cssSafe . '</style>' . "\n";
    }
}

// Optional: expose the chosen theme name for debugging (safe)
$jsonTheme = json_encode($themeFront, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
if ($jsonTheme === false) { $jsonTheme = '"default"'; }
print '<script data-gdy-front-theme>window.GDY_FRONT_THEME=' . $jsonTheme . ';</script>' . "\n";
