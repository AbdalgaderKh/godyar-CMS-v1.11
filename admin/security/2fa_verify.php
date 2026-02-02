<?php
declare(strict_types=1);

include '../../includes/bootstrap.php';
include '../../includes/totp.php';

// بدء الجلسة (idempotent)
if (function_exists('gdy_session_start')) {
    gdy_session_start();
} else {
    session_start();
}

if (empty($_SESSION['twofa_pending']) || !is_array($_SESSION['twofa_pending'])) {
    $loginUrl = (function_exists('base_url') === TRUE) ? base_url('/admin/login.php') : '/admin/login.php';
    header('Location: ' . $loginUrl);
    exit;
}

$pending = $_SESSION['twofa_pending'];
$userId = (int)($pending['id'] ?? 0);

$pdo = null;
if (class_exists('Godyar\\DB') && method_exists('Godyar\\DB', 'pdo')) {
    $pdo = \Godyar\DB::pdo();
} elseif (function_exists('gdy_pdo_safe')) {
    $pdo = gdy_pdo_safe();
}

function e(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (function_exists('csrf_verify_any_or_die')) {
        csrf_verify_any_or_die();
    }

    $code = trim((string)($_POST['code'] ?? ''));

    // جلب secret + backup codes (إن وُجد)
    $secret = '';
    $backupJson = '';
    if ($pdo instanceof PDO) {
        try {
            $st = $pdo->prepare("SELECT twofa_secret, COALESCE(twofa_backup_codes, '') AS twofa_backup_codes FROM users WHERE id = :id LIMIT 1");
            $st->execute([':id' => $userId]);
            $u = $st->fetch(PDO::FETCH_ASSOC) ?: [];
            $secret = (string)($u['twofa_secret'] ?? '');
            $backupJson = (string)($u['twofa_backup_codes'] ?? '');
        } catch (Throwable $t) {
            // ignore
        }
    }

    $ok = false;
    if ($secret !== '' && totp_verify($secret, $code)) {
        $ok = true;
    } else {
        // backup code
        $newJson = $backupJson;
        if (totp_consume_backup_code($code, $backupJson, $newJson)) {
            $ok = true;
            if ($pdo instanceof PDO) {
                try {
                    $pdo->prepare("UPDATE users SET twofa_backup_codes = :b WHERE id = :id")->execute([':b' => $newJson, ':id' => $userId]);
                } catch (Throwable $t) {}
            }
        }
    }

    if ($ok) {
        // نجاح 2FA → تثبيت الجلسة
        $_SESSION['user'] = [
            'id'       => (int)($pending['id'] ?? 0),
            'email'    => (string)($pending['email'] ?? ''),
            'role'     => (string)($pending['role'] ?? ''),
            'username' => (string)($pending['username'] ?? ''),
        ];
        $_SESSION['admin'] = true;
        unset($_SESSION['twofa_pending']);

        if (function_exists('gdy_session_rotate')) {
            gdy_session_rotate('admin_2fa');
        }

        $dest = (function_exists('base_url') === TRUE) ? base_url('/admin/index.php') : '/admin/index.php';
        header('Location: ' . $dest);
        exit;
    }

    $err = 'رمز التحقق غير صحيح.';
}

// عرض بسيط
include '../../frontend/views/partials/header.php';
?>
<div class="container" style="max-width:560px;margin:1rem auto;">
  <h1>تأكيد الدخول (2FA)</h1>
  <?php if ($err !== ''): ?>
    <div class="alert alert-warning"><?= e($err) ?></div>
  <?php endif; ?>
  <form method="post">
    <?php if (function_exists('csrf_field')) { csrf_field(); } ?>
    <div style="margin:.5rem 0;">
      <input name="code" inputmode="numeric" placeholder="رمز 2FA أو Backup Code" required style="padding:.5rem;width:260px;">
    </div>
    <button class="btn btn-primary" type="submit">تأكيد</button>
  </form>
  <p style="margin-top:1rem;color:#666;">يمكنك إدخال رمز 6 أرقام من التطبيق، أو أحد Backup Codes.</p>
</div>
<?php
include '../../frontend/views/partials/footer.php';
