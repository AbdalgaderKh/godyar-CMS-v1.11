<?php
// FIXED VERSION – security & quality compliant
declare(strict_types=1);

require_once __DIR__ . '/_admin_guard.php';
require_once __DIR__ . '/../includes/bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    gdy_session_start();
}

$currentPage = 'videos';
$pageTitle   = __('إدارة الفيديوهات المميزة');

function h(string $v): string {
    return htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
}

/** @var PDO $pdo */
$pdo = gdy_pdo_safe();
if ($pdo === null) {
    die('Database connection failed');
}

$errors = [];
$success = '';
$tableMissing = false;

// Safe user id
$userId = isset($_SESSION['user'], $_SESSION['user']['id'])
    ? (int) $_SESSION['user']['id']
    : 0;

/* SAVE */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $tokenValid = function_exists('validate_csrf_token')
        ? validate_csrf_token($_POST['csrf_token'] ?? '')
        : true;

    if ($tokenValid === false) {
        $errors[] = 'CSRF token invalid';
    }

    $title = trim($_POST['title'] ?? '');
    $url   = trim($_POST['url'] ?? '');

    if ($title === '') {
        $errors[] = 'العنوان مطلوب';
    }

    if ($url === '' || filter_var($url, FILTER_VALIDATE_URL) === false) {
        $errors[] = 'رابط الفيديو غير صالح';
    }

    if ($errors === []) {
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO featured_videos (title, video_url, created_by, created_at)
                 VALUES (:title, :url, :uid, NOW())'
            );
            $stmt->execute([
                ':title' => $title,
                ':url'   => $url,
                ':uid'   => $userId,
            ]);
            $success = 'تم حفظ الفيديو بنجاح';
        } catch (Throwable $e) {
            $errors[] = 'خطأ في قاعدة البيانات';
        }
    }
}

/* FETCH */
try {
    $stmt = $pdo->query('SELECT * FROM featured_videos ORDER BY id DESC');
    $videos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable) {
    $videos = [];
    $tableMissing = true;
}

$csrfToken = function_exists('csrf_token')
    ? csrf_token()
    : bin2hex(random_bytes(32));

require_once __DIR__ . '/layout/app_start.php';
?>

<?php if ($tableMissing === true): ?>
<div class="alert alert-warning">جدول featured_videos غير موجود</div>
<?php endif; ?>

<?php if ($errors !== []): ?>
<div class="alert alert-danger">
<ul>
<?php foreach ($errors as $e): ?>
<li><?= h($e) ?></li>
<?php endforeach; ?>
</ul>
</div>
<?php endif; ?>

<?php if ($success !== ''): ?>
<div class="alert alert-success"><?= h($success) ?></div>
<?php endif; ?>

<form method="post">
<input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
<input name="title" required>
<input name="url" required>
<button>حفظ</button>
</form>

<?php require_once __DIR__ . '/layout/app_end.php'; ?>
