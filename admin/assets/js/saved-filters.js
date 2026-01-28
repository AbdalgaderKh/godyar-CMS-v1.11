/**
 * Saved Filters (Admin)
 * - Persists current query string in localStorage for quick reuse.
 *
 * Markup:
 *  - Container: [data-saved-filters]
 *  - Save button: [data-save-filter]
 *  - Optional name input: [data-filter-name]
 */

'use strict';

const storageKey = () => `godyar_admin_saved_filters_v1:${window.location.pathname}`;

const safeParse = (json) => {
  try {
    return JSON.parse(json);
  } catch (_) {
    return null;
  }
};

const loadFilters = () => {
  const raw = window.localStorage.getItem(storageKey());
  const data = safeParse(raw || '[]');
  return Array.isArray(data) ? data : [];
};

const saveFilters = (filters) => {
  try {
    window.localStorage.setItem(storageKey(), JSON.stringify(filters || []));
  } catch (_) {
    // ignore
  }
};

const currentQueryString = () => {
  // Keep only query parameters (ignore hash). This is what we re-apply.
  const params = new URLSearchParams(window.location.search);

  // Remove typical non-filter parameters if present.
  params.delete('csrf_token');
  params.delete('_csrf_token');

  return params.toString();
};

const render = (container, filters) => {
  if (!container) return;

  container.innerHTML = '';

  if (!filters.length) {
    const empty = document.createElement('div');
    empty.className = 'text-muted small';
    empty.textContent = 'لا توجد فلاتر محفوظة.';
    container.appendChild(empty);
    return;
  }

  filters.forEach((f, idx) => {
    const row = document.createElement('div');
    row.className = 'd-flex align-items-center justify-content-between gap-2 py-1';

    const nameBtn = document.createElement('button');
    nameBtn.type = 'button';
    nameBtn.className = 'btn btn-sm btn-outline-secondary flex-grow-1 text-start';
    nameBtn.textContent = f?.name || `فلتر #${idx + 1}`;
    nameBtn.addEventListener('click', () => {
      const qs = String(f?.qs || '').trim();
      const base = window.location.pathname;
      window.location.href = qs ? `${base}?${qs}` : base;
    });

    const delBtn = document.createElement('button');
    delBtn.type = 'button';
    delBtn.className = 'btn btn-sm btn-outline-danger';
    delBtn.textContent = 'حذف';
    delBtn.addEventListener('click', () => {
      const next = loadFilters().filter((_, i) => i !== idx);
      saveFilters(next);
      render(container, next);
    });

    row.appendChild(nameBtn);
    row.appendChild(delBtn);
    container.appendChild(row);
  });
};

const init = () => {
  const container = document.querySelector('[data-saved-filters]');
  const saveBtn = document.querySelector('[data-save-filter]');
  const nameInput = document.querySelector('[data-filter-name]');

  // If the page has no saved-filters UI, do nothing.
  if (!container && !saveBtn) return;

  render(container, loadFilters());

  if (saveBtn) {
    saveBtn.addEventListener('click', () => {
      let name = nameInput?.value ? nameInput.value.trim() : '';
      if (!name) name = `فلتر ${new Date().toLocaleString()}`;

      const qs = currentQueryString();
      let next = loadFilters();
      next.unshift({ name, qs, saved_at: Date.now() });

      // Keep a reasonable max size.
      if (next.length > 20) next = next.slice(0, 20);

      saveFilters(next);
      render(container, next);

      if (nameInput) nameInput.value = '';
    });
  }
};

if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
else init();
