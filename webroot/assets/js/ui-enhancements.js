/*
  UI enhancements (safe, minimal)
  - Smooth anchors
  - Back to top button (optional)
*/

'use strict';

// Smooth in-page anchors
document.addEventListener('click', (e) => {
  const anchor = e.target?.closest?.('a[href^="#"]') || null;
  if (!anchor) return;

  const href = anchor.getAttribute('href') || '';
  const id = href.startsWith('#') ? href.slice(1) : '';
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
    // Defensive (some browsers can throw if node is detached mid-scroll)
    if (!toTop.isConnected) return;
    toTop.style.display = window.scrollY > 400 ? '' : 'none';
  };

  window.addEventListener('scroll', onScroll, { passive: true });
  onScroll();
}
