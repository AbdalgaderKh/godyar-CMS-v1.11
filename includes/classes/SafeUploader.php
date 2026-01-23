<?php
declare(strict_types=1);

namespace Godyar;

/**
 * SafeUploader
 * -----------
 * Centralised safe upload helper to replace direct move_uploaded_file usage.
 *
 * Controls:
 * - validates upload error codes
 * - enforces max size
 * - allow-list extensions
 * - detects MIME with finfo and validates against per-extension allow-list
 * - randomises filenames
 * - uses wrappers when available (gdy_mkdir, gdy_move_uploaded_file)
 */
final class SafeUploader
{
    /**
     * @param array $file An entry from $_FILES
     * @param array $opt  Options:
     *   - dest_abs_dir (string) required absolute directory
     *   - url_prefix   (string) required public URL prefix (starts with /)
     *   - max_bytes    (int)    default 5MB
     *   - allowed_ext  (array)  extension allow-list, lower-case
     *   - allowed_mime (array)  map ext => list of allowed MIME strings
     *   - prefix       (string) filename prefix
     *
     * @return array{success:bool,error?:string,rel_url?:string,abs_path?:string,original_name?:string,ext?:string,mime?:string,size?:int}
     */
    public static function upload(array $file, array $opt): array
    {
        $destAbs = (string)($opt['dest_abs_dir'] ?? '');
        // Require absolute destination to avoid directory traversal via configuration.
        if ($destAbs !== '' && $destAbs[0] !== '/' && strpos($destAbs, ':') === false) {
            return ['success' => false, 'error' => 'Destination must be absolute'];
        }

        $urlPrefix = (string)($opt['url_prefix'] ?? '');
        if ($destAbs === '' || $urlPrefix === '' || $urlPrefix[0] !== '/') {
            return ['success' => false, 'error' => 'Uploader misconfigured'];
        }

        $err = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($err === UPLOAD_ERR_NO_FILE) {
            return ['success' => false, 'error' => 'No file uploaded'];
        }
        if ($err !== UPLOAD_ERR_OK) {
            return ['success' => false, 'error' => 'Upload error: ' . $err];
        }

        $tmp = (string)($file['tmp_name'] ?? '');
        $orig = (string)($file['name'] ?? '');
        $size = (int)($file['size'] ?? 0);

        $max = (int)($opt['max_bytes'] ?? (5 * 1024 * 1024));
        if ($size <= 0 || $size > $max) {
            return ['success' => false, 'error' => 'File size is not allowed'];
        }
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            return ['success' => false, 'error' => 'Invalid upload source'];
        }

        $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
        $allowedExt = $opt['allowed_ext'] ?? [];
        if (!is_array($allowedExt) || $ext === '' || !in_array($ext, $allowedExt, true)) {
            return ['success' => false, 'error' => 'File type is not allowed'];
        }

        $mime = 'application/octet-stream';
        if (function_exists('finfo_open')) {
            $fi = function_exists('gdy_finfo_open') ? gdy_finfo_open(FILEINFO_MIME_TYPE) : finfo_open(FILEINFO_MIME_TYPE);
            if ($fi) {
                $m = finfo_file($fi, $tmp);
                if (is_string($m) && $m !== '') $mime = $m;
                if (function_exists('gdy_suppress_errors')) { gdy_suppress_errors(static function () use ($fi) { finfo_close($fi); return true; }, true); } else { finfo_close($fi); }
            }
        }

        $allowedMime = $opt['allowed_mime'] ?? [];
        if (is_array($allowedMime) && isset($allowedMime[$ext]) && is_array($allowedMime[$ext])) {
            if (!in_array($mime, $allowedMime[$ext], true)) {
                return ['success' => false, 'error' => 'MIME type is not allowed'];
            }
        }

        // Normalise destination directory (avoid literal backslash in source by using chr(92)).
        $destAbsNorm = str_replace(chr(92), '/', $destAbs);
        $destAbsNorm = rtrim($destAbsNorm, '/');
        if ($destAbsNorm === '') {
            return ['success' => false, 'error' => 'Invalid destination'];
        }

        if (!is_dir($destAbsNorm)) {
            if (function_exists('gdy_mkdir')) {
                gdy_mkdir($destAbsNorm, 0755, true);
            } else {
                mkdir($destAbsNorm, 0755, true);
            }
        }

        if (!is_dir($destAbsNorm) || !is_writable($destAbsNorm)) {
            return ['success' => false, 'error' => 'Upload directory not writable'];
        }

        $prefix = (string)($opt['prefix'] ?? 'up_');
        $rand = bin2hex(random_bytes(16));
        $name = $prefix . $rand . '.' . $ext;
        $absPath = $destAbsNorm . '/' . $name;

        $moved = function_exists('gdy_move_uploaded_file')
            ? gdy_move_uploaded_file($tmp, $absPath)
            : move_uploaded_file($tmp, $absPath);

        if (!$moved) {
            return ['success' => false, 'error' => 'Failed to move uploaded file'];
        }

        if (function_exists('gdy_chmod')) {
            gdy_chmod($absPath, 0644);
        } else {
            chmod($absPath, 0644);
        }

        $relUrl = rtrim($urlPrefix, '/') . '/' . $name;

        return [
            'success' => true,
            'rel_url' => $relUrl,
            'abs_path' => $absPath,
            'original_name' => $orig,
            'ext' => $ext,
            'mime' => $mime,
            'size' => $size,
        ];
    }
}
