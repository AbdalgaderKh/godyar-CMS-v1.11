
/* GDY_FALLBACK_FIX_20260211
   Ensure fallback-bound images keep layout sizing and do not create unstyled duplicates. */
function __gdyApplyImgSizing(img){
  if(!img || !img.style) return;
  // If inside .ratio or card thumbs, force cover
  try{
    var inRatio = img.closest && img.closest('.ratio');
    var inCard  = img.closest && img.closest('.card');
    if(inRatio || inCard){
      img.style.width = '100%';
      img.style.height = '100%';
      img.style.objectFit = 'cover';
      img.style.display = 'block';
      return;
    }
  }catch(e){}
  // Otherwise safe defaults
  img.style.maxWidth = '100%';
  img.style.height = 'auto';
  img.style.display = 'block';
}

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


document.addEventListener('DOMContentLoaded', function(){
  try{
    var imgs = document.querySelectorAll('img[data-gdy-hide-onerror], img[data-gdy-fallback-bound]');
    imgs.forEach(function(img){
      __gdyApplyImgSizing(img);
      // If there is a duplicate sibling with same src, hide the later one (common in fallback scripts)
      try{
        var parent = img.parentElement;
        if(parent){
          var same = parent.querySelectorAll('img[src="' + img.getAttribute('src') + '"]');
          if(same && same.length > 1){
            for(var i=1;i<same.length;i++){
              // keep first visible
              same[i].style.display = 'none';
            }
          }
        }
      }catch(e){}
    });
  }catch(e){}
});


/* GDY_DUP_FIX_20260211
   Stronger duplicate image suppression:
   If an img has data-gdy-fallback-bound/hide-onerror and there is ANY other <img> with same src
   within the closest reasonable media container (a, figure, .ratio, .card, article), hide the fallback-bound one. */
(function(){
  function closestMediaRoot(el){
    if(!el || !el.closest) return null;
    return el.closest('.ratio, figure, a, .card, article, .col, .row, .container, #mainContent, body');
  }
  function sameSrcImgs(root, src){
    if(!root || !src) return [];
    try{
      return Array.prototype.slice.call(root.querySelectorAll('img[src="' + src.replace(/"/g,'\"') + '"]'));
    }catch(e){
      try{
        var all = Array.prototype.slice.call(root.querySelectorAll('img'));
        return all.filter(function(i){ return i.getAttribute('src') === src; });
      }catch(e2){ return []; }
    }
  }

  function runStrong(){
    try{
      var imgs = document.querySelectorAll('img[data-gdy-fallback-bound], img[data-gdy-hide-onerror]');
      imgs.forEach(function(img){
        var src = img.getAttribute('src');
        var root = closestMediaRoot(img) || img.parentElement;
        if(!root) return;
        var list = sameSrcImgs(root, src);
        if(list.length > 1){
          // Prefer keeping the one inside .ratio or with explicit cover sizing
          var keep = null;
          list.forEach(function(it){
            if(keep) return;
            try{
              if(it.closest && it.closest('.ratio')) keep = it;
              else if((it.getAttribute('style')||'').indexOf('object-fit:cover') !== -1) keep = it;
            }catch(e){}
          });
          if(!keep) keep = list[0];
          list.forEach(function(it){
            if(it === keep) return;
            // Hide duplicates
            it.style.display = 'none';
          });
        }
      });
    }catch(e){}
  }

  if(document.readyState === 'loading'){
    document.addEventListener('DOMContentLoaded', runStrong);
  } else {
    runStrong();
  }
})();
