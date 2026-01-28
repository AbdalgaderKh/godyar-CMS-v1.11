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
 * On click: opens a small modal, posts JSON, expects JSON {ok:true, value:"..."}.
 */

'use strict';

const getCsrfToken = () => {
  try {
    if (window.AdminSecurity?.getCsrfToken) return window.AdminSecurity.getCsrfToken();

    const meta = document.querySelector('meta[name="csrf-token"]');
    if (meta) return meta.getAttribute('content') || '';

    const input1 = document.querySelector('input[name="csrf_token"]');
    if (input1) return input1.value || '';

    const input2 = document.querySelector('input[name="_csrf_token"]');
    if (input2) return input2.value || '';
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

/* -------------------------------------------------------------------------- */
/* UI helpers (no alert/confirm/prompt)                                        */
/* -------------------------------------------------------------------------- */

const uiRoot = (() => {
  const id = 'gdy-inline-edit-ui';
  const existing = document.getElementById(id);
  if (existing) return existing;

  const root = document.createElement('div');
  root.id = id;
  root.setAttribute('dir', 'rtl');
  root.style.position = 'fixed';
  root.style.inset = '0';
  root.style.zIndex = '99999';
  root.style.pointerEvents = 'none';
  document.body.appendChild(root);
  return root;
})();

const showToast = (message, kind = 'info') => {
  try {
    const toast = document.createElement('div');
    toast.setAttribute('role', 'status');
    toast.style.pointerEvents = 'auto';
    toast.style.position = 'fixed';
    toast.style.bottom = '18px';
    toast.style.left = '18px';
    toast.style.maxWidth = 'min(420px, calc(100vw - 36px))';
    toast.style.padding = '10px 12px';
    toast.style.borderRadius = '10px';
    toast.style.fontSize = '14px';
    toast.style.boxShadow = '0 10px 25px rgba(0,0,0,.20)';
    toast.style.background = kind === 'error' ? '#fee2e2' : '#e0f2fe';
    toast.style.color = '#111827';
    toast.style.border = '1px solid rgba(0,0,0,.08)';
    toast.textContent = String(message || '');

    uiRoot.appendChild(toast);

    setTimeout(() => {
      try {
        toast.remove();
      } catch (_) {
        // ignore
      }
    }, 3200);
  } catch (_) {
    // ignore
  }
};

const promptModal = (titleText, defaultValue) =>
  new Promise((resolve) => {
    const overlay = document.createElement('div');
    overlay.style.pointerEvents = 'auto';
    overlay.style.position = 'fixed';
    overlay.style.inset = '0';
    overlay.style.background = 'rgba(0,0,0,.45)';
    overlay.style.display = 'flex';
    overlay.style.alignItems = 'center';
    overlay.style.justifyContent = 'center';
    overlay.style.padding = '16px';

    const card = document.createElement('div');
    card.style.width = 'min(520px, 100%)';
    card.style.background = '#ffffff';
    card.style.borderRadius = '14px';
    card.style.boxShadow = '0 15px 40px rgba(0,0,0,.25)';
    card.style.padding = '14px';
    card.style.border = '1px solid rgba(0,0,0,.10)';

    const title = document.createElement('div');
    title.style.fontWeight = '700';
    title.style.marginBottom = '10px';
    title.textContent = String(titleText || 'تعديل القيمة');

    const input = document.createElement('input');
    input.type = 'text';
    input.value = String(defaultValue ?? '');
    input.style.width = '100%';
    input.style.padding = '10px 12px';
    input.style.borderRadius = '10px';
    input.style.border = '1px solid rgba(0,0,0,.18)';
    input.style.outline = 'none';
    input.setAttribute('autocomplete', 'off');

    const actions = document.createElement('div');
    actions.style.display = 'flex';
    actions.style.justifyContent = 'flex-start';
    actions.style.gap = '10px';
    actions.style.marginTop = '12px';

    const okBtn = document.createElement('button');
    okBtn.type = 'button';
    okBtn.textContent = 'حفظ';
    okBtn.style.padding = '8px 12px';
    okBtn.style.borderRadius = '10px';
    okBtn.style.border = '1px solid rgba(0,0,0,.15)';
    okBtn.style.background = '#111827';
    okBtn.style.color = '#ffffff';
    okBtn.style.cursor = 'pointer';

    const cancelBtn = document.createElement('button');
    cancelBtn.type = 'button';
    cancelBtn.textContent = 'إلغاء';
    cancelBtn.style.padding = '8px 12px';
    cancelBtn.style.borderRadius = '10px';
    cancelBtn.style.border = '1px solid rgba(0,0,0,.15)';
    cancelBtn.style.background = '#ffffff';
    cancelBtn.style.color = '#111827';
    cancelBtn.style.cursor = 'pointer';

    const cleanup = () => {
      try {
        overlay.remove();
      } catch (_) {
        // ignore
      }
    };

    const accept = () => {
      const val = String(input.value ?? '').trim();
      cleanup();
      resolve(val);
    };

    const cancel = () => {
      cleanup();
      resolve(null);
    };

    okBtn.addEventListener('click', accept);
    cancelBtn.addEventListener('click', cancel);

    overlay.addEventListener('click', (e) => {
      if (e.target === overlay) cancel();
    });

    overlay.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') {
        e.preventDefault();
        cancel();
      } else if (e.key === 'Enter') {
        e.preventDefault();
        accept();
      }
    });

    actions.appendChild(okBtn);
    actions.appendChild(cancelBtn);

    card.appendChild(title);
    card.appendChild(input);
    card.appendChild(actions);
    overlay.appendChild(card);
    uiRoot.appendChild(overlay);

    setTimeout(() => {
      try {
        input.focus();
        input.select();
      } catch (_) {
        // ignore
      }
    }, 0);
  });

/* -------------------------------------------------------------------------- */

const onTriggerClick = async (e) => {
  const trigger = e.currentTarget;

  const endpoint = trigger.getAttribute('data-endpoint');
  const id = trigger.getAttribute('data-id');
  const field = trigger.getAttribute('data-field');
  const method = (trigger.getAttribute('data-method') || 'POST').toUpperCase();

  if (!endpoint || !id || !field) return;

  const valueTarget = findValueTarget(trigger);
  const currentValue = (valueTarget?.textContent || '').trim();

  const userInput = await promptModal('تعديل القيمة:', currentValue);
  if (userInput === null) return; // cancelled

  const newValue = String(userInput).trim();

  trigger.setAttribute('disabled', 'disabled');

  try {
    const resp = await postJson(endpoint, { id, field, value: newValue }, method);

    if (resp?.ok) {
      if (valueTarget) valueTarget.textContent = resp.value !== undefined ? resp.value : newValue;
      showToast('تم الحفظ.', 'info');
    } else {
      const msg = resp?.message || resp?.error || 'تعذر حفظ التعديل.';
      showToast(msg, 'error');
    }
  } catch (_) {
    showToast('خطأ أثناء الحفظ.', 'error');
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
