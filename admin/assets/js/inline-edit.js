/*!
 * Inline Edit (Ultra Pack)
 * Usage:
 *   <td data-inline-edit="1" data-entity="tags" data-id="12" data-field="name">Tag name</td>
 */
(function () {
  function getCsrf() {
    var el = document.querySelector('input[name="csrf_token"]');
    return el ? el.value : '';
  document.addEventListener('dblclick', function (e) {
    var cell = e.target.closest('[data-inline-edit="1"]');

  function ajax(url, data) {
    return fetch(url, {
      method: 'POST',
      headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
      body: new URLSearchParams(data).toString()
  document.addEventListener('dblclick', function (e) {
    var cell = e.target.closest('[data-inline-edit="1"]');
    var entity = cell.getAttribute('data-entity') || '';
  }
    }).then(r => r.json());
  }

    var entity = cell.getAttribute('data-entity') || '';
  }

    var old = cell.textContent.trim();
    var input = document.createElement('input');
    input.type = 'text';
    input.className = 'form-control form-control-sm';

    const old = cell.textContent.trim();
    const input = document.createElement('input');
    input.type = 'text';
    input.className = 'form-control form-control-sm';

    if (cell.querySelector('input')) return;
    input.style.minWidth = '160px';

    cell.textContent = '';
    cell.appendChild(input);
    input.focus();
    input.select();

    function finish(save) {
      var val = input.value.trim();
      ajax(${window.GDY_ADMIN_URL || '/admin'}/api/inline_edit.php, {
        csrf_token: getCsrf(),
        entity: entity,
        id: id,

      ajax((window.GDY_ADMIN_URL || '/admin') + '/api/inline_edit.php', {
        csrf_token: getCsrf(),
        entity,
        id,
        field,
        value: val
      }).then(function (res) {
        if (!res || !res.ok) {
          cell.textContent = old;
          alert('تعذر حفظ التعديل');
        }
      }).catch(function () {
        cell.textContent = old;
        alert('تعذر حفظ التعديل');
    input.addEventListener('keydown', (ev) => {
      if (ev.key === 'Enter') finish(true);
      if (ev.key === 'Escape') finish(false);
    });

    input.addEventListener('keydown', function (ev) {
      if (ev.key === 'Enter') finish(true);
      if (ev.key === 'Escape') finish(false);
    });
    input.addEventListener('blur', function () { finish(true); });
  });
})();
