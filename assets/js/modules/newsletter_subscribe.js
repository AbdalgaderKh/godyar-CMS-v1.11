/*
  Newsletter subscribe (safe, minimal)
  - Enhances a form if present; does not break if endpoint differs.
*/

'use strict';

const form = document.querySelector('#newsletter-form') || document.querySelector('form[data-newsletter]');
if (form) {
  const msgEl = document.querySelector('[data-newsletter-message]') || null;

  const setMsg = (text) => {
    if (msgEl) msgEl.textContent = text;
  };

  form.addEventListener('submit', async (e) => {
    // Prefer AJAX, but fall back to normal submit if it fails
    e.preventDefault();
    setMsg('...');

    try {
      const fd = new FormData(form);
      const res = await fetch(form.action || window.location.href, {
        method: form.method || 'POST',
        credentials: 'same-origin',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: fd
      });

      if (!res.ok) throw new Error('Request failed');
      setMsg('تم الاستلام.');
      form.reset();
    } catch (err) {
      // Allow server-side form handling
      try {
        form.submit();
      } catch (err) {
        setMsg('تعذر الإرسال.');
      }
    }
  });
}
