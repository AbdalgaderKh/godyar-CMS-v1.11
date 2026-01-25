/*
  Lazy images (safe, minimal)
  - Uses IntersectionObserver when available
  - Falls back to eager loading
*/
(() => {
  'use strict';

  const imgs = Array.from(document.querySelectorAll('img[data-src], source[data-srcset]'));
  if (!imgs.length) return;

  const load = (el) => {
    if (el.tagName.toLowerCase() === 'img' && el.dataset.src) {
      el.src = el.dataset.src;
      delete el.dataset.src;
    }
    if (el.tagName.toLowerCase() === 'source' && el.dataset.srcset) {
      el.srcset = el.dataset.srcset;
      delete el.dataset.srcset;
    }
    el.classList && el.classList.add('is-loaded');
  };

  if (!('IntersectionObserver' in window)) {
    imgs.forEach(load);
    return;
  }

  const io = new IntersectionObserver(
    (entries) => {
      entries.forEach((entry) => {
        if (!entry.isIntersecting) return;
        load(entry.target);
        io.unobserve(entry.target);
      });
    },
    { rootMargin: '200px 0px' }
  );

  imgs.forEach((el) => io.observe(el));
})();
