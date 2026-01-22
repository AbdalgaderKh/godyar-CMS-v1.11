<?php
declare(strict_types=1);

require_once __DIR__ . '/../_admin_guard.php';
require_once __DIR__ . '/../../includes/bootstrap.php';

$pageTitle   = __('t_f1e9d2a3a3', 'إدارة جماعية للأخبار');
$currentPage = 'news';

if (!function_exists('h')) {
    function h($v): string
    {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}

/** @var PDO|null $pdo */
$pdo = function_exists('gdy_pdo_safe') ? gdy_pdo_safe() : null;
if (!($pdo instanceof PDO)) {
    http_response_code(500);
    die('Database connection not available.');
}

// CSRF support (if present in the project)
if (session_status() !== PHP_SESSION_ACTIVE && function_exists('gdy_session_start')) {
    gdy_session_start();
} elseif (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$csrfToken = $_SESSION['news_bulk_csrf'] ?? '';
if (!is_string($csrfToken) || $csrfToken === '') {
    $csrfToken = bin2hex(random_bytes(16));
    $_SESSION['news_bulk_csrf'] = $csrfToken;
}

function news_columns(PDO $pdo): array
{
    try {
        $cols = [];
        $st = $pdo->query('DESCRIBE news');
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $cols[(string)$r['Field']] = true;
        }
        return $cols;
    } catch (Throwable $e) {
        return [];
    }
}

function exec_in(PDO $pdo, string $sqlBase, array $ids, array $prefixParams = []): int
{
    if (!$ids) return 0;
    $updated = 0;
    foreach (array_chunk($ids, 200) as $chunk) {
        $in = implode(',', array_fill(0, count($chunk), '?'));
        $sql = str_replace('%%IN%%', $in, $sqlBase);
        $st = $pdo->prepare($sql);
        $ok = $st->execute(array_merge($prefixParams, $chunk));
        if ($ok) {
            $updated += count($chunk);
        }
    }
    return $updated;
}

function str_limit(string $s, int $n): string
{
    $s = trim($s);
    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
        return mb_strlen($s, 'UTF-8') <= $n ? $s : (mb_substr($s, 0, $n - 1, 'UTF-8') . '…');
    }
    return strlen($s) <= $n ? $s : (substr($s, 0, $n - 1) . '…');
}

function slugify(string $s): string
{
    $s = trim(mb_strtolower($s, 'UTF-8'));
    $s = preg_replace('~[^\pL\pN]+~u', '-', $s) ?? '';
    $s = trim($s, '-');
    $s = preg_replace('~[^a-z0-9\-\_]+~i', '', $s) ?? '';
    return $s !== '' ? $s : 'news';
}

$cols = news_columns($pdo);

$flashSuccess = '';
$flashError   = '';

// Handle POST actions
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $token = (string)($_POST['_token'] ?? '');
    if (!hash_equals($csrfToken, $token)) {
        $flashError = __('t_7339ca256b', 'انتهت صلاحية النموذج، يرجى إعادة تحميل الصفحة ثم المحاولة مرة أخرى.');
    } else {
        $action = (string)($_POST['action'] ?? '');
        $ids = $_POST['ids'] ?? [];
        if (!is_array($ids)) $ids = [];
        $idList = array_values(array_filter(array_map('intval', $ids), static fn($v) => $v > 0));

        try {
            if (!$idList) {
                throw new RuntimeException(__('t_19a9ea0660', 'لم يتم تحديد أي عناصر.'));
            }

            if ($action === 'status') {
                $to = (string)($_POST['to_status'] ?? '');
                $allowed = ['published', 'draft', 'pending', 'approved', 'archived'];
                if (!in_array($to, $allowed, true)) {
                    throw new RuntimeException(__('t_3742196a8c', 'إجراء غير صالح.'));
                }
                $sql = 'UPDATE news SET status=? WHERE id IN (%%IN%%)';
                $n = exec_in($pdo, $sql, $idList, [$to]);
                $flashSuccess = 'تم تحديث الحالة لـ ' . $n . ' عنصر.';

            } elseif ($action === 'delete') {
                if (isset($cols['deleted_at'])) {
                    $sql = 'UPDATE news SET deleted_at=NOW() WHERE id IN (%%IN%%)';
                    $n = exec_in($pdo, $sql, $idList);
                    $flashSuccess = 'تم الحذف (Soft Delete) لـ ' . $n . ' عنصر.';
                } else {
                    $sql = 'DELETE FROM news WHERE id IN (%%IN%%)';
                    $n = exec_in($pdo, $sql, $idList);
                    $flashSuccess = 'تم حذف ' . $n . ' عنصر.';
                }

            } elseif ($action === 'set_category') {
                $cat = (int)($_POST['category_id'] ?? 0);
                if ($cat <= 0 || !isset($cols['category_id'])) {
                    throw new RuntimeException('فئة غير صالحة.');
                }
                $sql = 'UPDATE news SET category_id=? WHERE id IN (%%IN%%)';
                $n = exec_in($pdo, $sql, $idList, [$cat]);
                $flashSuccess = 'تم تحديث الفئة لـ ' . $n . ' عنصر.';

            } elseif ($action === 'duplicate') {
                // Duplicate rows one by one (schema-flexible)
                $duplicated = 0;
                foreach ($idList as $id) {
                    $st = $pdo->prepare('SELECT * FROM news WHERE id=? LIMIT 1');
                    $st->execute([$id]);
                    $row = $st->fetch(PDO::FETCH_ASSOC);
                    if (!$row) continue;

                    $now = date('Y-m-d H:i:s');
                    $title = (string)($row['title'] ?? '');
                    $slug  = (string)($row['slug'] ?? '');

                    $newTitle = trim(($title !== '' ? $title : 'خبر') . ' (نسخة)');
                    $suffix = '-copy-' . date('ymdHis') . '-' . random_int(100, 999);
                    $newSlug = ($slug !== '' ? $slug : slugify($newTitle)) . $suffix;

                    // Build insert using available columns
                    $fields = [];
                    $params = [];
                    foreach ($row as $k => $v) {
                        $k = (string)$k;
                        if ($k === 'id') continue;
                        if (!isset($cols[$k])) continue;

                        // overrides
                        if ($k === 'title') $v = $newTitle;
                        if ($k === 'slug')  $v = $newSlug;
                        if ($k === 'status') $v = 'draft';
                        if (in_array($k, ['views','view_count'], true)) $v = 0;
                        if (in_array($k, ['published_at','publish_at','deleted_at'], true)) $v = null;
                        if (in_array($k, ['created_at','updated_at'], true)) $v = $now;

                        $fields[] = $k;
                        $params[':' . $k] = $v;
                    }

                    if (!$fields) continue;
                    $sql = 'INSERT INTO news (' . implode(',', $fields) . ') VALUES (' . implode(',', array_map(fn($f) => ':' . $f, $fields)) . ')';
                    $pdo->prepare($sql)->execute($params);
                    $duplicated++;
                }
                $flashSuccess = 'تم نسخ ' . $duplicated . ' عنصر.';

            } else {
                throw new RuntimeException(__('t_3742196a8c', 'إجراء غير صالح.'));
            }

        } catch (Throwable $e) {
            $flashError = $e->getMessage();
        }
    }
}

// Load categories (optional)
$categories = [];
try {
    $st = $pdo->query('SELECT id, name FROM categories ORDER BY name ASC');
    $categories = $st ? $st->fetchAll(PDO::FETCH_ASSOC) : [];
} catch (Throwable $e) {
    $categories = [];
}

// Load recent news (for selection UI)
$newsRows = [];
try {
    $newsRows = $pdo->query('SELECT id, title, status, created_at FROM news ORDER BY id DESC LIMIT 100')->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
    $newsRows = [];
}

require_once __DIR__ . '/../layout/app_start.php';
?>

<?php if ($flashSuccess): ?>
  <div class="alert alert-success"><?= h($flashSuccess) ?></div>
<?php endif; ?>
<?php if ($flashError): ?>
  <div class="alert alert-danger"><?= h($flashError) ?></div>
<?php endif; ?>

<div class="card p-3">
  <div class="d-flex justify-content-between align-items-center mb-2">
    <h5 class="mb-0">إدارة جماعية</h5>
    <a class="btn btn-sm btn-outline-secondary" href="index.php">العودة لقائمة الأخبار</a>
  </div>

  <form method="post">
    <input type="hidden" name="_token" value="<?= h($csrfToken) ?>">

    <div class="row g-2 align-items-end mb-3">
      <div class="col-md-4">
        <label class="form-label">الإجراء</label>
        <select class="form-select" name="action" required>
          <option value="status">تغيير الحالة</option>
          <option value="set_category">تغيير الفئة</option>
          <option value="duplicate">نسخ</option>
          <option value="delete">حذف</option>
        </select>
      </div>

      <div class="col-md-4">
        <label class="form-label">إلى حالة</label>
        <select class="form-select" name="to_status">
          <option value="published">منشور</option>
          <option value="draft">مسودة</option>
          <option value="pending">قيد المراجعة</option>
          <option value="approved">معتمد</option>
          <option value="archived">مؤرشف</option>
        </select>
        <div class="form-text">يستخدم فقط عند اختيار "تغيير الحالة".</div>
      </div>

      <div class="col-md-4">
        <label class="form-label">الفئة</label>
        <select class="form-select" name="category_id">
          <option value="0">—</option>
          <?php foreach ($categories as $c): ?>
            <option value="<?= (int)($c['id'] ?? 0) ?>"><?= h((string)($c['name'] ?? '')) ?></option>
          <?php endforeach; ?>
        </select>
        <div class="form-text">يستخدم فقط عند اختيار "تغيير الفئة".</div>
      </div>
    </div>

    <div class="table-responsive">
      <table class="table table-sm align-middle">
        <thead>
          <tr>
            <th style="width:34px"><input type="checkbox" onclick="document.querySelectorAll('input[name=\"ids[]\"]').forEach(cb=>cb.checked=this.checked)"></th>
            <th>العنوان</th>
            <th style="width:110px">الحالة</th>
            <th style="width:140px">التاريخ</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($newsRows as $n): ?>
            <?php
              $id = (int)($n['id'] ?? 0);
              $title = (string)($n['title'] ?? '');
              $status = (string)($n['status'] ?? '');
              $date = !empty($n['created_at']) ? date('Y-m-d', strtotime((string)$n['created_at'])) : '';
            ?>
            <tr>
              <td><input type="checkbox" name="ids[]" value="<?= $id ?>"></td>
              <td><?= h(str_limit($title, 90)) ?></td>
              <td><span class="badge bg-secondary"><?= h($status) ?></span></td>
              <td><?= h($date) ?></td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$newsRows): ?>
            <tr><td colspan="4" class="text-muted">لا توجد بيانات.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <div class="d-flex justify-content-end">
      <button class="btn btn-primary" type="submit">تطبيق</button>
    </div>
  </form>
</div>

<?php
require_once __DIR__ . '/../layout/app_end.php';
