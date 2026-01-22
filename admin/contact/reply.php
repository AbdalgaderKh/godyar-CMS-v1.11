<?php
declare(strict_types=1);

require_once __DIR__ . '/../_admin_guard.php';
require_once __DIR__ . '/../../includes/bootstrap.php';

use Godyar\Auth;

$currentPage = 'contact';
$pageTitle   = __('t_35e98a991a', 'الرد على رسالة تواصل');

if (!function_exists('h')) {
    function h($v): string
    {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}

// 1) Auth guard (fallback-safe)
try {
    if (class_exists(Auth::class) && method_exists(Auth::class, 'isLoggedIn')) {
        if (!Auth::isLoggedIn()) {
            header('Location: ../login.php');
            exit;
        }
    } else {
        if (session_status() !== PHP_SESSION_ACTIVE && function_exists('gdy_session_start')) {
            gdy_session_start();
        } elseif (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        if (empty($_SESSION['user']) || (($_SESSION['user']['role'] ?? '') === 'guest')) {
            header('Location: ../login.php');
            exit;
        }
    }
} catch (Throwable $e) {
    error_log('[Admin Contact Reply] Auth: ' . $e->getMessage());
    header('Location: ../login.php');
    exit;
}

$pdo = function_exists('gdy_pdo_safe') ? gdy_pdo_safe() : null;
if (!($pdo instanceof PDO)) {
    http_response_code(500);
    die('Database connection not available.');
}

$id = isset($_GET['id']) && ctype_digit((string)$_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    header('Location: index.php');
    exit;
}

// 2) Fetch message
$stmt = $pdo->prepare('SELECT * FROM contact_messages WHERE id = :id LIMIT 1');
$stmt->execute([':id' => $id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

if (!$row) {
    require_once __DIR__ . '/../layout/header.php';
    require_once __DIR__ . '/../layout/sidebar.php';
    echo '<div class="admin-content container-fluid py-4"><div class="alert alert-danger">' . h(__('t_9aff8eacf6', 'الرسالة غير موجودة.')) . '</div></div>';
    require_once __DIR__ . '/../layout/footer.php';
    exit;
}

// 3) CSRF token
if (session_status() !== PHP_SESSION_ACTIVE && function_exists('gdy_session_start')) {
    gdy_session_start();
} elseif (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
if (empty($_SESSION['contact_reply_csrf'])) {
    $_SESSION['contact_reply_csrf'] = bin2hex(random_bytes(16));
}
$csrfToken = (string)$_SESSION['contact_reply_csrf'];

$errors = [];
$success = false;

$replySubject = __('t_f05a61edf6', 'رد على رسالتك: ') . (string)($row['subject'] ?? '');
$replyBody = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $token = (string)($_POST['_token'] ?? '');
    if (!hash_equals($csrfToken, $token)) {
        $errors[] = __('t_7339ca256b', 'انتهت صلاحية النموذج، يرجى إعادة تحميل الصفحة ثم المحاولة مرة أخرى.');
    } else {
        $replyBody = trim((string)($_POST['reply_message'] ?? ''));
        if ($replyBody === '') {
            $errors[] = __('t_e2ecaa68d3', 'نص الرد مطلوب.');
        }

        $to = trim((string)($row['email'] ?? ''));
        if ($to === '') {
            $errors[] = __('t_ef56189125', 'لا يمكن إرسال البريد لأن حقل البريد الإلكتروني فارغ في الرسالة.');
        }

        if (!$errors) {
            $defaultFrom = 'no-reply@' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
            $fromEmail = function_exists('env') ? (string)env('MAIL_FROM_ADDRESS', $defaultFrom) : $defaultFrom;
            $fromName  = function_exists('env') ? (string)env('MAIL_FROM_NAME', __('t_f71ce920b0', 'إدارة الموقع')) : __('t_f71ce920b0', 'إدارة الموقع');

            $headers = [];
            $headers[] = 'MIME-Version: 1.0';
            $headers[] = 'Content-Type: text/plain; charset=UTF-8';
            $headers[] = 'From: ' . $fromName . ' <' . $fromEmail . '>';
            $headers[] = 'Reply-To: ' . $fromEmail;

            $sent = false;
            try {
                // Prefer any internal mail helper if present.
                if (function_exists('gdy_mail')) {
                    $sent = (bool)gdy_mail($to, $replySubject, $replyBody, implode("\r\n", $headers));
                } else {
                    $sent = (bool)mail($to, $replySubject, $replyBody, implode("\r\n", $headers));
                }
            } catch (Throwable $e) {
                error_log('[Admin Contact Reply] mail: ' . $e->getMessage());
                $sent = false;
            }

            if (!$sent) {
                $errors[] = __('t_42903c1300', 'تعذر إرسال البريد. تأكد من إعدادات البريد في السيرفر.');
            } else {
                // Update message status (best effort)
                try {
                    $pdo->prepare('UPDATE contact_messages SET status = :st, is_read = 1 WHERE id = :id')->execute([
                        ':st' => 'replied',
                        ':id' => $id,
                    ]);
                } catch (Throwable $e) {
                    // ignore schema differences
                }

                $success = true;
            }
        }
    }
}

require_once __DIR__ . '/../layout/header.php';
require_once __DIR__ . '/../layout/sidebar.php';
?>

<div class="admin-content container-fluid py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <div>
      <h1 class="h4 mb-1"><?= h($pageTitle) ?></h1>
      <div class="text-muted small"><?= h(__('t_1cbd0d2d9a', 'رسالة من:')) ?> <?= h((string)($row['name'] ?? '')) ?> — <?= h((string)($row['email'] ?? '')) ?></div>
    </div>
    <a class="btn btn-outline-secondary" href="index.php"><?= h(__('t_27b62857fb', 'العودة')) ?></a>
  </div>

  <?php if ($success): ?>
    <div class="alert alert-success"><?= h(__('t_90e2dfc8e1', 'تم إرسال الرد بنجاح.')) ?></div>
  <?php endif; ?>

  <?php if ($errors): ?>
    <div class="alert alert-danger">
      <ul class="mb-0">
        <?php foreach ($errors as $e): ?>
          <li><?= h($e) ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <div class="card">
    <div class="card-body">
      <div class="mb-3">
        <label class="form-label"><?= h(__('t_67d67e57b0', 'الموضوع')) ?></label>
        <input class="form-control" value="<?= h($replySubject) ?>" disabled>
      </div>

      <div class="mb-3">
        <label class="form-label"><?= h(__('t_35e98a991a', 'نص الرد')) ?></label>
        <form method="post">
          <input type="hidden" name="_token" value="<?= h($csrfToken) ?>">
          <textarea name="reply_message" class="form-control" rows="10" placeholder="<?= h(__('t_e2ecaa68d3', 'اكتب نص الرد هنا...')) ?>"><?= h($replyBody) ?></textarea>
          <div class="d-flex gap-2 mt-3">
            <button class="btn btn-primary" type="submit"><?= h(__('t_2b5c257af1', 'إرسال')) ?></button>
            <a class="btn btn-outline-secondary" href="index.php"><?= h(__('t_27b62857fb', 'إلغاء')) ?></a>
          </div>
        </form>
      </div>

      <hr>

      <div class="mb-0">
        <h6 class="text-muted"><?= h(__('t_1cbd0d2d9a', 'نص الرسالة الأصلية')) ?></h6>
        <div class="border rounded p-3" style="white-space:pre-wrap"><?= h((string)($row['message'] ?? '')) ?></div>
      </div>
    </div>
  </div>
</div>

<?php
require_once __DIR__ . '/../layout/footer.php';
