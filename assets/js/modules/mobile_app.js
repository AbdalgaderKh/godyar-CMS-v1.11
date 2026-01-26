/*
  Mobile app shell behaviors (safe, minimal)
  - toggles mobile menu
  - closes overlays on navigation
*/
(() => {
  'use strict';

  const $ = (sel, root = document) => root.querySelector(sel);

  const menuBtn = $('[data-mobile-menu-toggle]') || $('.mobile-menu-toggle') || $('#mobileMenuToggle');
  const menu = $('[data-mobile-menu]') || $('#mobileMenu') || $('.mobile-menu');

  if (menuBtn && menu) {
    menuBtn.addEventListener('click', () => {
      menu.classList.toggle('is-open');
      menuBtn.classList.toggle('is-open');
    });
  }

  // Close menu when clicking any link inside it
  if (menu) {
    menu.addEventListener('click', (e) => {
      const a = e.target && e.target.closest ? e.target.closest('a') : null;
      if (!a) return;
      menu.classList.remove('is-open');
      if (menuBtn) menuBtn.classList.remove('is-open');
    });
  }
})();
