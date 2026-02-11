<?php
declare(strict_types=1);

// admin/security/2fa.php — إدارة التحقق بخطوتين (TOTP) للأدمن

require_once __DIR__ . '/../_admin_guard.php';
require_once __DIR__ . '/../../includes/totp.php';

$title = 'التحقق بخطوتين (2FA)';
$uid = (int)($_SESSION['user']['id'] ?? 0);

$pdo = null;
if (class_exists('Godyar\\DB') && method_exists('Godyar\\DB', 'pdo')) {
    $pdo = \Godyar\DB::pdo();
} elseif (function_exists('gdy_pdo_safe')) {
    $pdo = gdy_pdo_safe();
}
if (!$pdo instanceof PDO) {
    http_response_code(500);
    echo 'DB not available';
    return;
}

function e(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

// جلب حالة 2FA الحالية
$stmt = $pdo->prepare('SELECT twofa_enabled, twofa_secret, COALESCE(twofa_backup_codes, \'\') AS twofa_backup_codes FROM users WHERE id = :id LIMIT 1');
$stmt->execute([':id' => $uid]);
$row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
$enabled = ((int)($row['twofa_enabled'] ?? 0)) === 1;
$secret  = (string)($row['twofa_secret'] ?? '');
$backupJson = (string)($row['twofa_backup_codes'] ?? '');

$flash = '';
$showSetup = false;
$setupSecret = '';

if (session_status() !== PHP_SESSION_ACTIVE) {
    if (function_exists('gdy_session_start')) { gdy_session_start(); } else { session_start(); }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF (إن وجد)
    if (function_exists('csrf_verify_any_or_die')) {
        csrf_verify_any_or_die();
    }

    $action = (string)($_POST['action'] ?? '');
    $code   = trim((string)($_POST['code'] ?? ''));

    if ($action === 'start_enable') {
        // إنشاء secret وعرضه للمستخدم ليمسحه في تطبيق Authenticator
        $setupSecret = totp_base32_encode(random_bytes(20));
        $_SESSION['twofa_setup_secret'] = $setupSecret;
        $showSetup = true;

    } elseif ($action === 'confirm_enable') {
        $setupSecret = (string)($_SESSION['twofa_setup_secret'] ?? '');
        if ($setupSecret === '') {
            $flash = 'انتهت جلسة الإعداد. ابدأ من جديد.';
        } elseif (!totp_verify($setupSecret, $code)) {
            $flash = 'رمز التحقق غير صحيح. حاول مرة أخرى.';
            $showSetup = true;
        } else {
            $codes = totp_generate_backup_codes(10);
            $hashJson = totp_hash_backup_codes($codes);

            // حفظ السر + تفعيل
            $upd = $pdo->prepare('UPDATE users SET twofa_enabled = 1, twofa_secret = :s, twofa_backup_codes = :b WHERE id = :id');
            $upd->execute([':s' => $setupSecret, ':b' => $hashJson, ':id' => $uid]);

            unset($_SESSION['twofa_setup_secret']);

            // عرض backup codes مرة واحدة
            $_SESSION['twofa_show_backup_once'] = $codes;

            header('Location: 2fa.php?enabled=1');
            exit;
        }

    } elseif ($action === 'disable') {
        if (!$enabled || $secret === '') {
            $flash = '2FA غير مفعّل.';
        } elseif (!totp_verify($secret, $code)) {
            $flash = 'رمز التحقق غير صحيح.';
        } else {
            $upd = $pdo->prepare('UPDATE users SET twofa_enabled = 0, twofa_secret = NULL, twofa_backup_codes = NULL WHERE id = :id');
            $upd->execute([':id' => $uid]);
            $flash = 'تم تعطيل 2FA.';
            $enabled = false;
            $secret = '';
            $backupJson = '';
        }

    } elseif ($action === 'regen_backup') {
        if (!$enabled || $secret === '') {
            $flash = '2FA غير مفعّل.';
        } elseif (!totp_verify($secret, $code)) {
            $flash = 'رمز التحقق غير صحيح.';
        } else {
            $codes = totp_generate_backup_codes(10);
            $hashJson = totp_hash_backup_codes($codes);
            $upd = $pdo->prepare('UPDATE users SET twofa_backup_codes = :b WHERE id = :id');
            $upd->execute([':b' => $hashJson, ':id' => $uid]);

            $_SESSION['twofa_show_backup_once'] = $codes;
            header('Location: 2fa.php?backup=1');
            exit;
        }
    }
}

// عرض backup codes مرة واحدة (إن وجدت)
$backupOnce = $_SESSION['twofa_show_backup_once'] ?? null;
if (is_array($backupOnce)) {
    unset($_SESSION['twofa_show_backup_once']);
}

// بيانات QR/URI
$site = (function_exists('site_name')) ? (string)site_name() : 'Godyar';
$email = (string)($_SESSION['user']['email'] ?? '');
$issuer = rawurlencode($site);
$label  = rawurlencode($site . ':' . $email);

$otpauth = '';
if ($showSetup && $setupSecret !== '') {
    $otpauth = 'otpauth://totp/' . $label . '?secret=' . rawurlencode($setupSecret) . '&issuer=' . $issuer . '&algorithm=SHA1&digits=6&period=30';
}

include '../../frontend/views/partials/header.php';
?>
<div class="container" style="max-width: 760px; margin: 1rem auto;">
  <h1><?= e($title) ?></h1>

  <?php if ($flash !== ''): ?>
    <div class="alert alert-warning"><?= e($flash) ?></div>
  <?php endif; ?>

  <?php if (is_array($backupOnce) && count($backupOnce) > 0): ?>
    <div class="alert alert-success">
      <strong>Backup Codes (مرة واحدة فقط):</strong>
      <p>احتفظ بهذه الأكواد في مكان آمن. كل كود يُستخدم مرة واحدة.</p>
      <ul>
        <?php foreach ($backupOnce as $c): ?>
          <li><code><?= e((string)$c) ?></code></li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <?php if (!$enabled): ?>
    <p>الحالة: <strong>غير مفعّل</strong></p>

    <?php if (!$showSetup): ?>
      <form method="post">
        <?php if (function_exists('csrf_field')) { csrf_field(); } ?>
        <input type="hidden" name="action" value="start_enable">
        <button class="btn btn-primary" type="submit">تفعيل 2FA</button>
      </form>
    <?php else: ?>
      <div class="card" style="padding: 1rem; margin: 1rem 0;">
        <h3>1) أضف الحساب في تطبيق Authenticator</h3>
        <p>Secret:</p>
        <pre style="background:#f6f6f6;padding:8px;"><?= e($setupSecret) ?></pre>
        <p>OTPAUTH URI (يمكن نسخه في بعض التطبيقات):</p>
        <pre style="background:#f6f6f6;padding:8px; white-space: pre-wrap;"><?= e($otpauth) ?></pre>

        <h3 style="margin-top: 1rem;">2) أدخل رمز 6 أرقام للتأكيد</h3>
        <form method="post">
          <?php if (function_exists('csrf_field')) { csrf_field(); } ?>
          <input type="hidden" name="action" value="confirm_enable">
          <div style="margin: .5rem 0;">
            <input name="code" inputmode="numeric" pattern="[0-9]{6}" placeholder="123456" required style="padding: .5rem; width: 180px;">
          </div>
          <button class="btn btn-success" type="submit">تأكيد التفعيل</button>
        </form>
      </div>
    <?php endif; ?>

  <?php else: ?>
    <p>الحالة: <strong>مفعّل</strong></p>

    <div class="card" style="padding: 1rem; margin: 1rem 0;">
      <h3>توليد Backup Codes جديدة</h3>
      <p>سيتم عرض الأكواد مرة واحدة فقط.</p>
      <form method="post">
        <?php if (function_exists('csrf_field')) { csrf_field(); } ?>
        <input type="hidden" name="action" value="regen_backup">
        <input name="code" inputmode="numeric" pattern="[0-9]{6}" placeholder="رمز 2FA" required style="padding: .5rem; width: 180px;">
        <button class="btn btn-secondary" type="submit">توليد</button>
      </form>
    </div>

    <div class="card" style="padding: 1rem; margin: 1rem 0;">
      <h3>تعطيل 2FA</h3>
      <form method="post">
        <?php if (function_exists('csrf_field')) { csrf_field(); } ?>
        <input type="hidden" name="action" value="disable">
        <input name="code" inputmode="numeric" pattern="[0-9]{6}" placeholder="رمز 2FA" required style="padding: .5rem; width: 180px;">
        <button class="btn btn-danger" type="submit">تعطيل</button>
      </form>
    </div>
  <?php endif; ?>
</div>
<?php
include '../../frontend/views/partials/footer.php';
