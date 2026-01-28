<?php
// plugins/news_comments/views/widget.php
// متغيرات متاحة: $newsId, $news
$newsId = (int)($newsId ?? 0);
if ($newsId <= 0) return;

if (session_status() !== PHP_SESSION_ACTIVE) {
    if (function_exists('gdy_session_start')) { gdy_session_start(); } else { @session_start(); }
}

$pdo = function_exists('gdy_pdo_safe') ? gdy_pdo_safe() : null;
$items = [];
if ($pdo instanceof PDO) {
    try {
        $st = $pdo->prepare("SELECT id, name, body, created_at
                             FROM news_comments
                             WHERE news_id = :nid AND status = 'approved'
                             ORDER BY created_at ASC, id ASC
                             LIMIT 200");
        $st->execute([':nid' => $newsId]);
        $items = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) { $items = []; }
}

$csrf = '';
if (function_exists('csrf_token')) {
    $csrf = (string)csrf_token();
} else {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
    }
    $csrf = (string)$_SESSION['csrf_token'];
}
?>
<section class="gdy-comments" id="comments" style="margin:24px auto;max-width:980px;">
  <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:10px;">
    <div style="display:flex;align-items:center;gap:10px;">
      <span style="width:28px;height:28px;display:inline-flex;align-items:center;justify-content:center;border-radius:999px;background:#eef2ff;color:#3730a3;font-weight:700;">💬</span>
      <h3 style="margin:0;font-size:20px;">التعليقات</h3>
    </div>
    <span style="font-size:13px;opacity:.8;"><?php echo (int)count($items); ?> تعليق</span>
  </div>

  <form method="post" action="<?php echo h((string)$postEndpoint); ?>" style="border:1px solid rgba(0,0,0,.08);border-radius:14px;padding:14px;background:#fff;">
    <input type="hidden" name="news_id" value="<?php echo (int)$newsId; ?>">
    <input type="hidden" name="csrf_token" value="<?php echo h($csrf); ?>">
    <input type="hidden" name="redirect" value="<?php echo h((string)($_SERVER['REQUEST_URI'] ?? '')); ?>#comments">

    <div style="display:flex;gap:10px;flex-wrap:wrap;">
      <div style="flex:1;min-width:200px;">
        <label style="display:block;font-size:13px;margin-bottom:6px;">الاسم</label>
        <input name="name" required maxlength="150" autocomplete="name"
               style="width:100%;padding:10px 12px;border-radius:10px;border:1px solid rgba(0,0,0,.12);"
               placeholder="اسمك">
      </div>
      <div style="flex:1;min-width:220px;">
        <label style="display:block;font-size:13px;margin-bottom:6px;">البريد (اختياري)</label>
        <input name="email" type="email" maxlength="190" autocomplete="email"
               style="width:100%;padding:10px 12px;border-radius:10px;border:1px solid rgba(0,0,0,.12);"
               placeholder="name@example.com">
      </div>
    </div>

    <div style="margin-top:10px;">
      <label style="display:block;font-size:13px;margin-bottom:6px;">اكتب تعليقك</label>
      <textarea name="body" required maxlength="2000"
                style="width:100%;min-height:110px;padding:10px 12px;border-radius:10px;border:1px solid rgba(0,0,0,.12);"
                placeholder="اكتب تعليقك هنا..."></textarea>
      <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;margin-top:10px;">
        <small style="opacity:.7;">سيتم نشر تعليقك مباشرة.</small>
        <button type="submit" style="padding:10px 16px;border:0;border-radius:10px;background:#2563eb;color:#fff;cursor:pointer;">إرسال</button>
      </div>
    </div>
  </form>

  <div style="margin-top:14px;">
    <?php if (empty($items)): ?>
      <p style="opacity:.75;margin:10px 0;">لا توجد تعليقات بعد. كن أول من يعلق.</p>
    <?php else: ?>
      <ul style="list-style:none;padding:0;margin:0;display:flex;flex-direction:column;gap:10px;">
        <?php foreach ($items as $c): ?>
          <li style="border:1px solid rgba(0,0,0,.07);border-radius:14px;padding:12px 14px;background:#fff;">
            <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;">
              <strong><?php echo h((string)($c['name'] ?? '')); ?></strong>
              <small style="opacity:.65;"><?php echo h((string)($c['created_at'] ?? '')); ?></small>
            </div>
            <div style="margin-top:8px;line-height:1.7;"><?php echo nl2br(h((string)($c['body'] ?? ''))); ?></div>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </div>
</section>
