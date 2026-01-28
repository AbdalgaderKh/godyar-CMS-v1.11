/**
 * Inline Edit (Admin)
 *
 * A minimal inline-edit helper for tables.
 *
 * Markup contract (flexible):
 * - Elements with [data-inline-edit] act as edit triggers.
 *   Required attrs:
 *     data-inline-edit="1"
 *     data-endpoint="/admin/.../update.php"
 *     data-id="123"
 *     data-field="title"
 *   Optional:
 *     data-method="POST" (default)
 *
 * On click: prompts the user, posts JSON, expects JSON {ok:true, value:"..."}.
 */

'use strict';

const getCsrfToken = () => {
  try {
    if (window.AdminSecurity?.getCsrfToken) return window.AdminSecurity.getCsrfToken();

    const meta = document.querySelector('meta[name="csrf-token"]');
    if (meta) return meta.getAttribute('content') || '';

    const i1 = document.querySelector('input[name="csrf_token"]');
    if (i1) return i1.value || '';

    const i2 = document.querySelector('input[name="_csrf_token"]');
    if (i2) return i2.value || '';
  } catch (_) {
    // ignore
  }
  return '';
};

const postJson = async (url, payload, method = 'POST') => {
  const headers = new Headers({
    'Content-Type': 'application/json',
    'X-Requested-With': 'XMLHttpRequest'
  });

  const csrf = getCsrfToken();
  if (csrf) headers.set('X-CSRF-Token', csrf);

  const res = await fetch(url, {
    method,
    credentials: 'same-origin',
    headers,
    body: JSON.stringify(payload)
  });

  const text = await res.text();
  try {
    return JSON.parse(text);
  } catch (_) {
    return { ok: false, message: text || `HTTP ${res.status}` };
  }
};

const findValueTarget = (trigger) => {
  // Common pattern: trigger inside a TD and the value is in a span.
  const td = trigger.closest('td');
  if (!td) return null;
  return td.querySelector('[data-inline-value]') || td;
};

const onTriggerClick = async (e) => {
  const trigger = e.currentTarget;

  const endpoint = trigger.getAttribute('data-endpoint');
  const id = trigger.getAttribute('data-id');
  const field = trigger.getAttribute('data-field');
  const method = (trigger.getAttribute('data-method') || 'POST').toUpperCase();

  if (!endpoint || !id || !field) return;

  const valueTarget = findValueTarget(trigger);
  const currentValue = (valueTarget?.textContent || '').trim();

  const input = window.prompt('تعديل القيمة:', currentValue);
  if (input === null) return; // cancelled

  const newValue = String(input).trim();

  trigger.setAttribute('disabled', 'disabled');

  try {
    const resp = await postJson(endpoint, { id, field, value: newValue }, method);

    if (resp?.ok) {
      if (valueTarget) valueTarget.textContent = resp.value !== undefined ? resp.value : newValue;
    } else {
      const msg = resp?.message || resp?.error || 'تعذر حفظ التعديل.';
      alert(msg);
    }
  } catch (_) {
    alert('خطأ أثناء الحفظ.');
  } finally {
    trigger.removeAttribute('disabled');
  }
};

const init = () => {
  const triggers = document.querySelectorAll('[data-inline-edit]');
  if (!triggers.length) return;

  triggers.forEach((t) => t.addEventListener('click', onTriggerClick));
};

if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
else init();
