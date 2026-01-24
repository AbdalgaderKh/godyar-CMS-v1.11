<?php
declare(strict_types=1);

require_once __DIR__ . '/../_admin_guard.php';
require_once __DIR__ . '/../../includes/bootstrap.php';

use Godyar\Auth;

$pageTitle   = 'سجل النشاط الإداري';
$currentPage = 'logs';

if (!function_exists('h')) {
    function h($v): string
    {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}

// Optional permission check
try {
    if (class_exists(Auth::class) && method_exists(Auth::class, 'requirePermission')) {
        Auth::requirePermission('manage_security');
    }
} catch (Throwable $e) {
    // Fallback: allow admin guard to handle enforcement
}

$pdo = function_exists('gdy_pdo_safe') ? gdy_pdo_safe() : null;
if (!($pdo instanceof PDO)) {
    http_response_code(500);
    echo 'Database connection not available.';
    exit;
}

$filterUserId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
$filterAction = isset($_GET['action']) ? trim((string)$_GET['action']) : '';

$users = [];
try {
    $st = $pdo->query('SELECT id, username, name, email FROM users ORDER BY id DESC LIMIT 500');
    $users = $st ? $st->fetchAll(PDO::FETCH_ASSOC) : [];
} catch (Throwable $e) {
    $users = [];
}

$where = [];
$params = [];
if ($filterUserId > 0) {
    $where[] = 'al.user_id = :uid';
    $params[':uid'] = $filterUserId;
}
if ($filterAction !== '') {
    $where[] = 'al.action = :act';
    $params[':act'] = $filterAction;
}
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$logs = [];
try {
    $sql = "
        SELECT
          al.id,
          al.user_id,
          al.action,
          al.entity_type,
          al.entity_id,
          al.ip_address,
          al.user_agent,
          al.details,
          al.created_at,
          u.username,
          u.name,
          u.email
        FROM admin_logs al
        LEFT JOIN users u ON u.id = al.user_id
        $whereSql
        ORDER BY al.id DESC
        LIMIT 200
    ";
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $logs = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
    $logs = [];
}

function format_details(?string $details): string
{
    if (!$details) return '';
    $decoded = json_decode($details, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
        $out = [];
        foreach ($decoded as $k => $v) {
            $k = (string)$k;
            if (is_scalar($v)) {
                $out[] = '<strong>' . h($k) . ':</strong> ' . h((string)$v);
            } else {
                $out[] = '<strong>' . h($k) . ':</strong> ' . h(json_encode($v, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT));
            }
        }
        return implode('<br>', $out);
    }
    return nl2br(h($details));
}

require_once __DIR__ . '/../layout/app_start.php';
?>

<div class="card mb-3">
  <div class="card-body">
    <h1 class="h5 mb-2">سجل النشاط الإداري</h1>
    <div class="text-muted small">يعرض أحدث الأحداث (تسجيل الدخول، تعديل الإعدادات، إدارة المحتوى، وغيرها).</div>
  </div>
</div>

<div class="card mb-3">
  <div class="card-body">
    <form method="get" class="row g-2 align-items-end">
      <div class="col-md-5">
        <label class="form-label">المستخدم</label>
        <select name="user_id" class="form-select">
          <option value="0">الكل</option>
          <?php foreach ($users as $u): ?>
            <?php
              $id = (int)($u['id'] ?? 0);
              $label = (string)($u['name'] ?? '') ?: (string)($u['username'] ?? '') ?: ('User #' . $id);
            ?>
            <option value="<?php echo $id; ?>" <?php echo $filterUserId === $id ? 'selected' : ''; ?>>
              <?php echo h($label); ?> (ID: <?php echo $id; ?>)
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-md-4">
        <label class="form-label">الإجراء (Action)</label>
        <input type="text" name="action" value="<?php echo h($filterAction); ?>" class="form-control" placeholder="login, update_settings, ...">
      </div>

      <div class="col-md-3 d-flex gap-2">
        <button class="btn btn-primary w-100" type="submit">تطبيق</button>
        <a class="btn btn-outline-secondary w-100" href="admin_logs.php">إلغاء</a>
      </div>
    </form>
  </div>
</div>

<div class="card">
  <div class="card-body">
    <div class="table-responsive">
      <table class="table table-sm align-middle">
        <thead>
          <tr>
            <th>#</th>
            <th>الوقت</th>
            <th>المستخدم</th>
            <th>الإجراء</th>
            <th>الكيان</th>
            <th>IP</th>
            <th>تفاصيل</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$logs): ?>
            <tr><td colspan="7" class="text-muted">لا توجد سجلات لعرضها.</td></tr>
          <?php else: ?>
            <?php foreach ($logs as $row): ?>
              <?php
                $uid = (int)($row['user_id'] ?? 0);
                $uName = (string)($row['name'] ?? '') ?: (string)($row['username'] ?? '') ?: ($uid ? ('User #' . $uid) : '—');
                $when = (string)($row['created_at'] ?? '');
              ?>
              <tr>
                <td><?php echo (int)($row['id'] ?? 0); ?></td>
                <td class="text-muted small"><?php echo h($when); ?></td>
                <td><?php echo h($uName); ?></td>
                <td><code><?php echo h((string)($row['action'] ?? '')); ?></code></td>
                <td class="text-muted small"><?php echo h((string)($row['entity_type'] ?? '')); ?><?php echo ($row['entity_id'] ?? '') !== '' ? (' #' . (int)$row['entity_id']) : ''; ?></td>
                <td class="text-muted small"><?php echo h((string)($row['ip_address'] ?? '')); ?></td>
                <td class="small"><?php echo format_details($row['details'] ?? null); ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../layout/app_end.php';
