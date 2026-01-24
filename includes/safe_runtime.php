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
        });
    }
}

if (!function_exists('gdy_mkdir')) {
    function gdy_mkdir(string $path, int $mode = 0775, bool $recursive = true): bool {
        if ($path === '') return false;
        if (is_dir($path)) return true;
        $ok = (bool)gdy_suppress_errors(static function () use ($path, $mode, $recursive) {
            return mkdir($path, $mode, $recursive);
        });
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
        });
    }
}

if (!function_exists('gdy_file_put_contents')) {
    function gdy_file_put_contents(string $path, $data, int $flags = 0): int {
        return (int)gdy_suppress_errors(static function () use ($path, $data, $flags) {
            return file_put_contents($path, $data, $flags);
        });
    }
}

if (!function_exists('gdy_unlink')) {
    function gdy_unlink(string $path): bool {
        if ($path === '') return false;
        return (bool)gdy_suppress_errors(static function () use ($path) {
            return unlink($path);
        });
    }
}

if (!function_exists('gdy_chmod')) {
    function gdy_chmod(string $path, int $mode): bool {
        return (bool)gdy_suppress_errors(static function () use ($path, $mode) {
            return chmod($path, $mode);
        });
    }
}

if (!function_exists('gdy_finfo_open')) {
    function gdy_finfo_open(int $options = FILEINFO_MIME_TYPE, ?string $magicFile = null) {
        return gdy_suppress_errors(static function () use ($options, $magicFile) {
            return finfo_open($options, $magicFile);
        });
    }
}

if (!function_exists('gdy_finfo_file')) {
    function gdy_finfo_file($finfo, string $filename) {
        return gdy_suppress_errors(static function () use ($finfo, $filename) {
            return finfo_file($finfo, $filename);
        });
    }
}

if (function_exists('gdy_finfo_close') === false) {
    function gdy_finfo_close($finfo): bool {
        return (bool)gdy_suppress_errors(static function () use ($finfo) {
            return finfo_close($finfo);
        });
    }
}

if (!function_exists('gdy_mail')) {
    function gdy_mail(...$args): bool {
        return (bool)gdy_suppress_errors(static function () use ($args) {
            return mail(...$args);
        });
    }
}

if (!function_exists('gdy_setcookie')) {
    /**
     * Secure cookie helper.
     *
     * Supports both signatures:
     *  - setcookie(name, value, optionsArray)
     *  - setcookie(name, value, expires, path, domain, secure, httponly)
     *
     * Defaults:
     *  - HttpOnly = true
     *  - Secure   = auto when HTTPS is detected
     *  - SameSite = Lax
     */
    function gdy_setcookie(...$args): bool {
        if (headers_sent()) return false;

        return (bool)gdy_suppress_errors(static function () use ($args) {
            if (count($args) < 1) return false;

            $name = (string)$args[0];
            $value = (string)($args[1] ?? '');

            $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                || ((string)($_SERVER['SERVER_PORT'] ?? '') === '443');

            // Normalize to options array (PHP 7.3+)
            $options = [];
            if (isset($args[2]) && is_array($args[2])) {
                $options = $args[2];
            } else {
                $options = [
                    'expires'  => (int)($args[2] ?? 0),
                    'path'     => (string)($args[3] ?? '/'),
                    'domain'   => (string)($args[4] ?? ''),
                    'secure'   => (bool)($args[5] ?? false),
                    'httponly' => (bool)($args[6] ?? true),
                ];
            }

            if (!array_key_exists('httponly', $options)) $options['httponly'] = true;
            if (!array_key_exists('secure', $options))   $options['secure'] = $isHttps;
            if (!array_key_exists('samesite', $options)) $options['samesite'] = 'Lax';

            // SameSite=None must be Secure (modern browsers)
            $ss = strtolower((string)($options['samesite'] ?? ''));
            if ($ss === 'none') {
                $options['secure'] = true;
            }

            return setcookie($name, $value, $options);
        });
    }
}
if (!function_exists('gdy_ob_clean')) {
    function gdy_ob_clean(): bool {
        if (ob_get_level() <= 0) return false;
        return (bool)gdy_suppress_errors(static function () {
            return ob_clean();
        });
    }
}

// --- Additional wrappers for functions commonly used with '@' ---

if (!function_exists('gdy_ini_set')) {
    function gdy_ini_set(string $option, string $value): bool {
        return (bool)gdy_suppress_errors(static function () use ($option, $value) {
            return ini_set($option, $value);
        });
    }
}

if (!function_exists('gdy_session_destroy')) {
    function gdy_session_destroy(): bool {
        if (session_status() !== PHP_SESSION_ACTIVE) return true;
        return (bool)gdy_suppress_errors(static function () {
            return session_destroy();
        });
    }
}

if (!function_exists('gdy_readfile')) {
    function gdy_readfile(string $filename) {
        return gdy_suppress_errors(static function () use ($filename) {
            return readfile($filename);
        });
    }
}

if (!function_exists('gdy_parse_url')) {
    function gdy_parse_url(string $url, int $component = -1) {
        return gdy_suppress_errors(static function () use ($url, $component) {
            return $component === -1 ? parse_url($url) : parse_url($url, $component);
        });
    }
}

if (!function_exists('gdy_filesize')) {
    function gdy_filesize(string $filename): int {
        $r = gdy_suppress_errors(static function () use ($filename) {
            return filesize($filename);
        });
        return is_int($r) ? $r : 0;
    }
}

if (!function_exists('gdy_file')) {
    function gdy_file(string $filename, int $flags = 0): array {
        $r = gdy_suppress_errors(static function () use ($filename, $flags) {
            return file($filename, $flags);
        });
        return is_array($r) ? $r : [];
    }
}

if (!function_exists('gdy_getimagesize')) {
    function gdy_getimagesize(string $filename) {
        return gdy_suppress_errors(static function () use ($filename) {
            return getimagesize($filename);
        });
    }
}

if (function_exists('gdy_move_uploaded_file') === false) {
    function gdy_move_uploaded_file(string $from, string $to): bool {
        return (bool)gdy_suppress_errors(static function () use ($from, $to) {
            return move_uploaded_file($from, $to);
        });
    }
}

if (!function_exists('gdy_fread')) {
    function gdy_fread($handle, int $length): string {
        $r = gdy_suppress_errors(static function () use ($handle, $length) {
            return fread($handle, $length);
        });
        return is_string($r) ? $r : '';
    }
}

if (!function_exists('gdy_flock')) {
    function gdy_flock($handle, int $operation, ?int &$wouldBlock = null): bool {
        $r = gdy_suppress_errors(static function () use ($handle, $operation, &$wouldBlock) {
            return flock($handle, $operation, $wouldBlock);
        });
        return (bool)$r;
    }
}

if (!function_exists('gdy_ftruncate')) {
    function gdy_ftruncate($handle, int $size): bool {
        $r = gdy_suppress_errors(static function () use ($handle, $size) {
            return ftruncate($handle, $size);
        });
        return (bool)$r;
    }
}

if (!function_exists('gdy_rewind')) {
    function gdy_rewind($handle): bool {
        $r = gdy_suppress_errors(static function () use ($handle) {
            return rewind($handle);
        });
        return (bool)$r;
    }
}

if (!function_exists('gdy_fwrite')) {
    function gdy_fwrite($handle, string $string, ?int $length = null): int {
        $r = gdy_suppress_errors(static function () use ($handle, $string, $length) {
            return $length === null ? fwrite($handle, $string) : fwrite($handle, $string, $length);
        });
        return is_int($r) ? $r : 0;
    }
}

if (!function_exists('gdy_fflush')) {
    function gdy_fflush($handle): bool {
        $r = gdy_suppress_errors(static function () use ($handle) {
            return fflush($handle);
        });
        return (bool)$r;
    }
}

if (!function_exists('gdy_rmdir')) {
    function gdy_rmdir(string $dirname): bool {
        return (bool)gdy_suppress_errors(static function () use ($dirname) {
            return rmdir($dirname);
        });
    }
}

if (!function_exists('gdy_iconv')) {
    function gdy_iconv(string $from, string $to, string $str): string {
        $r = gdy_suppress_errors(static function () use ($from, $to, $str) {
            return iconv($from, $to, $str);
        });
        return is_string($r) ? $r : $str;
    }
}

if (!function_exists('gdy_preg_replace')) {
    function gdy_preg_replace(string $pattern, string $replacement, string $subject, int $limit = -1, ?int &$count = null): string {
        $r = gdy_suppress_errors(static function () use ($pattern, $replacement, $subject, $limit, &$count) {
            return preg_replace($pattern, $replacement, $subject, $limit, $count);
        });
        return is_string($r) ? $r : $subject;
    }
}

if (!function_exists('gdy_preg_replace_callback')) {
    function gdy_preg_replace_callback(string $pattern, callable $callback, string $subject, int $limit = -1, ?int &$count = null): string {
        $r = gdy_suppress_errors(static function () use ($pattern, $callback, $subject, $limit, &$count) {
            return preg_replace_callback($pattern, $callback, $subject, $limit, $count);
        });
        return is_string($r) ? $r : $subject;
    }
}

if (!function_exists('gdy_simplexml_load_string')) {
    function gdy_simplexml_load_string(string $data, string $className = 'SimpleXMLElement', int $options = 0) {
        return gdy_suppress_errors(static function () use ($data, $className, $options) {
            return simplexml_load_string($data, $className, $options);
        });
    }
}

if (!function_exists('gdy_date_default_timezone_set')) {
    function gdy_date_default_timezone_set(string $tz): bool {
        return (bool)gdy_suppress_errors(static function () use ($tz) {
            return date_default_timezone_set($tz);
        });
    }
}

// GD image wrappers
if (!function_exists('gdy_imagecreatefromjpeg')) {
    function gdy_imagecreatefromjpeg(string $path) {
        return gdy_suppress_errors(static function () use ($path) {
            return imagecreatefromjpeg($path);
        });
    }
}
if (!function_exists('gdy_imagecreatefrompng')) {
    function gdy_imagecreatefrompng(string $path) {
        return gdy_suppress_errors(static function () use ($path) {
            return imagecreatefrompng($path);
        });
    }
}
if (!function_exists('gdy_imagecreatefromwebp')) {
    function gdy_imagecreatefromwebp(string $path) {
        if (!function_exists('imagecreatefromwebp')) return null;
        return gdy_suppress_errors(static function () use ($path) {
            return imagecreatefromwebp($path);
        });
    }
}
if (!function_exists('gdy_imagecreatetruecolor')) {
    function gdy_imagecreatetruecolor(int $w, int $h) {
        return gdy_suppress_errors(static function () use ($w, $h) {
            return imagecreatetruecolor($w, $h);
        });
    }
}
if (!function_exists('gdy_imagealphablending')) {
    function gdy_imagealphablending($img, bool $blend): bool {
        return (bool)gdy_suppress_errors(static function () use ($img, $blend) {
            return imagealphablending($img, $blend);
        });
    }
}
if (!function_exists('gdy_imagesavealpha')) {
    function gdy_imagesavealpha($img, bool $save): bool {
        return (bool)gdy_suppress_errors(static function () use ($img, $save) {
            return imagesavealpha($img, $save);
        });
    }
}
if (!function_exists('gdy_imagecolorallocatealpha')) {
    function gdy_imagecolorallocatealpha($img, int $r, int $g, int $b, int $a): int {
        $res = gdy_suppress_errors(static function () use ($img, $r, $g, $b, $a) {
            return imagecolorallocatealpha($img, $r, $g, $b, $a);
        });
        return is_int($res) ? $res : 0;
    }
}
if (!function_exists('gdy_imagefilledrectangle')) {
    function gdy_imagefilledrectangle($img, int $x1, int $y1, int $x2, int $y2, int $color): bool {
        return (bool)gdy_suppress_errors(static function () use ($img, $x1, $y1, $x2, $y2, $color) {
            return imagefilledrectangle($img, $x1, $y1, $x2, $y2, $color);
        });
    }
}
if (!function_exists('gdy_imagecopyresampled')) {
    function gdy_imagecopyresampled($dst, $src, int $dstX, int $dstY, int $srcX, int $srcY, int $dstW, int $dstH, int $srcW, int $srcH): bool {
        return (bool)gdy_suppress_errors(static function () use ($dst, $src, $dstX, $dstY, $srcX, $srcY, $dstW, $dstH, $srcW, $srcH) {
            return imagecopyresampled($dst, $src, $dstX, $dstY, $srcX, $srcY, $dstW, $dstH, $srcW, $srcH);
        });
    }
}
if (!function_exists('gdy_imagejpeg')) {
    function gdy_imagejpeg($img, string $path, int $quality = 85): bool {
        return (bool)gdy_suppress_errors(static function () use ($img, $path, $quality) {
            return imagejpeg($img, $path, $quality);
        });
    }
}
if (!function_exists('gdy_imagepng')) {
    function gdy_imagepng($img, string $path, int $level = 7): bool {
        return (bool)gdy_suppress_errors(static function () use ($img, $path, $level) {
            return imagepng($img, $path, $level);
        });
    }
}
if (!function_exists('gdy_imagewebp')) {
    function gdy_imagewebp($img, string $path, int $quality = 82): bool {
        if (!function_exists('imagewebp')) return false;
        return (bool)gdy_suppress_errors(static function () use ($img, $path, $quality) {
            return imagewebp($img, $path, $quality);
        });
    }
}
if (!function_exists('gdy_imagedestroy')) {
    function gdy_imagedestroy($img): bool {
        if (!function_exists('imagedestroy')) return false;
        return (bool)gdy_suppress_errors(static function () use ($img) {
            return imagedestroy($img);
        });
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
        });
    }
}

if (!function_exists('gdy_parse_ini_file')) {
    function gdy_parse_ini_file(string $filename, bool $processSections = false, int $scannerMode = 0) {
        return gdy_suppress_errors(static function () use ($filename, $processSections, $scannerMode) {
            return parse_ini_file($filename, $processSections, $scannerMode);
        });
    }
}

if (!function_exists('gdy_copy')) {
    function gdy_copy(string $source, string $dest): bool {
        return (bool)gdy_suppress_errors(static function () use ($source, $dest) {
            return copy($source, $dest);
        });
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


if (!function_exists('req_get')) {
    function req_get(string $key, $default = null) {
        return array_key_exists($key, $_GET) ? gdy_sanitize($_GET[$key]) : $default;
    }
}
if (!function_exists('req_post')) {
    function req_post(string $key, $default = null) {
        return array_key_exists($key, $_POST) ? gdy_sanitize($_POST[$key]) : $default;
    }
}
if (!function_exists('req_cookie')) {
    function req_cookie(string $key, $default = null) {
        return array_key_exists($key, $_COOKIE) ? gdy_sanitize($_COOKIE[$key]) : $default;
    }
}
if (!function_exists('req_server')) {
    function req_server(string $key, $default = null) {
        return array_key_exists($key, $_SERVER) ? gdy_sanitize($_SERVER[$key]) : $default;
    }
}

if (!function_exists('req_has_get')) {
    function req_has_get(string $key): bool {
        return array_key_exists($key, $_GET);
    }
}
if (!function_exists('req_has_post')) {
    function req_has_post(string $key): bool {
        return array_key_exists($key, $_POST);
    }
}

// HTML escaping helper
if (!function_exists('e')) {
    function e($v): string {
        if (is_array($v)) {
            $v = json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

// -----------------------------------------------------------------------------
// Request + Output helpers
// الهدف: تقليل الاستخدام المباشر لـ superglobals وتوحيد التعقيم/الترميز.
// -----------------------------------------------------------------------------

if (!function_exists('gdy_sanitize_scalar')) {
    function gdy_sanitize_scalar($v): string {
        if ($v === null) return '';
        if (is_bool($v)) return $v ? '1' : '0';
        if (is_int($v) || is_float($v)) return (string)$v;
        if (is_string($v)) {
            // Remove null-bytes and trim
            $v = str_replace("\0", '', $v);
            return trim($v);
        }
        return trim((string)$v);
    }
}

if (!function_exists('gdy_sanitize')) {
    function gdy_sanitize($v) {
        if (is_array($v)) {
            $out = [];
            foreach ($v as $k => $vv) {
                $out[$k] = gdy_sanitize($vv);
            }
            return $out;
        }
        return gdy_sanitize_scalar($v);
    }
}

if (!function_exists('req_get')) {
    function req_get(string $key, $default = null) {
        return array_key_exists($key, $_GET) ? gdy_sanitize($_GET[$key]) : $default;
    }
}
if (!function_exists('req_post')) {
    function req_post(string $key, $default = null) {
        return array_key_exists($key, $_POST) ? gdy_sanitize($_POST[$key]) : $default;
    }
}
if (!function_exists('req_has_get')) {
    function req_has_get(string $key): bool {
        return array_key_exists($key, $_GET);
    }
}
if (!function_exists('req_has_post')) {
    function req_has_post(string $key): bool {
        return array_key_exists($key, $_POST);
    }
}

// Unified HTML output escaping
if (!function_exists('e')) {
    function e($v): string {
        if (is_array($v)) {
            $v = json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
