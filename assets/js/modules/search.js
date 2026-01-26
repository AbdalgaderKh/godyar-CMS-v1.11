/*
  Search module (safe, minimal)
  - Works with common search forms/inputs
  - No hard dependencies; fails gracefully
*/
(() => {
  'use strict';

  const $ = (sel, root = document) => root.querySelector(sel);

  const input =
    $("input[name='q']") ||
    $("input[name='query']") ||
    $("input[type='search']") ||
    $("#search") ||
    $("#searchInput") ||
    $(".search-input");

  const form = input ? input.closest('form') : $("form.search-form") || $("#searchForm");

  function buildUrl(q) {
    const url = new URL(window.location.origin + '/search');
    url.searchParams.set('q', q);
    return url.toString();
  }

  function go(q) {
    const query = String(q || '').trim();
    if (!query) return;
    window.location.href = buildUrl(query);
  }

  if (form) {
    form.addEventListener('submit', (e) => {
      const q = input ? input.value : (form.querySelector("input[type='search']") || {}).value;
      if (q && String(q).trim()) {
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
    const btn = e.target.closest('[data-search-submit]');
    if (!btn) return;
    e.preventDefault();
    go(input ? input.value : '');
  });
})();
