<?php
declare(strict_types=1);

require_once __DIR__ . '/../_admin_guard.php';

/**
 * News helpers (tags + datetime)
 */

function gdy_dt_local_to_sql(?string $v): ?string {
    $v = trim((string)$v);
    if ($v === '') return null;
    $v = str_replace('T', ' ', $v);
    if (strlen($v) === 16) $v .= ':00';
    return $v;
}

function gdy_slugify(string $text): string {
    $text = trim($text);
    $text = preg_replace('~\s+~u', '-', $text);
    $text = preg_replace('~[^\p{L}\p{N}\-_]+~u', '', $text);
    $text = trim($text, '-');
    return mb_strtolower($text, 'UTF-8');
}

function gdy_parse_tags(string $tags): array {
    $parts = preg_split('~[،,]+~u', $tags);
    $out = [];
    foreach ($parts as $p) {
        $t = trim($p);
        if ($t === '') continue;
        $out[] = $t;
    }
    // unique
    $out = array_values(array_unique($out));
    // limit
    return array_slice($out, 0, 20);
}

function gdy_sync_news_tags(PDO $pdo, int $newsId, string $tagsInput): void {
    $tags = gdy_parse_tags($tagsInput);

    // clear existing
    $pdo->prepare("DELETE FROM news_tags WHERE news_id = ?")->execute([$newsId]);
    if (empty($tags)) return;

    // Some installs have unique constraints on name and/or slug. Handle duplicates safely.
    $sel  = $pdo->prepare("SELECT id FROM tags WHERE slug = ? OR name = ? LIMIT 1");
    $ins  = $pdo->prepare("INSERT INTO tags (name, slug, created_at) VALUES (?, ?, CURRENT_TIMESTAMP)");
    $link = $pdo->prepare("INSERT INTO news_tags (news_id, tag_id) VALUES (?, ?)");

    foreach ($tags as $name) {
        $slug = gdy_slugify($name);
        if ($slug === '') continue;

        $sel->execute([$slug, $name]);
        $id = (int)($sel->fetchColumn() ?: 0);

        if ($id <= 0) {
            try {
                $ins->execute([$name, $slug]);
            } catch (PDOException $e) {
                if (function_exists('gdy_db_is_duplicate_exception') && gdy_db_is_duplicate_exception($e, $pdo)) {
                    // Duplicate key: ignore, we'll re-select below.
                } else {
                    throw $e;
                }
            }

            $sel->execute([$slug, $name]);
            $id = (int)($sel->fetchColumn() ?: 0);
        }

        if ($id > 0) {
            $link->execute([$newsId, $id]);
        }
    }
}

function gdy_get_news_tags(PDO $pdo, int $newsId): string {
    $stmt = $pdo->prepare("SELECT t.name
        FROM news_tags nt
        JOIN tags t ON t.id = nt.tag_id
        WHERE nt.news_id = ?
        ORDER BY t.name ASC");
    $stmt->execute([$newsId]);
    $names = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $sep = (function_exists('gdy_current_lang') && gdy_current_lang() === 'ar') ? '، ' : ', ';
    return implode($sep, array_map('strval', $names ?: []));
}

// -----------------------------------------------------------------------------
// Attachments helpers (PDF/Word/Excel/...)
// -----------------------------------------------------------------------------

/**
 * Cache table columns to avoid repeated column-inspection queries.
 */
function gdy_db_columns(PDO $pdo, string $table): array {
    static $cache = [];
    $key = spl_object_id($pdo) . ':' . $table;
    if (isset($cache[$key])) return $cache[$key];

    // IMPORTANT:
    // The admin/news module expects an associative map of existing columns:
    //   [ 'column_name' => true, ... ]
    // Older helpers return a numeric list of column names; that breaks checks like:
    //   isset($cols['content'])
    // and leads to NULL inserts (title only, missing category/image, etc.).
    // We normalize to an associative map here.

    // Prefer shared helper (supports MySQL/PostgreSQL)
    if (function_exists('db_table_columns')) {
        $list = db_table_columns($pdo, $table);
        $out = [];
        foreach (($list ?: []) as $c) {
            $c = trim((string)$c);
            if ($c === '') continue;
            $out[$c] = true;
        }
        $cache[$key] = $out;
        return $cache[$key];
    }

    // Fallback (legacy MySQL)
    $st = gdy_db_stmt_columns($pdo, $table);
    $out = [];
    while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
        $c = trim((string)($row['Field'] ?? ''));
        if ($c === '') continue;
        $out[$c] = true;
    }
    $cache[$key] = $out;
    return $cache[$key];
}

if (!function_exists('gdy_db_column_exists')) {
function gdy_db_column_exists(PDO $pdo, string $table, string $column): bool {
    // Prefer shared helper (supports MySQL/PostgreSQL)
    if (function_exists('db_column_exists')) {
        return db_column_exists($pdo, $table, $column);
    }

    // Fallback (legacy MySQL)
    $st = $pdo->prepare("
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = DATABASE()
          AND table_name = :t
          AND column_name = :c
        LIMIT 1
    ");
    $st->execute([':t' => $table, ':c' => $column]);
    return (bool)$st->fetchColumn();
}

}

if (!function_exists('gdy_db_table_exists')) {
function gdy_db_table_exists(PDO $pdo, string $table): bool {
    try {
        $stmt = $pdo->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema = " . gdy_db_schema_expr($pdo) . " AND table_name = ? LIMIT 1");
        $stmt->execute([$table]);
        return (bool)$stmt->fetchColumn();
    } catch (Throwable $e) {
        return false;
    }
}

}


// -----------------------------------------------------------------------------
// Attachments helpers (safe upload + DB)
// -----------------------------------------------------------------------------

if (!function_exists('gdy_starts_with')) {
    function gdy_starts_with(string $haystack, string $needle): bool {
        return $needle === '' || substr($haystack, 0, strlen($needle)) === $needle;
    }
}

/**
 * Ensure news_attachments table exists.
 */
function gdy_ensure_news_attachments_table(PDO $pdo): void
{
    if (function_exists('gdy_db_table_exists') && gdy_db_table_exists($pdo, 'news_attachments')) {
        return;
    }

    $sql = "CREATE TABLE IF NOT EXISTS `news_attachments` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `news_id` INT UNSIGNED NOT NULL,
        `original_name` VARCHAR(255) NOT NULL,
        `file_path` VARCHAR(255) NOT NULL,
        `mime_type` VARCHAR(120) NULL,
        `file_size` INT UNSIGNED NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_news_id` (`news_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    try {
        $pdo->exec($sql);
    } catch (Throwable $e) {
        error_log('[News Helpers] failed creating news_attachments: ' . $e->getMessage());
    }
}

/**
 * Fetch attachments for a given news item.
 *
 * @return array<int, array{id:int,news_id:int,original_name:string,file_path:string,mime_type:?string,file_size:?int,created_at:string}>
 */
function gdy_get_news_attachments(PDO $pdo, int $newsId): array
{
    $newsId = (int)$newsId;
    if ($newsId <= 0) {
        return [];
    }

    gdy_ensure_news_attachments_table($pdo);

    try {
        $st = $pdo->prepare("SELECT id, news_id, original_name, file_path, mime_type, file_size, created_at\n                             FROM news_attachments\n                             WHERE news_id = ?\n                             ORDER BY id DESC");
        $st->execute([$newsId]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        return is_array($rows) ? $rows : [];
    } catch (Throwable $e) {
        error_log('[News Helpers] get attachments failed: ' . $e->getMessage());
        return [];
    }
}

/**
 * Normalize the $_FILES multi-upload structure.
 */
function gdy_normalize_files_array(array $files): array
{
    $out = [];
    if (!isset($files['name'])) {
        return $out;
    }

    if (is_array($files['name'])) {
        $count = count($files['name']);
        for ($i = 0; $i < $count; $i++) {
            $out[] = [
                'name'     => (string)($files['name'][$i] ?? ''),
                'type'     => (string)($files['type'][$i] ?? ''),
                'tmp_name' => (string)($files['tmp_name'][$i] ?? ''),
                'error'    => (int)($files['error'][$i] ?? UPLOAD_ERR_NO_FILE),
                'size'     => (int)($files['size'][$i] ?? 0),
            ];
        }
    } else {
        $out[] = [
            'name'     => (string)($files['name'] ?? ''),
            'type'     => (string)($files['type'] ?? ''),
            'tmp_name' => (string)($files['tmp_name'] ?? ''),
            'error'    => (int)($files['error'] ?? UPLOAD_ERR_NO_FILE),
            'size'     => (int)($files['size'] ?? 0),
        ];
    }

    return $out;
}

/**
 * Icon class for attachment type (FontAwesome).
 */
function gdy_attachment_icon_class(string $filenameOrExt): string
{
    $ext = strtolower(pathinfo($filenameOrExt, PATHINFO_EXTENSION));
    if ($ext === '') {
        $ext = strtolower($filenameOrExt);
    }

    switch ($ext) {
        case 'pdf':
            return 'fa-regular fa-file-pdf';
        case 'doc':
        case 'docx':
            return 'fa-regular fa-file-word';
        case 'xls':
        case 'xlsx':
            return 'fa-regular fa-file-excel';
        case 'ppt':
        case 'pptx':
            return 'fa-regular fa-file-powerpoint';
        case 'zip':
        case 'rar':
        case '7z':
            return 'fa-regular fa-file-zipper';
        case 'txt':
        case 'rtf':
            return 'fa-regular fa-file-lines';
        case 'mp3':
        case 'wav':
        case 'ogg':
        case 'm4a':
            return 'fa-regular fa-file-audio';
        case 'mp4':
        case 'webm':
            return 'fa-regular fa-file-video';
        case 'png':
        case 'jpg':
        case 'jpeg':
        case 'gif':
        case 'webp':
            return 'fa-regular fa-file-image';
        default:
            return 'fa-regular fa-file';
    }
}

/**
 * Best-effort protection inside upload directories.
 */
function gdy_protect_upload_dir(string $absDir): void
{
    try {
        if (!is_dir($absDir)) {
            if (function_exists('gdy_mkdir')) {
                gdy_mkdir($absDir, 0775, true);
            } else {
                @mkdir($absDir, 0775, true);
            }
        }

        $ht = rtrim($absDir, "/\\") . '/.htaccess';
        if (!is_file($ht)) {
            $rules = "Options -Indexes\n" .
                     "<FilesMatch \\\"\\.(php|phtml|php\d|phar)\\$\\\">\n" .
                     "  Deny from all\n" .
                     "</FilesMatch>\n";
            @file_put_contents($ht, $rules);
        }
    } catch (Throwable $e) {
        // ignore
    }
}

/**
 * Save attachments for a news item (centralised safe upload).
 *
 * @param array $files The $_FILES['attachments'] array.
 * @param array $errors Collects errors under key 'attachments'.
 */
function gdy_save_news_attachments(PDO $pdo, int $newsId, array $files, array &$errors): void
{
    if ($newsId <= 0) {
        return;
    }

    gdy_ensure_news_attachments_table($pdo);

    if (!class_exists('Godyar\\SafeUploader')) {
        $errors['attachments'] = __('t_556247d5d4', 'مكوّن الرفع الآمن غير متاح حالياً.');
        return;
    }

    $items = gdy_normalize_files_array($files);
    if (empty($items)) {
        return;
    }

    // Limits
    $maxSize = 50 * 1024 * 1024; // 50MB per file (supports mp4)

    // Allow-list types (images + documents + audio/video)
    $allowedMime = [
        'pdf'  => ['application/pdf'],
        'doc'  => ['application/msword', 'application/octet-stream'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip', 'application/octet-stream'],
        'xls'  => ['application/vnd.ms-excel', 'application/octet-stream'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip', 'application/octet-stream'],
        'ppt'  => ['application/vnd.ms-powerpoint', 'application/octet-stream'],
        'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/zip', 'application/octet-stream'],
        'zip'  => ['application/zip', 'application/x-zip-compressed', 'application/octet-stream'],
        'rar'  => ['application/x-rar', 'application/vnd.rar', 'application/octet-stream'],
        '7z'   => ['application/x-7z-compressed', 'application/octet-stream'],
        'txt'  => ['text/plain', 'application/octet-stream'],
        'rtf'  => ['application/rtf', 'text/rtf', 'application/octet-stream'],
        'png'  => ['image/png'],
        'jpg'  => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'gif'  => ['image/gif'],
        'webp' => ['image/webp'],
        'mp3'  => ['audio/mpeg', 'audio/mp3', 'application/octet-stream'],
        'wav'  => ['audio/wav', 'audio/x-wav', 'application/octet-stream'],
        'ogg'  => ['audio/ogg', 'application/ogg', 'application/octet-stream'],
        'm4a'  => ['audio/mp4', 'application/octet-stream'],
        'mp4'  => ['video/mp4', 'application/octet-stream'],
        'webm' => ['video/webm', 'application/octet-stream'],
    ];
    $allowedExt = array_keys($allowedMime);

    // Destination
    $root = defined('ROOT_PATH') ? (string)ROOT_PATH : (string)dirname(__DIR__, 2);
    $destAbs = rtrim($root, "/\\") . '/uploads/news/attachments';
    gdy_protect_upload_dir($destAbs);

    // URL prefix (supports subdirectory installs)
    $baseUrl = function_exists('base_url') ? rtrim((string)base_url(), '/') : '';
    $basePath = '';
    if ($baseUrl !== '') {
        $bp = parse_url($baseUrl, PHP_URL_PATH);
        if (is_string($bp) && $bp !== '' && $bp !== '/') {
            $basePath = rtrim($bp, '/');
        }
    }
    $urlPrefix = ($basePath !== '' ? $basePath : '') . '/uploads/news/attachments';

    $ins = $pdo->prepare("INSERT INTO news_attachments (news_id, original_name, file_path, mime_type, file_size, created_at)
                          VALUES (:news_id, :original_name, :file_path, :mime_type, :file_size, NOW())");

    foreach ($items as $f) {
        $err = (int)($f['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($err === UPLOAD_ERR_NO_FILE) {
            continue;
        }

        $res = \Godyar\SafeUploader::upload($f, [
            'max_bytes'    => $maxSize,
            'allowed_ext'  => $allowedExt,
            'allowed_mime' => $allowedMime,
            'dest_abs_dir' => $destAbs,
            'url_prefix'   => $urlPrefix,
            'prefix'       => 'att_',
        ]);

        if (empty($res['success'])) {
            $errors['attachments'] = (string)($res['error'] ?? __('t_556244d2a1', 'حدث خطأ أثناء رفع أحد المرفقات.'));
            continue;
        }

        // Store DB relative path (without basePath)
        $relUrl = (string)($res['rel_url'] ?? '');
        $path = ltrim($relUrl, '/');
        if ($basePath !== '') {
            $bpTrim = ltrim($basePath, '/');
            if ($bpTrim !== '' && gdy_starts_with($path, $bpTrim . '/')) {
                $path = substr($path, strlen($bpTrim) + 1);
            }
        }

        if ($path === '') {
            $errors['attachments'] = __('t_7148406c0e', 'تعذر حفظ أحد المرفقات على الخادم.');
            continue;
        }

        try {
            $ins->execute([
                ':news_id'       => $newsId,
                ':original_name' => (string)($res['original_name'] ?? ($f['name'] ?? '')),
                ':file_path'     => $path,
                ':mime_type'     => (string)($res['mime'] ?? '') ?: null,
                ':file_size'     => (int)($res['size'] ?? ($f['size'] ?? 0)) ?: null,
            ]);
        } catch (Throwable $e) {
            error_log('[News Helpers] insert attachment failed: ' . $e->getMessage());
        }
    }
}

/**
 * Delete an attachment safely (DB + file within uploads/news/attachments).
 */
function gdy_delete_news_attachment(PDO $pdo, int $newsId, int $attachmentId): bool
{
    if ($newsId <= 0 || $attachmentId <= 0) {
        return false;
    }

    if (!function_exists('gdy_db_table_exists') || !gdy_db_table_exists($pdo, 'news_attachments')) {
        return false;
    }

    try {
        $stmt = $pdo->prepare("SELECT file_path FROM news_attachments WHERE id = ? AND news_id = ? LIMIT 1");
        $stmt->execute([$attachmentId, $newsId]);
        $path = (string)($stmt->fetchColumn() ?: '');

        if ($path !== '') {
            $root = defined('ROOT_PATH') ? (string)ROOT_PATH : (string)dirname(__DIR__, 2);
            $uploadsRoot = realpath(rtrim($root, "/\\") . '/uploads/news/attachments');
            $absCandidate = realpath(rtrim($root, "/\\") . '/' . ltrim($path, '/'));

            if ($uploadsRoot && $absCandidate && gdy_starts_with($absCandidate, $uploadsRoot)) {
                if (function_exists('gdy_unlink')) {
                    gdy_unlink($absCandidate);
                } else {
                    @unlink($absCandidate);
                }
            }
        }

        $del = $pdo->prepare("DELETE FROM news_attachments WHERE id = ? AND news_id = ? LIMIT 1");
        $del->execute([$attachmentId, $newsId]);
        return true;
    } catch (Throwable $e) {
        error_log('[News Helpers] delete attachment failed: ' . $e->getMessage());
        return false;
    }
}

// -----------------------------------------------------------------------------
// Editorial workflow helpers (notes + revisions)
// -----------------------------------------------------------------------------

function gdy_ensure_news_notes_table(PDO $pdo): void
{
    if (gdy_db_table_exists($pdo, 'news_notes')) {
        return;
    }

    $sql = "CREATE TABLE IF NOT EXISTS `news_notes` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `news_id` INT UNSIGNED NOT NULL,
        `user_id` INT UNSIGNED NULL,
        `note` TEXT NOT NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_news_id` (`news_id`),
        KEY `idx_created_at` (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    try {
        $pdo->exec($sql);
    } catch (Throwable $e) {
        error_log('[News Helpers] failed creating news_notes: ' . $e->getMessage());
    }
}

function gdy_add_news_note(PDO $pdo, int $newsId, ?int $userId, string $note): bool
{
    $note = trim($note);
    if ($newsId <= 0 || $note === '') return false;

    gdy_ensure_news_notes_table($pdo);

    try {
        $stmt = $pdo->prepare("INSERT INTO news_notes (news_id, user_id, note, created_at)
                              VALUES (:news_id, :user_id, :note, NOW())");
        $stmt->bindValue(':news_id', $newsId, PDO::PARAM_INT);
        if ($userId && $userId > 0) $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        else $stmt->bindValue(':user_id', null, PDO::PARAM_NULL);
        $stmt->bindValue(':note', $note, PDO::PARAM_STR);
        return (bool)$stmt->execute();
    } catch (Throwable $e) {
        error_log('[News Helpers] add note failed: ' . $e->getMessage());
        return false;
    }
}

function gdy_get_news_notes(PDO $pdo, int $newsId, int $limit = 50): array
{
    if ($newsId <= 0) return [];
    if (!gdy_db_table_exists($pdo, 'news_notes')) return [];

    $limit = max(1, min(200, $limit));

    try {
        $stmt = $pdo->prepare("SELECT n.id, n.note, n.created_at, n.user_id,
                                      COALESCE(u.name, u.username, '') AS user_name
                               FROM news_notes n
                               LEFT JOIN users u ON u.id = n.user_id
                               WHERE n.news_id = :nid
                               ORDER BY n.id DESC
                               LIMIT {$limit}");
        $stmt->bindValue(':nid', $newsId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        error_log('[News Helpers] get notes failed: ' . $e->getMessage());
        return [];
    }
}

function gdy_ensure_news_revisions_table(PDO $pdo): void
{
    if (gdy_db_table_exists($pdo, 'news_revisions')) {
        return;
    }

    $sql = "CREATE TABLE IF NOT EXISTS `news_revisions` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `news_id` INT UNSIGNED NOT NULL,
        `user_id` INT UNSIGNED NULL,
        `action` VARCHAR(30) NOT NULL DEFAULT 'update',
        `payload` LONGTEXT NOT NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_news_id` (`news_id`),
        KEY `idx_created_at` (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    try {
        $pdo->exec($sql);
    } catch (Throwable $e) {
        error_log('[News Helpers] failed creating news_revisions: ' . $e->getMessage());
    }
}

function gdy_capture_news_revision(PDO $pdo, int $newsId, ?int $userId, string $action, array $newsRow, string $tags = ''): bool
{
    if ($newsId <= 0) return false;

    gdy_ensure_news_revisions_table($pdo);

    // Keep only relevant keys
    $payload = [
        'news' => $newsRow,
        'tags' => $tags,
    ];

    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    if (!is_string($json) || $json === '') return false;

    try {
        $stmt = $pdo->prepare("INSERT INTO news_revisions (news_id, user_id, action, payload, created_at)
                              VALUES (:news_id, :user_id, :action, :payload, NOW())");
        $stmt->bindValue(':news_id', $newsId, PDO::PARAM_INT);
        if ($userId && $userId > 0) $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        else $stmt->bindValue(':user_id', null, PDO::PARAM_NULL);
        $stmt->bindValue(':action', $action !== '' ? $action : 'update', PDO::PARAM_STR);
        $stmt->bindValue(':payload', $json, PDO::PARAM_STR);
        return (bool)$stmt->execute();
    } catch (Throwable $e) {
        error_log('[News Helpers] capture revision failed: ' . $e->getMessage());
        return false;
    }
}

function gdy_get_news_revisions(PDO $pdo, int $newsId, int $limit = 30): array
{
    if ($newsId <= 0) return [];
    if (!gdy_db_table_exists($pdo, 'news_revisions')) return [];

    $limit = max(1, min(200, $limit));

    try {
        $stmt = $pdo->prepare("SELECT r.id, r.action, r.created_at, r.user_id,
                                      COALESCE(u.name, u.username, '') AS user_name
                               FROM news_revisions r
                               LEFT JOIN users u ON u.id = r.user_id
                               WHERE r.news_id = :nid
                               ORDER BY r.id DESC
                               LIMIT {$limit}");
        $stmt->bindValue(':nid', $newsId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        error_log('[News Helpers] get revisions failed: ' . $e->getMessage());
        return [];
    }
}

function gdy_get_revision_payload(PDO $pdo, int $revisionId): ?array
{
    if ($revisionId <= 0) return null;
    if (!gdy_db_table_exists($pdo, 'news_revisions')) return null;

    try {
        $stmt = $pdo->prepare("SELECT payload FROM news_revisions WHERE id = :id LIMIT 1");
        $stmt->bindValue(':id', $revisionId, PDO::PARAM_INT);
        $stmt->execute();
        $payload = $stmt->fetchColumn();
        if (!is_string($payload) || $payload === '') return null;

        $data = json_decode($payload, true);
        return is_array($data) ? $data : null;
    } catch (Throwable $e) {
        error_log('[News Helpers] get revision payload failed: ' . $e->getMessage());
        return null;
    }
}

function gdy_restore_news_from_revision(PDO $pdo, int $newsId, int $revisionId, ?int $actorUserId = null): bool
{
    if ($newsId <= 0 || $revisionId <= 0) return false;

    $payload = gdy_get_revision_payload($pdo, $revisionId);
    if (!$payload || empty($payload['news']) || !is_array($payload['news'])) {
        return false;
    }

    $news = $payload['news'];
    $tags = isset($payload['tags']) ? (string)$payload['tags'] : '';

    $cols = gdy_db_columns($pdo, 'news');

    $sets = [];
    $params = [':id' => $newsId];

    $map = [
        'title' => 'title',
        'slug' => 'slug',
        'excerpt' => 'excerpt',
        'summary' => 'summary',
        'content' => 'content',
        'body' => 'body',
        'category_id' => 'category_id',
        'author_id' => 'author_id',
        'opinion_author_id' => 'opinion_author_id',
        'status' => 'status',
        'featured' => 'featured',
        'is_breaking' => 'is_breaking',
        'published_at' => 'published_at',
        'publish_at' => 'publish_at',
        'unpublish_at' => 'unpublish_at',
        'seo_title' => 'seo_title',
        'seo_description' => 'seo_description',
        'seo_keywords' => 'seo_keywords',
        'image' => 'image',
    ];

    foreach ($map as $k => $col) {
        if (!isset($cols[$col])) continue;
        if (!array_key_exists($k, $news)) continue;

        $sets[] = "`{$col}` = :{$col}";
        $params[":{$col}"] = $news[$k];
    }

    if (empty($sets)) return false;

    try {
        // capture current state as a revision before restoring
        try {
            $stmt0 = $pdo->prepare('SELECT * FROM news WHERE id = :id LIMIT 1');
            $stmt0->execute([':id' => $newsId]);
            $currentRow = $stmt0->fetch(PDO::FETCH_ASSOC) ?: [];
            $currentTags = '';
            try { $currentTags = gdy_get_news_tags($pdo, $newsId); } catch (Throwable) {}
            gdy_capture_news_revision($pdo, $newsId, $actorUserId, 'restore_backup', $currentRow, $currentTags);
        } catch (Throwable) {}

        $sql = 'UPDATE news SET ' . implode(', ', $sets) . ' WHERE id = :id LIMIT 1';
        $stmt = $pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->execute();

        // restore tags
        try {
            gdy_sync_news_tags($pdo, $newsId, $tags);
        } catch (Throwable) {}

        // capture restoration action
        try {
            $stmt1 = $pdo->prepare('SELECT * FROM news WHERE id = :id LIMIT 1');
            $stmt1->execute([':id' => $newsId]);
            $restoredRow = $stmt1->fetch(PDO::FETCH_ASSOC) ?: [];
            $restoredTags = '';
            try { $restoredTags = gdy_get_news_tags($pdo, $newsId); } catch (Throwable) {}
            gdy_capture_news_revision($pdo, $newsId, $actorUserId, 'restore', $restoredRow, $restoredTags);
        } catch (Throwable) {}

        return true;
    } catch (Throwable $e) {
        error_log('[News Helpers] restore revision failed: ' . $e->getMessage());
        return false;
    }
}

/**
 * Smart Suggestions: related news based on shared tags.
 * Returns recent news items that share tags with current one.
 */
function gdy_get_related_news(PDO $pdo, int $newsId, int $limit = 6): array {
    $limit = max(1, min(20, $limit));
    try {
        $chk = gdy_db_stmt_table_exists($pdo, 'news_tags');
        $has = $chk && $chk->fetchColumn();
        if (!$has) return [];
    } catch (Throwable) { return []; }

    try {
        $sql = "
            SELECT n.id, n.title, n.status, n.created_at, COUNT(*) AS score
            FROM news_tags nt
            INNER JOIN news_tags nt2 ON nt2.tag_id = nt.tag_id AND nt2.news_id <> nt.news_id
            INNER JOIN news n ON n.id = nt2.news_id
            WHERE nt.news_id = :id
            GROUP BY n.id
            ORDER BY score DESC, n.id DESC
            LIMIT $limit
        ";
        $st = $pdo->prepare($sql);
        $st->execute(['id' => $newsId]);
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable) {
        return [];
    }
}
