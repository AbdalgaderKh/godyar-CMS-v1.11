<?php
// plugins/reader_questions/views/widget.php
// المتغيرات المتاحة: $newsId, $questionsCount
$newsId = (int)($newsId ?? 0);
if ($newsId <= 0) return;

if (session_status() !== PHP_SESSION_ACTIVE) {
    if (function_exists('gdy_session_start')) { gdy_session_start(); } else { session_start(); }
}

$pdo = function_exists('gdy_pdo_safe') ? gdy_pdo_safe() : null;
$ip  = substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 64);

$flash = null;
$flashType = 'info';
$csrf = function_exists('csrf_token') ? csrf_token() : (function_exists('generate_csrf_token') ? generate_csrf_token() : '');

// Create question
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['gdy_questions_action']) && $_POST['gdy_questions_action'] === 'create') {
    $token = (string)($_POST['csrf_token'] ?? '');
    $okCsrf = function_exists('verify_csrf_token') ? verify_csrf_token($token) : true;

    if (!$okCsrf) {
        $flash = 'فشل التحقق الأمني. حدّث الصفحة وحاول مجددًا.';
        $flashType = 'danger';
    } else {
        if (function_exists('gody_rate_limit') && !gody_rate_limit('questions_create:' . $newsId . ':' . $ip, 5, 60)) {
            $flash = 'تم تجاوز حد المحاولات. حاول بعد دقيقة.';
            $flashType = 'danger';
        } elseif (($pdo instanceof PDO) === false) {
            $flash = 'تعذّر الاتصال بقاعدة البيانات.';
            $flashType = 'danger';
        } else {
            $name  = trim((string)($_POST['author_name'] ?? ''));
            $email = trim((string)($_POST['author_email'] ?? ''));
            $q     = trim((string)($_POST['question'] ?? ''));

            $q = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $q ?? '');
            $q = strip_tags((string)$q);

            if ($name !== '') {
                $name = strip_tags($name);
                $name = mb_substr($name, 0, 150);
            } else {
                $name = 'زائر';
            }
            if ($email !== '') {
                $email = strip_tags($email);
                $email = mb_substr($email, 0, 190);
            } else {
                $email = null;
            }

            if ($q === '' || mb_strlen($q) < 2) {
                $flash = 'اكتب سؤالك قبل الإرسال.';
                $flashType = 'danger';
            } else {
                $q = mb_substr($q, 0, 2000);

                $editToken = bin2hex(random_bytes(16));
                $editHash  = hash('sha256', $editToken);

                try {
                    $st = $pdo->prepare("INSERT INTO reader_questions (news_id, author_name, author_email, question, answer, status, ip, user_agent, created_at, answered_at, edit_token_hash, updated_at, updated_ip)
                                         VALUES (?, ?, ?, ?, NULL, 'pending', ?, ?, NOW(), NULL, ?, NULL, NULL)");
                    $st->execute([
                        $newsId,
                        $name,
                        $email,
                        $q,
                        $ip,
                        substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
                        $editHash,
                    ]);

                    $newId = (int)$pdo->lastInsertId();
                    $_SESSION['gdy_questions_last'] = ['id' => $newId, 'token' => $editToken, 'at' => time()];
                    setcookie('gdy_q_edit', $editToken, time()+86400*7, '/', '', isset($_SERVER['HTTPS']), true);

                    $flash = 'تم استلام سؤالك وسيظهر بعد المراجعة.';
                    $flashType = 'success';
                } catch (Exception $e) {
                    error_log('[ReaderQuestionsWidget] create: ' . $e->getMessage());
                    $flash = 'حدث خطأ أثناء الحفظ.';
                    $flashType = 'danger';
                }
            }
        }
    }
}

// Update pending question
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['gdy_questions_action']) && $_POST['gdy_questions_action'] === 'update') {
    $token = (string)($_POST['csrf_token'] ?? '');
    $okCsrf = function_exists('verify_csrf_token') ? verify_csrf_token($token) : true;

    if (!$okCsrf) {
        $flash = 'فشل التحقق الأمني. حدّث الصفحة وحاول مجددًا.';
        $flashType = 'danger';
    } else {
        if (function_exists('gody_rate_limit') && !gody_rate_limit('questions_update:' . $newsId . ':' . $ip, 5, 60)) {
            $flash = 'تم تجاوز حد المحاولات. حاول بعد دقيقة.';
            $flashType = 'danger';
        } elseif (($pdo instanceof PDO) === false) {
            $flash = 'تعذّر الاتصال بقاعدة البيانات.';
            $flashType = 'danger';
        } else {
            $qid = (int)($_POST['question_id'] ?? 0);
            $editToken = (string)($_POST['edit_token'] ?? '');
            $q = trim((string)($_POST['question'] ?? ''));

            $q = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $q ?? '');
            $q = strip_tags((string)$q);
            $q = mb_substr($q, 0, 2000);

            if ($qid <= 0 || $editToken === '') {
                $flash = 'بيانات تعديل غير مكتملة.';
                $flashType = 'danger';
            } elseif ($q === '' || mb_strlen($q) < 2) {
                $flash = 'اكتب سؤالًا صحيحًا قبل الإرسال.';
                $flashType = 'danger';
            } else {
                $editHash = hash('sha256', $editToken);
                try {
                    $st = $pdo->prepare("UPDATE reader_questions
                                         SET question=?, status='pending', updated_at=NOW(), updated_ip=?
                                         WHERE id=? AND news_id=? AND status='pending' AND edit_token_hash=?");
                    $st->execute([$q, $ip, $qid, $newsId, $editHash]);
                    if ($st->rowCount() > 0) {
                        $flash = 'تم تعديل سؤالك وإرساله للمراجعة.';
                        $flashType = 'success';
                    } else {
                        $flash = 'تعذر تعديل السؤال (قد يكون تمت مراجعته أو انتهت صلاحية التعديل).';
                        $flashType = 'danger';
                    }
                } catch (Exception $e) {
                    error_log('[ReaderQuestionsWidget] update: ' . $e->getMessage());
                    $flash = 'حدث خطأ أثناء التعديل.';
                    $flashType = 'danger';
                }
            }
        }
    }
}

// Load approved/answered questions
$items = [];
if ($pdo instanceof PDO) {
    try {
        $st = $pdo->prepare("SELECT id, author_name, question, answer, status, created_at, answered_at
                             FROM reader_questions
                             WHERE news_id=? AND status IN ('approved','answered')
                             ORDER BY id DESC
                             LIMIT 50");
        $st->execute([$newsId]);
        $items = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Exception $e) {
        error_log('[ReaderQuestionsWidget] list: ' . $e->getMessage());
        $items = [];
    }
}

// Load last pending for edit
$lastPending = null;
$editToken = '';
$cookieToken = (string)($_COOKIE['gdy_q_edit'] ?? '');
if ($cookieToken !== '' && $pdo instanceof PDO) {
    try {
        $st = $pdo->prepare("SELECT id, question FROM reader_questions WHERE news_id=? AND status='pending' AND edit_token_hash=? ORDER BY id DESC LIMIT 1");
        $st->execute([$newsId, hash('sha256', $cookieToken)]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $lastPending = ['id' => (int)$row['id'], 'question' => (string)$row['question']];
            $editToken = $cookieToken;
        }
    } catch (Exception $e) {
        // ignore
    }
}

if (!function_exists('gdy_reader_questions_initials')) {
function gdy_reader_questions_initials(string $name): string {
    $name = trim($name);
    if ($name === '' || $name === 'زائر') return 'G';
    $parts = preg_split('/\s+/u', $name) ?: [];
    $a = mb_substr((string)($parts[0] ?? 'G'), 0, 1);
    $b = mb_substr((string)($parts[1] ?? ''), 0, 1);
    $ini = mb_strtoupper($a . $b);
    return $ini !== '' ? $ini : 'G';
}
}
?>

<style>
/* نفس ستايل التعليقات لضمان مظهر مطابق */
.gdy-discuss{border:1px solid #e5e7eb;border-radius:18px;background:#fff;overflow:hidden;margin:16px 0}
.gdy-discuss-h{padding:18px 18px 10px;display:flex;align-items:center;justify-content:space-between;gap:12px}
.gdy-discuss-title{display:flex;align-items:center;gap:10px;font-weight:800;font-size:18px}
.gdy-discuss-sub{padding:0 18px 14px;color:#64748b;font-size:13px}
.gdy-discuss-body{padding:0 18px 18px}
.gdy-row{display:flex;gap:14px;align-items:flex-start}
.gdy-avatar{width:44px;height:44px;border-radius:999px;background:#e6f3ff;color:#0b63b6;display:flex;align-items:center;justify-content:center;font-weight:800;flex:0 0 44px}
.gdy-box{flex:1}
.gdy-textarea{width:100%;min-height:86px;border:1px solid #d1d5db;border-radius:12px;padding:12px 12px;outline:none;resize:vertical}
.gdy-actions{margin-top:10px;display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap}
.gdy-btn{display:inline-flex;align-items:center;gap:8px;border:0;border-radius:8px;padding:9px 14px;background:#7cc6f3;color:#fff;font-weight:700;cursor:pointer}
.gdy-btn:disabled{opacity:.6;cursor:not-allowed}
.gdy-note{color:#64748b;font-size:12px}
.gdy-flash{margin:10px 0 0;padding:10px 12px;border-radius:10px;font-size:13px}
.gdy-flash.success{background:#ecfdf5;color:#065f46;border:1px solid #a7f3d0}
.gdy-flash.danger{background:#fef2f2;color:#991b1b;border:1px solid #fecaca}
.gdy-list{margin-top:16px;border-top:1px solid #eef2f7;padding-top:14px}
.gdy-empty{background:#f8fafc;border-radius:14px;padding:22px;text-align:center;color:#64748b}
.gdy-empty .ic{font-size:28px;margin-bottom:8px}
.gdy-item{padding:12px 0;border-bottom:1px solid #eef2f7}
.gdy-item:last-child{border-bottom:0}
.gdy-item-head{display:flex;align-items:center;justify-content:space-between;gap:10px;font-size:12px;color:#64748b;margin-bottom:6px}
.gdy-item-name{font-weight:800;color:#0f172a}
.gdy-pill{font-size:11px;padding:2px 8px;border-radius:999px;background:#eef2ff;color:#3730a3}
.gdy-editbox{margin-top:12px;padding:12px;border:1px dashed #cbd5e1;border-radius:12px;background:#f8fafc}
.gdy-answer{margin-top:8px;background:#f1f5f9;border-radius:12px;padding:10px 12px}
</style>

<section class="gdy-discuss" id="gdy-questions-box">
  <div class="gdy-discuss-h">
    <div class="gdy-discuss-title">
      <span style="display:inline-flex;align-items:center;justify-content:center;width:34px;height:34px;border-radius:999px;background:#e6f3ff;color:#0b63b6">❓</span>
      <span>أسئلة القرّاء</span>
    </div>
    <span class="gdy-pill">الأسئلة: <?php echo (int)($questionsCount ?? count($items)); ?></span>
  </div>
  <div class="gdy-discuss-sub">اكتب سؤالك وسيظهر بعد المراجعة</div>

  <div class="gdy-discuss-body">
    <?php if ($flash): ?>
      <div class="gdy-flash <?php echo $flashType; ?>"><?php echo h($flash); ?></div>
    <?php endif; ?>

    <div class="gdy-row">
      <div class="gdy-avatar"><?php echo h(gdy_reader_questions_initials((string)($_POST['author_name'] ?? ''))); ?></div>

      <div class="gdy-box">
        <form method="post" action="#gdy-questions-box">
          <input type="hidden" name="gdy_questions_action" value="create">
          <input type="hidden" name="csrf_token" value="<?php echo h($csrf); ?>">

          <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:10px">
            <input type="text" name="author_name" placeholder="الاسم" style="flex:1;min-width:160px;border:1px solid #d1d5db;border-radius:10px;padding:10px 12px" maxlength="150">
            <input type="email" name="author_email" placeholder="البريد (اختياري)" style="flex:1;min-width:220px;border:1px solid #d1d5db;border-radius:10px;padding:10px 12px" maxlength="190">
          </div>

          <textarea class="gdy-textarea" name="question" placeholder="اكتب سؤالك هنا..." maxlength="2000" required></textarea>

          <div class="gdy-actions">
            <button class="gdy-btn" type="submit">إرسال السؤال</button>
            <div class="gdy-note">الإدخال نص فقط. سيظهر بعد المراجعة.</div>
          </div>
        </form>

        <?php if ($lastPending && $editToken !== ''): ?>
          <div class="gdy-editbox">
            <div class="gdy-note" style="margin-bottom:8px">آخر سؤال لك بانتظار المراجعة. يمكنك تعديله وإعادة إرساله:</div>
            <form method="post" action="#gdy-questions-box">
              <input type="hidden" name="gdy_questions_action" value="update">
              <input type="hidden" name="csrf_token" value="<?php echo h($csrf); ?>">
              <input type="hidden" name="question_id" value="<?php echo (int)$lastPending['id']; ?>">
              <input type="hidden" name="edit_token" value="<?php echo h($editToken); ?>">
              <textarea class="gdy-textarea" name="question" maxlength="2000" required><?php echo h($lastPending['question']); ?></textarea>
              <div class="gdy-actions">
                <button class="gdy-btn" type="submit">تعديل وإعادة إرسال</button>
                <div class="gdy-note">سيبقى في حالة “قيد المراجعة”.</div>
              </div>
            </form>
          </div>
        <?php endif; ?>

        <div class="gdy-list">
          <?php if (empty($items)): ?>
            <div class="gdy-empty">
              <div class="ic">💭</div>
              <div style="font-weight:800;color:#0f172a;margin-bottom:6px">لا توجد أسئلة بعد</div>
              <div>كن أول من يطرح سؤالًا</div>
            </div>
          <?php else: ?>
            <?php foreach ($items as $q): ?>
              <div class="gdy-item">
                <div class="gdy-item-head">
                  <div class="gdy-item-name"><?php echo h((string)($q['author_name'] ?? 'زائر')); ?></div>
                  <div><?php echo h((string)($q['created_at'] ?? '')); ?></div>
                </div>
                <div><?php echo nl2br(h((string)($q['question'] ?? '')), false); ?></div>

                <?php if (($q['status'] ?? '') === 'answered' && !empty($q['answer'])): ?>
                  <div class="gdy-answer">
                    <div style="font-weight:800;color:#0f172a;margin-bottom:6px">الإجابة</div>
                    <div><?php echo nl2br(h((string)$q['answer']), false); ?></div>
                  </div>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>

      </div>
    </div>
  </div>
</section>
