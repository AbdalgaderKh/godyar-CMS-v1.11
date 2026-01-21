<?php
declare(strict_types=1);

/**
 * LoginController.php (Hardened)
 * --------------------------------
 * أهداف التعديل:
 * - CSRF Token (موحد)
 * - Rate limiting (IP + جلسة)
 * - Redirect آمن بعد الدخول عبر ?next=/path
 * - فصل العرض في frontend/views/login.php
 * - دعم تسجيل الدخول بالبريد أو اسم المستخدم مع الحفاظ على التوافق قدر الإمكان.
 */

if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(__DIR__, 3));
}

require_once ROOT_PATH . '/includes/bootstrap.php';
require_once ROOT_PATH . '/includes/rate_limit.php';

// CSRF helpers (إن لم تكن محملة ضمن bootstrap)
$fn = ROOT_PATH . '/includes/functions.php';
if (is_file($fn)) {
    require_once $fn;
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    if (function_exists('gdy_session_start')) {
        gdy_session_start();
    } else {
        @session_start();
    }
}

if (!function_exists('h')) {
    function h($v): string {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}

/**
 * مسار Redirect آمن:
 * - يسمح فقط بمسارات داخلية تبدأ بـ /
 * - يمنع // أو http(s):// أو javascript:
 */
function safe_next_path(?string $next): string
{
    $next = trim((string)$next);
    if ($next === '') return '';
    if (preg_match('~^(https?:)?//~i', $next)) return '';
    if (preg_match('~^[a-z]+:~i', $next)) return '';
    if ($next[0] !== '/') return '';
    if (strpos($next, '//') === 0) return '';
    return $next;
}

// Base URL helper
$baseUrl = function_exists('base_url') ? rtrim((string)base_url(), '/') : '';

$next = safe_next_path($_GET['next'] ?? $_POST['next'] ?? '');
$redirectAfterLogin = $next !== '' ? ($baseUrl . $next) : ($baseUrl . '/');

// لو المستخدم مسجّل مسبقاً → Redirect
if (!empty($_SESSION['user']) && is_array($_SESSION['user'])) {
    header('Location: ' . $redirectAfterLogin);
    exit;
}

/** @var PDO|null $pdo */
$pdo = function_exists('gdy_pdo_safe') ? gdy_pdo_safe() : null;

$errorMessage = '';
$oldLogin = '';

// Throttle بالجلسة: 5 محاولات فاشلة خلال 10 دقائق → قفل 5 دقائق
function throttle_state(): array
{
    $s = $_SESSION['login_throttle'] ?? null;
    if (!is_array($s)) {
        $s = ['count' => 0, 'first' => time(), 'lock_until' => 0];
    }
    if (time() - (int)$s['first'] > 600) {
        $s = ['count' => 0, 'first' => time(), 'lock_until' => 0];
    }
    return $s;
}
function throttle_save(array $s): void
{
    $_SESSION['login_throttle'] = $s;
}
function throttle_blocked_seconds(): int
{
    $s = throttle_state();
    $lockUntil = (int)($s['lock_until'] ?? 0);
    return ($lockUntil > time()) ? ($lockUntil - time()) : 0;
}
function throttle_on_fail(): void
{
    $s = throttle_state();
    $s['count'] = (int)($s['count'] ?? 0) + 1;

    if ($s['count'] >= 5) {
        $s['lock_until'] = time() + 300;
        $s['count'] = 0;
        $s['first'] = time();
    }
    throttle_save($s);
}
function throttle_on_success(): void
{
    throttle_save(['count' => 0, 'first' => time(), 'lock_until' => 0]);
}

// CSRF token
$csrfToken = '';
if (function_exists('generate_csrf_token')) {
    $csrfToken = generate_csrf_token();
} else {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['csrf_time']  = time();
    }
    $csrfToken = (string)$_SESSION['csrf_token'];
}

$blockedFor = throttle_blocked_seconds();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // IP-based rate limiting (10 attempts / 10 minutes)
    if (!gody_rate_limit('login', 10, 600)) {
        $wait = gody_rate_limit_retry_after('login');
        $errorMessage = 'محاولات كثيرة. حاول بعد ' . (int)$wait . ' ثانية.';
    }

    $login    = trim((string)($_POST['login'] ?? $_POST['email'] ?? $_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? $_POST['pass'] ?? '');
    $remember = !empty($_POST['remember']) || !empty($_POST['remember_me']);

    $oldLogin = $login;

    // CSRF validate
    $postedToken = (string)($_POST['csrf_token'] ?? '');
    if ($errorMessage === '') {
        if (function_exists('verify_csrf_token')) {
            if (!verify_csrf_token($postedToken)) {
                $errorMessage = 'انتهت صلاحية الجلسة أو حدث خطأ في التحقق. حدّث الصفحة وحاول مرة أخرى.';
            }
        } else {
            if (!hash_equals((string)($_SESSION['csrf_token'] ?? ''), $postedToken)) {
                $errorMessage = 'انتهت صلاحية الجلسة أو حدث خطأ في التحقق. حدّث الصفحة وحاول مرة أخرى.';
            }
        }
    }

    // Session throttle
    if ($errorMessage === '') {
        $blockedFor = throttle_blocked_seconds();
        if ($blockedFor > 0) {
            $errorMessage = 'محاولات كثيرة. الرجاء الانتظار ' . (int)$blockedFor . ' ثانية ثم المحاولة مجدداً.';
        }
    }

    if ($errorMessage === '') {
        if ($login === '' || $password === '') {
            $errorMessage = 'يرجى إدخال البريد الإلكتروني / اسم المستخدم وكلمة المرور.';
        } else {
            try {
                // Prefer existing User model if available
                $userFile = ROOT_PATH . '/includes/classes/User.php';
                if (is_file($userFile)) {
                    require_once $userFile;
                }

                $loggedIn = false;

                if (class_exists('User') && method_exists('User', 'login')) {
                    $u = new \User();
                    $loggedIn = (bool)$u->login($login, $password);
                } else {
                    if (!$pdo instanceof PDO) {
                        $errorMessage = 'لا يمكن الاتصال بقاعدة البيانات حالياً.';
                    } else {
                        $stmt = $pdo->prepare(
                            'SELECT id, username, email, role, status, password_hash, password '
                            . 'FROM users WHERE (email = :v OR username = :v) LIMIT 1'
                        );
                        $stmt->execute([':v' => $login]);
                        $user = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

                        if (!$user) {
                            $loggedIn = false;
                        } else {
                            $status = (string)($user['status'] ?? 'active');
                            if (in_array($status, ['blocked', 'banned'], true)) {
                                $errorMessage = 'حسابك موقوف، يرجى مراجعة إدارة الموقع.';
                                $loggedIn = false;
                            } else {
                                $hash = (string)($user['password_hash'] ?? $user['password'] ?? '');
                                $loggedIn = ($hash !== '' && password_verify($password, $hash));

                                if ($loggedIn) {
                                    $_SESSION['user_id'] = (int)($user['id'] ?? 0);
                                    $_SESSION['user'] = [
                                        'id' => (int)($user['id'] ?? 0),
                                        'username' => (string)($user['username'] ?? ''),
                                        'email' => (string)($user['email'] ?? ''),
                                        'role' => (string)($user['role'] ?? 'member'),
                                    ];
                                    $_SESSION['is_member_logged'] = 1;
                                }
                            }
                        }
                    }
                }

                if ($errorMessage === '' && $loggedIn) {
                    throttle_on_success();

                    // "تذكرني" (آمن): تمديد عمر كوكي الجلسة 30 يوم
                    if ($remember) {
                        $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
                        $params = session_get_cookie_params();
                        setcookie(
                            session_name(),
                            session_id(),
                            [
                                'expires'  => time() + 60 * 60 * 24 * 30,
                                'path'     => $params['path'] ?? '/',
                                'domain'   => $params['domain'] ?? '',
                                'secure'   => $isSecure,
                                'httponly' => true,
                                'samesite' => 'Strict',
                            ]
                        );
                    }

                    header('Location: ' . $redirectAfterLogin);
                    exit;
                }

                if ($errorMessage === '' && !$loggedIn) {
                    throttle_on_fail();
                    $errorMessage = 'بيانات الدخول غير صحيحة.';
                }
            } catch (Throwable $e) {
                throttle_on_fail();
                error_log('[login] ' . $e->getMessage());
                $errorMessage = 'حدث خطأ أثناء تسجيل الدخول. حاول مرة أخرى.';
            }
        }
    }
}

// Render view
$view = ROOT_PATH . '/frontend/views/login.php';
if (is_file($view)) {
    $login_error      = $errorMessage;
    $login_identifier = $oldLogin;
    $login_csrf       = $csrfToken;
    $login_next       = $next;
    $login_wait       = $blockedFor;
    require $view;
    return;
}

// Fallback (should not happen once view exists)
http_response_code(500);
echo 'تعذر تحميل واجهة تسجيل الدخول.';
