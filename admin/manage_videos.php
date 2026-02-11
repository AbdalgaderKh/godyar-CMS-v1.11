<?php
// /admin/manage_videos.php
// Godyar CMS v1.11 — Featured Videos manager (hardened)

// phpcs:disable

declare(strict_types=1);

require_once __DIR__ . '/_admin_guard.php';
require_once __DIR__ . '/../includes/bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    gdy_session_start();
}

// -------------------------
// Escaping / Sanitization
// -------------------------
if (!function_exists('gdy_esc_html')) {
    function gdy_esc_html($value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('gdy_esc_attr')) {
    function gdy_esc_attr($value): string
    {
        // Same as HTML escaping; kept separate for readability.
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('gdy_unslash')) {
    function gdy_unslash($value): string
    {
        // Mimic wp_unslash(): remove slashes added by magic quotes (rare) / legacy environments.
        return is_string($value) ? stripslashes($value) : '';
    }
}

if (!function_exists('gdy_sanitize_text')) {
    function gdy_sanitize_text($value): string
    {
        $v = trim((string)$value);
        // Remove control chars except \n\r\t
        $v = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $v) ?? '';
        return $v;
    }
}

// -------------------------
// Page meta (admin shell)
// -------------------------
$currentPage = 'videos';
$pageTitle   = function_exists('__') ? __('t_c930ea3a42', 'إدارة الفيديوهات المميزة') : 'إدارة الفيديوهات المميزة';
$pageSubtitle = function_exists('__') ? __('t_8dbe1cbde5', 'إدارة الفيديوهات المميزة من المنصات المختلفة.') : 'إدارة الفيديوهات المميزة من المنصات المختلفة.';

// -------------------------
// DB
// -------------------------
/** @var PDO|null $pdo */
$pdo = gdy_pdo_safe();
if (($pdo instanceof PDO) === false) {
    http_response_code(500);
    echo gdy_esc_html(function_exists('__') ? __('t_d1569354af', 'تعذّر الاتصال بقاعدة البيانات.') : 'تعذّر الاتصال بقاعدة البيانات.');
    exit;
}

$errors = [];
$success = '';
$editing = null;
$videos = [];
$tableMissing = false;

// -------------------------
// Current user id (avoid direct $_SESSION access)
// -------------------------
$userId = 0;
try {
    if (class_exists('\\Godyar\\Auth') && method_exists('\\Godyar\\Auth', 'user')) {
        $u = \Godyar\Auth::user();
        if (is_array($u) && isset($u['id'])) {
            $userId = (int)$u['id'];
        }
    }
} catch (Exception $e) {
    $userId = 0;
}

// -------------------------
// CSRF token
// -------------------------
$csrfToken = '';
if (function_exists('csrf_token') === true) {
    $csrfToken = (string)csrf_token();
} elseif (function_exists('generate_csrf_token') === true) {
    $csrfToken = (string)generate_csrf_token();
} else {
    $csrfToken = bin2hex(random_bytes(16));
}

// -------------------------
// Delete
// -------------------------
$deleteId = filter_input(INPUT_GET, 'delete', FILTER_VALIDATE_INT);
if (is_int($deleteId) && $deleteId > 0) {
    try {
        $stmt = $pdo->prepare('DELETE FROM featured_videos WHERE id = :id');
        $stmt->execute([':id' => $deleteId]);
        $success = function_exists('__') ? __('t_a0aac81546', 'تم حذف الفيديو بنجاح.') : 'تم حذف الفيديو بنجاح.';
    } catch (Exception $e) {
        $errors[] = function_exists('__') ? __('t_efb6890f77', 'تعذّر حذف الفيديو، يرجى المحاولة لاحقاً.') : 'تعذّر حذف الفيديو، يرجى المحاولة لاحقاً.';
        error_log('[Manage Videos] Delete error: ' . $e->getMessage());
    }
}

// -------------------------
// Load for edit
// -------------------------
$editId = filter_input(INPUT_GET, 'edit', FILTER_VALIDATE_INT);
if (is_int($editId) && $editId > 0) {
    try {
        $stmt = $pdo->prepare('SELECT * FROM featured_videos WHERE id = :id');
        $stmt->execute([':id' => $editId]);
        $editing = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Exception $e) {
        error_log('[Manage Videos] Load edit error: ' . $e->getMessage());
    }
}

// -------------------------
// Save (POST)
// -------------------------
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $postedCsrf = filter_input(INPUT_POST, 'csrf_token', FILTER_UNSAFE_RAW);
    $postedCsrf = gdy_sanitize_text(gdy_unslash($postedCsrf));

    $csrfOk = true;
    if (function_exists('validate_csrf_token') === true) {
        $csrfOk = (validate_csrf_token($postedCsrf) === true);
    }

    if ($csrfOk === false) {
        $errors[] = function_exists('__') ? __('t_fbbc004136', 'رمز الحماية (CSRF) غير صالح، يرجى إعادة المحاولة.') : 'رمز الحماية (CSRF) غير صالح، يرجى إعادة المحاولة.';
    } else {
        $idRaw = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        $id = is_int($idRaw) ? $idRaw : 0;

        $title = gdy_sanitize_text(gdy_unslash(filter_input(INPUT_POST, 'title', FILTER_UNSAFE_RAW)));
        $url = gdy_sanitize_text(gdy_unslash(filter_input(INPUT_POST, 'url', FILTER_UNSAFE_RAW)));
        $description = gdy_sanitize_text(gdy_unslash(filter_input(INPUT_POST, 'description', FILTER_UNSAFE_RAW)));
        $isActive = (filter_has_var(INPUT_POST, 'is_active') === true) ? 1 : 0;

        if ($title === '') {
            $errors[] = function_exists('__') ? __('t_38d6011714', 'يرجى إدخال عنوان الفيديو.') : 'يرجى إدخال عنوان الفيديو.';
        }

        if ($url === '') {
            $errors[] = function_exists('__') ? __('t_7ee97cc87f', 'يرجى إدخال رابط الفيديو.') : 'يرجى إدخال رابط الفيديو.';
        } elseif (filter_var($url, FILTER_VALIDATE_URL) === false) {
            $errors[] = function_exists('__') ? __('t_0ab6a291ed', 'يرجى إدخال رابط صحيح يبدأ بـ http أو https.') : 'يرجى إدخال رابط صحيح يبدأ بـ http أو https.';
        }

        if (count($errors) === 0) {
            try {
                if ($id > 0) {
                    $stmt = $pdo->prepare(
                        'UPDATE featured_videos
                         SET title = :title,
                             video_url = :video_url,
                             description = :description,
                             is_active = :is_active,
                             updated_at = NOW()
                         WHERE id = :id'
                    );
                    $stmt->execute([
                        ':title' => $title,
                        ':video_url' => $url,
                        ':description' => $description,
                        ':is_active' => $isActive,
                        ':id' => $id,
                    ]);
                    $success = function_exists('__') ? __('t_0f4f44d63c', 'تم تحديث الفيديو بنجاح.') : 'تم تحديث الفيديو بنجاح.';
                } else {
                    $stmt = $pdo->prepare(
                        'INSERT INTO featured_videos
                            (title, video_url, description, is_active, created_by, created_at, updated_at)
                         VALUES
                            (:title, :video_url, :description, :is_active, :created_by, NOW(), NOW())'
                    );
                    $stmt->execute([
                        ':title' => $title,
                        ':video_url' => $url,
                        ':description' => $description,
                        ':is_active' => $isActive,
                        ':created_by' => $userId,
                    ]);
                    $success = function_exists('__') ? __('t_b8238932d4', 'تمت إضافة الفيديو بنجاح.') : 'تمت إضافة الفيديو بنجاح.';
                }

                $editing = null;
            } catch (Exception $e) {
                $errors[] = (function_exists('__') ? __('t_a7e651d555', 'حدث خطأ أثناء حفظ البيانات في قاعدة البيانات: ') : 'حدث خطأ أثناء حفظ البيانات في قاعدة البيانات: ') . $e->getMessage();
                error_log('[Manage Videos] Save error: ' . $e->getMessage());
            }
        }
    }
}

// -------------------------
// Fetch list
// -------------------------
try {
    $stmt = $pdo->query('SELECT * FROM featured_videos ORDER BY created_at DESC, id DESC');
    $videos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $tableMissing = true;
    error_log('[Manage Videos] Fetch error: ' . $e->getMessage());
}

// -------------------------
// Admin shell (unified layout)
// -------------------------
$adminBase = (function_exists('base_url') ? rtrim((string)base_url(), '/') : '') . '/admin';
$breadcrumbs = [
    (function_exists('__') ? __('t_3aa8578699', 'الرئيسية') : 'الرئيسية') => $adminBase . '/index.php',
    (function_exists('__') ? __('t_c930ea3a42', 'الفيديوهات') : 'الفيديوهات') => null,
];

require_once __DIR__ . '/layout/app_start.php';
?>

<?php if ($tableMissing === true): ?>
    <div class="alert alert-warning" style="margin:12px 18px;border-radius:10px;">
        <?php echo gdy_esc_html('تنبيه: جدول'); ?> <code>featured_videos</code> <?php echo gdy_esc_html('غير موجود.'); ?>
        <a href="/admin/create_featured_videos_table.php" style="font-weight:700;">
            <?php echo gdy_esc_html('انقر هنا لإنشاء الجدول'); ?>
        </a>.
    </div>
<?php endif; ?>

<div class="container-fluid py-3">
    <div class="d-flex justify-content-end mb-3">
        <a href="index.php" class="btn btn-outline-secondary btn-sm">
            <svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="#arrow-left"></use></svg>
            <?php echo gdy_esc_html(function_exists('__') ? __('t_2f09126266', 'العودة للوحة التحكم') : 'العودة للوحة التحكم'); ?>
        </a>
    </div>

    <?php if (count($errors) > 0): ?>
        <div class="alert alert-danger">
            <svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="#more-h"></use></svg>
            <?php echo gdy_esc_html(function_exists('__') ? __('t_4e7e8d83c3', 'حدثت الأخطاء التالية:') : 'حدثت الأخطاء التالية:'); ?>
            <ul class="mb-0 mt-2">
                <?php foreach ($errors as $e): ?>
                    <li><?php echo gdy_esc_html($e); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if ($success !== ''): ?>
        <div class="alert alert-success">
            <svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="#more-h"></use></svg>
            <?php echo gdy_esc_html($success); ?>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card video-card border-0">
                <div class="card-body">
                    <h2 class="h6 mb-3">
                        <svg class="gdy-icon me-2 text-info" aria-hidden="true" focusable="false"><use href="#more-h"></use></svg>
                        <?php echo gdy_esc_html($editing ? (function_exists('__') ? __('t_3dc5805e67', 'تعديل الفيديو') : 'تعديل الفيديو') : (function_exists('__') ? __('t_ae2be6f43c', 'إضافة فيديو جديد') : 'إضافة فيديو جديد')); ?>
                    </h2>

                    <form method="post" action="">
                        <input type="hidden" name="csrf_token" value="<?php echo gdy_esc_attr($csrfToken); ?>">
                        <input type="hidden" name="id" value="<?php echo (int)($editing['id'] ?? 0); ?>">

                        <div class="mb-3">
                            <label class="form-label" for="video_title"><?php echo gdy_esc_html(function_exists('__') ? __('t_91d18bfdaf', 'عنوان الفيديو') : 'عنوان الفيديو'); ?></label>
                            <input
                                id="video_title"
                                type="text"
                                name="title"
                                class="form-control"
                                required
                                value="<?php echo gdy_esc_attr($editing['title'] ?? ''); ?>"
                            >
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="video_url">
                                <?php echo gdy_esc_html(function_exists('__') ? __('t_b16a72514e', 'رابط الفيديو (YouTube / Facebook / TikTok / Instagram / Snapchat / Vimeo / Dailymotion)') : 'رابط الفيديو (YouTube / Facebook / TikTok / Instagram / Snapchat / Vimeo / Dailymotion)'); ?>
                            </label>
                            <input
                                id="video_url"
                                type="url"
                                name="url"
                                class="form-control"
                                required
                                value="<?php echo gdy_esc_attr($editing['video_url'] ?? ''); ?>"
                                placeholder="<?php echo gdy_esc_attr(function_exists('__') ? __('t_85dbfef47a', 'مثال: https://www.youtube.com/watch?v=XXXX أو https://www.tiktok.com/... أو https://fb.watch/...') : 'مثال: https://www.youtube.com/watch?v=XXXX أو https://www.tiktok.com/... أو https://fb.watch/...'); ?>"
                            >
                            <div class="form-text text-muted">
                                <?php echo gdy_esc_html(function_exists('__') ? __('t_3522381271', '✅ يدعم أغلب منصات الفيديو الشهيرة.') : '✅ يدعم أغلب منصات الفيديو الشهيرة.'); ?><br>
                                <?php echo gdy_esc_html(function_exists('__') ? __('t_d181fc0889', '⚠ بعض المنصات مثل Instagram و Snapchat قد لا تسمح بالتشغيل داخل الموقع، وفي هذه الحالة سيتم فتح الفيديو في تبويب جديد على المنصة الأصلية.') : '⚠ بعض المنصات مثل Instagram و Snapchat قد لا تسمح بالتشغيل داخل الموقع، وفي هذه الحالة سيتم فتح الفيديو في تبويب جديد على المنصة الأصلية.'); ?>
                                <br>
                                <?php echo gdy_esc_html(function_exists('__') ? __('t_138a029459', 'لاختبار الرابط:') : 'لاختبار الرابط:'); ?>
                                <a href="#" id="testVideoLink" target="_blank" rel="noopener noreferrer"><?php echo gdy_esc_html(function_exists('__') ? __('t_7b0fb866e4', 'افتح الرابط في نافذة جديدة') : 'افتح الرابط في نافذة جديدة'); ?></a>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="video_desc"><?php echo gdy_esc_html(function_exists('__') ? __('t_81edd198f5', 'وصف مختصر') : 'وصف مختصر'); ?></label>
                            <textarea id="video_desc" name="description" class="form-control" rows="3"><?php echo gdy_esc_html($editing['description'] ?? ''); ?></textarea>
                        </div>

                        <div class="form-check mb-3">
                            <input
                                class="form-check-input"
                                type="checkbox"
                                name="is_active"
                                id="is_active"
                                value="1"
                                <?php echo (!isset($editing['is_active']) || (int)$editing['is_active'] === 1) ? 'checked' : ''; ?>
                            >
                            <label class="form-check-label" for="is_active">
                                <?php echo gdy_esc_html(function_exists('__') ? __('t_67be8c29d6', 'تفعيل عرض هذا الفيديو في الواجهة') : 'تفعيل عرض هذا الفيديو في الواجهة'); ?>
                            </label>
                        </div>

                        <button type="submit" class="btn btn-primary w-100">
                            <svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="#more-h"></use></svg>
                            <?php echo gdy_esc_html($editing ? (function_exists('__') ? __('t_35f75fe13d', 'تحديث الفيديو') : 'تحديث الفيديو') : (function_exists('__') ? __('t_417b6442fa', 'حفظ الفيديو') : 'حفظ الفيديو')); ?>
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card video-card border-0">
                <div class="card-body">
                    <h2 class="h6 mb-3">
                        <svg class="gdy-icon me-2 text-info" aria-hidden="true" focusable="false"><use href="#more-h"></use></svg>
                        <?php echo gdy_esc_html(function_exists('__') ? __('t_569c2cfc5d', 'قائمة الفيديوهات') : 'قائمة الفيديوهات'); ?>
                    </h2>

                    <?php if (count($videos) === 0): ?>
                        <p class="text-muted mb-0"><?php echo gdy_esc_html(function_exists('__') ? __('t_939b14dffe', 'لا توجد فيديوهات مضافة بعد.') : 'لا توجد فيديوهات مضافة بعد.'); ?></p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-dark table-striped align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th><?php echo gdy_esc_html(function_exists('__') ? __('t_6dc6588082', 'العنوان') : 'العنوان'); ?></th>
                                        <th><?php echo gdy_esc_html(function_exists('__') ? __('t_1253eb5642', 'الحالة') : 'الحالة'); ?></th>
                                        <th><?php echo gdy_esc_html(function_exists('__') ? __('t_8456f22b47', 'التاريخ') : 'التاريخ'); ?></th>
                                        <th class="text-center"><?php echo gdy_esc_html(function_exists('__') ? __('t_901efe9b1c', 'إجراءات') : 'إجراءات'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($videos as $index => $v): ?>
                                        <tr>
                                            <td><?php echo (int)$index + 1; ?></td>
                                            <td>
                                                <div class="fw-semibold mb-1"><?php echo gdy_esc_html($v['title'] ?? ''); ?></div>
                                                <div class="small text-muted text-truncate" style="max-width: 260px;">
                                                    <?php echo gdy_esc_html($v['video_url'] ?? ''); ?>
                                                </div>
                                            </td>
                                            <td>
                                                <?php if ((int)($v['is_active'] ?? 0) === 1): ?>
                                                    <span class="video-badge-active"><?php echo gdy_esc_html(function_exists('__') ? __('t_918499f2af', 'مفعل') : 'مفعل'); ?></span>
                                                <?php else: ?>
                                                    <span class="video-badge-inactive"><?php echo gdy_esc_html(function_exists('__') ? __('t_60dfc10f77', 'غير مفعل') : 'غير مفعل'); ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="small text-muted">
                                                <?php echo !empty($v['created_at']) ? gdy_esc_html($v['created_at']) : ''; ?>
                                            </td>
                                            <td class="text-center">
                                                <a href="?edit=<?php echo (int)($v['id'] ?? 0); ?>" class="btn btn-sm btn-outline-info me-1" aria-label="edit">
                                                    <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="#edit"></use></svg>
                                                </a>
                                                <a
                                                    href="?delete=<?php echo (int)($v['id'] ?? 0); ?>"
                                                    class="btn btn-sm btn-outline-danger"
                                                    data-confirm="هل أنت متأكد من حذف هذا الفيديو؟"
                                                    aria-label="delete"
                                                >
                                                    <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="#trash"></use></svg>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>

                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Simple URL test link sync + basic client-side validation
document.addEventListener('DOMContentLoaded', function() {
    var videoUrlInput = document.getElementById('video_url');
    var testLink = document.getElementById('testVideoLink');
    var form = document.querySelector('form');

    function syncTestLink() {
        if (!videoUrlInput || !testLink) return;
        var url = (videoUrlInput.value || '').trim();
        testLink.href = url ? url : '#';
    }

    syncTestLink();
    if (videoUrlInput) videoUrlInput.addEventListener('input', syncTestLink);

    if (form && videoUrlInput) {
        form.addEventListener('submit', function(e) {
            var url = (videoUrlInput.value || '').trim();
            if (!url) {
                alert('يرجى إدخال رابط الفيديو.');
                videoUrlInput.focus();
                e.preventDefault();
                return;
            }
            if (!/^https?:\/\/.+/i.test(url)) {
                alert('يرجى إدخال رابط صحيح يبدأ بـ http أو https.');
                videoUrlInput.focus();
                e.preventDefault();
            }
        });
    }
});
</script>

<?php require_once __DIR__ . '/layout/app_end.php'; ?>

<?php
// phpcs:enable
?>
