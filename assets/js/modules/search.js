/*
  Search module (safe, minimal)
  - Works with common search forms/inputs
  - No hard dependencies; fails gracefully
*/

'use strict';

const $ = (sel, root = document) => root.querySelector(sel);

const input =
  $("input[name='q']") ||
  $("input[name='query']") ||
  $("input[type='search']") ||
  $('#search') ||
  $('#searchInput') ||
  $('.search-input');

const form = input?.closest('form') || $('form.search-form') || $('#searchForm');

const buildUrl = (q) => {
  const url = new URL(`${window.location.origin}/search`);
  url.searchParams.set('q', q);
  return url.toString();
};

const go = (q) => {
  const query = String(q || '').trim();
  if (!query) return;
  window.location.href = buildUrl(query);
};

if (form) {
  form.addEventListener('submit', (e) => {
    const q = input?.value || form.querySelector("input[type='search']")?.value || '';
    if (String(q).trim()) {
      // allow normal submit if action exists; otherwise route to /search?q=
      if (!form.getAttribute('action')) {
        e.preventDefault();
        go(q);
      }
    }
  });
}

// Optional: click-to-search buttons
document.addEventListener('click', (e) => {
  const btn = e.target?.closest?.('[data-search-submit]') || null;
  if (!btn) return;
  e.preventDefault();
  go(input?.value || '');
});
