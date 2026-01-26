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
 *     data-type="text|number"
 *
 * On click: prompts the user, posts JSON, expects JSON {ok:true, value:"..."}.
 */

(function () {
  'use strict';

  function getCsrfToken() {
    try {
      if (window.AdminSecurity && typeof window.AdminSecurity.getCsrfToken === 'function') {
        return window.AdminSecurity.getCsrfToken();
      }
      var el = document.querySelector('meta[name="csrf-token"]');
      if (el) return el.getAttribute('content') || '';
      var i1 = document.querySelector('input[name="csrf_token"]');
      if (i1) return i1.value || '';
      var i2 = document.querySelector('input[name="_csrf_token"]');
      if (i2) return i2.value || '';
    } catch (e) {
      // ignore
    }
    return '';
  }

  async function postJson(url, payload, method) {
    var headers = {
      'Content-Type': 'application/json',
      'X-Requested-With': 'XMLHttpRequest'
    };
    var csrf = getCsrfToken();
    if (csrf) headers['X-CSRF-Token'] = csrf;

    var res = await fetch(url, {
      method: method || 'POST',
      credentials: 'same-origin',
      headers: headers,
      body: JSON.stringify(payload)
    });

    var text = await res.text();
    try {
      return JSON.parse(text);
    } catch (e) {
      return { ok: false, message: text || ('HTTP ' + res.status) };
    }
  }

  function findValueTarget(trigger) {
    // Common pattern: trigger inside a TD and the value is in a span.
    var td = trigger.closest('td');
    if (!td) return null;
    var span = td.querySelector('[data-inline-value]');
    return span || td;
  }

  async function onTriggerClick(e) {
    var trigger = e.currentTarget;
    var endpoint = trigger.getAttribute('data-endpoint');
    var id = trigger.getAttribute('data-id');
    var field = trigger.getAttribute('data-field');
    var method = (trigger.getAttribute('data-method') || 'POST').toUpperCase();

    if (!endpoint || !id || !field) return;

    var valueTarget = findValueTarget(trigger);
    var currentValue = valueTarget ? (valueTarget.textContent || '').trim() : '';

    var newValue = window.prompt('تعديل القيمة:', currentValue);
    if (newValue === null) return; // cancelled

    newValue = (newValue + '').trim();

    trigger.setAttribute('disabled', 'disabled');

    try {
      var resp = await postJson(endpoint, { id: id, field: field, value: newValue }, method);
      if (resp && resp.ok) {
        if (valueTarget) valueTarget.textContent = (resp.value !== undefined) ? resp.value : newValue;
      } else {
        var msg = (resp && (resp.message || resp.error)) ? (resp.message || resp.error) : 'تعذر حفظ التعديل.';
        alert(msg);
      }
    } catch (err) {
      alert('خطأ أثناء الحفظ.');
    } finally {
      trigger.removeAttribute('disabled');
    }
  }

  function init() {
    var triggers = document.querySelectorAll('[data-inline-edit]');
    if (!triggers.length) return;

    triggers.forEach(function (t) {
      t.addEventListener('click', onTriggerClick);
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
