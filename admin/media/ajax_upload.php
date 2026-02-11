<?php
declare(strict_types=1);

require_once __DIR__ . '/../_admin_guard.php';
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/auth.php';

use Godyar\Auth;
use Godyar\SafeUploader;

header('Content-Type: application/json; charset=utf-8');

function jexit(int $code, array $data): void {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    exit;
}

if (!Auth::isLoggedIn()) {
    jexit(401, ['ok' => false, 'error' => 'unauthorized']);
}

// CSRF (supports both verify_csrf_token and verify_csrf)
try {
    $csrf = (string)($_POST['csrf_token'] ?? '');
    if (function_exists('verify_csrf_token')) {
        if (!verify_csrf_token($csrf)) {
            jexit(419, ['ok' => false, 'error' => 'csrf', 'message' => 'رمز الحماية غير صحيح أو انتهت الجلسة.']);
        }
    } elseif (function_exists('verify_csrf')) {
        // may throw
        verify_csrf();
    }
} catch (Exception $e) {
    jexit(419, ['ok' => false, 'error' => 'csrf', 'message' => 'رمز الحماية غير صحيح أو انتهت الجلسة.']);
}

if (!isset($_FILES['file']) || !is_array($_FILES['file'])) {
    jexit(400, ['ok' => false, 'error' => 'no_file', 'message' => 'لم يتم اختيار ملف.']);
}

$f = $_FILES['file'];
$err = (int)($f['error'] ?? UPLOAD_ERR_NO_FILE);
if ($err === UPLOAD_ERR_NO_FILE) {
    jexit(400, ['ok' => false, 'error' => 'no_file', 'message' => 'لم يتم اختيار ملف.']);
}
if ($err !== UPLOAD_ERR_OK) {
    jexit(400, ['ok' => false, 'error' => 'upload_error', 'message' => 'فشل رفع الملف.']);
}

$orig = (string)($f['name'] ?? 'file');

// Upload destination
$root = defined('ROOT_PATH') ? ROOT_PATH : dirname(__DIR__, 2);
$destAbs = rtrim((string)$root, "/\\") . '/assets/uploads/media';

// Protect upload directory (no listing, no PHP execution)
try {
    if (!is_dir($destAbs)) {
        if (function_exists('gdy_mkdir')) {
            gdy_mkdir($destAbs, 0775, true);
        } else {
            @mkdir($destAbs, 0775, true);
        }
    }
    $ht = rtrim($destAbs, "/" . chr(92)) . '/.htaccess';
    if (!is_file($ht)) {
        $rules = "Options -Indexes\n" .
                 "<FilesMatch \\\"\\.(php|phtml|php\\d|phar)\\\$\\\">\n" .
                 "  Deny from all\n" .
                 "</FilesMatch>\n";
        @file_put_contents($ht, $rules);
    }
} catch (Exception $e) {
    // ignore
}

// Build URL prefix for this installation (supports subdirectory installs)
$baseUrl = function_exists('base_url') ? rtrim((string)base_url(), '/') : '';
$basePath = '';
if ($baseUrl !== '') {
    $bp = parse_url($baseUrl, PHP_URL_PATH);
    if (is_string($bp) && $bp !== '' && $bp !== '/') {
        $basePath = rtrim($bp, '/');
    }
}
$urlPrefix = ($basePath !== '' ? $basePath : '') . '/assets/uploads/media';

// Helper: build absolute URL from rel path
$origin = '';
if ($baseUrl !== '') {
    $scheme = parse_url($baseUrl, PHP_URL_SCHEME);
    $host   = parse_url($baseUrl, PHP_URL_HOST);
    $port   = parse_url($baseUrl, PHP_URL_PORT);
    if (is_string($scheme) && $scheme !== '' && is_string($host) && $host !== '') {
        $origin = $scheme . '://' . $host . ($port ? (':' . (int)$port) : '');
    }
}

// Allowed types (images + documents + audio/video)
$allowedMime = [
    // Images
    'jpg'  => ['image/jpeg'],
    'jpeg' => ['image/jpeg'],
    'png'  => ['image/png'],
    'gif'  => ['image/gif'],
    'webp' => ['image/webp'],

    // Documents
    'pdf'  => ['application/pdf'],
    'doc'  => ['application/msword','application/octet-stream'],
    'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document','application/zip','application/octet-stream'],
    'xls'  => ['application/vnd.ms-excel','application/octet-stream'],
    'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet','application/zip','application/octet-stream'],
    'ppt'  => ['application/vnd.ms-powerpoint','application/octet-stream'],
    'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation','application/zip','application/octet-stream'],
    'csv'  => ['text/csv','text/plain','application/octet-stream'],
    'txt'  => ['text/plain','application/octet-stream'],
    'rtf'  => ['application/rtf','text/rtf','application/octet-stream'],
    'zip'  => ['application/zip','application/x-zip-compressed','application/octet-stream'],
    'rar'  => ['application/x-rar','application/vnd.rar','application/octet-stream'],
    '7z'   => ['application/x-7z-compressed','application/octet-stream'],

    // Audio/Video
    'mp4'  => ['video/mp4','application/octet-stream'],
    'webm' => ['video/webm','application/octet-stream'],
    'mp3'  => ['audio/mpeg','audio/mp3','application/octet-stream'],
    'wav'  => ['audio/wav','audio/x-wav','application/octet-stream'],
    'ogg'  => ['audio/ogg','application/ogg','application/octet-stream'],
    'm4a'  => ['audio/mp4','application/octet-stream'],
];
$allowedExt = array_keys($allowedMime);

try {
    $res = SafeUploader::upload($f, [
        'max_bytes'    => 100 * 1024 * 1024,
        'allowed_ext'  => $allowedExt,
        'allowed_mime' => $allowedMime,
        'dest_abs_dir' => $destAbs,
        'url_prefix'   => $urlPrefix,
        'prefix'       => 'media_',
    ]);
} catch (Exception $e) {
    jexit(500, ['ok' => false, 'error' => 'upload', 'message' => 'تعذر رفع الملف.']);
}

if (empty($res['success'])) {
    $msg = (string)($res['error'] ?? 'تعذر رفع الملف.');
    // Improve common message
    if (str_contains($msg, 'size')) {
        $msg = 'حجم الملف غير مسموح (الحد 100MB).';
    }
    jexit(400, ['ok' => false, 'error' => 'upload', 'message' => $msg]);
}

$rel = (string)($res['rel_url'] ?? '');
$mime = (string)($res['mime'] ?? 'application/octet-stream');
$size = (int)($res['size'] ?? 0);
$abs  = (string)($res['abs_path'] ?? '');
$ext  = (string)($res['ext'] ?? '');

$url = $rel;
if ($origin !== '' && $rel !== '') {
    $url = $origin . $rel;
} elseif ($baseUrl !== '' && $rel !== '') {
    $url = $baseUrl . $rel;
}

// For images: basic integrity check + optional compression/watermark
$isImage = in_array($ext, ['jpg','jpeg','png','gif','webp'], true) && str_starts_with($mime, 'image/');
if ($isImage) {
    if ($abs === '' || !is_file($abs) || gdy_getimagesize($abs) === false) {
        // Remove invalid image
        if ($abs !== '' && is_file($abs)) {
            @unlink($abs);
        }
        jexit(400, ['ok' => false, 'error' => 'bad_image', 'message' => 'الملف ليس صورة صالحة.']);
    }
}

// ---- Optional compression + watermark (GD) ----
function gdy_image_open(string $path, string $mime) {
    switch ($mime) {
        case 'image/jpeg':
        case 'image/jpg':
            return gdy_imagecreatefromjpeg($path);
        case 'image/png':
            return gdy_imagecreatefrompng($path);
        case 'image/webp':
            return function_exists('imagecreatefromwebp') ? gdy_imagecreatefromwebp($path) : null;
        default:
            return null;
    }
}
function gdy_image_save($im, string $path, string $mime, int $quality): bool {
    if ($mime === 'image/png') {
        // PNG: 0 (no compression) .. 9 (max)
        $level = 6;
        imagesavealpha($im, true);
        return gdy_imagepng($im, $path, $level);
    }
    if ($mime === 'image/webp' && function_exists('imagewebp')) {
        return gdy_imagewebp($im, $path, max(0, min(100, $quality)));
    }
    // default jpeg
    return gdy_imagejpeg($im, $path, max(40, min(95, $quality)));
}
function gdy_apply_watermark($im, int $w, int $h, int $opacity): void {
    try {
        $enabled = (int)settings_get('media.watermark.enabled', 0);
        if (!$enabled) { return; }

        $logoUrl = (string)settings_get('site.logo', '');
        if ($logoUrl === '') { return; }

        $p = gdy_parse_url($logoUrl);
        $rel = (string)($p['path'] ?? '');
        if ($rel === '') { return; }

        // Resolve file path under public root
        $root = defined('ROOT_PATH') ? ROOT_PATH : dirname(__DIR__, 3);
        $logoPath = rtrim($root, '/') . $rel;
        if (!is_file($logoPath)) { return; }

        $info = gdy_getimagesize($logoPath);
        if (!$info) { return; }
        $mime = (string)($info['mime'] ?? '');
        $wm = gdy_image_open($logoPath, $mime);
        if (!$wm) { return; }

        $wmW = imagesx($wm);
        $wmH = imagesy($wm);

        // Resize watermark to ~18% of image width (max 220px)
        $targetW = (int)min(220, max(80, (int)round($w * 0.18)));
        $ratio = $wmW > 0 ? ($targetW / $wmW) : 1.0;
        $targetH = (int)max(1, (int)round($wmH * $ratio));

        $wm2 = imagecreatetruecolor($targetW, $targetH);
        imagealphablending($wm2, false);
        imagesavealpha($wm2, true);
        $trans = imagecolorallocatealpha($wm2, 0, 0, 0, 127);
        imagefilledrectangle($wm2, 0, 0, $targetW, $targetH, $trans);
        imagecopyresampled($wm2, $wm, 0, 0, 0, 0, $targetW, $targetH, $wmW, $wmH);
        imagedestroy($wm);

        // Position bottom-left for RTL sites (with padding)
        $pad = 12;
        $x = $pad;
        $y = $h - $targetH - $pad;

        // Merge with opacity
        imagealphablending($im, true);
        // For PNG alpha watermark, imagecopymerge ignores alpha; blend onto a temp first
        $tmp = imagecreatetruecolor($targetW, $targetH);
        imagealphablending($tmp, false);
        imagesavealpha($tmp, true);
        $trans2 = imagecolorallocatealpha($tmp, 0, 0, 0, 127);
        imagefilledrectangle($tmp, 0, 0, $targetW, $targetH, $trans2);
        imagecopy($tmp, $im, 0, 0, $x, $y, $targetW, $targetH);
        imagecopy($tmp, $wm2, 0, 0, 0, 0, $targetW, $targetH);
        imagecopymerge($im, $tmp, $x, $y, 0, 0, $targetW, $targetH, max(10, min(100, $opacity)));
        imagedestroy($tmp);
        imagedestroy($wm2);
    } catch (Exception $e) {
        error_log('[ajax_upload watermark] ' . $e->getMessage());
    }
}
function gdy_compress_and_watermark(string $path, string $mime): void {
    if (!function_exists('imagecreatetruecolor')) { return; }
    $enabled = (int)settings_get('media.compress.enabled', 1);
    if (!$enabled) { 
        // still allow watermark-only
        $enabledWm = (int)settings_get('media.watermark.enabled', 0);
        if (!$enabledWm) { return; }
    }

    $info = gdy_getimagesize($path);
    if (!$info) { return; }
    $w = (int)$info[0];
    $h = (int)$info[1];

    $im = gdy_image_open($path, $mime);
    if (!$im) { return; }

    $maxW = (int)settings_get('media.compress.max_width', 1920);
    $quality = (int)settings_get('media.compress.quality', 82);
    $wmOpacity = (int)settings_get('media.watermark.opacity', 35);

    // Resize if needed
    if ($enabled && $maxW > 0 && $w > $maxW) {
        $newW = $maxW;
        $newH = (int)max(1, (int)round($h * ($newW / $w)));
        $dst = imagecreatetruecolor($newW, $newH);
        if ($mime === 'image/png' || $mime === 'image/webp') {
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
            $trans = imagecolorallocatealpha($dst, 0, 0, 0, 127);
            imagefilledrectangle($dst, 0, 0, $newW, $newH, $trans);
        }
        imagecopyresampled($dst, $im, 0, 0, 0, 0, $newW, $newH, $w, $h);
        imagedestroy($im);
        $im = $dst;
        $w = $newW; $h = $newH;
    }

    // Watermark (logo)
    gdy_apply_watermark($im, $w, $h, $wmOpacity);

    // Save (compress)
    if ($enabled) {
        // For PNG, saving as PNG may not reduce much. Keep type, but apply compression level.
        gdy_image_save($im, $path, $mime, $quality);
    } else {
        // watermark-only, preserve
        gdy_image_save($im, $path, $mime, 90);
    }

    imagedestroy($im);
}

// Process image after upload (only for non-GIF images)
if ($isImage && $ext !== 'gif' && $abs !== '') {
    try {
        gdy_compress_and_watermark($abs, $mime);
    } catch (Exception $e) {
        error_log('[ajax_upload process] ' . $e->getMessage());
    }
}

$size = (int)(($abs !== '' && is_file($abs)) ? (gdy_filesize($abs) ?: $size) : $size);

// Insert into media table if exists
$pdo = gdy_pdo_safe();
if ($pdo instanceof PDO) {
    try {
        $check = gdy_db_stmt_table_exists($pdo, 'media');
        $exists = (bool)($check && $check->fetchColumn());
        if ($exists) {
            $st = $pdo->prepare("INSERT INTO media (file_name, file_path, file_type, file_size, created_at) VALUES (:n,:p,:t,:s,NOW())");
            $st->execute([
                ':n' => $orig,
                ':p' => $url,
                ':t' => $mime,
                ':s' => $size,
            ]);
        }
    } catch (Exception $e) {
        // ignore db failure; upload still OK
        error_log('[ajax_upload] ' . $e->getMessage());
    }
}

jexit(200, ['ok' => true, 'url' => $url, 'name' => $orig, 'size' => $size, 'mime' => $mime]);
