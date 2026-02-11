<?php
declare(strict_types=1);

/**
 * File/IO and "safe" wrappers.
 *
 * Notes:
 * - Some modules call helpers like gdy_file_get_contents() with different parameter counts.
 *   We intentionally mirror PHP's native signatures (with sane defaults) to avoid
 *   inconsistent-parameter warnings from static analyzers.
 * - We avoid the @ error suppression operator and instead use a temporary error handler.
 */

if (!function_exists('gdy_suppress_errors')) {
    function gdy_suppress_errors(callable $fn, $default = null)
    {
        set_error_handler(static function () { return true; });
        try {
            return $fn();
        } catch (Exception $e) {
            return $default;
        } finally {
            restore_error_handler();
        }
    }
}

if (!function_exists('gdy_session_start')) {
    function gdy_session_start(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            gdy_suppress_errors(static function () { session_start(); });
        }
    }
}

if (!function_exists('gdy_mkdir')) {
    function gdy_mkdir(string $dir, int $mode = 0755, bool $recursive = true): bool
    {
        if ($dir === '' || is_dir($dir)) {
            return true;
        }
        // Prevent overly permissive modes in shared hosting.
        if ($mode >= 0770) {
            $mode = 0755;
        }
        $old = umask(0);
        try {
            return (bool)gdy_suppress_errors(static function () use ($dir, $mode, $recursive) {
                return mkdir($dir, $mode, $recursive);
            });
        } finally {
            umask($old);
        }
    }
}

// Signature mirrors native file_get_contents.
if (!function_exists('gdy_file_get_contents')) {
    function gdy_file_get_contents(
        string $filename,
        bool $use_include_path = false,
        $context = null,
        int $offset = 0,
        ?int $length = null
    ) {
        // Allow remote URLs when caller provides a context (or allow_url_fopen is enabled).
        if (!preg_match('~^https?://~i', $filename) && !is_file($filename)) {
            return false;
        }
        return gdy_suppress_errors(static function () use ($filename, $use_include_path, $context, $offset, $length) {
            if ($length === null) {
                return file_get_contents($filename, $use_include_path, $context, $offset);
            }
            return file_get_contents($filename, $use_include_path, $context, $offset, $length);
        });
    }
}

if (!function_exists('gdy_file_put_contents')) {
    function gdy_file_put_contents(string $filename, string $data, int $flags = 0, $context = null)
    {
        $dir = dirname($filename);
        if ($dir !== '' && !is_dir($dir)) {
            gdy_mkdir($dir, 0755, true);
        }
        return gdy_suppress_errors(static function () use ($filename, $data, $flags, $context) {
            if ($context !== null) {
                return file_put_contents($filename, $data, $flags, $context);
            }
            return file_put_contents($filename, $data, $flags);
        });
    }
}

if (!function_exists('gdy_unlink')) {
    function gdy_unlink(string $path): bool
    {
        if (!is_file($path)) {
            return false;
        }
        return (bool)gdy_suppress_errors(static function () use ($path) {
            return unlink($path);
        });
    }
}

if (!function_exists('gdy_chmod')) {
    function gdy_chmod(string $path, int $mode): bool
    {
        if (!file_exists($path)) {
            return false;
        }
        return (bool)gdy_suppress_errors(static function () use ($path, $mode) {
            return chmod($path, $mode);
        });
    }
}

if (!function_exists('gdy_fopen')) {
    function gdy_fopen(string $filename, string $mode)
    {
        return gdy_suppress_errors(static function () use ($filename, $mode) {
            return fopen($filename, $mode);
        });
    }
}

if (!function_exists('gdy_fclose')) {
    function gdy_fclose($handle): bool
    {
        if (!is_resource($handle)) {
            return false;
        }
        return (bool)gdy_suppress_errors(static function () use ($handle) {
            return fclose($handle);
        });
    }
}

if (!function_exists('gdy_strtotime')) {
    function gdy_strtotime(string $value)
    {
        return gdy_suppress_errors(static function () use ($value) {
            return strtotime($value);
        });
    }
}

if (!function_exists('gdy_parse_url')) {
    function gdy_parse_url(string $url, int $component = -1)
    {
        return gdy_suppress_errors(static function () use ($url, $component) {
            return parse_url($url, $component);
        });
    }
}

if (!function_exists('gdy_filemtime')) {
    function gdy_filemtime(string $filename)
    {
        if (!file_exists($filename)) {
            return false;
        }
        return gdy_suppress_errors(static function () use ($filename) {
            return filemtime($filename);
        });
    }
}

if (!function_exists('gdy_filesize')) {
    function gdy_filesize(string $filename)
    {
        if (!file_exists($filename)) {
            return false;
        }
        return gdy_suppress_errors(static function () use ($filename) {
            return filesize($filename);
        });
    }
}

if (!function_exists('gdy_getimagesize')) {
    function gdy_getimagesize(string $filename)
    {
        if (!is_file($filename)) {
            return false;
        }
        return gdy_suppress_errors(static function () use ($filename) {
            return getimagesize($filename);
        });
    }
}

if (!function_exists('gdy_move_uploaded_file')) {
    function gdy_move_uploaded_file(string $from, string $to): bool
    {
        return (bool)gdy_suppress_errors(static function () use ($from, $to) {
            return move_uploaded_file($from, $to);
        });
    }
}

if (!function_exists('gdy_finfo_open')) {
    function gdy_finfo_open(int $flags = FILEINFO_MIME_TYPE)
    {
        return gdy_suppress_errors(static function () use ($flags) {
            return finfo_open($flags);
        });
    }
}

if (!function_exists('gdy_finfo_file')) {
    function gdy_finfo_file($finfo, string $filename)
    {
        return gdy_suppress_errors(static function () use ($finfo, $filename) {
            return finfo_file($finfo, $filename);
        });
    }
}

if (!function_exists('gdy_finfo_close')) {
    function gdy_finfo_close($finfo): bool
    {
        return (bool)gdy_suppress_errors(static function () use ($finfo) {
            return finfo_close($finfo);
        });
    }
}

if (!function_exists('gdy_readfile')) {
    function gdy_readfile(string $filename)
    {
        if (!is_file($filename) || !is_readable($filename)) {
            return false;
        }
        return gdy_suppress_errors(static function () use ($filename) {
            return readfile($filename);
        });
    }
}

// Signature mirrors native simplexml_load_string.
if (!function_exists('gdy_simplexml_load_string')) {
    function gdy_simplexml_load_string(
        string $data,
        ?string $class_name = 'SimpleXMLElement',
        int $options = 0,
        string $namespace_or_prefix = '',
        bool $is_prefix = false
    ) {
        $prev = libxml_use_internal_errors(true);
        try {
            return gdy_suppress_errors(static function () use ($data, $class_name, $options, $namespace_or_prefix, $is_prefix) {
                return simplexml_load_string($data, $class_name, $options, $namespace_or_prefix, $is_prefix);
            });
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($prev);
        }
    }
}

if (!function_exists('gdy_parse_ini_file')) {
    function gdy_parse_ini_file(string $filename, bool $process_sections = false, int $scanner_mode = INI_SCANNER_NORMAL)
    {
        if (!is_file($filename) || !is_readable($filename)) {
            return false;
        }
        return gdy_suppress_errors(static function () use ($filename, $process_sections, $scanner_mode) {
            return parse_ini_file($filename, $process_sections, $scanner_mode);
        });
    }
}

if (!function_exists('gdy_iconv')) {
    function gdy_iconv(string $from, string $to, string $str)
    {
        return gdy_suppress_errors(static function () use ($from, $to, $str) {
            return iconv($from, $to, $str);
        });
    }
}

if (!function_exists('gdy_copy')) {
    function gdy_copy(string $from, string $to): bool
    {
        return (bool)gdy_suppress_errors(static function () use ($from, $to) {
            return copy($from, $to);
        });
    }
}

if (!function_exists('gdy_file_lines')) {
    function gdy_file_lines(string $filename, int $flags = 0): array
    {
        if (!is_file($filename) || !is_readable($filename)) {
            return [];
        }
        $res = gdy_suppress_errors(static function () use ($filename, $flags) {
            return file($filename, $flags);
        });
        return is_array($res) ? $res : [];
    }
}

if (!function_exists('gdy_rmdir')) {
    function gdy_rmdir(string $dirname): bool
    {
        if (!is_dir($dirname)) {
            return false;
        }
        return (bool)gdy_suppress_errors(static function () use ($dirname) {
            return rmdir($dirname);
        });
    }
}

if (!function_exists('gdy_mail')) {
    function gdy_mail(string $to, string $subject, string $message, string $additional_headers = '', string $additional_params = ''): bool
    {
        return (bool)gdy_suppress_errors(static function () use ($to, $subject, $message, $additional_headers, $additional_params) {
            return $additional_params !== ''
                ? mail($to, $subject, $message, $additional_headers, $additional_params)
                : mail($to, $subject, $message, $additional_headers);
        });
    }
}

// GD image helpers (used by OG + uploads)
if (!function_exists('gdy_imagecreatefromjpeg')) {
    function gdy_imagecreatefromjpeg(string $path) { return gdy_suppress_errors(static fn() => imagecreatefromjpeg($path)); }
}
if (!function_exists('gdy_imagecreatefrompng')) {
    function gdy_imagecreatefrompng(string $path) { return gdy_suppress_errors(static fn() => imagecreatefrompng($path)); }
}
if (!function_exists('gdy_imagecreatefromwebp')) {
    function gdy_imagecreatefromwebp(string $path) { return gdy_suppress_errors(static fn() => imagecreatefromwebp($path)); }
}
if (!function_exists('gdy_imagecreatetruecolor')) {
    function gdy_imagecreatetruecolor(int $w, int $h) { return gdy_suppress_errors(static fn() => imagecreatetruecolor($w, $h)); }
}
if (!function_exists('gdy_imagecopyresampled')) {
    function gdy_imagecopyresampled($dst, $src, int $dst_x, int $dst_y, int $src_x, int $src_y, int $dst_w, int $dst_h, int $src_w, int $src_h): bool
    {
        return (bool)gdy_suppress_errors(static function () use ($dst, $src, $dst_x, $dst_y, $src_x, $src_y, $dst_w, $dst_h, $src_w, $src_h) {
            return imagecopyresampled($dst, $src, $dst_x, $dst_y, $src_x, $src_y, $dst_w, $dst_h, $src_w, $src_h);
        });
    }
}
if (!function_exists('gdy_imagejpeg')) {
    function gdy_imagejpeg($img, ?string $filename = null, int $quality = 90): bool
    {
        return (bool)gdy_suppress_errors(static function () use ($img, $filename, $quality) {
            return $filename !== null ? imagejpeg($img, $filename, $quality) : imagejpeg($img);
        });
    }
}
if (!function_exists('gdy_imagepng')) {
    function gdy_imagepng($img, ?string $filename = null, int $quality = 6): bool
    {
        return (bool)gdy_suppress_errors(static function () use ($img, $filename, $quality) {
            return $filename !== null ? imagepng($img, $filename, $quality) : imagepng($img);
        });
    }
}
if (!function_exists('gdy_imagewebp')) {
    function gdy_imagewebp($img, ?string $filename = null, int $quality = 80): bool
    {
        return (bool)gdy_suppress_errors(static function () use ($img, $filename, $quality) {
            return $filename !== null ? imagewebp($img, $filename, $quality) : imagewebp($img);
        });
    }
}
if (!function_exists('gdy_imagedestroy')) {
    function gdy_imagedestroy($img): bool { return (bool)gdy_suppress_errors(static fn() => imagedestroy($img)); }
}
if (!function_exists('gdy_imagesavealpha')) {
    function gdy_imagesavealpha($img, bool $enable): bool { return (bool)gdy_suppress_errors(static fn() => imagesavealpha($img, $enable)); }
}
if (!function_exists('gdy_imagepalettetotruecolor')) {
    function gdy_imagepalettetotruecolor($img): bool { return (bool)gdy_suppress_errors(static fn() => imagepalettetotruecolor($img)); }
}

// Convenience wrapper: suppress errors for IndexNow submission.
if (!function_exists('gdy_indexnow_submit_safe')) {
    function gdy_indexnow_submit_safe(...$args): bool
    {
        if (!function_exists('gdy_indexnow_submit')) {
            return false;
        }
        $res = gdy_suppress_errors(static function () use ($args) {
            return gdy_indexnow_submit(...$args);
        });
        return (bool)$res;
    }
}
