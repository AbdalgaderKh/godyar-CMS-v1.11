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
  'use strict';

  var ATTR_FALLBACK = 'data-gdy-fallback-src';
  var ATTR_HIDE = 'data-gdy-hide-onerror';
  var ATTR_SHOW = 'data-gdy-show-onload';
  var ATTR_BOUND = 'data-gdy-fallback-bound';
  var ATTR_TRIED = 'data-gdy-fallback-tried';

  function isImg(el) {
    return el && el.tagName && el.tagName.toLowerCase() === 'img';
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
    } catch (_) {}
  }

  function safeSetOpacity(img, value) {
    try {
      img.style.opacity = value;
    } catch (_) {}
  }

  function onLoad(img) {
    if (img.getAttribute(ATTR_SHOW) === '1') {
      safeSetOpacity(img, '1');
    }
  }

  function onError(img) {
    // 1) Try fallback once if provided
    var fallback = img.getAttribute(ATTR_FALLBACK);
    var tried = img.getAttribute(ATTR_TRIED) === '1';

    if (fallback && !tried) {
      img.setAttribute(ATTR_TRIED, '1');
      // Prevent infinite loops if fallback equals current src
      var current = (img.getAttribute('src') || '').trim();
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

    img.addEventListener('load', function () { onLoad(img); }, { passive: true });
    img.addEventListener('error', function () { onError(img); }, { passive: true });

    // If image is already complete from cache
    if (img.complete && img.naturalWidth > 0) {
      onLoad(img);
    } else if (img.complete && img.naturalWidth === 0) {
      onError(img);
    }
  }

  function scan(root) {
    var scope = root && root.querySelectorAll ? root : document;
    var imgs = scope.querySelectorAll(
      'img[' + ATTR_FALLBACK + '],img[' + ATTR_HIDE + '],img[' + ATTR_SHOW + ']'
    );
    for (var i = 0; i < imgs.length; i++) bindOne(imgs[i]);
  }

  function init() {
    scan(document);

    // Watch for dynamically inserted images
    if (typeof MutationObserver === 'function') {
      var obs = new MutationObserver(function (mutations) {
        for (var i = 0; i < mutations.length; i++) {
          var m = mutations[i];
          if (!m.addedNodes) continue;
          for (var j = 0; j < m.addedNodes.length; j++) {
            var n = m.addedNodes[j];
            if (isImg(n)) {
              bindOne(n);
            } else if (n && n.querySelectorAll) {
              scan(n);
            }
          }
        }
      });
      obs.observe(document.documentElement || document.body, { childList: true, subtree: true });
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init, { once: true });
  } else {
    init();
  }
})();
