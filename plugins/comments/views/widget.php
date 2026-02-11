<?php
// plugins/comments/views/widget.php
declare(strict_types=1);

/**
 * Comments widget (threading كامل) - يعمل بدون JavaScript
 * - إنشاء تعليق/رد (POST) مع CSRF + rate limit
 * - عرض التعليقات المعتمدة فقط (approved) مع شجرة ردود لا نهائية عبر parent_id
 * - تمييز رد الإدارة عبر is_admin=1 (Badge)
 */

// Helpers (آمنة من إعادة التعريف)
if (!function_exists('h')) {
    function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
}

if (!function_exists('gdy_comments_initials')) {
    function gdy_comments_initials(string $name): string {
        $name = trim($name);
        if ($name === '') return 'ز';
        $parts = preg_split('/\s+/u', $name) ?: [];
        $a = mb_substr($parts[0] ?? $name, 0, 1, 'UTF-8');
        $b = '';
        if (count($parts) > 1) $b = mb_substr($parts[1], 0, 1, 'UTF-8');
        $out = mb_strtoupper($a . $b, 'UTF-8');
        return $out !== '' ? $out : 'ز';
    }
}

// newsId متوقع من Plugin.php
$newsId = (int)($newsId ?? 0);
if ($newsId <= 0) return;

if (session_status() !== PHP_SESSION_ACTIVE) {
    if (function_exists('gdy_session_start')) {
        gdy_session_start();
    } else {
        @session_start();
    }
}

$pdo = function_exists('gdy_pdo_safe') ? gdy_pdo_safe() : null;

// CSRF (يستخدم نواة النظام إن وجدت، وإلا fallback محلي)
$localCsrfKey = 'gdy_front_csrf';
if (!isset($_SESSION[$localCsrfKey]) || !is_string($_SESSION[$localCsrfKey]) || $_SESSION[$localCsrfKey] === '') {
    $_SESSION[$localCsrfKey] = bin2hex(random_bytes(16));
}
$localCsrf = (string)$_SESSION[$localCsrfKey];

$csrf_field_html = '';
if (function_exists('csrf_field')) {
    // حقل النواة (قد يولد input باسم csrf_token)
    ob_start();
    csrf_field();
    $csrf_field_html = (string)ob_get_clean();
} else {
    $csrf_field_html = '<input type="hidden" name="csrf_token" value="' . h($localCsrf) . '">';
}

$verify_csrf = function(string $token) use ($localCsrf): bool {
    $ok = false;
    if (function_exists('verify_csrf_token')) {
        try { $ok = (bool)verify_csrf_token($token); } catch (Exception $e) { $ok = false; }
    }
    if ($ok) return true;
    // fallback محلي
    return $token !== '' && hash_equals($localCsrf, $token);
};

// rate limit بسيط بالـ IP (fallback)
$rate_limit = function(string $bucket, int $limit, int $windowSec): bool {
    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');

if (function_exists('gdy_rate_limit')) {
    try {
        return (bool)gdy_rate_limit($bucket, $ip, $limit, $windowSec);
    } catch (Exception $e) {
    }
}

$key = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $bucket . '_' . $ip);
$dir = sys_get_temp_dir() . '/gdy_rl';
if (!is_dir($dir)) @mkdir($dir, 0777, true);
    $file = $dir . '/' . $key . '.json';
    $now = time();
    $data = ['t' => $now, 'c' => 0];
    if (is_file($file)) {
        $raw = @file_get_contents($file);
        $j = is_string($raw) ? json_decode($raw, true) : null;
        if (is_array($j)) $data = array_merge($data, $j);
    }
    $t = (int)($data['t'] ?? $now);
    $c = (int)($data['c'] ?? 0);
    if ($now - $t >= $windowSec) { $t = $now; $c = 0; }
    $c++;
    $data = ['t'=>$t,'c'=>$c];
    @file_put_contents($file, json_encode($data));
    return $c <= $limit;
};

$columnExists = function(PDO $pdo, string $table, string $column): bool {
    static $cache = [];
    $key = $table . '.' . $column;
    if (array_key_exists($key, $cache)) return (bool)$cache[$key];
    try {
        $db = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
        if ($db === '') return $cache[$key] = false;
        $st = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND COLUMN_NAME=?");
        $st->execute([$db, $table, $column]);
        return $cache[$key] = ((int)$st->fetchColumn() > 0);
    } catch (Exception $e) {
        return $cache[$key] = false;
    }
};

$hasAuthorName = ($pdo instanceof PDO) ? $columnExists($pdo, 'comments', 'author_name') : false;
$hasAuthorEmail = ($pdo instanceof PDO) ? $columnExists($pdo, 'comments', 'author_email') : false;
$hasParentId   = ($pdo instanceof PDO) ? $columnExists($pdo, 'comments', 'parent_id') : false;
$hasIsAdmin    = ($pdo instanceof PDO) ? $columnExists($pdo, 'comments', 'is_admin') : false;

$flash = null;

// Handle submit (comment/reply)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['_gdy_comments_submit'])) {
    $token = (string)($_POST['csrf_token'] ?? $_POST['_token'] ?? '');
    if (!$verify_csrf($token)) {
        $flash = ['type'=>'danger','msg'=>'فشل التحقق الأمني. حدّث الصفحة وحاول مجددًا.'];
    } elseif (!$rate_limit('comments_create', 5, 60)) {
        $flash = ['type'=>'danger','msg'=>'محاولات كثيرة. حاول لاحقًا.'];
    } elseif (($pdo instanceof PDO) === false) {
        $flash = ['type'=>'danger','msg'=>'قاعدة البيانات غير متاحة.'];
    } else {
        $name  = trim((string)($_POST['author_name'] ?? ''));
        $email = trim((string)($_POST['author_email'] ?? ''));
        $body  = (string)($_POST['body'] ?? '');
        $body  = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $body ?? '') ?? '';
        $body  = trim(strip_tags($body));
        if ($body === '' || mb_strlen($body,'UTF-8') < 2) {
            $flash = ['type'=>'danger','msg'=>'اكتب تعليقًا صالحًا (نص فقط).'];
        } else {
            if (mb_strlen($body,'UTF-8') > 2000) $body = mb_substr($body,0,2000,'UTF-8');

            $parentId = 0;
            if ($hasParentId) $parentId = (int)($_POST['parent_id'] ?? 0);

            $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
            $ua = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');

            try {
                $fields = ['news_id', 'body', 'status', 'ip', 'user_agent', 'created_at'];
                $vals   = [$newsId, $body, 'pending', $ip, $ua, date('Y-m-d H:i:s')];

                if ($hasParentId) { $fields[] = 'parent_id'; $vals[] = ($parentId>0?$parentId:null); }
                if ($hasAuthorName) { $fields[] = 'author_name'; $vals[] = ($name!==''?$name:null); }
                if ($hasAuthorEmail) { $fields[] = 'author_email'; $vals[] = ($email!==''?$email:null); }
                if ($hasIsAdmin) { $fields[] = 'is_admin'; $vals[] = 0; }

                $ph = implode(',', array_fill(0, count($fields), '?'));
                $sql = "INSERT INTO comments (" . implode(',', $fields) . ") VALUES ($ph)";
                $st = $pdo->prepare($sql);
                $st->execute($vals);

                $flash = ['type'=>'success','msg'=>'تم إرسال تعليقك، وسيظهر بعد المراجعة.'];
            } catch (Exception $e) {
                error_log('[CommentsWidget] create: ' . $e->getMessage());
                $flash = ['type'=>'danger','msg'=>'تعذر حفظ التعليق.'];
            }
        }
    }
}

// Load approved comments
$approved = [];
if ($pdo instanceof PDO) {
    try {
        $select = ['id','news_id','body','created_at'];
        if ($hasParentId) $select[] = 'parent_id';
        if ($hasAuthorName) $select[] = 'author_name'; else $select[] = "NULL AS author_name";
        if ($hasIsAdmin) $select[] = 'is_admin'; else $select[] = "0 AS is_admin";

        $sql = "SELECT " . implode(',', $select) . " FROM comments WHERE news_id=? AND status='approved' ORDER BY created_at ASC, id ASC LIMIT 500";
        $st = $pdo->prepare($sql);
        $st->execute([$newsId]);
        $approved = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Exception $e) {
        error_log('[CommentsWidget] list: ' . $e->getMessage());
        $approved = [];
    }
}

// Build tree (threading كامل)
$byId = [];
$children = [];
foreach ($approved as $row) {
    $id = (int)($row['id'] ?? 0);
    if ($id <= 0) continue;
    $byId[$id] = $row;
}
foreach ($byId as $id => $row) {
    $pid = (int)($row['parent_id'] ?? 0);
    if ($pid > 0 && isset($byId[$pid])) {
        $children[$pid][] = $id;
    } else {
        $children[0][] = $id;
    }
}

// Render recursive
$renderNode = function(int $id, int $depth = 0) use (&$renderNode, $byId, $children, $csrf_field_html): void {
    if (!isset($byId[$id])) return;
    $row = $byId[$id];
    $name = (string)($row['author_name'] ?? '');
    $name = $name !== '' ? $name : 'زائر';
    $isAdmin = (int)($row['is_admin'] ?? 0) === 1;
    $pad = min(42, $depth * 14);
    ?>
    <div class="gdy-item" style="margin-right: <?php echo (int)$pad; ?>px;">
      <div class="gdy-row" style="align-items:flex-start">
        <div class="gdy-avatar"><?php echo h(gdy_comments_initials($name)); ?></div>
        <div class="gdy-box">
          <div class="gdy-item-head">
            <div class="gdy-item-name">
              <?php echo h($name); ?>
              <?php if ($isAdmin): ?>
                <span class="gdy-badge">رد الإدارة</span>
              <?php endif; ?>
            </div>
            <div class="gdy-item-date"><?php echo h((string)($row['created_at'] ?? '')); ?></div>
          </div>
          <div class="gdy-item-body"><?php echo nl2br(h((string)($row['body'] ?? '')), false); ?></div>

          <div class="gdy-item-actions">
            <details class="gdy-reply-details">
              <summary class="gdy-reply-summary">رد</summary>
              <form method="post" class="gdy-reply-form">
                <?php echo $csrf_field_html; ?>
                <input type="hidden" name="_gdy_comments_submit" value="1">
                <input type="hidden" name="parent_id" value="<?php echo (int)$id; ?>">
                <textarea class="gdy-textarea" name="body" required minlength="2" maxlength="2000" placeholder="اكتب ردك (نص فقط)"></textarea>
                <div class="gdy-actions">
                  <button class="gdy-btn" type="submit">إرسال الرد</button>
                  <div class="gdy-note">الرد يظهر بعد المراجعة.</div>
                </div>
              </form>
            </details>
          </div>
        </div>
      </div>

      <?php if (!empty($children[$id])): ?>
        <div class="gdy-children">
          <?php foreach ($children[$id] as $cid): ?>
            <?php $renderNode((int)$cid, $depth + 1); ?>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
    <?php
};

$commentsCount = (int)($commentsCount ?? count($approved));
?>
<style>
.gdy-card{border:1px solid #e5e7eb;border-radius:18px;background:#fff;overflow:hidden}
.gdy-head{display:flex;align-items:center;justify-content:space-between;padding:16px 18px}
.gdy-title{display:flex;align-items:center;gap:10px;font-weight:800;font-size:18px}
.gdy-sub{padding:0 18px 14px;color:#64748b;font-size:13px}
.gdy-body{padding:0 18px 18px}
.gdy-row{display:flex;gap:14px;align-items:flex-start}
.gdy-avatar{width:44px;height:44px;border-radius:999px;background:#dbeafe;color:#1d4ed8;display:flex;align-items:center;justify-content:center;font-weight:800;flex:0 0 44px}
.gdy-box{flex:1}
.gdy-textarea{width:100%;min-height:86px;border:1px solid #d1d5db;border-radius:12px;padding:12px 12px;outline:none;resize:vertical}
.gdy-actions{margin-top:10px;display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap}
.gdy-btn{display:inline-flex;align-items:center;gap:8px;border:0;border-radius:10px;padding:10px 14px;background:#7cc6f3;color:#fff;font-weight:700;cursor:pointer}
.gdy-btn:disabled{opacity:.6;cursor:not-allowed}
.gdy-note{color:#64748b;font-size:12px}
.gdy-flash{margin:10px 0 0;padding:10px 12px;border-radius:10px;font-size:13px}
.gdy-flash.success{background:#ecfdf5;color:#065f46;border:1px solid #a7f3d0}
.gdy-flash.danger{background:#fef2f2;color:#991b1b;border:1px solid #fecaca}
.gdy-list{margin-top:18px;border-top:1px solid #f1f5f9}
.gdy-item{padding:16px 0;border-bottom:1px dashed #e5e7eb}
.gdy-item:last-child{border-bottom:0}
.gdy-item-head{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:6px}
.gdy-item-name{font-weight:800}
.gdy-item-date{color:#64748b;font-size:12px}
.gdy-item-body{color:#111827}
.gdy-empty{padding:22px 0;color:#64748b;text-align:center}
.gdy-badge{display:inline-block;margin-right:8px;background:#e0f2fe;color:#075985;border:1px solid #bae6fd;border-radius:999px;padding:2px 8px;font-size:12px;font-weight:800}
.gdy-item-actions{margin-top:10px}
.gdy-reply-summary{cursor:pointer;color:#2563eb;font-weight:700}
.gdy-reply-form{margin-top:10px}
.gdy-children{margin-top:12px}
</style>

<section class="gdy-card mt-3">
  <div class="gdy-head">
    <div class="gdy-title">النقاش</div>
    <div style="color:#64748b;font-size:13px">التعليقات (<?php echo (int)$commentsCount; ?>)</div>
  </div>
  <div class="gdy-sub">كن أول من يشارك رأيه في هذا الخبر</div>

  <div class="gdy-body">
    <div class="gdy-row">
      <div class="gdy-avatar"><?php echo h(gdy_comments_initials((string)($_SESSION['gdy_display_name'] ?? 'زائر'))); ?></div>
      <div class="gdy-box">
        <form method="post">
          <?php echo $csrf_field_html; ?>
          <input type="hidden" name="_gdy_comments_submit" value="1">
          <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:10px">
            <input class="gdy-textarea" style="min-height:auto;height:44px" type="text" name="author_name" placeholder="الاسم (اختياري)" maxlength="150">
            <input class="gdy-textarea" style="min-height:auto;height:44px" type="email" name="author_email" placeholder="البريد (اختياري)" maxlength="190">
          </div>
          <textarea class="gdy-textarea" name="body" required minlength="2" maxlength="2000" placeholder="ما رأيك في هذا الخبر؟ شاركنا وجهة نظرك..."></textarea>
          <div class="gdy-actions">
            <button class="gdy-btn" type="submit">نشر التعليق</button>
            <div class="gdy-note">الإدخال نص فقط — يظهر بعد المراجعة.</div>
          </div>
        </form>

        <?php if (is_array($flash)): ?>
          <div class="gdy-flash <?php echo h($flash['type'] ?? 'success'); ?>"><?php echo h($flash['msg'] ?? ''); ?></div>
        <?php endif; ?>

        <div class="gdy-list">
          <?php if (empty($children[0])): ?>
            <div class="gdy-empty">
              لا توجد تعليقات بعد<br><small>كن أول من يبدأ النقاش</small>
            </div>
          <?php else: ?>
            <?php foreach ($children[0] as $rootId): ?>
              <?php $renderNode((int)$rootId, 0); ?>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</section>
