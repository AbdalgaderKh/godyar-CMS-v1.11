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

'use strict';

const ATTR_FALLBACK = 'data-gdy-fallback-src';
const ATTR_HIDE = 'data-gdy-hide-onerror';
const ATTR_SHOW = 'data-gdy-show-onload';
const ATTR_BOUND = 'data-gdy-fallback-bound';
const ATTR_TRIED = 'data-gdy-fallback-tried';

const isImg = (el) => el?.tagName?.toLowerCase() === 'img';

const shouldManage = (img) =>
  img.hasAttribute(ATTR_FALLBACK) || img.hasAttribute(ATTR_HIDE) || img.hasAttribute(ATTR_SHOW);

const safeSetHidden = (img, hidden) => {
  try {
    if (!img?.style) return;
    img.style.display = hidden ? 'none' : '';
  } catch (_) {
    // Some environments may throw if element is detached; ignore.
  }
};

const safeSetOpacity = (img, value) => {
  try {
    if (!img?.style) return;
    img.style.opacity = value;
  } catch (_) {
    // ignore
  }
};

const onLoad = (img) => {
  if (img.getAttribute(ATTR_SHOW) === '1') safeSetOpacity(img, '');
};

const onError = (img) => {
  // 1) Try fallback once if provided
  const fallback = img.getAttribute(ATTR_FALLBACK);
  const tried = img.getAttribute(ATTR_TRIED) === '1';

  if (fallback && !tried) {
    img.setAttribute(ATTR_TRIED, '1');
    // Prevent infinite loops if fallback equals current src
    const current = String(img.getAttribute('src') || '').trim();
    if (current !== fallback) {
      img.setAttribute('src', fallback);
      return;
    }
  }

  // 2) Hide if configured
  if (img.getAttribute(ATTR_HIDE) === '1') safeSetHidden(img, true);
};

const bindOne = (img) => {
  if (!img || !isImg(img) || !shouldManage(img)) return;
  if (img.getAttribute(ATTR_BOUND) === '1') return;
  img.setAttribute(ATTR_BOUND, '1');

  // Default to hidden while loading if show-onload is enabled.
  if (img.getAttribute(ATTR_SHOW) === '1') safeSetOpacity(img, '0');

  img.addEventListener('load', () => onLoad(img), { passive: true });
  img.addEventListener('error', () => onError(img), { passive: true });

  // If image is already complete from cache
  if (img.complete && img.naturalWidth > 0) onLoad(img);
};

const scan = (root) => {
  const scope = root?.querySelectorAll ? root : document;
  const imgs = scope.querySelectorAll(`img[${ATTR_FALLBACK}],img[${ATTR_HIDE}],img[${ATTR_SHOW}]`);
  for (const img of imgs) bindOne(img);
};

const init = () => {
  scan(document);

  // Watch for dynamically inserted images
  if (typeof MutationObserver === 'function') {
    const obs = new MutationObserver((mutations) => {
      for (const m of mutations) {
        if (!m.addedNodes) continue;
        for (const n of m.addedNodes) {
          if (isImg(n)) bindOne(n);
          else if (n?.querySelectorAll) scan(n);
        }
      }
    });
    obs.observe(document.documentElement || document.body, { childList: true, subtree: true });
  }
};

if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
else init();
