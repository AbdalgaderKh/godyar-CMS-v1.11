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
    /**
     * Start a hardened session with safe defaults.
     *
     * - Forces cookies-only sessions
     * - Enables strict mode
     * - Sets HttpOnly + SameSite and Secure when on HTTPS
     *
     * You may override any option by passing $options.
     */
    function gdy_session_start(array $options = []): bool {
        if (session_status() === PHP_SESSION_ACTIVE) return true;

        // Apply best-practice INI defaults (safe no-ops if ini_set is disabled)
        if (function_exists('ini_set')) {
            @ini_set('session.use_only_cookies', '1');
            @ini_set('session.use_strict_mode', '1');
            @ini_set('session.use_trans_sid', '0');
        }

        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || ((string)($_SERVER['SERVER_PORT'] ?? '') === '443')
            || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower((string)$_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https');

        $defaults = [
            // PHP session_start options (supported in modern PHP versions)
            'use_strict_mode'  => true,
            'cookie_httponly'  => true,
            'cookie_secure'    => $isHttps,
            // If your app relies on cross-site POSTs/iframes, change to 'None' and ensure HTTPS.
            'cookie_samesite'  => 'Lax',
        ];

        // Caller overrides defaults
        $startOptions = array_merge($defaults, $options);

        return (bool)gdy_suppress_errors(static function () use ($startOptions) {
            // Avoid warnings if headers already sent
            if (headers_sent()) {
                return session_start();
            }
            return session_start($startOptions);
        });
    }
}

if (!function_exists('gdy_session_rotate')) {
    /**
     * Regenerate session ID (best-effort) to mitigate fixation/hijacking.
     * Safe to call multiple times; will no-op if headers already sent.
     */
    function gdy_session_rotate(string $reason = ''): void {
        if (session_status() !== PHP_SESSION_ACTIVE) return;
        if (!headers_sent()) {
            // true => delete old session
            @session_regenerate_id(true);
        }
        $_SESSION['__gdy_rotated_at'] = time();
        if ($reason !== '') {
            $_SESSION['__gdy_rotated_reason'] = $reason;
        }
    }
}

if (!function_exists('gdy_admin_session_guard')) {
    /**
     * Admin-only session hardening:
     * - idle timeout
     * - periodic rotation
     *
     * Returns true if session is valid, false if invalidated.
     */
    function gdy_admin_session_guard(int $idleSeconds = 1800, int $rotateEverySeconds = 900): bool {
        if (session_status() !== PHP_SESSION_ACTIVE) return true;

        $now = time();

        // Idle timeout (only if user is logged-in for admin)
        $role = (string)($_SESSION['user']['role'] ?? '');
        if ($role !== '' && ($role === 'admin' || $role === 'superadmin' || $role === 'writer' || $role === 'author')) {
            $last = (int)($_SESSION['__gdy_last_activity'] ?? 0);
            if ($last > 0 && ($now - $last) > $idleSeconds) {
                // Invalidate
                $_SESSION = [];
                if (ini_get('session.use_cookies')) {
                    $params = session_get_cookie_params();
                    setcookie(session_name(), '', $now - 3600, $params['path'] ?? '/', $params['domain'] ?? '', (bool)($params['secure'] ?? false), (bool)($params['httponly'] ?? true));
                }
                @session_destroy();
                return false;
            }

            // Update activity timestamp
            $_SESSION['__gdy_last_activity'] = $now;

            // Periodic rotation
            $rot = (int)($_SESSION['__gdy_rotated_at'] ?? 0);
            if ($rot === 0 || ($now - $rot) >= $rotateEverySeconds) {
                gdy_session_rotate('periodic');
            }
        }

        return true;
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
    function gdy_setcookie(...$args): bool {
        if (headers_sent()) return false;
        return (bool)gdy_suppress_errors(static function () use ($args) {
            // Harden defaults without breaking callers:
            // - If HTTPS is detected, force `secure=true` unless explicitly provided.
            // - Default `httponly=true` unless explicitly provided.
            // Supports both: setcookie(name, value, optionsArray) and legacy positional args.

            $isHttps = false;
            try {
                if (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') {
                    $isHttps = true;
                } elseif (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower((string)$_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') {
                    $isHttps = true;
                } elseif (!empty($_SERVER['SERVER_PORT']) && (string)$_SERVER['SERVER_PORT'] === '443') {
                    $isHttps = true;
                }
            } catch (\Throwable $e) {
                // ignore
            }

            // options-array form: setcookie(name, value, ['expires'=>.., 'secure'=>.., ...])
            if (isset($args[2]) && is_array($args[2])) {
                $opts = $args[2];
                if (!array_key_exists('secure', $opts)) {
                    $opts['secure'] = $isHttps;
                } elseif ($isHttps) {
                    $opts['secure'] = (bool)$opts['secure'] || $isHttps;
                }
                if (!array_key_exists('httponly', $opts)) {
                    $opts['httponly'] = true;
                }
                // Default SameSite when not set (keeps most flows working; avoids "None" without Secure).
                if (!array_key_exists('samesite', $opts)) {
                    $opts['samesite'] = 'Lax';
                }
                $args[2] = $opts;
                return setcookie(...$args);
            }

            // legacy positional args: name,value,expires,path,domain,secure,httponly
            $defaults = [2 => 0, 3 => '', 4 => '', 5 => false, 6 => true];
            $args = $args + $defaults;
            if ($isHttps) {
                $args[5] = (bool)$args[5] || $isHttps;
            }
            $args[6] = array_key_exists(6, $args) ? (bool)$args[6] : true;
            return setcookie(...$args);
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


// ------------------------------
// Input sanitization helpers
// ------------------------------
if (!function_exists('gdy_strip_controls')) {
    /**
     * Remove control characters (including NULL bytes) except tab/newline.
     */
    function gdy_strip_controls(string $s): string {
        // Normalize newlines first
        $s = str_replace(["\r\n", "\r"], "\n", $s);
        // Remove all C0 controls except \t and \n
        $s = preg_replace('~[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+~u', '', $s) ?? $s;
        return $s;
    }
}

if (!function_exists('gdy_clean_user_text')) {
    /**
     * Clean user-provided text for safe storage (text-only).
     * - strips HTML tags
     * - removes control chars
     * - collapses excessive whitespace
     * - enforces max length (UTF-8)
     */
    function gdy_clean_user_text($v, int $maxLen = 2000): string {
        $s = is_string($v) ? $v : (is_null($v) ? '' : (string)$v);
        $s = trim($s);
        $s = gdy_strip_controls($s);
        // Text-only storage
        $s = strip_tags($s);
        // Collapse whitespace (but keep newlines)
        $s = preg_replace('~[ \t]+~u', ' ', $s) ?? $s;
        $s = preg_replace('~\n{4,}~u', "\n\n\n", $s) ?? $s;
        $s = trim($s);

        if ($maxLen > 0 && function_exists('mb_strlen') && function_exists('mb_substr')) {
            if (mb_strlen($s, 'UTF-8') > $maxLen) {
                $s = mb_substr($s, 0, $maxLen, 'UTF-8');
            }
        } elseif ($maxLen > 0 && strlen($s) > $maxLen) {
            $s = substr($s, 0, $maxLen);
        }
        return $s;
    }
}



// -----------------------------------------------------------------------------
// Admin session fingerprint (lightweight, proxy-tolerant)
// -----------------------------------------------------------------------------
if (!function_exists('gdy_client_ip_for_fingerprint')) {
    /**
     * Returns a coarse client-IP representation suitable for session binding.
     * - IPv4: first 3 octets ( /24 )
     * - IPv6: first 4 hextets ( /64-ish )
     *
     * Set GDY_TRUST_PROXY=1 to trust X-Forwarded-For.
     */
    function gdy_client_ip_for_fingerprint(): string {
        $trustProxy = (string)($_ENV['GDY_TRUST_PROXY'] ?? getenv('GDY_TRUST_PROXY') ?? '');
        $ip = '';
        if ($trustProxy === '1') {
            $xff = (string)($_SERVER['HTTP_X_FORWARDED_FOR'] ?? '');
            if ($xff !== '') {
                // Take the first IP in the list
                $parts = explode(',', $xff);
                $ip = trim((string)($parts[0] ?? ''));
            }
        }
        if ($ip === '') {
            $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
        }
        if ($ip === '') { return ''; }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $oct = explode('.', $ip);
            return implode('.', array_slice($oct, 0, 3)); // /24
        }
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $norm = strtolower($ip);
            // Keep first 4 hextets
            $hextets = explode(':', $norm);
            $hextets = array_values(array_filter($hextets, static fn($x) => $x !== ''));
            return implode(':', array_slice($hextets, 0, 4));
        }
        return '';
    }
}

if (!function_exists('gdy_admin_session_fingerprint_guard')) {
    /**
     * Binds an admin session to a lightweight fingerprint:
     * - User-Agent hash
     * - Coarse IP prefix (/24 or /64-ish)
     *
     * Designed to be tolerant of proxies and mobile IP churn while still
     * stopping basic session theft.
     *
     * Toggle with GDY_ADMIN_SESSION_FINGERPRINT=0
     */
    function gdy_admin_session_fingerprint_guard(): bool {
        if (session_status() !== PHP_SESSION_ACTIVE) { return true; }

        $enabled = (string)($_ENV['GDY_ADMIN_SESSION_FINGERPRINT'] ?? getenv('GDY_ADMIN_SESSION_FINGERPRINT') ?? '1');
        if ($enabled === '0') { return true; }

        $role = (string)($_SESSION['user']['role'] ?? '');
        if ($role === '' || in_array($role, ['admin','superadmin','writer','author'], true) === FALSE) {
            return true; // not an admin-style session
        }

        $ua = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
        $ipPrefix = gdy_client_ip_for_fingerprint();

        $fp = hash('sha256', $ua . '|' . $ipPrefix);

        if (!isset($_SESSION['__gdy_admin_fp'])) {
            $_SESSION['__gdy_admin_fp'] = $fp;
            return true;
        }

        $prev = (string)($_SESSION['__gdy_admin_fp'] ?? '');
        if ($prev === $fp) { return true; }

        // mismatch => invalidate admin session
        error_log('[Godyar Admin FP] mismatch - invalidating session');
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 3600, $params['path'] ?? '/', $params['domain'] ?? '', (bool)($params['secure'] ?? false), (bool)($params['httponly'] ?? true));
        }
        @session_destroy();
        return false;
    }
}
