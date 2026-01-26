/**
 * Saved Filters (Admin)
 *
 * Lightweight client-side saved filters for list pages.
 * Defensive: if hooks are missing, it becomes a no-op.
 */

(function () {
  'use strict';

  function storageKey() {
    return 'godyar_admin_saved_filters_v1:' + window.location.pathname;
  }

  function safeParse(json) {
    try {
      return JSON.parse(json);
    } catch (e) {
      return [];
    }
  }

  function loadFilters() {
    var raw = window.localStorage.getItem(storageKey());
    var data = safeParse(raw || '[]');
    return Array.isArray(data) ? data : [];
  }

  function saveFilters(filters) {
    window.localStorage.setItem(storageKey(), JSON.stringify(filters));
  }

  function currentQueryString() {
    // Keep only query parameters (ignore hash). This is what we re-apply.
    var params = new URLSearchParams(window.location.search);
    // Remove typical non-filter parameters if present.
    params.delete('csrf_token');
    params.delete('_csrf_token');
    return params.toString();
  }

  function render(container, filters) {
    if (!container) return;

    container.innerHTML = '';

    if (!filters.length) {
      var empty = document.createElement('div');
      empty.className = 'text-muted small';
      empty.textContent = 'لا توجد فلاتر محفوظة.';
      container.appendChild(empty);
      return;
    }

    filters.forEach(function (f, idx) {
      var row = document.createElement('div');
      row.className = 'd-flex align-items-center justify-content-between gap-2 py-1';

      var name = document.createElement('button');
      name.type = 'button';
      name.className = 'btn btn-sm btn-outline-secondary flex-grow-1 text-start';
      name.textContent = f.name || ('فلتر #' + (idx + 1));
      name.addEventListener('click', function () {
        var qs = (f.qs || '').trim();
        var base = window.location.pathname;
        window.location.href = qs ? (base + '?' + qs) : base;
      });

      var del = document.createElement('button');
      del.type = 'button';
      del.className = 'btn btn-sm btn-outline-danger';
      del.textContent = 'حذف';
      del.addEventListener('click', function () {
        var next = loadFilters().filter(function (_x, i) { return i !== idx; });
        saveFilters(next);
        render(container, next);
      });

      row.appendChild(name);
      row.appendChild(del);
      container.appendChild(row);
    });
  }

  function init() {
    var container = document.querySelector('[data-saved-filters]');
    var saveBtn = document.querySelector('[data-save-filter]');
    var nameInput = document.querySelector('[data-filter-name]');

    // If the page has no saved-filters UI, do nothing.
    if (!container && !saveBtn) return;

    var filters = loadFilters();
    render(container, filters);

    if (saveBtn) {
      saveBtn.addEventListener('click', function () {
        var name = (nameInput && nameInput.value) ? nameInput.value.trim() : '';
        if (!name) name = 'فلتر ' + new Date().toLocaleString();

        var qs = currentQueryString();
        var next = loadFilters();
        next.unshift({ name: name, qs: qs, saved_at: Date.now() });

        // Keep a reasonable max size.
        if (next.length > 20) next = next.slice(0, 20);

        saveFilters(next);
        render(container, next);

        if (nameInput) nameInput.value = '';
      });
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
