<?php
declare(strict_types=1);

require_once __DIR__ . '/../_admin_guard.php';
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/auth.php';

use Godyar\Auth;
use Godyar\SafeUploader;

if (!Auth::isLoggedIn()) {
    header('Location: ../login.php');
    exit;
}

$currentPage = 'media';
$pageTitle   = __('t_ce4c722d7f', 'رفع ملف وسائط');

$pdo = gdy_pdo_safe();
$errors = [];
$success = null;
$uploaded = null;

// Upload destination
$root = defined('ROOT_PATH') ? ROOT_PATH : dirname(__DIR__, 2);
$destAbs = rtrim((string)$root, "/\\") . '/assets/uploads/media';

// Protect upload directory (no listing, no PHP execution)
gdy_protect_upload_dir_media($destAbs);

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

// Allowed types
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

// Best-effort protection inside upload directories.
function gdy_protect_upload_dir_media(string $absDir): void
{
    try {
        if (!is_dir($absDir)) {
            if (function_exists('gdy_mkdir')) {
                gdy_mkdir($absDir, 0775, true);
            } else {
                @mkdir($absDir, 0775, true);
            }
        }
        $ht = rtrim($absDir, "/" . chr(92)) . '/.htaccess';
        if (!is_file($ht)) {
            $rules = "Options -Indexes
" .
                     "<FilesMatch \"\.(php|phtml|php\d|phar)\$\">
" .
                     "  Deny from all
" .
                     "</FilesMatch>
";
            @file_put_contents($ht, $rules);
        }
    } catch (Throwable $e) {
        // ignore
    }
}


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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF validation
    $csrf = (string)($_POST['csrf_token'] ?? '');
    if (function_exists('verify_csrf_token') && !verify_csrf_token($csrf)) {
        $errors[] = __('t_0d5b2d99a5', 'انتهت الجلسة أو رمز الحماية غير صحيح. أعد المحاولة.');
    }

    $file = $_FILES['file'] ?? null;
    if (!$errors && (!is_array($file) || (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE))) {
        $errors[] = __('t_b9f81100e5', 'يرجى اختيار ملف لرفعه.');
    }

    if (!$errors && is_array($file)) {
        try {
            $res = SafeUploader::upload($file, [
                'max_bytes'    => 100 * 1024 * 1024,
                'allowed_ext'  => $allowedExt,
                'allowed_mime' => $allowedMime,
                'dest_abs_dir' => $destAbs,
                'url_prefix'   => $urlPrefix,
                'prefix'       => 'media_',
            ]);

            if (!empty($res['success'])) {
                $rel = (string)($res['rel_url'] ?? '');
                $mime = (string)($res['mime'] ?? 'application/octet-stream');
                $size = (int)($res['size'] ?? 0);
                $orig = (string)($res['original_name'] ?? '');

                $url = $rel;
                if ($origin !== '' && $rel !== '') {
                    $url = $origin . $rel;
                } elseif ($baseUrl !== '' && $rel !== '') {
                    // best-effort fallback
                    $url = $baseUrl . $rel;
                }

                // Insert into media table if exists
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
                    } catch (Throwable $e) {
                        error_log('[admin/media/upload] db insert failed: ' . $e->getMessage());
                    }
                }

                $success = __('t_2b1b76c0d1', 'تم رفع الملف بنجاح.');
                $uploaded = [
                    'name' => $orig,
                    'url'  => $url,
                    'mime' => $mime,
                    'size' => $size,
                ];
            } else {
                $errors[] = (string)($res['error'] ?? __('t_14a4dd5d81', 'حدث خطأ أثناء رفع الملف. حاول مرة أخرى.'));
            }
        } catch (Throwable $e) {
            $errors[] = __('t_14a4dd5d81', 'حدث خطأ أثناء رفع الملف. حاول مرة أخرى.');
            error_log('[admin/media/upload] ' . $e->getMessage());
        }
    }
}

// View
require_once __DIR__ . '/../partials/header.php';
?>

<div class="container-fluid py-3">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h1 class="h4 mb-0"><?php echo htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?></h1>
    <a class="btn btn-outline-secondary" href="index.php"><?php echo htmlspecialchars(__('t_7b8f1de1c0', 'العودة'), ENT_QUOTES, 'UTF-8'); ?></a>
  </div>

  <?php if ($success): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?></div>
  <?php endif; ?>

  <?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
      <ul class="mb-0">
        <?php foreach ($errors as $e): ?>
          <li><?php echo htmlspecialchars((string)$e, ENT_QUOTES, 'UTF-8'); ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <?php if (is_array($uploaded) && !empty($uploaded['url'])): ?>
    <div class="card mb-3">
      <div class="card-body">
        <div><strong><?php echo htmlspecialchars(__('t_8a0a8b9a3e', 'الملف'), ENT_QUOTES, 'UTF-8'); ?>:</strong> <?php echo htmlspecialchars((string)$uploaded['name'], ENT_QUOTES, 'UTF-8'); ?></div>
        <div><strong>URL:</strong> <a href="<?php echo htmlspecialchars((string)$uploaded['url'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer"><?php echo htmlspecialchars((string)$uploaded['url'], ENT_QUOTES, 'UTF-8'); ?></a></div>
        <div><strong>MIME:</strong> <?php echo htmlspecialchars((string)$uploaded['mime'], ENT_QUOTES, 'UTF-8'); ?></div>
        <div><strong><?php echo htmlspecialchars(__('t_9d0db0c2f1', 'الحجم'), ENT_QUOTES, 'UTF-8'); ?>:</strong> <?php echo (int)$uploaded['size']; ?> bytes</div>
      </div>
    </div>
  <?php endif; ?>

  <div class="card">
    <div class="card-body">
      <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(function_exists('csrf_token') ? csrf_token() : '', ENT_QUOTES, 'UTF-8'); ?>">

        <div class="mb-3">
          <label class="form-label"><?php echo htmlspecialchars(__('t_3e61d45733', 'اختر ملفًا'), ENT_QUOTES, 'UTF-8'); ?></label>
          <input class="form-control" type="file" name="file" required>
          <div class="form-text"><?php echo htmlspecialchars(__('t_4a76188b70', 'الحد الأقصى: 100MB. الامتدادات المسموحة: صور، PDF، Office، أرشيفات، MP3/MP4.'), ENT_QUOTES, 'UTF-8'); ?></div>
        </div>

        <button class="btn btn-primary" type="submit"><?php echo htmlspecialchars(__('t_5e3e0d8a11', 'رفع'), ENT_QUOTES, 'UTF-8'); ?></button>
      </form>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>
