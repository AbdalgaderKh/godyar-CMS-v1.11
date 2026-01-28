/**
 * Godyar Minimal Editor (Admin)
 * - Small toolbar
 * - Syncs sanitized HTML to textarea
 * - Safe paste (plain text)
 */

'use strict';

const createEl = (tag, attrs, children) => {
  const node = document.createElement(tag);

  if (attrs) {
    Object.keys(attrs).forEach((k) => {
      if (k === 'class') node.className = attrs[k];
      else if (k === 'text') node.textContent = attrs[k];
      else if (k === 'html') node.innerHTML = attrs[k];
      else node.setAttribute(k, String(attrs[k]));
    });
  }

  if (children?.length) children.forEach((c) => c && node.appendChild(c));
  return node;
};

const exec = (cmd, value) => {
  try {
    document.execCommand(cmd, false, value);
  } catch (_) {
    // ignore (execCommand can be blocked)
  }
};

const sanitizeHtml = (html) => {
  if (!html) return '';

  // استخدم DOMParser لتقليل أخطاء regex
  const parser = new DOMParser();
  const doc = parser.parseFromString(`<div>${html}</div>`, 'text/html');
  const root = doc.body.firstChild;

  if (!root) return '';

  // حذف وسوم خطيرة
  root.querySelectorAll('script, iframe, object, embed, style, link, meta').forEach((n) => n.remove());

  // حذف أي attributes تبدأ بـ on (onclick, onerror...) + javascript: في href/src
  root.querySelectorAll('*').forEach((n) => {
    Array.from(n.attributes).forEach((a) => {
      const name = a.name.toLowerCase();
      const val = String(a.value || '');

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
};

const buildToolbar = (editor) => {
  const bar = createEl('div', { class: 'gdy-editor-toolbar' });

  const buttons = [
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

  buttons.forEach((b) => {
    const btn = createEl('button', {
      type: 'button',
      class: 'gdy-editor-btn',
      'data-cmd': b.cmd,
      title: b.cmd,
      text: b.label
    });

    btn.addEventListener('click', () => {
      editor.focus();
      exec(b.cmd);
    });

    bar.appendChild(btn);
  });

  // Heading select
  const select = createEl('select', { class: 'gdy-editor-select', title: 'Heading' });
  [
    { v: 'p', t: 'نص' },
    { v: 'h1', t: 'H1' },
    { v: 'h2', t: 'H2' },
    { v: 'h3', t: 'H3' },
    { v: 'blockquote', t: 'اقتباس' }
  ].forEach((o) => select.appendChild(createEl('option', { value: o.v, text: o.t })));

  select.addEventListener('change', () => {
    editor.focus();
    const v = select.value;
    if (v === 'blockquote') exec('formatBlock', '<blockquote>');
    else exec('formatBlock', `<${v}>`);
    select.value = 'p';
  });

  bar.appendChild(select);

  // Link
  const linkBtn = createEl('button', { type: 'button', class: 'gdy-editor-btn', title: 'Link', text: '🔗' });
  linkBtn.addEventListener('click', () => {
    editor.focus();
    let url = prompt('رابط (URL):'); // eslint-disable-line no-alert
    if (!url) return;

    url = String(url).trim();
    if (/^\s*javascript:/i.test(url)) return;

    if (!/^https?:\/\//i.test(url) && !/^\//.test(url)) url = `https://${url}`;

    exec('createLink', url);
  });
  bar.appendChild(linkBtn);

  const unlinkBtn = createEl('button', { type: 'button', class: 'gdy-editor-btn', title: 'Unlink', text: '⛔' });
  unlinkBtn.addEventListener('click', () => {
    editor.focus();
    exec('unlink');
  });
  bar.appendChild(unlinkBtn);

  // Image (URL)
  const imgBtn = createEl('button', { type: 'button', class: 'gdy-editor-btn', title: 'Image', text: '🖼️' });
  imgBtn.addEventListener('click', () => {
    editor.focus();
    const url = prompt('رابط الصورة (URL):'); // eslint-disable-line no-alert
    if (!url) return;
    if (/^\s*javascript:/i.test(url)) return;
    exec('insertImage', String(url).trim());
  });
  bar.appendChild(imgBtn);

  // Clear formatting
  const clearBtn = createEl('button', { type: 'button', class: 'gdy-editor-btn', title: 'Clear', text: 'Tx' });
  clearBtn.addEventListener('click', () => {
    editor.focus();
    exec('removeFormat');
  });
  bar.appendChild(clearBtn);

  return bar;
};

const initOne = (textarea) => {
  if (!textarea || textarea.__gdyEditorReady) return;
  textarea.__gdyEditorReady = true;

  const form = textarea.closest('form');

  const wrapper = createEl('div', { class: 'gdy-editor-wrap' });
  const editor = createEl('div', {
    class: 'gdy-editor-area',
    contenteditable: 'true',
    dir: document.documentElement.getAttribute('dir') || 'auto'
  });

  // محتوى ابتدائي
  editor.innerHTML = sanitizeHtml(textarea.value);

  const toolbar = buildToolbar(editor);
  wrapper.appendChild(toolbar);
  wrapper.appendChild(editor);

  // ضع المحرر بعد textarea وأخفِ textarea
  textarea.style.display = 'none';
  textarea.parentNode.insertBefore(wrapper, textarea.nextSibling);

  const syncToTextarea = () => {
    textarea.value = sanitizeHtml(editor.innerHTML);
  };

  // مزامنة مستمرة
  editor.addEventListener('input', syncToTextarea);
  editor.addEventListener('blur', syncToTextarea);

  // مزامنة قبل الإرسال
  form?.addEventListener('submit', syncToTextarea);

  // لصق آمن
  editor.addEventListener('paste', (e) => {
    e.preventDefault();
    const text = (e.clipboardData || window.clipboardData).getData('text/plain');
    exec('insertText', text);
    syncToTextarea();
  });
};

const initAll = () => {
  document
    .querySelectorAll('textarea[data-gdy-editor="1"], textarea.gdy-editor')
    .forEach((ta) => initOne(ta));
};

if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initAll);
else initAll();
