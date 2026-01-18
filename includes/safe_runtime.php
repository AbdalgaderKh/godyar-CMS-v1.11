<?php
/**
 * Runtime helpers to avoid using the '@' error suppression operator.
 *
 * These helpers suppress warnings/notices by installing a temporary error handler,
 * then restoring the previous handler.
 */

if (!function_exists('gdy_suppress_errors')) {
    /**
     * Execute a callable while suppressing PHP warnings/notices.
     *
     * @template T
     * @param callable():T $fn
     * @param mixed $default
     * @return T|mixed
     */
    function gdy_suppress_errors(callable $fn, $default = null) {
        $prev = set_error_handler(static function () { return true; });
        try {
            return $fn();
        } catch (Throwable $e) {
            return $default;
        } finally {
            if ($prev !== null) {
                set_error_handler($prev);
            } else {
                restore_error_handler();
            }
        }
    }
}

if (!function_exists('gdy_session_start')) {
    function gdy_session_start(array $options = []): bool {
        if (session_status() === PHP_SESSION_ACTIVE) return true;
        return (bool)gdy_suppress_errors(static function () use ($options) {
            return session_start($options);
        }, false);
    }
}

if (!function_exists('gdy_mkdir')) {
    function gdy_mkdir(string $path, int $mode = 0775, bool $recursive = true): bool {
        if ($path === '') return false;
        if (is_dir($path)) return true;
        $ok = (bool)gdy_suppress_errors(static function () use ($path, $mode, $recursive) {
            return mkdir($path, $mode, $recursive);
        }, false);
        if ($ok) {
            // Best-effort hardening: ensure group-writable but not world-writable.
            gdy_suppress_errors(static function () use ($path, $mode) {
                gdy_chmod($path, $mode);
                return true;
            }, true);
        }
        return $ok;
    }
}

if (!function_exists('gdy_file_get_contents')) {
    function gdy_file_get_contents(string $path) {
        return gdy_suppress_errors(static function () use ($path) {
            return file_get_contents($path);
        }, false);
    }
}

if (!function_exists('gdy_file_put_contents')) {
    function gdy_file_put_contents(string $path, $data, int $flags = 0): int {
        return (int)gdy_suppress_errors(static function () use ($path, $data, $flags) {
            return file_put_contents($path, $data, $flags);
        }, 0);
    }
}

if (!function_exists('gdy_unlink')) {
    function gdy_unlink(string $path): bool {
        if ($path === '') return false;
        return (bool)gdy_suppress_errors(static function () use ($path) {
            return unlink($path);
        }, false);
    }
}

if (!function_exists('gdy_chmod')) {
    function gdy_chmod(string $path, int $mode): bool {
        return (bool)gdy_suppress_errors(static function () use ($path, $mode) {
            return chmod($path, $mode);
        }, false);
    }
}

if (!function_exists('gdy_finfo_open')) {
    function gdy_finfo_open(int $options = FILEINFO_MIME_TYPE, ?string $magicFile = null) {
        return gdy_suppress_errors(static function () use ($options, $magicFile) {
            return finfo_open($options, $magicFile);
        }, false);
    }
}

if (!function_exists('gdy_finfo_file')) {
    function gdy_finfo_file($finfo, string $filename) {
        return gdy_suppress_errors(static function () use ($finfo, $filename) {
            return finfo_file($finfo, $filename);
        }, false);
    }
}

if (!function_exists('gdy_finfo_close')) {
    function gdy_finfo_close($finfo): bool {
        return (bool)gdy_suppress_errors(static function () use ($finfo) {
            return finfo_close($finfo);
        }, false);
    }
}

if (!function_exists('gdy_mail')) {
    function gdy_mail(...$args): bool {
        return (bool)gdy_suppress_errors(static function () use ($args) {
            return mail(...$args);
        }, false);
    }
}

if (!function_exists('gdy_setcookie')) {
    function gdy_setcookie(...$args): bool {
        if (headers_sent()) return false;
        return (bool)gdy_suppress_errors(static function () use ($args) {
            return setcookie(...$args);
        }, false);
    }
}

if (!function_exists('gdy_ob_clean')) {
    function gdy_ob_clean(): bool {
        if (ob_get_level() <= 0) return false;
        return (bool)gdy_suppress_errors(static function () {
            return ob_clean();
        }, false);
    }
}

// --- Additional wrappers for functions commonly used with '@' ---

if (!function_exists('gdy_ini_set')) {
    function gdy_ini_set(string $option, string $value): bool {
        return (bool)gdy_suppress_errors(static function () use ($option, $value) {
            return ini_set($option, $value);
        }, false);
    }
}

if (!function_exists('gdy_session_destroy')) {
    function gdy_session_destroy(): bool {
        if (session_status() !== PHP_SESSION_ACTIVE) return true;
        return (bool)gdy_suppress_errors(static function () {
            return session_destroy();
        }, false);
    }
}

if (!function_exists('gdy_readfile')) {
    function gdy_readfile(string $filename) {
        return gdy_suppress_errors(static function () use ($filename) {
            return readfile($filename);
        }, false);
    }
}

if (!function_exists('gdy_parse_url')) {
    function gdy_parse_url(string $url, int $component = -1) {
        return gdy_suppress_errors(static function () use ($url, $component) {
            return $component === -1 ? parse_url($url) : parse_url($url, $component);
        }, null);
    }
}

if (!function_exists('gdy_filesize')) {
    function gdy_filesize(string $filename): int {
        $r = gdy_suppress_errors(static function () use ($filename) {
            return filesize($filename);
        }, false);
        return is_int($r) ? $r : 0;
    }
}

if (!function_exists('gdy_file')) {
    function gdy_file(string $filename, int $flags = 0): array {
        $r = gdy_suppress_errors(static function () use ($filename, $flags) {
            return file($filename, $flags);
        }, false);
        return is_array($r) ? $r : [];
    }
}

if (!function_exists('gdy_getimagesize')) {
    function gdy_getimagesize(string $filename) {
        return gdy_suppress_errors(static function () use ($filename) {
            return getimagesize($filename);
        }, false);
    }
}

if (!function_exists('gdy_move_uploaded_file')) {
    function gdy_move_uploaded_file(string $from, string $to): bool {
        return (bool)gdy_suppress_errors(static function () use ($from, $to) {
            return move_uploaded_file($from, $to);
        }, false);
    }
}

if (!function_exists('gdy_fread')) {
    function gdy_fread($handle, int $length): string {
        $r = gdy_suppress_errors(static function () use ($handle, $length) {
            return fread($handle, $length);
        }, false);
        return is_string($r) ? $r : '';
    }
}

if (!function_exists('gdy_flock')) {
    function gdy_flock($handle, int $operation, ?int &$wouldBlock = null): bool {
        $r = gdy_suppress_errors(static function () use ($handle, $operation, &$wouldBlock) {
            return flock($handle, $operation, $wouldBlock);
        }, false);
        return (bool)$r;
    }
}

if (!function_exists('gdy_ftruncate')) {
    function gdy_ftruncate($handle, int $size): bool {
        $r = gdy_suppress_errors(static function () use ($handle, $size) {
            return ftruncate($handle, $size);
        }, false);
        return (bool)$r;
    }
}

if (!function_exists('gdy_rewind')) {
    function gdy_rewind($handle): bool {
        $r = gdy_suppress_errors(static function () use ($handle) {
            return rewind($handle);
        }, false);
        return (bool)$r;
    }
}

if (!function_exists('gdy_fwrite')) {
    function gdy_fwrite($handle, string $string, ?int $length = null): int {
        $r = gdy_suppress_errors(static function () use ($handle, $string, $length) {
            return $length === null ? fwrite($handle, $string) : fwrite($handle, $string, $length);
        }, false);
        return is_int($r) ? $r : 0;
    }
}

if (!function_exists('gdy_fflush')) {
    function gdy_fflush($handle): bool {
        $r = gdy_suppress_errors(static function () use ($handle) {
            return fflush($handle);
        }, false);
        return (bool)$r;
    }
}

if (!function_exists('gdy_rmdir')) {
    function gdy_rmdir(string $dirname): bool {
        return (bool)gdy_suppress_errors(static function () use ($dirname) {
            return rmdir($dirname);
        }, false);
    }
}

if (!function_exists('gdy_iconv')) {
    function gdy_iconv(string $from, string $to, string $str): string {
        $r = gdy_suppress_errors(static function () use ($from, $to, $str) {
            return iconv($from, $to, $str);
        }, false);
        return is_string($r) ? $r : $str;
    }
}

if (!function_exists('gdy_preg_replace')) {
    function gdy_preg_replace(string $pattern, string $replacement, string $subject, int $limit = -1, ?int &$count = null): string {
        $r = gdy_suppress_errors(static function () use ($pattern, $replacement, $subject, $limit, &$count) {
            return preg_replace($pattern, $replacement, $subject, $limit, $count);
        }, false);
        return is_string($r) ? $r : $subject;
    }
}

if (!function_exists('gdy_preg_replace_callback')) {
    function gdy_preg_replace_callback(string $pattern, callable $callback, string $subject, int $limit = -1, ?int &$count = null): string {
        $r = gdy_suppress_errors(static function () use ($pattern, $callback, $subject, $limit, &$count) {
            return preg_replace_callback($pattern, $callback, $subject, $limit, $count);
        }, false);
        return is_string($r) ? $r : $subject;
    }
}

if (!function_exists('gdy_simplexml_load_string')) {
    function gdy_simplexml_load_string(string $data, string $className = 'SimpleXMLElement', int $options = 0) {
        return gdy_suppress_errors(static function () use ($data, $className, $options) {
            return simplexml_load_string($data, $className, $options);
        }, false);
    }
}

if (!function_exists('gdy_date_default_timezone_set')) {
    function gdy_date_default_timezone_set(string $tz): bool {
        return (bool)gdy_suppress_errors(static function () use ($tz) {
            return date_default_timezone_set($tz);
        }, false);
    }
}

// GD image wrappers
if (!function_exists('gdy_imagecreatefromjpeg')) {
    function gdy_imagecreatefromjpeg(string $path) {
        return gdy_suppress_errors(static function () use ($path) {
            return imagecreatefromjpeg($path);
        }, null);
    }
}
if (!function_exists('gdy_imagecreatefrompng')) {
    function gdy_imagecreatefrompng(string $path) {
        return gdy_suppress_errors(static function () use ($path) {
            return imagecreatefrompng($path);
        }, null);
    }
}
if (!function_exists('gdy_imagecreatefromwebp')) {
    function gdy_imagecreatefromwebp(string $path) {
        if (!function_exists('imagecreatefromwebp')) return null;
        return gdy_suppress_errors(static function () use ($path) {
            return imagecreatefromwebp($path);
        }, null);
    }
}
if (!function_exists('gdy_imagecreatetruecolor')) {
    function gdy_imagecreatetruecolor(int $w, int $h) {
        return gdy_suppress_errors(static function () use ($w, $h) {
            return imagecreatetruecolor($w, $h);
        }, null);
    }
}
if (!function_exists('gdy_imagealphablending')) {
    function gdy_imagealphablending($img, bool $blend): bool {
        return (bool)gdy_suppress_errors(static function () use ($img, $blend) {
            return imagealphablending($img, $blend);
        }, false);
    }
}
if (!function_exists('gdy_imagesavealpha')) {
    function gdy_imagesavealpha($img, bool $save): bool {
        return (bool)gdy_suppress_errors(static function () use ($img, $save) {
            return imagesavealpha($img, $save);
        }, false);
    }
}
if (!function_exists('gdy_imagecolorallocatealpha')) {
    function gdy_imagecolorallocatealpha($img, int $r, int $g, int $b, int $a): int {
        $res = gdy_suppress_errors(static function () use ($img, $r, $g, $b, $a) {
            return imagecolorallocatealpha($img, $r, $g, $b, $a);
        }, false);
        return is_int($res) ? $res : 0;
    }
}
if (!function_exists('gdy_imagefilledrectangle')) {
    function gdy_imagefilledrectangle($img, int $x1, int $y1, int $x2, int $y2, int $color): bool {
        return (bool)gdy_suppress_errors(static function () use ($img, $x1, $y1, $x2, $y2, $color) {
            return imagefilledrectangle($img, $x1, $y1, $x2, $y2, $color);
        }, false);
    }
}
if (!function_exists('gdy_imagecopyresampled')) {
    function gdy_imagecopyresampled($dst, $src, int $dstX, int $dstY, int $srcX, int $srcY, int $dstW, int $dstH, int $srcW, int $srcH): bool {
        return (bool)gdy_suppress_errors(static function () use ($dst, $src, $dstX, $dstY, $srcX, $srcY, $dstW, $dstH, $srcW, $srcH) {
            return imagecopyresampled($dst, $src, $dstX, $dstY, $srcX, $srcY, $dstW, $dstH, $srcW, $srcH);
        }, false);
    }
}
if (!function_exists('gdy_imagejpeg')) {
    function gdy_imagejpeg($img, string $path, int $quality = 85): bool {
        return (bool)gdy_suppress_errors(static function () use ($img, $path, $quality) {
            return imagejpeg($img, $path, $quality);
        }, false);
    }
}
if (!function_exists('gdy_imagepng')) {
    function gdy_imagepng($img, string $path, int $level = 7): bool {
        return (bool)gdy_suppress_errors(static function () use ($img, $path, $level) {
            return imagepng($img, $path, $level);
        }, false);
    }
}
if (!function_exists('gdy_imagewebp')) {
    function gdy_imagewebp($img, string $path, int $quality = 82): bool {
        if (!function_exists('imagewebp')) return false;
        return (bool)gdy_suppress_errors(static function () use ($img, $path, $quality) {
            return imagewebp($img, $path, $quality);
        }, false);
    }
}
if (!function_exists('gdy_imagedestroy')) {
    function gdy_imagedestroy($img): bool {
        if (!function_exists('imagedestroy')) return false;
        return (bool)gdy_suppress_errors(static function () use ($img) {
            return imagedestroy($img);
        }, false);
    }
}

// IndexNow submit wrapper (custom)
if (!function_exists('gdy_indexnow_submit_safe')) {
    function gdy_indexnow_submit_safe(...$args): void {
        try {
            if (function_exists('gdy_indexnow_submit')) {
                gdy_indexnow_submit(...$args);
            }
        } catch (Throwable $e) {
            // ignore
        }
    }
}

if (!function_exists('gdy_imagepalettetotruecolor')) {
    function gdy_imagepalettetotruecolor($img): bool {
        if (!function_exists('imagepalettetotruecolor')) return false;
        return (bool)gdy_suppress_errors(static function () use ($img) {
            return imagepalettetotruecolor($img);
        }, false);
    }
}

if (!function_exists('gdy_parse_ini_file')) {
    function gdy_parse_ini_file(string $filename, bool $processSections = false, int $scannerMode = 0) {
        return gdy_suppress_errors(static function () use ($filename, $processSections, $scannerMode) {
            return parse_ini_file($filename, $processSections, $scannerMode);
        }, false);
    }
}

if (!function_exists('gdy_copy')) {
    function gdy_copy(string $source, string $dest): bool {
        return (bool)gdy_suppress_errors(static function () use ($source, $dest) {
            return copy($source, $dest);
        }, false);
    }
}

if (!function_exists('gdy_header_remove')) {
    function gdy_header_remove(?string $name = null): void {
        if (headers_sent()) return;
        gdy_suppress_errors(static function () use ($name) {
            if ($name === null) {
                header_remove();
            } else {
                header_remove($name);
            }
            return true;
        }, true);
    }
}
