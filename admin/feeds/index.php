<?php
declare(strict_types=1);

require_once __DIR__ . '/../_admin_guard.php';
require_once __DIR__ . '/../../includes/bootstrap.php';

use Godyar\Feeds\RssReader;
use Godyar\Services\FeedImportService;

$pageTitle   = __('t_9efb9c0c64', 'مصادر RSS');
$currentPage = 'feeds';

if (!function_exists('h')) {
    function h($v): string
    {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}

$pdo = function_exists('gdy_pdo_safe') ? gdy_pdo_safe() : null;
if (!($pdo instanceof PDO)) {
    die('Database connection not available.');
}

$flashSuccess = '';
$flashError   = '';

// Basic CSRF (reuse global if available)
if (session_status() !== PHP_SESSION_ACTIVE) {
    if (function_exists('gdy_session_start')) {
        gdy_session_start();
    } else {
        session_start();
    }
}
if (empty($_SESSION['feeds_csrf'])) {
    $_SESSION['feeds_csrf'] = bin2hex(random_bytes(16));
}
$csrfToken = (string)$_SESSION['feeds_csrf'];

function feeds_verify_csrf(string $token): bool
{
    $sess = (string)($_SESSION['feeds_csrf'] ?? '');
    return $sess !== '' && hash_equals($sess, $token);
}

// Handle actions
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $action = (string)($_POST['action'] ?? '');

    // Prefer project-level CSRF if present; fallback to local token.
    try {
        if (function_exists('verify_csrf')) {
            verify_csrf();
        } else {
            $t = (string)($_POST['_token'] ?? '');
            if (!feeds_verify_csrf($t)) {
                throw new RuntimeException(__('t_7339ca256b', 'انتهت صلاحية النموذج، يرجى إعادة تحميل الصفحة ثم المحاولة مرة أخرى.'));
            }
        }

        if ($action === 'create') {
            $name     = trim((string)($_POST['name'] ?? ''));
            $url      = trim((string)($_POST['url'] ?? ''));
            $category = (int)($_POST['category_id'] ?? 0);
            $active   = isset($_POST['is_active']) ? 1 : 0;
            $interval = (int)($_POST['fetch_interval_minutes'] ?? 60);
            if ($interval < 5) $interval = 5;

            if ($name === '' || $url === '') {
                throw new RuntimeException(__('t_2b3cfc2d6e', 'الاسم والرابط مطلوبان.'));
            }

            $st = $pdo->prepare('INSERT INTO feeds (name, url, category_id, is_active, fetch_interval_minutes, created_at) VALUES (:n,:u,:c,:a,:i,NOW())');
            $st->execute([':n'=>$name, ':u'=>$url, ':c'=>$category, ':a'=>$active, ':i'=>$interval]);
            $flashSuccess = __('t_8e32d1a20d', 'تمت إضافة المصدر.');

        } elseif ($action === 'update') {
            $id       = (int)($_POST['id'] ?? 0);
            $name     = trim((string)($_POST['name'] ?? ''));
            $url      = trim((string)($_POST['url'] ?? ''));
            $category = (int)($_POST['category_id'] ?? 0);
            $active   = isset($_POST['is_active']) ? 1 : 0;
            $interval = (int)($_POST['fetch_interval_minutes'] ?? 60);
            if ($interval < 5) $interval = 5;
            if ($id <= 0) throw new RuntimeException(__('t_17ae6e50d5', 'مصدر غير صالح.'));

            $st = $pdo->prepare('UPDATE feeds SET name=:n, url=:u, category_id=:c, is_active=:a, fetch_interval_minutes=:i WHERE id=:id');
            $st->execute([':n'=>$name, ':u'=>$url, ':c'=>$category, ':a'=>$active, ':i'=>$interval, ':id'=>$id]);
            $flashSuccess = __('t_30a2ff105a', 'تم تحديث المصدر.');

        } elseif ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) throw new RuntimeException(__('t_17ae6e50d5', 'مصدر غير صالح.'));
            $pdo->prepare('DELETE FROM feeds WHERE id=:id')->execute([':id'=>$id]);
            $flashSuccess = __('t_abf320b86d', 'تم حذف المصدر.');

        } elseif ($action === 'toggle') {
            $id = (int)($_POST['id'] ?? 0);
            $to = (int)($_POST['to'] ?? 0) ? 1 : 0;
            if ($id <= 0) throw new RuntimeException(__('t_17ae6e50d5', 'مصدر غير صالح.'));
            $pdo->prepare('UPDATE feeds SET is_active=:a WHERE id=:id')->execute([':a'=>$to, ':id'=>$id]);
            $flashSuccess = $to ? __('t_627469da7c', 'تم تفعيل المصدر.') : __('t_a7b5d66e42', 'تم إيقاف المصدر.');

        } elseif ($action === 'test') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) throw new RuntimeException(__('t_17ae6e50d5', 'مصدر غير صالح.'));
            $st = $pdo->prepare('SELECT * FROM feeds WHERE id=:id LIMIT 1');
            $st->execute([':id'=>$id]);
            $f = $st->fetch(PDO::FETCH_ASSOC) ?: null;
            if (!$f) throw new RuntimeException(__('t_69d011164e', 'المصدر غير موجود.'));

            $items = RssReader::fetch((string)$f['url'], 3);
            if ($items && is_array($items)) {
                $first = (string)($items[0]['title'] ?? '');
                $flashSuccess = __('t_abb38a77f7', 'نجح الاتصال بالمصدر. العناصر المقروءة الآن: ') . count($items) . ($first ? __('t_59a1291925', ' — مثال: ') . $first : '');
            } else {
                $flashError = __('t_42903c1300', 'تعذر قراءة RSS. تأكد أن الرابط RSS/Atom صحيح وغير محمي.');
            }

        } elseif ($action === 'fetch_now') {
            // Runs the importer (will import only due feeds based on last_fetched_at + interval)
            $svc = new FeedImportService($pdo);
            $res = $svc->run();
            $flashSuccess = __('t_1d83c69dc2', 'تم الجلب: تم استيراد ') . (int)($res['imported'] ?? 0) . __('t_11d6c8485e', ' وتخطي ') . (int)($res['skipped'] ?? 0) . __('t_4e3f8ea82f', ' من العناصر.');

        } else {
            throw new RuntimeException(__('t_3742196a8c', 'إجراء غير صالح.'));
        }
    } catch (Exception $e) {
        $flashError = $flashError ?: (string)$e->getMessage();
        error_log('[admin feeds] ' . $e->getMessage());
    }
}

$feeds = [];
try {
    $feeds = $pdo->query('SELECT * FROM feeds ORDER BY id DESC')->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Exception $e) {
    $feeds = [];
}

$cats = [];
try {
    // optional categories table
    $cats = $pdo->query('SELECT id, name FROM categories ORDER BY name')->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Exception $e) {
    $cats = [];
}

require_once __DIR__ . '/../layout/header.php';
require_once __DIR__ . '/../layout/sidebar.php';
?>

<div class="admin-content container-fluid py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <div>
      <h1 class="h4 mb-1"><?php echo h($pageTitle); ?></h1>
      <div class="text-muted small"><?php echo h(__('t_9b9b0d2fd9', 'إدارة مصادر RSS/Atom لاستيراد الأخبار تلقائياً.')); ?></div>
    </div>
    <form method="post" class="m-0">
      <input type="hidden" name="action" value="fetch_now">
      <input type="hidden" name="_token" value="<?php echo h($csrfToken); ?>">
      <button class="btn btn-sm btn-outline-primary" type="submit"><?php echo h(__('t_61c5b6e1fd', 'جلب الآن')); ?></button>
    </form>
  </div>

  <?php if ($flashSuccess): ?>
    <div class="alert alert-success"><?php echo h($flashSuccess); ?></div>
  <?php endif; ?>
  <?php if ($flashError): ?>
    <div class="alert alert-danger"><?php echo h($flashError); ?></div>
  <?php endif; ?>

  <div class="card mb-4">
    <div class="card-header"><strong><?php echo h(__('t_7fd80e5ddc', 'إضافة مصدر')); ?></strong></div>
    <div class="card-body">
      <form method="post" class="row g-3">
        <input type="hidden" name="action" value="create">
        <input type="hidden" name="_token" value="<?php echo h($csrfToken); ?>">

        <div class="col-md-4">
          <label class="form-label"><?php echo h(__('t_2b778c7a76', 'الاسم')); ?></label>
          <input class="form-control" name="name" required>
        </div>
        <div class="col-md-5">
          <label class="form-label"><?php echo h(__('t_2e1d14fb67', 'رابط RSS/Atom')); ?></label>
          <input class="form-control" name="url" required>
        </div>
        <div class="col-md-3">
          <label class="form-label"><?php echo h(__('t_0e1f4b2a21', 'تصنيف (اختياري)')); ?></label>
          <select class="form-select" name="category_id">
            <option value="0">—</option>
            <?php foreach ($cats as $c): ?>
              <option value="<?php echo (int)$c['id']; ?>"><?php echo h((string)$c['name']); ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-md-3">
          <label class="form-label"><?php echo h(__('t_3b9cf1f4b5', 'الفاصل (دقائق)')); ?></label>
          <input class="form-control" type="number" name="fetch_interval_minutes" value="60" min="5">
        </div>
        <div class="col-md-3 d-flex align-items-end">
          <div class="form-check">
            <input class="form-check-input" type="checkbox" name="is_active" id="is_active" checked>
            <label class="form-check-label" for="is_active"><?php echo h(__('t_4d3f2e1b8a', 'مفعل')); ?></label>
          </div>
        </div>
        <div class="col-md-6 d-flex align-items-end">
          <button class="btn btn-primary" type="submit"><?php echo h(__('t_6b0f1fd3b5', 'حفظ')); ?></button>
        </div>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="card-header"><strong><?php echo h(__('t_8e4c6e41c2', 'المصادر')); ?></strong></div>
    <div class="card-body">
      <div class="table-responsive">
        <table class="table table-sm align-middle">
          <thead>
            <tr>
              <th>ID</th>
              <th><?php echo h(__('t_2b778c7a76', 'الاسم')); ?></th>
              <th><?php echo h(__('t_2e1d14fb67', 'الرابط')); ?></th>
              <th><?php echo h(__('t_3b9cf1f4b5', 'الفاصل')); ?></th>
              <th><?php echo h(__('t_4d3f2e1b8a', 'الحالة')); ?></th>
              <th><?php echo h(__('t_b8cde2321a', 'آخر جلب')); ?></th>
              <th></th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($feeds as $f): ?>
            <?php
              $fid = (int)($f['id'] ?? 0);
              $active = (int)($f['is_active'] ?? 0) === 1;
            ?>
            <tr>
              <td><?php echo $fid; ?></td>
              <td><?php echo h((string)($f['name'] ?? '')); ?></td>
              <td style="max-width:420px;">
                <div class="text-truncate" title="<?php echo h((string)($f['url'] ?? '')); ?>"><?php echo h((string)($f['url'] ?? '')); ?></div>
              </td>
              <td><?php echo (int)($f['fetch_interval_minutes'] ?? 60); ?>m</td>
              <td><?php echo $active ? '<span class="badge bg-success">ON</span>' : '<span class="badge bg-secondary">OFF</span>'; ?></td>
              <td><?php echo h((string)($f['last_fetched_at'] ?? '')); ?></td>
              <td class="text-end">
                <div class="btn-group btn-group-sm">
                  <form method="post" class="d-inline">
                    <input type="hidden" name="_token" value="<?php echo h($csrfToken); ?>">
                    <input type="hidden" name="action" value="toggle">
                    <input type="hidden" name="id" value="<?php echo $fid; ?>">
                    <input type="hidden" name="to" value="<?php echo $active ? 0 : 1; ?>">
                    <button type="submit" class="btn btn-outline-secondary"><?php echo $active ? 'إيقاف' : 'تفعيل'; ?></button>
                  </form>
                  <form method="post" class="d-inline">
                    <input type="hidden" name="_token" value="<?php echo h($csrfToken); ?>">
                    <input type="hidden" name="action" value="test">
                    <input type="hidden" name="id" value="<?php echo $fid; ?>">
                    <button type="submit" class="btn btn-outline-info">Test</button>
                  </form>
                  <form method="post" class="d-inline" onsubmit="return confirm('حذف المصدر؟');">
                    <input type="hidden" name="_token" value="<?php echo h($csrfToken); ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?php echo $fid; ?>">
                    <button type="submit" class="btn btn-outline-danger">حذف</button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../layout/footer.php'; ?>
