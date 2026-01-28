/*
  Mobile app shell behaviors (safe, minimal)
  - toggles mobile menu
  - closes overlays on navigation
*/

'use strict';

const qs = (selector, rootEl = document) => rootEl.querySelector(selector);

const menuToggleBtn =
  qs('[data-mobile-menu-toggle]') ||
  qs('.mobile-menu-toggle') ||
  document.getElementById('mobileMenuToggle');

const menuEl = qs('[data-mobile-menu]') || document.getElementById('mobileMenu') || qs('.mobile-menu');

if (menuToggleBtn && menuEl) {
  menuToggleBtn.addEventListener('click', () => {
    menuEl.classList.toggle('is-open');
    menuToggleBtn.classList.toggle('is-open');
  });
}

// Close menu when clicking any link inside it
menuEl?.addEventListener('click', (e) => {
  const anchorEl = e.target?.closest?.('a') || null;
  if (!anchorEl) return;
  menuEl.classList.remove('is-open');
  menuToggleBtn?.classList.remove('is-open');
});
