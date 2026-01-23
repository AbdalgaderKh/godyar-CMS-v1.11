<?php
namespace Godyar\Util;

use Godyar\SafeUploader;

/**
 * Legacy helper used by some controllers.
 * Migrated to SafeUploader to avoid direct move_uploaded_file() and to enforce MIME/extension checks.
 */
final class Upload
{
    /**
     * Upload an image from a single input field.
     *
     * @param string $field      Input name in $_FILES
     * @param string $destRelDir Destination directory relative to ROOT_PATH (e.g. "/uploads/news")
     * @param int    $maxMB      Max size in MB
     *
     * @return string|null Path relative to web root (same style used by legacy code)
     */
    public static function image(string $field, string $destRelDir, int $maxMB = 5): ?string
    {
        if (empty($_FILES[$field]['name'])) {
            return null;
        }

        if (!defined('ROOT_PATH')) {
            return null;
        }

        if (!class_exists('Godyar\\SafeUploader')) {
            return null;
        }

        $file = $_FILES[$field];
        if (!is_array($file)) {
            return null;
        }

        $destRelDir = '/' . ltrim($destRelDir, '/');
        $destAbs = rtrim((string)ROOT_PATH, "/" . chr(92)) . $destRelDir;

        // Best-effort protection inside upload directories.
        try {
            if (!is_dir($destAbs)) {
                if (function_exists('gdy_mkdir')) {
                    gdy_mkdir($destAbs, 0755, true);
                } else {
                    mkdir($destAbs, 0755, true);
                }
            }
            $ht = rtrim($destAbs, "/" . chr(92)) . '/.htaccess';
            if (!is_file($ht)) {
                @file_put_contents($ht, "Options -Indexes\n<FilesMatch \\\"\\.(php|phtml|php\\d|phar)\\$\\\">\n  Deny from all\n</FilesMatch>\n");
            }
        } catch (\Throwable $e) {
            // ignore
        }

        $allowedMime = [
            'jpg'  => ['image/jpeg'],
            'jpeg' => ['image/jpeg'],
            'png'  => ['image/png'],
            'gif'  => ['image/gif'],
            'webp' => ['image/webp'],
        ];

        $res = SafeUploader::upload($file, [
            'max_bytes'    => max(1, $maxMB) * 1024 * 1024,
            'allowed_ext'  => array_keys($allowedMime),
            'allowed_mime' => $allowedMime,
            'dest_abs_dir' => $destAbs,
            'url_prefix'   => $destRelDir,
            'prefix'       => 'img_',
        ]);

        if (empty($res['success'])) {
            return null;
        }

        // Keep legacy return format (relative path)
        return (string)($res['rel_url'] ?? null);
    }
}
