<?php
declare(strict_types=1);

require_once __DIR__ . '/../../_admin_guard.php';
require_once __DIR__ . '/../../../includes/bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    gdy_session_start();
}

$pdo = gdy_pdo_safe();
if (($pdo instanceof PDO) === false) {
    http_response_code(500);
    exit('DB unavailable');
}
// عمود موجود؟
function gdy_admin_col_exists(PDO $pdo, string $table, string $col): bool {
    static $c = [];
    $k = $table.'.'.$col;
    if (array_key_exists($k, $c)) return (bool)$c[$k];
    try {
        $db = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
        if ($db === '') return $c[$k] = false;
        $st = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND COLUMN_NAME=?");
        $st->execute([$db,$table,$col]);
        return $c[$k] = ((int)$st->fetchColumn() > 0);
    } catch (Throwable $e) { return $c[$k] = false; }
}

$hasAuthorName = gdy_admin_col_exists($pdo, 'comments', 'author_name');
$hasAuthorEmail = gdy_admin_col_exists($pdo, 'comments', 'author_email');
$hasParentId = gdy_admin_col_exists($pdo, 'comments', 'parent_id');
$hasIsAdmin = gdy_admin_col_exists($pdo, 'comments', 'is_admin');


// تمييز الصفحة في القائمة
$currentPage = 'plugin_comments';

// Ensure table exists (lazy)
$pdo->exec("
    CREATE TABLE IF NOT EXISTS comments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        news_id INT NOT NULL,
        user_id INT NULL,
        author_name VARCHAR(150) NULL,
        author_email VARCHAR(190) NULL,
        body TEXT NOT NULL,
        status ENUM('pending','approved','spam') NOT NULL DEFAULT 'pending',
        ip VARCHAR(64) NULL,
        user_agent VARCHAR(255) NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_news_status (news_id, status),
        INDEX idx_created_at (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

$status = (string)($_GET['status'] ?? 'pending');
$allowed = ['pending','approved','spam'];
if (!in_array($status, $allowed, true)) $status = 'pending';

$flash = null;

// Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = (string)($_POST['csrf_token'] ?? '');
    if (!verify_csrf_token($token)) {
        $flash = ['type' => 'danger', 'msg' => 'CSRF فشل.'];
    } else {
        $id = (int)($_POST['id'] ?? 0);
        $action = (string)($_POST['action'] ?? '');
        if ($id > 0) {
            try {
                if ($action === 'approve') {
                    $st = $pdo->prepare("UPDATE comments SET status='approved' WHERE id=?");
                    $st->execute([$id]);
                    $flash = ['type' => 'success', 'msg' => 'تمت الموافقة.'];
                } elseif ($action === 'spam') {
                    $st = $pdo->prepare("UPDATE comments SET status='spam' WHERE id=?");
                    $st->execute([$id]);
                    $flash = ['type' => 'warning', 'msg' => 'تم تعليم التعليق كسبام.'];
                } elseif ($action === 'pending') {
                    $st = $pdo->prepare("UPDATE comments SET status='pending' WHERE id=?");
                    $st->execute([$id]);
                    $flash = ['type' => 'info', 'msg' => 'تمت الإعادة إلى قيد المراجعة.'];

                } elseif ($action === 'admin_reply') {
                    $parentId = (int)($_POST['parent_id'] ?? 0);
                    $body = trim(strip_tags((string)($_POST['body'] ?? '')));
                    if (mb_strlen($body,'UTF-8') < 2) {
                        $flash = ['type' => 'danger', 'msg' => 'نص الرد غير صالح.'];
                    } else {
                        if (mb_strlen($body,'UTF-8') > 2000) $body = mb_substr($body,0,2000,'UTF-8');
                        $fields = ['news_id','body','status','ip','user_agent','created_at'];
                        $vals = [
                            (int)($_POST['news_id'] ?? 0),
                            $body,
                            'approved',
                            (string)($_SERVER['REMOTE_ADDR'] ?? ''),
                            (string)($_SERVER['HTTP_USER_AGENT'] ?? ''),
                            date('Y-m-d H:i:s'),
                        ];
                        if ($hasParentId) { $fields[]='parent_id'; $vals[] = ($parentId>0?$parentId:null); }
                        if ($hasAuthorName) { $fields[]='author_name'; $vals[]='الإدارة'; }
                        if ($hasAuthorEmail) { $fields[]='author_email'; $vals[]=null; }
                        if ($hasIsAdmin) { $fields[]='is_admin'; $vals[]=1; }

                        $ph = implode(',', array_fill(0, count($fields), '?'));
                        $sql = "INSERT INTO comments (" . implode(',', $fields) . ") VALUES ($ph)";
                        $st = $pdo->prepare($sql);
                        $st->execute($vals);
                        $flash = ['type' => 'success', 'msg' => 'تم نشر رد الإدارة.'];
                    }

                } elseif ($action === 'delete') {
                    $st = $pdo->prepare("DELETE FROM comments WHERE id=?");
                    $st->execute([$id]);
                    $flash = ['type' => 'success', 'msg' => 'تم الحذف.'];
                }
            } catch (Throwable $e) {
                error_log('[Admin Comments] action failed: ' . $e->getMessage());
                $flash = ['type' => 'danger', 'msg' => 'فشل تنفيذ العملية.'];
            }
        }
    }
}

// Pagination
$page = (int)($_GET['page'] ?? 1);
if ($page < 1) $page = 1;
$perPage = 25;
$offset = ($page - 1) * $perPage;

$total = 0;
try {
    $st = $pdo->prepare("SELECT COUNT(*) FROM comments WHERE status=?");
    $st->execute([$status]);
    $total = (int)$st->fetchColumn();
} catch (Throwable $e) {
    $total = 0;
}

$rows = [];
try {
    $select = ['id','news_id','body','status','ip','created_at'];
    if ($hasParentId) $select[] = 'parent_id'; else $select[] = 'NULL AS parent_id';
    if ($hasAuthorName) $select[] = 'author_name'; else $select[] = "NULL AS author_name";
    if ($hasAuthorEmail) $select[] = 'author_email'; else $select[] = "NULL AS author_email";
    if ($hasIsAdmin) $select[] = 'is_admin'; else $select[] = "0 AS is_admin";
    $st = $pdo->prepare("SELECT " . implode(',', $select) . " FROM comments
                         WHERE status=?
                         ORDER BY id DESC
                         LIMIT {$perPage} OFFSET {$offset}");
    $st->execute([$status]);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
    $rows = [];
}

require_once __DIR__ . '/../../layout/header.php';
require_once __DIR__ . '/../../layout/sidebar.php';
?>
<div class="admin-content">
  <div class="admin-page__header">
    <div>
      <h1 class="admin-page__title">التعليقات</h1>
      <div class="admin-page__sub">إدارة التعليقات عبر الإضافة (نص فقط + مراجعة).</div>
    </div>
    <div class="admin-page__actions">
      <a class="btn btn-outline-secondary" href="?status=pending">قيد المراجعة</a>
      <a class="btn btn-outline-secondary" href="?status=approved">المعتمدة</a>
      <a class="btn btn-outline-secondary" href="?status=spam">سبام</a>
    </div>
  </div>

  <?php if ($flash): ?>
    <div class="alert alert-<?php echo h($flash['type']); ?>"><?php echo h($flash['msg']); ?></div>
  <?php endif; ?>

  <div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
      <strong>الحالة: <?php echo h($status); ?></strong>
      <small class="text-muted">الإجمالي: <?php echo (int)$total; ?></small>
    </div>
    <div class="table-responsive">
      <table class="table table-striped mb-0">
        <thead>
          <tr>
            <th>#</th>
            <th>خبر</th>
            <th>الاسم</th>
            <th>النص</th>
            <th>IP</th>
            <th>التاريخ</th>
            <th>إجراءات</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$rows): ?>
            <tr><td colspan="7" class="text-muted">لا توجد عناصر.</td></tr>
          <?php else: ?>
            <?php foreach ($rows as $r): ?>
              <tr>
                <td><?php echo (int)$r['id']; ?></td>
                <td><?php echo (int)$r['news_id']; ?></td>
                <td>
                  <div><?php echo h((string)($r['author_name'] ?? 'زائر')); ?></div>
                  <?php if (!empty($r['author_email'])): ?><small class="text-muted"><?php echo h((string)$r['author_email']); ?></small><?php endif; ?>
                </td>
                <td style="max-width:520px; white-space:pre-wrap;"><?php echo h((string)($r['body'] ?? '')); ?></td>
                <td><small class="text-muted"><?php echo h((string)($r['ip'] ?? '')); ?></small></td>
                <td><small class="text-muted"><?php echo h((string)($r['created_at'] ?? '')); ?></small></td>
                <td>
                  <form method="post" class="d-flex gap-1 flex-wrap">
                    <?php csrf_field(); ?>
                    <input type="hidden" name="id" value="<?php echo (int)$r['id']; ?>">
                    <?php if ($status !== 'approved'): ?>
                      <button class="btn btn-sm btn-success" name="action" value="approve" type="submit">موافقة</button>
                    <?php endif; ?>
                    <?php if ($status !== 'pending'): ?>
                      <button class="btn btn-sm btn-secondary" name="action" value="pending" type="submit">مراجعة</button>
                    <?php endif; ?>
                    <?php if ($status !== 'spam'): ?>
                      <button class="btn btn-sm btn-warning" name="action" value="spam" type="submit">سبام</button>
                    <?php endif; ?>
                    <button class="btn btn-sm btn-danger" name="action" value="delete" type="submit" onclick="return confirm('حذف التعليق نهائيًا؟')">حذف</button>
                  </form>
                  <details class="mt-2">
                    <summary style="cursor:pointer;font-weight:700">رد الإدارة</summary>
                    <form method="post" class="mt-2">
                      <?php csrf_field(); ?>
                      <input type="hidden" name="action" value="admin_reply">
                      <input type="hidden" name="news_id" value="<?php echo (int)$r['news_id']; ?>">
                      <input type="hidden" name="parent_id" value="<?php echo (int)$r['id']; ?>">
                      <textarea class="form-control form-control-sm" name="body" rows="2" maxlength="2000" required placeholder="اكتب رد الإدارة (نص فقط)"></textarea>
                      <button class="btn btn-sm btn-primary mt-2" type="submit">نشر الرد</button>
                    </form>
                  </details>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <?php
    $pages = (int)ceil($total / $perPage);
    if ($pages < 1) $pages = 1;
  ?>
  <?php if ($pages > 1): ?>
    <nav class="mt-3">
      <ul class="pagination">
        <?php for ($i=1; $i<=$pages; $i++): ?>
          <li class="page-item <?php echo $i===$page ? 'active' : ''; ?>">
            <a class="page-link" href="?status=<?php echo h($status); ?>&page=<?php echo (int)$i; ?>"><?php echo (int)$i; ?></a>
          </li>
        <?php endfor; ?>
      </ul>
    </nav>
  <?php endif; ?>

</div>
<?php require_once __DIR__ . '/../../layout/footer.php'; ?>
