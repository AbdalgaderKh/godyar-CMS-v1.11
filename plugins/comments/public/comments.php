<?php
// plugins/comments/public/comments.php
declare(strict_types=1);

require_once __DIR__ . '/../../../includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

if (session_status() !== PHP_SESSION_ACTIVE) {
    gdy_session_start();
}

function gdy_client_ip(): string {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    if (!is_string($ip) || $ip === '') return '0.0.0.0';
    return substr($ip, 0, 64);
}

function gdy_rate_limit(string $key, string $ip, int $max, int $windowSeconds): bool {
    $dir = rtrim((string)sys_get_temp_dir(), '/');
    $file = $dir . '/gdy_rl_' . hash('sha256', $key . '|' . $ip) . '.json';
    $now = time();

    $times = [];
    if (is_file($file)) {
        $raw = @file_get_contents($file);
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $times = $decoded;
            }
        }
    }

    // prune
    $min = $now - $windowSeconds;
    $times = array_values(array_filter($times, static fn($t) => is_int($t) && $t >= $min));

    if (count($times) >= $max) {
        @file_put_contents($file, json_encode($times), LOCK_EX);
        return false;
    }

    $times[] = $now;
    @file_put_contents($file, json_encode($times), LOCK_EX);
    return true;
}

function ensure_comments_tables(PDO $pdo): void {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS comments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            news_id INT NOT NULL,
            user_id INT NULL,
            author_name VARCHAR(150) NULL,
            author_email VARCHAR(190) NULL,
            body TEXT NOT NULL,
            status ENUM('pending','approved','spam') NOT NULL DEFAULT 'pending',
            ip VARCHAR(64) NULL,
            user_agent VARCHAR(255) NULL,
            edit_token_hash VARCHAR(255) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NULL,
            updated_ip VARCHAR(64) NULL,
            INDEX idx_news_status (news_id, status),
            INDEX idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}

function ensure_comments_columns(PDO $pdo): void {
    // في حال كانت الجداول قديمة (قبل schema v2)
    $alters = [
        "ALTER TABLE comments ADD COLUMN edit_token_hash VARCHAR(255) NULL AFTER user_agent",
        "ALTER TABLE comments ADD COLUMN updated_at DATETIME NULL AFTER created_at",
        "ALTER TABLE comments ADD COLUMN updated_ip VARCHAR(64) NULL AFTER updated_at",
    ];
    foreach ($alters as $sql) {
        try { $pdo->exec($sql); } catch (Throwable $e) { /* ignore */ }
    }
}

function json_error(string $code, int $http = 400): void {
    http_response_code($http);
    echo json_encode(['ok' => false, 'error' => $code], JSON_UNESCAPED_UNICODE);
    exit;
}

$pdo = function_exists('gdy_pdo_safe') ? gdy_pdo_safe() : null;
if (($pdo instanceof PDO) === false) {
    json_error('db_unavailable', 500);
}
ensure_comments_tables($pdo);
ensure_comments_columns($pdo);

$action = $_REQUEST['action'] ?? 'list';
$action = is_string($action) ? $action : 'list';

$newsId = (int)($_REQUEST['news_id'] ?? 0);
if ($newsId <= 0) {
    json_error('invalid_news_id', 422);
}

$ip = gdy_client_ip();
$ua = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);

// --- LIST ---
if ($action === 'list') {
    if (!gdy_rate_limit('comments_list', $ip, 60, 60)) {
        json_error('rate_limited', 429);
    }

    $limit = (int)($_GET['limit'] ?? 20);
    if ($limit < 1) $limit = 1;
    if ($limit > 50) $limit = 50;

    try {
        $st = $pdo->prepare("SELECT id, author_name, body, created_at
                             FROM comments
                             WHERE news_id=? AND status='approved'
                             ORDER BY id DESC
                             LIMIT {$limit}");
        $st->execute([$newsId]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        $items = [];
        foreach ($rows as $r) {
            $items[] = [
                'id' => (int)($r['id'] ?? 0),
                'author_name' => (string)($r['author_name'] ?? ''),
                'body' => (string)($r['body'] ?? ''),
                'created_at' => (string)($r['created_at'] ?? ''),
            ];
        }
        echo json_encode(['ok' => true, 'items' => $items], JSON_UNESCAPED_UNICODE);
        exit;
    } catch (Throwable $e) {
        error_log('[CommentsEndpoint] list: ' . $e->getMessage());
        json_error('query_failed', 500);
    }
}

// --- CREATE ---
if ($action === 'create') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_error('method_not_allowed', 405);
    }

    if (!gdy_rate_limit('comments_create', $ip, 5, 60)) {
        json_error('rate_limited', 429);
    }

    $token = (string)($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!function_exists('verify_csrf_token') || !verify_csrf_token($token)) {
        json_error('csrf_failed', 403);
    }

    $name  = trim((string)($_POST['name'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $body  = trim((string)($_POST['body'] ?? ''));

    // Text-only + basic sanitization
    $name  = substr((string)preg_replace('~[\x00-\x1F\x7F]+~u', ' ', $name), 0, 150);
    $email = substr((string)preg_replace('~[\x00-\x1F\x7F]+~u', ' ', $email), 0, 190);

    $body = str_replace(["\r\n", "\r"], "\n", $body);
    $body = strip_tags($body);
    $body = preg_replace('~[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+~u', '', (string)$body);
    $body = trim((string)$body);

    if ($body === '') {
        json_error('empty_body', 422);
    }
    if (mb_strlen($body, 'UTF-8') > 2000) {
        json_error('body_too_long', 422);
    }

    try {
        $editToken = bin2hex(random_bytes(16));
        $hash = password_hash($editToken, PASSWORD_DEFAULT);

        $st = $pdo->prepare("INSERT INTO comments (news_id, author_name, author_email, body, status, ip, user_agent, edit_token_hash)
                             VALUES (?, ?, ?, ?, 'pending', ?, ?, ?)");
        $st->execute([
            $newsId,
            $name !== '' ? $name : null,
            $email !== '' ? $email : null,
            $body,
            $ip,
            $ua,
            $hash,
        ]);

        $id = (int)$pdo->lastInsertId();
        echo json_encode(['ok' => true, 'status' => 'pending', 'id' => $id, 'edit_token' => $editToken], JSON_UNESCAPED_UNICODE);
        exit;
    } catch (Throwable $e) {
        error_log('[CommentsEndpoint] create: ' . $e->getMessage());
        json_error('insert_failed', 500);
    }
}

// --- UPDATE ---
if ($action === 'update') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_error('method_not_allowed', 405);
    }

    if (!gdy_rate_limit('comments_update', $ip, 10, 60)) {
        json_error('rate_limited', 429);
    }

    $token = (string)($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!function_exists('verify_csrf_token') || !verify_csrf_token($token)) {
        json_error('csrf_failed', 403);
    }

    $id = (int)($_POST['id'] ?? 0);
    $editToken = (string)($_POST['edit_token'] ?? '');
    $body = trim((string)($_POST['body'] ?? ''));

    if ($id <= 0 || $editToken === '' || strlen($editToken) > 128) {
        json_error('invalid_update_payload', 422);
    }

    $body = str_replace(["\r\n", "\r"], "\n", $body);
    $body = strip_tags($body);
    $body = preg_replace('~[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+~u', '', (string)$body);
    $body = trim((string)$body);

    if ($body === '') {
        json_error('empty_body', 422);
    }
    if (mb_strlen($body, 'UTF-8') > 2000) {
        json_error('body_too_long', 422);
    }

    try {
        $st = $pdo->prepare("SELECT edit_token_hash FROM comments WHERE id=? AND news_id=? LIMIT 1");
        $st->execute([$id, $newsId]);
        $hash = (string)$st->fetchColumn();

        if ($hash === '') {
            json_error('not_found', 404);
        }
        if (!password_verify($editToken, $hash)) {
            json_error('forbidden', 403);
        }

        // أعده إلى pending لضمان المراجعة بعد التعديل
        $up = $pdo->prepare("UPDATE comments SET body=?, status='pending', updated_at=NOW(), updated_ip=? WHERE id=? AND news_id=?");
        $up->execute([$body, $ip, $id, $newsId]);

        echo json_encode(['ok' => true, 'status' => 'pending'], JSON_UNESCAPED_UNICODE);
        exit;
    } catch (Throwable $e) {
        error_log('[CommentsEndpoint] update: ' . $e->getMessage());
        json_error('update_failed', 500);
    }
}

json_error('unknown_action', 400);
