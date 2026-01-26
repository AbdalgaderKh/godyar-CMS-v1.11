/*
  UI enhancements (safe, minimal)
  - Smooth anchors
  - Back to top button (optional)
*/
(() => {
  'use strict';

  // Smooth in-page anchors
  document.addEventListener('click', (e) => {
    const a = e.target && e.target.closest ? e.target.closest('a[href^="#"]') : null;
    if (!a) return;
    const id = a.getAttribute('href').slice(1);
    if (!id) return;
    const el = document.getElementById(id);
    if (!el) return;
    e.preventDefault();
    el.scrollIntoView({ behavior: 'smooth', block: 'start' });
  });

  // Back to top (if button exists)
  const toTop = document.querySelector('[data-back-to-top]') || document.querySelector('.back-to-top');
  if (toTop) {
    toTop.addEventListener('click', (e) => {
      e.preventDefault();
      window.scrollTo({ top: 0, behavior: 'smooth' });
    });

    const onScroll = () => {
      try {
        toTop.style.display = window.scrollY > 400 ? '' : 'none';
      } catch (_) {}
    };
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();
  }
})();
