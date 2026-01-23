/* admin/assets/js/admin-csp.js
 * هدف الملف: إزالة الاعتماد على inline event handlers (onclick/onsubmit/…)
 * ليكون متوافقًا مع CSP صارمة.
 *
 * هذه نسخة نظيفة تُركّز على:
 * - data-confirm: تأكيد قبل تنفيذ الروابط/الأزرار
 * - data-check-target: تحديد checkbox هدف
 * - data-action="uncheck-all": إلغاء تحديد جميع checkboxes في bulk form
 */
(function () {
  'use strict';

  function closest(el, selector) {
    while (el && el.nodeType === 1) {
      if (el.matches(selector)) return el;
      el = el.parentElement;
    }
    return null;
  }

  document.addEventListener('click', function (e) {
    var target = e.target;

    // stop propagation helper
    var stopEl = closest(target, '[data-stop-prop="1"]');
    if (stopEl) {
      e.stopPropagation();
      return;
    }

    // confirmation
    var confirmEl = closest(target, '[data-confirm]');
    if (confirmEl) {
      var msg = (confirmEl.getAttribute('data-confirm') || '').trim();
      if (msg && !window.confirm(msg)) {
        e.preventDefault();
        e.stopPropagation();
        return;
      }
    }

    // check target checkbox
    var checkEl = closest(target, '[data-check-target]');
    if (checkEl) {
      var sel = checkEl.getAttribute('data-check-target');
      if (sel) {
        var cb = document.querySelector(sel);
        if (cb) cb.checked = true;
      }
    }

    // bulk uncheck-all
    var actionEl = closest(target, '[data-action]');
    if (actionEl) {
      var action = actionEl.getAttribute('data-action');
      if (action === 'uncheck-all') {
        var form = closest(actionEl, 'form') || document.getElementById('bulkForm');
        if (form) {
          form.querySelectorAll('input[type="checkbox"][name="ids[]"]').forEach(function (cb) {
            cb.checked = false;
          });
        }
        e.preventDefault();
      }
    }
  });
})();
