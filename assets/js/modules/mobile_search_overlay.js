/*
  Mobile search overlay (safe, minimal)
*/
(() => {
  'use strict';

  const openBtn = document.querySelector('[data-search-open]') || document.querySelector('.search-open');
  const closeBtn = document.querySelector('[data-search-close]') || document.querySelector('.search-close');
  const overlay = document.querySelector('[data-search-overlay]') || document.querySelector('.search-overlay');

  if (!overlay) return;

  function open() {
    overlay.classList.add('is-open');
    const inp = overlay.querySelector('input[type="search"],input[name="q"],input[name="query"],input');
    if (inp) setTimeout(() => inp.focus(), 0);
  }

  function close() {
    overlay.classList.remove('is-open');
  }

  if (openBtn) openBtn.addEventListener('click', (e) => {
    e.preventDefault();
    open();
  });
  if (closeBtn) closeBtn.addEventListener('click', (e) => {
    e.preventDefault();
    close();
  });

  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') close();
  });
})();
