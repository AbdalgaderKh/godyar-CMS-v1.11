/**
 * image_fallback.js
 *
 * Progressive image fallback handler (no inline JS needed).
 * - Supports:
 *   - data-gdy-fallback-src: fallback image URL if primary fails
 *   - data-gdy-hide-onerror: "1" to hide the img if it still fails
 *   - data-gdy-show-onload: "1" to unhide (opacity) when loaded
 *
 * This script is defensive and safe to run multiple times.
 */
(function () {
  const ATTR_FALLBACK = 'data-gdy-fallback-src';
  const ATTR_HIDE = 'data-gdy-hide-onerror';
  const ATTR_SHOW = 'data-gdy-show-onload';
  const ATTR_BOUND = 'data-gdy-fallback-bound';
  const ATTR_TRIED = 'data-gdy-fallback-tried';

  function isImg(el) {
    return el?.tagName?.toLowerCase() === 'img';
  }

  function shouldManage(img) {
    return (
      img.hasAttribute(ATTR_FALLBACK) ||
      img.hasAttribute(ATTR_HIDE) ||
      img.hasAttribute(ATTR_SHOW)
    );
  }

  function safeSetHidden(img, hidden) {
    try {
      img.style.display = hidden ? 'none' : '';
    } catch (_) {
      /* empty */
    }
  }

  function safeSetOpacity(img, value) {
    try {
      img.style.opacity = value;
    } catch (_) {
      /* empty */
    }
  }

  function onLoad(img) {
    if (img.getAttribute(ATTR_SHOW) === '1') {
      safeSetOpacity(img, '1');
    }
  }

  function onError(img) {
    // 1) Try fallback once if provided
    const fallback = img.getAttribute(ATTR_FALLBACK);
    const tried = img.getAttribute(ATTR_TRIED) === '1';

    if (fallback && !tried) {
      img.setAttribute(ATTR_TRIED, '1');

      // Prevent infinite loops if fallback equals current src
      const current = (img.getAttribute('src') || '').trim();
      if (current !== fallback) {
        img.setAttribute('src', fallback);
        return;
      }
    }

    // 2) If requested, hide the image
    if (img.getAttribute(ATTR_HIDE) === '1') {
      safeSetHidden(img, true);
    }
  }

  function bindOne(img) {
    if (!isImg(img) || !shouldManage(img)) return;
    if (img.getAttribute(ATTR_BOUND) === '1') return;

    img.setAttribute(ATTR_BOUND, '1');

    // If the page wants images hidden until loaded, start hidden.
    if (img.getAttribute(ATTR_SHOW) === '1') {
      // Avoid layout shift by using opacity rather than display.
      safeSetOpacity(img, '0');
    }

    img.addEventListener('load', () => onLoad(img), { passive: true });
    img.addEventListener('error', () => onError(img), { passive: true });

    // If image is already complete from cache
    if (img.complete && img.naturalWidth > 0) {
      onLoad(img);
    } else if (img.complete && img.naturalWidth === 0) {
      onError(img);
    }
  }

  function scan(root) {
    const scope = root?.querySelectorAll ? root : document;
    const imgs = scope.querySelectorAll(`img[${ATTR_FALLBACK}],img[${ATTR_HIDE}],img[${ATTR_SHOW}]`);
    for (let i = 0; i < imgs.length; i++) bindOne(imgs[i]);
  }

  function init() {
    scan(document);

    // Watch for dynamically inserted images
    if (typeof MutationObserver === 'function') {
      const observer = new MutationObserver((mutations) => {
        for (let i = 0; i < mutations.length; i++) {
          const mutation = mutations[i];
          if (!mutation.addedNodes) continue;

          for (let j = 0; j < mutation.addedNodes.length; j++) {
            const node = mutation.addedNodes[j];
            if (isImg(node)) {
              bindOne(node);
            } else if (node?.querySelectorAll) {
              scan(node);
            }
          }
        }
      });

      observer.observe(document.documentElement || document.body, { childList: true, subtree: true });
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init, { once: true });
  } else {
    init();
  }
})();
