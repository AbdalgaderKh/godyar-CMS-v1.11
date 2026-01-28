/*
  Mobile app shell behaviors (safe, minimal)
  - toggles mobile menu
  - closes overlays on navigation
*/

'use strict';

const $ = (sel, root = document) => root.querySelector(sel);

const menuBtn = $('[data-mobile-menu-toggle]') || $('.mobile-menu-toggle') || document.getElementById('mobileMenuToggle');
const menu = $('[data-mobile-menu]') || document.getElementById('mobileMenu') || $('.mobile-menu');

if (menuBtn && menu) {
  menuBtn.addEventListener('click', () => {
    menu.classList.toggle('is-open');
    menuBtn.classList.toggle('is-open');
  });
}

// Close menu when clicking any link inside it
menu?.addEventListener('click', (e) => {
  const anchor = e.target?.closest?.('a') || null;
  if (!anchor) return;
  menu.classList.remove('is-open');
  menuBtn?.classList.remove('is-open');
});
