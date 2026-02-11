<?php
declare(strict_types=1);

/**
 * Frontend snippet: عرض مرفقات الخبر داخل الصفحة + زر حفظ.
 *
 * الاستخدام داخل صفحة الخبر (بعد توفر PDO و $newsId):
 *   require_once __DIR__ . '/news_attachments_embed.php';
 *   gdy_render_news_attachments_embed($pdo, (int)$newsId);
 */

if (!function_exists('h')) {
    function h($v): string
    {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('gdy_starts_with')) {
    function gdy_starts_with(string $haystack, string $needle): bool
    {
        return $needle === '' || strncmp($haystack, $needle, strlen($needle)) === 0;
    }
}

function gdy_att_icon(string $filename): string
{
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    return match ($ext) {
        'pdf' => '📄',
        'doc', 'docx' => '📝',
        'xls', 'xlsx' => '📊',
        'ppt', 'pptx' => '📽️',
        'zip', 'rar', '7z' => '🗜️',
        'png', 'jpg', 'jpeg', 'gif', 'webp' => '🖼️',
        'txt', 'rtf' => '📃',
        default => '📎',
    };
}

function gdy_att_preview_meta(string $filename): array
{
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    return [
        'ext' => $ext,
        'pdf' => ($ext === 'pdf'),
        'img' => in_array($ext, ['png', 'jpg', 'jpeg', 'gif', 'webp'], true),
        'txt' => in_array($ext, ['txt', 'rtf'], true),
    ];
}

/**
 * @param array $options
 *   - base_url: (string) إذا كان الموقع ليس على نفس الجذر. الافتراضي '/'
 *   - title: (string) عنوان صندوق المرفقات
 */
function gdy_render_news_attachments_embed(PDO $pdo, int $newsId, array $options = []): void
{
    if ($newsId <= 0) {
        return;
    }

    // جدول المرفقات قد لا يكون موجوداً في بعض الإصدارات
    if (function_exists('gdy_db_table_exists')) {
        try {
            if (!gdy_db_table_exists($pdo, 'news_attachments')) {
                return;
            }
        } catch (Exception $e) {
            return;
        }
    }

    $baseUrl = (string)($options['base_url'] ?? '/');
    $baseUrl = $baseUrl === '' ? '/' : $baseUrl;
    $title   = (string)($options['title'] ?? (function_exists('__') ? __('t_a2737af54c', 'المرفقات') : 'المرفقات'));

    $stmt = $pdo->prepare(
        'SELECT id, original_name, file_path, mime_type, file_size '
        . 'FROM news_attachments WHERE news_id = :nid ORDER BY id DESC'
    );
    $stmt->execute([':nid' => $newsId]);
    $atts = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    if (!$atts) {
        return;
    }

    $uid = 'gdyAtt' . $newsId . '_' . substr(hash('sha256', (string)$newsId . '|' . (string)count($atts)), 0, 6);

    // Styles (lightweight, no bootstrap dependency)
    echo "<style>\n";
    echo ".{$uid}-box{border:1px solid rgba(0,0,0,.12);border-radius:14px;padding:14px;margin:16px 0;background:#fff;max-width:100%;overflow:hidden}\n";
    echo ".{$uid}-title{font-weight:700;margin:0 0 10px;font-size:16px}\n";
    echo ".{$uid}-item{border:1px solid rgba(0,0,0,.10);border-radius:12px;padding:10px;background:rgba(0,0,0,.02);margin:10px 0}\n";
    echo ".{$uid}-row{display:flex;gap:10px;align-items:center;justify-content:space-between}\n";
    echo ".{$uid}-name{display:flex;align-items:center;gap:8px;min-width:0}\n";
    echo ".{$uid}-name span.fn{white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:70vw;display:inline-block}\n";
    echo ".{$uid}-actions{display:flex;gap:8px;flex-shrink:0}\n";
    echo ".{$uid}-btn{border:1px solid rgba(0,0,0,.18);border-radius:10px;padding:6px 10px;font-size:13px;cursor:pointer;background:#fff;text-decoration:none;color:#111;display:inline-flex;align-items:center;gap:6px}\n";
    echo ".{$uid}-btn:hover{background:rgba(0,0,0,.04)}\n";

    // Modal styles
    echo ".{$uid}-modal{position:fixed;inset:0;display:none;align-items:center;justify-content:center;background:rgba(0,0,0,.55);padding:16px;z-index:99999}\n";
    echo ".{$uid}-modal.open{display:flex}\n";
    echo ".{$uid}-dialog{width:min(980px, 100%);max-height:92vh;background:#fff;border-radius:16px;box-shadow:0 20px 60px rgba(0,0,0,.35);overflow:hidden;display:flex;flex-direction:column}\n";
    echo ".{$uid}-header{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:12px;border-bottom:1px solid rgba(0,0,0,.10)}\n";
    echo ".{$uid}-hname{font-weight:700;min-width:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}\n";
    echo ".{$uid}-close{border:1px solid rgba(0,0,0,.18);border-radius:12px;background:#fff;cursor:pointer;padding:6px 10px;font-size:13px}\n";
    echo ".{$uid}-close:hover{background:rgba(0,0,0,.04)}\n";
    echo ".{$uid}-body{padding:12px;overflow:auto}\n";
    echo ".{$uid}-frame{width:100%;height:72vh;min-height:420px;border:1px solid rgba(0,0,0,.10);border-radius:12px;background:#f7f7f7}\n";
    echo ".{$uid}-img{max-width:100%;height:auto;border:1px solid rgba(0,0,0,.10);border-radius:12px;display:block;margin:0 auto}\n";
    echo ".{$uid}-footer{display:flex;gap:8px;justify-content:flex-end;flex-wrap:wrap;padding:12px;border-top:1px solid rgba(0,0,0,.10)}\n";
    echo "</style>\n";

    echo '<div class="' . h($uid) . '-box">';
    echo '<div class="' . h($uid) . '-title">' . h($title) . '</div>';

    foreach ($atts as $att) {
        $name = (string)($att['original_name'] ?? '');
        $path = (string)($att['file_path'] ?? '');

        // حماية بسيطة: لا نعرض مسارات غريبة
        $trimPath = ltrim($path, '/');
        if ($trimPath === '' || !(gdy_starts_with($trimPath, 'uploads/') || gdy_starts_with($trimPath, 'storage/') || gdy_starts_with($trimPath, 'public/'))) {
            continue;
        }

        $url  = rtrim($baseUrl, '/') . '/' . $trimPath;
        $meta = gdy_att_preview_meta($name);

        $data = [
            'data-url'  => $url,
            'data-name' => $name,
            'data-ext'  => (string)$meta['ext'],
            'data-pdf'  => $meta['pdf'] ? '1' : '0',
            'data-img'  => $meta['img'] ? '1' : '0',
            'data-txt'  => $meta['txt'] ? '1' : '0',
        ];

        $dataAttr = '';
        foreach ($data as $k => $v) {
            $dataAttr .= ' ' . h($k) . '="' . h($v) . '"';
        }

        echo '<div class="' . h($uid) . '-item">';
        echo '  <div class="' . h($uid) . '-row">';
        echo '    <div class="' . h($uid) . '-name">';
        echo '      <span aria-hidden="true">' . h(gdy_att_icon($name)) . '</span>';
        echo '      <span class="fn">' . h($name) . '</span>';
        echo '    </div>';
        echo '    <div class="' . h($uid) . '-actions">';
        echo '      <button type="button" class="' . h($uid) . '-btn"' . $dataAttr . ' data-action="gdy-att-open" data-uid="' . h($uid) . '">👁️ مشاهدة</button>';
        echo '      <a class="' . h($uid) . '-btn" href="' . h($url) . '" download>⬇️ حفظ</a>';
        echo '    </div>';
        echo '  </div>';
        echo '</div>';
    }

    echo '</div>';

    // Modal template
    echo '<div class="' . h($uid) . '-modal" id="' . h($uid) . '_modal" role="dialog" aria-modal="true" aria-hidden="true">';
    echo '  <div class="' . h($uid) . '-dialog">';
    echo '    <div class="' . h($uid) . '-header">';
    echo '      <div class="' . h($uid) . '-hname" id="' . h($uid) . '_m_name">المرفق</div>';
    echo '      <button type="button" class="' . h($uid) . '-close" data-action="gdy-att-close" data-uid="' . h($uid) . '">✖ إغلاق</button>';
    echo '    </div>';
    echo '    <div class="' . h($uid) . '-body" id="' . h($uid) . '_m_body"></div>';
    echo '    <div class="' . h($uid) . '-footer">';
    echo '      <a class="' . h($uid) . '-btn" id="' . h($uid) . '_m_download" href="#" download>⬇️ حفظ</a>';
    echo '      <button type="button" class="' . h($uid) . '-btn" data-action="gdy-att-close" data-uid="' . h($uid) . '">تم</button>';
    echo '    </div>';
    echo '  </div>';
    echo '</div>';

    // JS: فتح/إغلاق مودال + معاينة آمنة بدون innerHTML
    echo "<script>(function(){\n";
    echo "function qs(sel,root){return (root||document).querySelector(sel)}\n";
    echo "function qsa(sel,root){return Array.prototype.slice.call((root||document).querySelectorAll(sel))}\n";
    echo "function openModal(uid,btn){\n";
    echo "  var m=qs('#'+uid+'_modal'); if(!m) return;\n";
    echo "  var body=qs('#'+uid+'_m_body'); var nameEl=qs('#'+uid+'_m_name'); var dl=qs('#'+uid+'_m_download');\n";
    echo "  var url=btn.getAttribute('data-url')||''; var name=btn.getAttribute('data-name')||'';\n";
    echo "  var isPdf=btn.getAttribute('data-pdf')==='1'; var isImg=btn.getAttribute('data-img')==='1'; var isTxt=btn.getAttribute('data-txt')==='1';\n";
    echo "  if(nameEl) nameEl.textContent=name||'المرفق';\n";
    echo "  if(dl){dl.href=url||'#'; dl.setAttribute('download', name||'');}\n";
    echo "  if(body){ while(body.firstChild) body.removeChild(body.firstChild);\n";
    echo "    if(isImg){ var img=new Image(); img.className=uid+'-img'; img.src=url; img.alt=name; body.appendChild(img); }\n";
    echo "    else if(isPdf||isTxt){ var fr=document.createElement('iframe'); fr.className=uid+'-frame'; fr.src=url; fr.loading='lazy'; body.appendChild(fr); }\n";
    echo "    else { var d=document.createElement('div'); d.textContent='لا تتوفر معاينة لهذا النوع. استخدم زر الحفظ لتنزيل الملف.'; body.appendChild(d); }\n";
    echo "  }\n";
    echo "  m.classList.add('open'); m.setAttribute('aria-hidden','false');\n";
    echo "}\n";
    echo "function closeModal(uid){ var m=qs('#'+uid+'_modal'); if(!m) return; m.classList.remove('open'); m.setAttribute('aria-hidden','true'); }\n";
    echo "document.addEventListener('click',function(e){\n";
    echo "  var t=e.target;\n";
    echo "  while(t && t!==document){\n";
    echo "    var act=t.getAttribute && t.getAttribute('data-action');\n";
    echo "    if(act==='gdy-att-open'){ openModal(t.getAttribute('data-uid'), t); return; }\n";
    echo "    if(act==='gdy-att-close'){ closeModal(t.getAttribute('data-uid')); return; }\n";
    echo "    t=t.parentNode;\n";
    echo "  }\n";
    echo "});\n";
    echo "document.addEventListener('keydown',function(e){ if(e.key==='Escape'){ closeModal('" . h($uid) . "'); } });\n";
    echo "})();</script>\n";
}
