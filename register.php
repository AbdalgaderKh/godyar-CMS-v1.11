<?php
declare(strict_types=1);

if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', __DIR__);
}
require_once __DIR__ . '/includes/bootstrap.php';

if (!function_exists('h')) {
    function h($v): string {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}

// Basic rate-limit: per IP + per session
$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
if (function_exists('gdy_rate_limit')) {
    if (!gdy_rate_limit('register', $ip, 10, 600)) {
        http_response_code(429);
        echo 'Too many requests.';
        exit;
    }
}

$errors = [];
$ok = false;

// PDO helper (best effort)
$pdo = null;
try {
    if (class_exists('\Godyar\\DB') && method_exists('\Godyar\\DB', 'pdo')) {
        $pdo = \Godyar\DB::pdo();
    } elseif (function_exists('gdy_pdo_safe')) {
        $pdo = gdy_pdo_safe();
    }
} catch (Throwable $e) {
    $pdo = null;
}

function gdy_users_columns(PDO $pdo): array {
    $cols = [];
    try {
        $stmt = $pdo->query('DESCRIBE users');
        $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        foreach ($rows as $r) {
            if (empty($r['Field']) === false) {
                $cols[] = (string)$r['Field'];
            }
        }
    } catch (Throwable $e) {
        // ignore
    }
    return $cols;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF
    $token = (string)($_POST['csrf_token'] ?? '');
    $csrfOk = true;
    if (function_exists('verify_csrf_token')) {
        $csrfOk = verify_csrf_token($token);
    } elseif (function_exists('validate_csrf_token')) {
        $csrfOk = validate_csrf_token($token);
    }
    if (!$csrfOk) {
        $errors[] = 'CSRF token invalid.';
    }

    $email = trim((string)($_POST['email'] ?? ''));
    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $password2 = (string)($_POST['password_confirm'] ?? $_POST['password2'] ?? '');

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Email غير صحيح.';
    }
    if ($username === '') {
        $errors[] = 'اسم المستخدم مطلوب.';
    }
    if ($password === '' || strlen($password) < 8) {
        $errors[] = 'كلمة المرور يجب أن تكون 8 أحرف على الأقل.';
    }
    if ($password !== $password2) {
        $errors[] = 'تأكيد كلمة المرور غير مطابق.';
    }

    if (($pdo instanceof PDO) === false) {
        $errors[] = 'Database not available.';
    }

    if (!$errors && $pdo instanceof PDO) {
        try {
            $cols = gdy_users_columns($pdo);

            // Ensure unique email/username
            $checkEmail = $pdo->prepare('SELECT COUNT(*) FROM users WHERE email = ?');
            $checkEmail->execute([$email]);
            if ((int)$checkEmail->fetchColumn() > 0) {
                $errors[] = 'البريد مستخدم مسبقًا.';
            }

            $checkUser = $pdo->prepare('SELECT COUNT(*) FROM users WHERE username = ?');
            $checkUser->execute([$username]);
            if ((int)$checkUser->fetchColumn() > 0) {
                $errors[] = 'اسم المستخدم مستخدم مسبقًا.';
            }

            if (!$errors) {
                $hash = password_hash($password, PASSWORD_DEFAULT);

                $data = [];
                $fields = [];
                $params = [];

                // Map common columns
                if (in_array('username', $cols, true)) { $fields[] = 'username'; $params[] = $username; }
                if (in_array('email', $cols, true))    { $fields[] = 'email';    $params[] = $email; }

                if (in_array('password_hash', $cols, true)) {
                    $fields[] = 'password_hash';
                    $params[] = $hash;
                } elseif (in_array('password', $cols, true) === true) {
                    $fields[] = 'password';
                    $params[] = $hash;
                }

                // Optional display name
                $displayName = trim((string)($_POST['name'] ?? $_POST['display_name'] ?? $username));
                if ($displayName !== '') {
                    if (in_array('display_name', $cols, true)) { $fields[] = 'display_name'; $params[] = $displayName; }
                    elseif (in_array('name', $cols, true))      { $fields[] = 'name';         $params[] = $displayName; }
                }

                // Default role/status if present
                if (in_array('role', $cols, true)) { $fields[] = 'role'; $params[] = 'user'; }
                if (in_array('is_active', $cols, true)) { $fields[] = 'is_active'; $params[] = 1; }
                if (in_array('status', $cols, true)) { $fields[] = 'status'; $params[] = 'active'; }

                // Timestamps
                $now = date('Y-m-d H:i:s');
                if (in_array('created_at', $cols, true)) { $fields[] = 'created_at'; $params[] = $now; }
                if (in_array('updated_at', $cols, true)) { $fields[] = 'updated_at'; $params[] = $now; }

                if (empty($fields)) {
                    $errors[] = 'Users table schema غير معروف.';
                } else {
                    $placeholders = implode(',', array_fill(0, count($fields), '?'));
                    $sql = 'INSERT INTO users (' . implode(',', $fields) . ') VALUES (' . $placeholders . ')';
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                    $ok = true;
                }
            }
        } catch (Throwable $e) {
            $errors[] = 'حدث خطأ أثناء التسجيل.';
        }
    }
}

$csrf = function_exists('csrf_token') ? (string)csrf_token() : '';

?><!doctype html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>تسجيل حساب</title>
  <style>
    body{font-family:system-ui,-apple-system,"Segoe UI",sans-serif;background:#0b1220;color:#e2e8f0;margin:0;}
    .wrap{max-width:520px;margin:6vh auto;padding:1.25rem;}
    .card{background:#0f172a;border:1px solid rgba(148,163,184,.2);border-radius:16px;padding:1.25rem;}
    label{display:block;margin:.75rem 0 .35rem;}
    input{width:100%;padding:.7rem .8rem;border-radius:12px;border:1px solid rgba(148,163,184,.25);background:#0b1220;color:#e2e8f0;}
    button{margin-top:1rem;width:100%;padding:.8rem;border-radius:12px;border:1px solid rgba(148,163,184,.25);background:#1d4ed8;color:white;font-weight:700;}
    .msg{margin:.75rem 0;padding:.75rem;border-radius:12px;}
    .err{background:rgba(239,68,68,.12);border:1px solid rgba(239,68,68,.25);}
    .ok{background:rgba(34,197,94,.12);border:1px solid rgba(34,197,94,.25);}
    a{color:#93c5fd;text-decoration:none;}
  </style>
</head>
<body>
  <div class="wrap">
    <div class="card">
      <h1 style="margin:0 0 .5rem;">تسجيل حساب</h1>
      <div style="opacity:.85;margin-bottom:1rem;">أدخل بياناتك لإنشاء حساب جديد.</div>

      <?php if ($ok): ?>
        <div class="msg ok">تم إنشاء الحساب بنجاح. <a href="/login">تسجيل الدخول</a></div>
      <?php endif; ?>

      <?php if (!$ok && !empty($errors)): ?>
        <div class="msg err">
          <ul style="margin:0;padding-inline-start:1.2rem;">
            <?php foreach ($errors as $e): ?>
              <li><?php echo h($e); ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <form method="post" action="">
        <input type="hidden" name="csrf_token" value="<?php echo h($csrf); ?>">

        <label>البريد الإلكتروني</label>
        <input type="email" name="email" value="<?php echo h($_POST['email'] ?? ''); ?>" required>

        <label>اسم المستخدم</label>
        <input type="text" name="username" value="<?php echo h($_POST['username'] ?? ''); ?>" required>

        <label>كلمة المرور</label>
        <input type="password" name="password" required>

        <label>تأكيد كلمة المرور</label>
        <input type="password" name="password_confirm" required>

        <button type="submit">إنشاء الحساب</button>
      </form>

      <div style="margin-top:1rem;opacity:.85;">
        لديك حساب؟ <a href="/login">تسجيل الدخول</a>
      </div>
    </div>
  </div>
</body>
</html>

return;
