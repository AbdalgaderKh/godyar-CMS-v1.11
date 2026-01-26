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

$currentPage = 'plugin_reader_questions';

// Ensure table exists (lazy)
$pdo->exec("
    CREATE TABLE IF NOT EXISTS reader_questions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        news_id INT NOT NULL,
        user_id INT NULL,
        author_name VARCHAR(150) NULL,
        author_email VARCHAR(190) NULL,
        question TEXT NOT NULL,
        answer TEXT NULL,
        status ENUM('pending','approved','answered','spam') NOT NULL DEFAULT 'pending',
        ip VARCHAR(64) NULL,
        user_agent VARCHAR(255) NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        answered_at DATETIME NULL,
        INDEX idx_news_status (news_id, status),
        INDEX idx_created_at (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

$status = (string)($_GET['status'] ?? 'pending');
$allowed = ['pending','approved','answered','spam'];
if (!in_array($status, $allowed, true)) $status = 'pending';

$flash = null;

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
                    $st = $pdo->prepare("UPDATE reader_questions SET status='approved' WHERE id=?");
                    $st->execute([$id]);
                    $flash = ['type' => 'success', 'msg' => 'تمت الموافقة على السؤال.'];
                } elseif ($action === 'spam') {
                    $st = $pdo->prepare("UPDATE reader_questions SET status='spam' WHERE id=?");
                    $st->execute([$id]);
                    $flash = ['type' => 'warning', 'msg' => 'تم تعليم السؤال كسبام.'];
                } elseif ($action === 'pending') {
                    $st = $pdo->prepare("UPDATE reader_questions SET status='pending' WHERE id=?");
                    $st->execute([$id]);
                    $flash = ['type' => 'info', 'msg' => 'تمت الإعادة إلى قيد المراجعة.'];
                } elseif ($action === 'delete') {
                    $st = $pdo->prepare("DELETE FROM reader_questions WHERE id=?");
                    $st->execute([$id]);
                    $flash = ['type' => 'success', 'msg' => 'تم الحذف.'];
                } elseif ($action === 'answer') {
                    $answer = trim((string)($_POST['answer'] ?? ''));
                    $answer = str_replace(["\r\n", "\r"], "\n", $answer);
                    $answer = strip_tags($answer);
                    $answer = preg_replace('~[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+~u', '', (string)$answer);
                    $answer = trim((string)$answer);

                    if ($answer === '') {
                        $flash = ['type' => 'danger', 'msg' => 'الإجابة مطلوبة.'];
                    } else {
                        $st = $pdo->prepare("UPDATE reader_questions
                                             SET answer=?, status='answered', answered_at=NOW()
                                             WHERE id=?");
                        $st->execute([$answer, $id]);
                        $flash = ['type' => 'success', 'msg' => 'تم حفظ الإجابة ونشرها.'];
                    }
                }
            } catch (Throwable $e) {
                error_log('[Admin ReaderQuestions] action failed: ' . $e->getMessage());
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
    $st = $pdo->prepare("SELECT COUNT(*) FROM reader_questions WHERE status=?");
    $st->execute([$status]);
    $total = (int)$st->fetchColumn();
} catch (Throwable $e) {
    $total = 0;
}

$rows = [];
try {
    $st = $pdo->prepare("SELECT id, news_id, author_name, author_email, question, answer, status, ip, created_at, answered_at
                         FROM reader_questions
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
      <h1 class="admin-page__title">أسئلة القرّاء</h1>
      <div class="admin-page__sub">إدارة الأسئلة والرد عليها عبر الإضافة (نص فقط + مراجعة).</div>
    </div>
    <div class="admin-page__actions">
      <a class="btn btn-outline-secondary" href="?status=pending">قيد المراجعة</a>
      <a class="btn btn-outline-secondary" href="?status=approved">المعتمدة</a>
      <a class="btn btn-outline-secondary" href="?status=answered">المجاب عنها</a>
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
            <th>السؤال</th>
            <th>الإجابة</th>
            <th>IP</th>
            <th>التاريخ</th>
            <th>إجراءات</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$rows): ?>
            <tr><td colspan="8" class="text-muted">لا توجد عناصر.</td></tr>
          <?php else: ?>
            <?php foreach ($rows as $r): ?>
              <tr>
                <td><?php echo (int)$r['id']; ?></td>
                <td><?php echo (int)$r['news_id']; ?></td>
                <td>
                  <div><?php echo h((string)($r['author_name'] ?? 'زائر')); ?></div>
                  <?php if (!empty($r['author_email'])): ?><small class="text-muted"><?php echo h((string)$r['author_email']); ?></small><?php endif; ?>
                </td>
                <td style="max-width:420px; white-space:pre-wrap;"><?php echo h((string)($r['question'] ?? '')); ?></td>
                <td style="max-width:420px;">
                  <?php if (!empty($r['answer'])): ?>
                    <div style="white-space:pre-wrap;"><?php echo h((string)$r['answer']); ?></div>
                    <small class="text-muted">تمت الإجابة: <?php echo h((string)($r['answered_at'] ?? '')); ?></small>
                  <?php else: ?>
                    <form method="post">
                      <?php csrf_field(); ?>
                      <input type="hidden" name="id" value="<?php echo (int)$r['id']; ?>">
                      <input type="hidden" name="action" value="answer">
                      <textarea class="form-control form-control-sm" name="answer" rows="3" maxlength="2000" placeholder="اكتب الرد (نص فقط)"></textarea>
                      <button class="btn btn-sm btn-primary mt-1" type="submit">حفظ ونشر الرد</button>
                    </form>
                  <?php endif; ?>
                </td>
                <td><small class="text-muted"><?php echo h((string)($r['ip'] ?? '')); ?></small></td>
                <td><small class="text-muted"><?php echo h((string)($r['created_at'] ?? '')); ?></small></td>
                <td>
                  <form method="post" class="d-flex gap-1 flex-wrap">
                    <?php csrf_field(); ?>
                    <input type="hidden" name="id" value="<?php echo (int)$r['id']; ?>">
                    <?php if ($status !== 'approved' && $status !== 'answered'): ?>
                      <button class="btn btn-sm btn-success" name="action" value="approve" type="submit">موافقة</button>
                    <?php endif; ?>
                    <?php if ($status !== 'pending'): ?>
                      <button class="btn btn-sm btn-secondary" name="action" value="pending" type="submit">مراجعة</button>
                    <?php endif; ?>
                    <?php if ($status !== 'spam'): ?>
                      <button class="btn btn-sm btn-warning" name="action" value="spam" type="submit">سبام</button>
                    <?php endif; ?>
                    <button class="btn btn-sm btn-danger" name="action" value="delete" type="submit" onclick="return confirm('حذف السؤال نهائيًا؟')">حذف</button>
                  </form>
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
