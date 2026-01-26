/**
 * Godyar Lightweight WYSIWYG Editor
 * - بدون مكتبات خارجية (مناسب للاستضافة المشتركة)
 * - يحوّل textarea[data-gdy-editor="1"] إلى محرر rich-text
 * - يزامن محتوى HTML إلى textarea تلقائياً + قبل إرسال النموذج
 */
(function () {
  'use strict';

  function createEl(tag, attrs, children) {
    var node = document.createElement(tag);
    if (attrs) {
      Object.keys(attrs).forEach(function (k) {
        if (k === 'class') node.className = attrs[k];
        else if (k === 'text') node.textContent = attrs[k];
        else if (k === 'html') node.innerHTML = attrs[k];
        else node.setAttribute(k, String(attrs[k]));
      });
    }
    if (children && children.length) {
      children.forEach(function (c) {
        if (c) node.appendChild(c);
      });
    }
    return node;
  }

  function sanitizeHtml(html) {
    if (!html) return '';

    // استخدم DOMParser لتقليل أخطاء regex
    var parser = new DOMParser();
    var doc = parser.parseFromString('<div>' + html + '</div>', 'text/html');
    var root = doc.body.firstChild;

    // حذف وسوم خطيرة
    var blocked = root.querySelectorAll('script, iframe, object, embed, style, link, meta');
    blocked.forEach(function (n) { n.remove(); });

    // حذف أي attributes تبدأ بـ on (onclick, onerror...) + javascript: في href/src
    var all = root.querySelectorAll('*');
    all.forEach(function (n) {
      Array.from(n.attributes).forEach(function (a) {
        var name = a.name.toLowerCase();
        var val = String(a.value || '');
        if (name.startsWith('on')) {
          n.removeAttribute(a.name);
          return;
        }
        if ((name === 'href' || name === 'src') && /^\s*javascript:/i.test(val)) {
          n.removeAttribute(a.name);
        }
      });
    });

    return root.innerHTML;
  }

  function exec(cmd, value) {
    try {
      document.execCommand(cmd, false, value);
    } catch (e) {
      // ignore
    }
  }

  function buildToolbar(editor) {
    var bar = createEl('div', { class: 'gdy-editor-toolbar' });

    var buttons = [
      { cmd: 'bold', label: 'B' },
      { cmd: 'italic', label: 'I' },
      { cmd: 'underline', label: 'U' },
      { cmd: 'insertUnorderedList', label: '•' },
      { cmd: 'insertOrderedList', label: '1.' },
      { cmd: 'justifyRight', label: '↦' },
      { cmd: 'justifyCenter', label: '↔' },
      { cmd: 'justifyLeft', label: '↤' },
      { cmd: 'undo', label: '↶' },
      { cmd: 'redo', label: '↷' }
    ];

    buttons.forEach(function (b) {
      var btn = createEl('button', {
        type: 'button',
        class: 'gdy-editor-btn',
        'data-cmd': b.cmd,
        title: b.cmd,
        text: b.label
      });
      btn.addEventListener('click', function () {
        editor.focus();
        exec(b.cmd);
      });
      bar.appendChild(btn);
    });

    // Heading select
    var select = createEl('select', { class: 'gdy-editor-select', title: 'Heading' });
    [
      { v: 'p', t: 'نص' },
      { v: 'h1', t: 'H1' },
      { v: 'h2', t: 'H2' },
      { v: 'h3', t: 'H3' },
      { v: 'blockquote', t: 'اقتباس' }
    ].forEach(function (o) {
      select.appendChild(createEl('option', { value: o.v, text: o.t }));
    });
    select.addEventListener('change', function () {
      editor.focus();
      var v = select.value;
      if (v === 'blockquote') exec('formatBlock', '<blockquote>');
      else exec('formatBlock', '<' + v + '>');
      select.value = 'p';
    });
    bar.appendChild(select);

    // Link
    var linkBtn = createEl('button', { type: 'button', class: 'gdy-editor-btn', title: 'Link', text: '🔗' });
    linkBtn.addEventListener('click', function () {
      editor.focus();
      var url = prompt('رابط (URL):');
      if (!url) return;
      if (!/^https?:\/\//i.test(url) && !/^\//.test(url)) {
        // امنع javascript: إلخ
        if (/^\s*javascript:/i.test(url)) return;
        url = 'https://' + url;
      }
      exec('createLink', url);
    });
    bar.appendChild(linkBtn);

    var unlinkBtn = createEl('button', { type: 'button', class: 'gdy-editor-btn', title: 'Unlink', text: '⛔' });
    unlinkBtn.addEventListener('click', function () {
      editor.focus();
      exec('unlink');
    });
    bar.appendChild(unlinkBtn);

    // Image (URL)
    var imgBtn = createEl('button', { type: 'button', class: 'gdy-editor-btn', title: 'Image', text: '🖼️' });
    imgBtn.addEventListener('click', function () {
      editor.focus();
      var url = prompt('رابط الصورة (URL):');
      if (!url) return;
      if (/^\s*javascript:/i.test(url)) return;
      exec('insertImage', url);
    });
    bar.appendChild(imgBtn);

    // Clear formatting
    var clearBtn = createEl('button', { type: 'button', class: 'gdy-editor-btn', title: 'Clear', text: 'Tx' });
    clearBtn.addEventListener('click', function () {
      editor.focus();
      exec('removeFormat');
    });
    bar.appendChild(clearBtn);

    return bar;
  }

  function initOne(textarea) {
    if (textarea.__gdyEditorReady) return;
    textarea.__gdyEditorReady = true;

    var form = textarea.closest('form');

    var wrapper = createEl('div', { class: 'gdy-editor-wrap' });
    var editor = createEl('div', {
      class: 'gdy-editor-area',
      contenteditable: 'true',
      dir: document.documentElement.getAttribute('dir') || 'auto'
    });

    // محتوى ابتدائي
    editor.innerHTML = sanitizeHtml(textarea.value);

    var toolbar = buildToolbar(editor);
    wrapper.appendChild(toolbar);
    wrapper.appendChild(editor);

    // ضع المحرر بعد textarea وأخفِ textarea
    textarea.style.display = 'none';
    textarea.parentNode.insertBefore(wrapper, textarea.nextSibling);

    function syncToTextarea() {
      textarea.value = sanitizeHtml(editor.innerHTML);
    }

    // مزامنة مستمرة
    editor.addEventListener('input', syncToTextarea);
    editor.addEventListener('blur', syncToTextarea);

    // مزامنة قبل الإرسال
    if (form) {
      form.addEventListener('submit', function () {
        syncToTextarea();
      });
    }

    // لصق آمن
    editor.addEventListener('paste', function (e) {
      e.preventDefault();
      var text = (e.clipboardData || window.clipboardData).getData('text/plain');
      exec('insertText', text);
      syncToTextarea();
    });
  }

  function initAll() {
    var list = document.querySelectorAll('textarea[data-gdy-editor="1"], textarea.gdy-editor');
    list.forEach(function (ta) { initOne(ta); });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initAll);
  } else {
    initAll();
  }
})();
