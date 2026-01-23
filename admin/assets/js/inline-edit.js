/*!
 * Inline Edit
 * - Double click on elements with data-inline-edit="1"
 * - Requires: data-entity, data-id, data-field
 */
(function () {
  'use strict';

  function getCsrf() {
    var el = document.querySelector('input[name="csrf_token"]');
    return el ? (el.value || '') : '';
  }

  function ajax(data) {
    var base = (window.GDY_ADMIN_URL || '/admin').replace(/\/+$/, '');
    var url = base + '/api/inline_edit.php';
    return fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
      body: new URLSearchParams(data).toString()
    }).then(function (r) { return r.json(); });
  }

  document.addEventListener('dblclick', function (e) {
    var cell = e.target && e.target.closest ? e.target.closest('[data-inline-edit="1"]') : null;
    if (!cell) return;

    if (cell.querySelector('input')) return;

    var entity = cell.getAttribute('data-entity') || '';
    var id = cell.getAttribute('data-id') || '';
    var field = cell.getAttribute('data-field') || '';
    if (!entity || !id || !field) return;

    var oldText = (cell.textContent || '').trim();
    var input = document.createElement('input');
    input.type = 'text';
    input.className = 'form-control form-control-sm';
    input.value = oldText;
    input.style.minWidth = '160px';

    cell.textContent = '';
    cell.appendChild(input);
    input.focus();
    input.select();

    function restore(txt) {
      cell.textContent = txt;
    }

    function finish(save) {
      var val = (input.value || '').trim();
      if (!save) {
        restore(oldText);
        return;
      }
      ajax({
        csrf_token: getCsrf(),
        entity: entity,
        id: id,
        field: field,
        value: val
      }).then(function (res) {
        if (!res || !res.ok) {
          restore(oldText);
          alert('تعذر حفظ التعديل');
          return;
        }
        restore(val);
      }).catch(function () {
        restore(oldText);
        alert('تعذر حفظ التعديل');
      });
    }

    input.addEventListener('keydown', function (ev) {
      if (ev.key === 'Enter') finish(true);
      if (ev.key === 'Escape') finish(false);
    });
    input.addEventListener('blur', function () { finish(true); });
  });
})();
