<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

/**
 * OG Image generator (safe + simple)
 *
 * - Supports admin settings saved by admin/settings/og.php
 * - Dynamic mode draws title/site/tagline using GD + TTF
 * - Static mode serves the configured default image
 *
 * Endpoint:
 *   /og.php?title=...
 */

// --------------------
// Helpers
// --------------------
function og_hex_to_rgb(string $hex, array $fallback): array
{
    $hex = trim($hex);
    if ($hex === '') return $fallback;
    if ($hex[0] === '#') $hex = substr($hex, 1);
    if (!preg_match('/^[0-9a-fA-F]{6}$/', $hex)) return $fallback;
    return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
}

function og_is_local_path(string $path): bool
{
    $path = trim($path);
    if ($path === '') return false;
    if (preg_match('#^https?://#i', $path)) return false;
    if (strpos($path, "\0") !== false) return false;
    if (strpos($path, '..') !== false) return false;
    return true;
}

function og_resolve_local(string $path): string
{
    $path = ltrim(trim($path), '/');
    return rtrim((string)ROOT_PATH, '/\\') . '/' . $path;
}

function og_output_static(string $defaultPath): void
{
    header('Content-Type: image/png');
    header('Cache-Control: public, max-age=86400');

    if (is_file($defaultPath)) {
        if (function_exists('gdy_readfile')) {
            gdy_readfile($defaultPath);
        } else {
            readfile($defaultPath);
        }
        return;
    }

    // Fallback: 1x1 transparent PNG
    echo base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO3+2lQAAAAASUVORK5CYII=');
}

function og_wrap_text(string $text, int $maxLen): array
{
    $text = trim($text);
    if ($text === '') return [];

    // Prefer splitting on spaces
    $words = preg_split('/\s+/u', $text) ?: [];
    $lines = [];
    $line = '';
    foreach ($words as $w) {
        $cand = $line === '' ? $w : ($line . ' ' . $w);
        if (mb_strlen($cand, 'UTF-8') <= $maxLen) {
            $line = $cand;
        } else {
            if ($line !== '') $lines[] = $line;
            $line = $w;
        }
    }
    if ($line !== '') $lines[] = $line;

    // Hard limit lines
    return array_slice($lines, 0, 4);
}

function og_gd_load_image(string $diskPath)
{
    $ext = strtolower(pathinfo($diskPath, PATHINFO_EXTENSION));
    if (!is_file($diskPath)) return null;
    if (in_array($ext, ['jpg', 'jpeg'], true) && function_exists('imagecreatefromjpeg')) return @imagecreatefromjpeg($diskPath);
    if ($ext === 'png' && function_exists('imagecreatefrompng')) return @imagecreatefrompng($diskPath);
    if ($ext === 'webp' && function_exists('imagecreatefromwebp')) return @imagecreatefromwebp($diskPath);
    return null;
}

function og_render_gd(string $title, string $siteName, string $tagline, array $og, string $arabicMode): void
{
    // Arabic handling: allow forcing static fallback
    $containsArabic = (bool)preg_match('/[\x{0600}-\x{06FF}\x{0750}-\x{077F}\x{08A0}-\x{08FF}]/u', $title);
    if ($containsArabic && $arabicMode === 'static') {
        $default = og_resolve_local((string)($og['default_image'] ?? 'assets/images/og-default.png'));
        og_output_static($default);
        return;
    }

    if (!extension_loaded('gd') || !function_exists('imagecreatetruecolor')) {
        $default = og_resolve_local((string)($og['default_image'] ?? 'assets/images/og-default.png'));
        og_output_static($default);
        return;
    }

    $W = 1200;
    $H = 630;

    $img = imagecreatetruecolor($W, $H);
    imagesavealpha($img, true);

    $bgRGB  = og_hex_to_rgb((string)($og['bg_color'] ?? '#F5F5F5'), [245, 245, 245]);
    $fgRGB  = og_hex_to_rgb((string)($og['text_color'] ?? '#141414'), [20, 20, 20]);
    $mutRGB = og_hex_to_rgb((string)($og['muted_color'] ?? '#4B5563'), [75, 85, 99]);
    $acRGB  = og_hex_to_rgb((string)($og['accent_color'] ?? '#111827'), [17, 24, 39]);

    $bg  = imagecolorallocate($img, $bgRGB[0], $bgRGB[1], $bgRGB[2]);
    $fg  = imagecolorallocate($img, $fgRGB[0], $fgRGB[1], $fgRGB[2]);
    $mut = imagecolorallocate($img, $mutRGB[0], $mutRGB[1], $mutRGB[2]);
    $ac  = imagecolorallocate($img, $acRGB[0], $acRGB[1], $acRGB[2]);

    imagefilledrectangle($img, 0, 0, $W, $H, $bg);

    // Optional template image (local)
    $template = (string)($og['template_image'] ?? '');
    if (og_is_local_path($template)) {
        $tp = og_resolve_local($template);
        $src = og_gd_load_image($tp);
        if ($src) {
            imagecopyresampled($img, $src, 0, 0, 0, 0, $W, $H, imagesx($src), imagesy($src));
            imagedestroy($src);
        }
    }

    // Accent bar
    imagefilledrectangle($img, 0, 0, $W, 14, $ac);

    // Font
    $font = og_resolve_local('assets/fonts/DejaVuSans-Bold.ttf');
    if (!is_file($font)) {
        // If font missing, fallback to static
        $default = og_resolve_local((string)($og['default_image'] ?? 'assets/images/og-default.png'));
        imagedestroy($img);
        og_output_static($default);
        return;
    }

    // Optional logo
    $logo = (string)($og['logo_image'] ?? '');
    if (og_is_local_path($logo)) {
        $lp = og_resolve_local($logo);
        $limg = og_gd_load_image($lp);
        if ($limg) {
            $maxW = 180;
            $maxH = 180;
            $lw = imagesx($limg);
            $lh = imagesy($limg);
            $scale = min($maxW / max(1, $lw), $maxH / max(1, $lh), 1.0);
            $dw = (int)round($lw * $scale);
            $dh = (int)round($lh * $scale);
            imagecopyresampled($img, $limg, 56, 52, 0, 0, $dw, $dh, $lw, $lh);
            imagedestroy($limg);
        }
    }

    // Title
    $title = trim($title);
    if ($title === '') {
        $title = (string)($_GET['t'] ?? '');
    }
    $title = $title !== '' ? $title : ' '; // keep non-empty for bbox calc

    $titleLines = og_wrap_text($title, 34);
    if (!$titleLines) $titleLines = [$title];

    $titleSize = 56;
    if (count($titleLines) >= 3) $titleSize = 48;
    if (count($titleLines) >= 4) $titleSize = 44;

    $lineH = (int)round($titleSize * 1.25);
    $blockH = $lineH * count($titleLines);
    $startY = (int)round(($H * 0.52) - ($blockH / 2));
    $xPad = 70;

    foreach ($titleLines as $i => $ln) {
        $bbox = imagettfbbox($titleSize, 0, $font, $ln) ?: [0,0,0,0,0,0,0,0];
        $textW = abs($bbox[2] - $bbox[0]);
        $x = (int)max($xPad, ($W - $textW) / 2);
        $y = $startY + ($i * $lineH) + $titleSize;
        imagettftext($img, $titleSize, 0, $x, $y, $fg, $font, $ln);
    }

    // Bottom meta (site + tagline)
    $siteName = trim($siteName);
    $tagline = trim($tagline);

    $metaY = $H - 64;
    if ($siteName !== '') {
        imagettftext($img, 24, 0, 70, $metaY, $ac, $font, $siteName);
        $metaY += 30;
    }
    if ($tagline !== '') {
        imagettftext($img, 22, 0, 70, $metaY, $mut, $font, $tagline);
    }

    header('Content-Type: image/png');
    header('Cache-Control: public, max-age=86400');
    imagepng($img);
    imagedestroy($img);
}

// --------------------
// Load settings
// --------------------
$og = [
    'enabled'        => function_exists('settings_get') ? (settings_get('og.enabled', '1') === '1') : true,
    'mode'           => function_exists('settings_get') ? settings_get('og.mode', 'dynamic') : 'dynamic',
    'engine'         => function_exists('settings_get') ? settings_get('og.engine', 'auto') : 'auto',
    'default_image'  => function_exists('settings_get') ? settings_get('og.default_image', 'assets/images/og-default.png') : 'assets/images/og-default.png',
    'template_image' => function_exists('settings_get') ? settings_get('og.template_image', '') : '',
    'logo_image'     => function_exists('settings_get') ? settings_get('og.logo_image', '') : '',
    'bg_color'       => function_exists('settings_get') ? settings_get('og.bg_color', '#F5F5F5') : '#F5F5F5',
    'text_color'     => function_exists('settings_get') ? settings_get('og.text_color', '#141414') : '#141414',
    'muted_color'    => function_exists('settings_get') ? settings_get('og.muted_color', '#4B5563') : '#4B5563',
    'accent_color'   => function_exists('settings_get') ? settings_get('og.accent_color', '#111827') : '#111827',
    'site_name'      => function_exists('settings_get') ? settings_get('og.site_name', '') : '',
    'tagline'        => function_exists('settings_get') ? settings_get('og.tagline', '') : '',
    'arabic_mode'    => function_exists('settings_get') ? settings_get('og.arabic_mode', 'auto') : 'auto',
];

$title = trim((string)($_GET['title'] ?? ''));
$siteName = trim((string)($og['site_name'] ?? ''));
$tagline  = trim((string)($og['tagline'] ?? ''));
$arabicMode = in_array((string)$og['arabic_mode'], ['auto','shape','static'], true) ? (string)$og['arabic_mode'] : 'auto';

$defaultDisk = og_resolve_local((string)$og['default_image']);

// Disabled or static mode => serve default
if (!$og['enabled'] || (string)$og['mode'] === 'static') {
    og_output_static($defaultDisk);
    exit;
}

// Engine selection: currently GD-only (safe default)
og_render_gd($title, $siteName, $tagline, $og, $arabicMode);
exit;
