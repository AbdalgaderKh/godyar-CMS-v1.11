<?php
// plugins/reader_questions/public/questions.php
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

function ensure_reader_questions_tables(PDO $pdo): void {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS reader_questions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            news_id INT NOT NULL,
            user_id INT NULL,
            author_name VARCHAR(150) NULL,
            author_email VARCHAR(190) NULL,
            question TEXT NOT NULL,
            answer TEXT NULL,
            status ENUM('pending','approved','answered','spam') NOT NULL DEFAULT 'pending',
            ip VARCHAR(64) NULL,
            user_agent VARCHAR(255) NULL,
            edit_token_hash VARCHAR(255) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NULL,
            updated_ip VARCHAR(64) NULL,
            answered_at DATETIME NULL,
            INDEX idx_news_status (news_id, status),
            INDEX idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}

function ensure_reader_questions_columns(PDO $pdo): void {
    $stmts = [
        "ALTER TABLE reader_questions ADD COLUMN edit_token_hash VARCHAR(255) NULL AFTER user_agent",
        "ALTER TABLE reader_questions ADD COLUMN updated_at DATETIME NULL AFTER created_at",
        "ALTER TABLE reader_questions ADD COLUMN updated_ip VARCHAR(64) NULL AFTER updated_at",
    ];
    foreach ($stmts as $sql) {
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
ensure_reader_questions_tables($pdo);
ensure_reader_questions_columns($pdo);

$action = $_REQUEST['action'] ?? 'list';
$action = is_string($action) ? $action : 'list';

$newsId = (int)($_REQUEST['news_id'] ?? 0);
if ($newsId <= 0) {
    json_error('invalid_news_id', 422);
}

$ip = gdy_client_ip();
$ua = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);

if ($action === 'list') {
    if (!gdy_rate_limit('rq_list', $ip, 60, 60)) {
        json_error('rate_limited', 429);
    }

    $limit = (int)($_GET['limit'] ?? 20);
    if ($limit < 1) $limit = 1;
    if ($limit > 50) $limit = 50;

    try {
        $st = $pdo->prepare("SELECT id, author_name, question, answer, created_at
                             FROM reader_questions
                             WHERE news_id=? AND status IN ('approved','answered')
                             ORDER BY id DESC
                             LIMIT {$limit}");
        $st->execute([$newsId]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        $items = [];
        foreach ($rows as $r) {
            $items[] = [
                'id' => (int)($r['id'] ?? 0),
                'author_name' => (string)($r['author_name'] ?? ''),
                'question' => (string)($r['question'] ?? ''),
                'answer' => (string)($r['answer'] ?? ''),
                'created_at' => (string)($r['created_at'] ?? ''),
            ];
        }
        echo json_encode(['ok' => true, 'items' => $items], JSON_UNESCAPED_UNICODE);
        exit;
    } catch (Throwable $e) {
        error_log('[ReaderQuestionsEndpoint] list: ' . $e->getMessage());
        json_error('query_failed', 500);
    }
}

if ($action === 'create') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_error('method_not_allowed', 405);
    }

    if (!gdy_rate_limit('rq_create', $ip, 5, 60)) {
        json_error('rate_limited', 429);
    }

    $token = (string)($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!function_exists('verify_csrf_token') || !verify_csrf_token($token)) {
        json_error('csrf_failed', 403);
    }

    $name  = trim((string)($_POST['name'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $q     = trim((string)($_POST['question'] ?? ''));

    $name  = substr(preg_replace('~[\x00-\x1F\x7F]+~u', ' ', $name), 0, 150);
    $email = substr(preg_replace('~[\x00-\x1F\x7F]+~u', ' ', $email), 0, 190);

    $q = str_replace(["\r\n", "\r"], "\n", $q);
    $q = strip_tags($q);
    $q = preg_replace('~[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+~u', '', (string)$q);
    $q = trim((string)$q);

    if ($q === '') {
        json_error('empty_question', 422);
    }
    if (mb_strlen($q, 'UTF-8') > 2000) {
        json_error('question_too_long', 422);
    }

    $editToken = bin2hex(random_bytes(16));
    $editHash  = password_hash($editToken, PASSWORD_DEFAULT);

    try {
        $st = $pdo->prepare("INSERT INTO reader_questions (news_id, author_name, author_email, question, status, ip, user_agent, edit_token_hash)
                             VALUES (?, ?, ?, ?, 'pending', ?, ?, ?)");
        $st->execute([
            $newsId,
            $name !== '' ? $name : null,
            $email !== '' ? $email : null,
            $q,
            $ip,
            $ua,
            $editHash,
        ]);

        $id = (int)$pdo->lastInsertId();
        echo json_encode(['ok' => true, 'status' => 'pending', 'id' => $id, 'edit_token' => $editToken], JSON_UNESCAPED_UNICODE);
        exit;
    } catch (Throwable $e) {
        error_log('[ReaderQuestionsEndpoint] create: ' . $e->getMessage());
        json_error('insert_failed', 500);
    }
}

if ($action === 'update') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_error('method_not_allowed', 405);
    }

    if (!gdy_rate_limit('rq_update', $ip, 10, 60)) {
        json_error('rate_limited', 429);
    }

    $token = (string)($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!function_exists('verify_csrf_token') || !verify_csrf_token($token)) {
        json_error('csrf_failed', 403);
    }

    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) {
        json_error('invalid_id', 422);
    }

    $editToken = (string)($_POST['edit_token'] ?? '');
    if ($editToken === '' || strlen($editToken) > 128) {
        json_error('invalid_edit_token', 403);
    }

    $q = trim((string)($_POST['question'] ?? ''));
    $q = str_replace(["\r\n", "\r"], "\n", $q);
    $q = strip_tags($q);
    $q = preg_replace('~[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+~u', '', (string)$q);
    $q = trim((string)$q);

    if ($q === '') {
        json_error('empty_question', 422);
    }
    if (mb_strlen($q, 'UTF-8') > 2000) {
        json_error('question_too_long', 422);
    }

    try {
        $st = $pdo->prepare("SELECT edit_token_hash FROM reader_questions WHERE id=? AND news_id=? LIMIT 1");
        $st->execute([$id, $newsId]);
        $hash = (string)($st->fetchColumn() ?? '');
        if ($hash === '') {
            json_error('not_found', 404);
        }
        if (!password_verify($editToken, $hash)) {
            json_error('edit_forbidden', 403);
        }

        $st = $pdo->prepare("UPDATE reader_questions
                             SET question=?, status='pending', updated_at=NOW(), updated_ip=?
                             WHERE id=? AND news_id=?");
        $st->execute([$q, $ip, $id, $newsId]);

        echo json_encode(['ok' => true, 'status' => 'pending'], JSON_UNESCAPED_UNICODE);
        exit;
    } catch (Throwable $e) {
        error_log('[ReaderQuestionsEndpoint] update: ' . $e->getMessage());
        json_error('update_failed', 500);
    }
}

json_error('unknown_action', 400);
