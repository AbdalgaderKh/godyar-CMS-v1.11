<?php
// /godyar/admin/manage_videos.php
declare(strict_types=1);

require_once __DIR__ . '/_admin_guard.php';
require_once __DIR__ . '/../includes/bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    gdy_session_start();
}

// إعداد بيانات الصفحة
$currentPage = 'videos';
$pageTitle   = __('t_c930ea3a42', 'إدارة الفيديوهات المميزة');

// دالة هروب بسيطة
if (!function_exists('h')) {
    function h($v): string
    {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}

/** @var PDO|null $pdo */
$pdo = gdy_pdo_safe();
if (($pdo instanceof PDO) === false) {
    die(__('t_d1569354af', 'تعذّر الاتصال بقاعدة البيانات.'));
}

$errors  = [];
$success = '';
$editing = null;
$videos  = [];
$tableMissing = false;

/* ========================
   حذف فيديو
======================== */
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    if ($id > 0) {
        try {
            $stmt = $pdo->prepare("DELETE FROM featured_videos WHERE id = :id");
            $stmt->execute([':id' => $id]);
            $success = __('t_a0aac81546', 'تم حذف الفيديو بنجاح.');
        } catch (Throwable $e) {
            $errors[] = __('t_efb6890f77', 'تعذّر حذف الفيديو.');
            error_log('[Manage Videos] Delete error: ' . $e->getMessage());
        }
    }
}

/* ========================
   تحميل فيديو للتعديل
======================== */
if (isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    if ($id > 0) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM featured_videos WHERE id = :id");
            $stmt->execute([':id' => $id]);
            $editing = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (Throwable $e) {
            error_log('[Manage Videos] Edit load error: ' . $e->getMessage());
        }
    }
}

/* ========================
   حفظ / تحديث
======================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (function_exists('validate_csrf_token') && !validate_csrf_token($csrf)) {
        $errors[] = __('t_fbbc004136', 'رمز CSRF غير صالح.');
    } else {
        $id          = (int)($_POST['id'] ?? 0);
        $title       = trim($_POST['title'] ?? '');
        $url         = trim($_POST['url'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $isActive    = isset($_POST['is_active']) ? 1 : 0;

        if ($title === '') {
            $errors[] = __('t_38d6011714', 'يرجى إدخال عنوان الفيديو.');
        }

        if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
            $errors[] = __('t_0ab6a291ed', 'يرجى إدخال رابط صحيح.');
        }

        if (!$errors) {
            try {
                if ($id > 0) {
                    $stmt = $pdo->prepare("
                        UPDATE featured_videos SET
                            title = :title,
                            video_url = :url,
                            description = :description,
                            is_active = :active,
                            updated_at = NOW()
                        WHERE id = :id
                    ");
                    $stmt->execute([
                        ':title' => $title,
                        ':url'   => $url,
                        ':description' => $description,
                        ':active' => $isActive,
                        ':id' => $id,
                    ]);
                    $success = __('t_0f4f44d63c', 'تم تحديث الفيديو.');
                } else {
                    $stmt = $pdo->prepare("
                        INSERT INTO featured_videos
                        (title, video_url, description, is_active, created_by, created_at, updated_at)
                        VALUES (:title, :url, :description, :active, :uid, NOW(), NOW())
                    ");
                    $stmt->execute([
                        ':title' => $title,
                        ':url'   => $url,
                        ':description' => $description,
                        ':active' => $isActive,
                        ':uid' => (int)($_SESSION['user']['id'] ?? 0),
                    ]);
                    $success = __('t_b8238932d4', 'تمت إضافة الفيديو.');
                }
                $editing = null;
            } catch (Throwable $e) {
                $errors[] = $e->getMessage();
                error_log('[Manage Videos] Save error: ' . $e->getMessage());
            }
        }
    }
}

/* ========================
   تحميل القائمة
======================== */
try {
    $stmt = $pdo->query("SELECT * FROM featured_videos ORDER BY created_at DESC, id DESC");
    $videos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $tableMissing = true;
}

$csrfToken = function_exists('csrf_token') ? csrf_token() : bin2hex(random_bytes(16));

require_once __DIR__ . '/layout/app_start.php';
?>

<?php if ($tableMissing): ?>
<div class="alert alert-warning">
    جدول <code>featured_videos</code> غير موجود.
</div>
<?php endif; ?>

<div class="container-fluid py-3">

<?php if ($errors): ?>
<div class="alert alert-danger">
    <ul class="mb-0">
        <?php foreach ($errors as $e): ?><li><?php echo h($e); ?></li><?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<?php if ($success): ?>
<div class="alert alert-success"><?php echo h($success); ?></div>
<?php endif; ?>

<div class="row g-4">

<!-- النموذج -->
<div class="col-lg-4">
<div class="card border-0">
<div class="card-body">

<h2 class="h6 mb-3"><?php echo $editing ? 'تعديل الفيديو' : 'إضافة فيديو'; ?></h2>

<form method="post">
<input type="hidden" name="csrf_token" value="<?php echo h($csrfToken); ?>">
<input type="hidden" name="id" value="<?php echo (int)($editing['id'] ?? 0); ?>">

<div class="mb-3">
<label for="video_title" class="form-label">عنوان الفيديو</label>
<input id="video_title" name="title" class="form-control" required
value="<?php echo h($editing['title'] ?? ''); ?>">
</div>

<div class="mb-3">
<label for="video_url" class="form-label">رابط الفيديو</label>
<input id="video_url" name="url" type="url" class="form-control" required
value="<?php echo h($editing['video_url'] ?? ''); ?>">
</div>

<div class="mb-3">
<label for="video_desc" class="form-label">وصف مختصر</label>
<textarea id="video_desc" name="description" class="form-control" rows="3"><?php
echo h($editing['description'] ?? '');
?></textarea>
</div>

<div class="form-check mb-3">
<input id="is_active" class="form-check-input" type="checkbox" name="is_active" value="1"
<?php echo !isset($editing['is_active']) || (int)$editing['is_active'] === 1 ? 'checked' : ''; ?>>
<label class="form-check-label" for="is_active">تفعيل العرض</label>
</div>

<button class="btn btn-primary w-100">حفظ</button>
</form>

</div>
</div>
</div>

<!-- القائمة -->
<div class="col-lg-8">
<div class="card border-0">
<div class="card-body">

<h2 class="h6 mb-3">قائمة الفيديوهات</h2>

<?php if (!$videos): ?>
<p class="text-muted">لا توجد فيديوهات.</p>
<?php else: ?>
<table class="table table-striped">
<thead>
<tr><th>#</th><th>العنوان</th><th>الحالة</th><th>إجراءات</th></tr>
</thead>
<tbody>
<?php foreach ($videos as $i => $v): ?>
<tr>
<td><?php echo $i + 1; ?></td>
<td><?php echo h($v['title']); ?></td>
<td><?php echo $v['is_active'] ? 'مفعل' : 'غير مفعل'; ?></td>
<td>
<a class="btn btn-sm btn-outline-info" aria-label="تعديل الفيديو" href="?edit=<?php echo (int)$v['id']; ?>">✏️</a>
<a class="btn btn-sm btn-outline-danger" aria-label="حذف الفيديو" href="?delete=<?php echo (int)$v['id']; ?>">🗑</a>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
<?php endif; ?>

</div>
</div>
</div>

</div>
</div>

<?php require_once __DIR__ . '/layout/app_end.php'; ?>
