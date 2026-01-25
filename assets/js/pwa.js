/*
 * PWA helper (stable)
 * - Registers the service worker
 * - Handles beforeinstallprompt with an explicit user gesture (button)
 * - Prompts for update when a new service worker is waiting
 */

(function () {
  'use strict';

  var INSTALL_DISMISSED_KEY = 'pwa_install_dismissed_v1';
  var UPDATE_DISMISSED_KEY  = 'pwa_update_dismissed_v1';

  function lsGet(key) {
    try {
      return window.localStorage.getItem(key);
    } catch (e) {
      return null;
    }
  }

  function lsSet(key, val) {
    try {
      window.localStorage.setItem(key, val);
    } catch (e) {
      // ignore
    }
  }

  function removeEl(el) {
    if (el && el.parentNode) {
      el.parentNode.removeChild(el);
    }
  }

  function createBanner(id, titleText, bodyText, primaryText, secondaryText) {
    // Avoid duplicates
    if (document.getElementById(id)) return document.getElementById(id);

    var wrap = document.createElement('div');
    wrap.id = id;
    wrap.setAttribute('dir', 'rtl');
    wrap.style.position = 'fixed';
    wrap.style.left = '16px';
    wrap.style.right = '16px';
    wrap.style.bottom = '16px';
    wrap.style.zIndex = '99999';
    wrap.style.background = 'rgba(10, 18, 30, 0.95)';
    wrap.style.color = '#fff';
    wrap.style.borderRadius = '12px';
    wrap.style.padding = '14px';
    wrap.style.boxShadow = '0 10px 30px rgba(0,0,0,.35)';

    var title = document.createElement('div');
    title.style.fontWeight = '700';
    title.style.marginBottom = '6px';
    title.textContent = titleText;

    var body = document.createElement('div');
    body.style.opacity = '0.9';
    body.style.marginBottom = '12px';
    body.textContent = bodyText;

    var actions = document.createElement('div');
    actions.style.display = 'flex';
    actions.style.gap = '10px';

    var primary = document.createElement('button');
    primary.type = 'button';
    primary.textContent = primaryText;
    primary.style.flex = '0 0 auto';
    primary.style.padding = '10px 14px';
    primary.style.borderRadius = '10px';
    primary.style.border = '0';
    primary.style.cursor = 'pointer';
    primary.style.fontWeight = '700';

    var secondary = document.createElement('button');
    secondary.type = 'button';
    secondary.textContent = secondaryText;
    secondary.style.flex = '0 0 auto';
    secondary.style.padding = '10px 14px';
    secondary.style.borderRadius = '10px';
    secondary.style.border = '1px solid rgba(255,255,255,.25)';
    secondary.style.background = 'transparent';
    secondary.style.color = '#fff';
    secondary.style.cursor = 'pointer';

    actions.appendChild(primary);
    actions.appendChild(secondary);

    wrap.appendChild(title);
    wrap.appendChild(body);
    wrap.appendChild(actions);

    document.body.appendChild(wrap);

    // attach handles
    wrap._primaryBtn = primary;
    wrap._secondaryBtn = secondary;

    return wrap;
  }

  // ---------------------------------------------------------------------------
  // Service Worker + Update flow
  // ---------------------------------------------------------------------------

  function showUpdateBanner(reg) {
    if (!reg || !reg.waiting) return;
    if (lsGet(UPDATE_DISMISSED_KEY) === '1') return;

    var banner = createBanner(
      'pwa-update-banner',
      'تحديث متوفر',
      'يوجد إصدار جديد. هل تريد تحديث الموقع الآن؟',
      'تحديث الآن',
      'لاحقاً'
    );

    banner._primaryBtn.onclick = function () {
      try {
        reg.waiting.postMessage({ type: 'SKIP_WAITING' });
      } catch (e) {
        // ignore
      }
      removeEl(banner);
    };

    banner._secondaryBtn.onclick = function () {
      lsSet(UPDATE_DISMISSED_KEY, '1');
      removeEl(banner);
    };

    // Reload on controller change
    var reloaded = false;
    navigator.serviceWorker.addEventListener('controllerchange', function () {
      if (reloaded) return;
      reloaded = true;
      window.location.reload();
    });
  }

  function registerServiceWorker() {
    if (!('serviceWorker' in navigator)) return;

    navigator.serviceWorker
      .register('/sw.js', { scope: '/' })
      .then(function (reg) {
        // If there's already a waiting SW (after refresh), offer update
        if (reg.waiting) {
          showUpdateBanner(reg);
        }

        reg.addEventListener('updatefound', function () {
          var newWorker = reg.installing;
          if (!newWorker) return;

          newWorker.addEventListener('statechange', function () {
            // Installed + controller exists => update ready
            if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
              showUpdateBanner(reg);
            }
          });
        });
      })
      .catch(function () {
        // ignore
      });
  }

  // ---------------------------------------------------------------------------
  // Install prompt flow
  // ---------------------------------------------------------------------------

  var deferredPrompt = null;

  function showInstallBanner() {
    if (!deferredPrompt) return;
    if (lsGet(INSTALL_DISMISSED_KEY) === '1') return;

    var banner = createBanner(
      'pwa-install-banner',
      'تثبيت التطبيق',
      'يمكنك تثبيت الموقع كتطبيق للوصول السريع.',
      'تثبيت',
      'لاحقاً'
    );

    banner._primaryBtn.onclick = function () {
      // Must be triggered by a user gesture
      var p = deferredPrompt;
      deferredPrompt = null;

      if (!p || !p.prompt) {
        removeEl(banner);
        return;
      }

      try {
        p.prompt();
      } catch (e) {
        removeEl(banner);
        return;
      }

      // userChoice may not exist in some browsers
      if (p.userChoice && typeof p.userChoice.then === 'function') {
        p.userChoice
          .then(function (choice) {
            if (choice && choice.outcome !== 'accepted') {
              lsSet(INSTALL_DISMISSED_KEY, '1');
            }
          })
          .finally(function () {
            removeEl(banner);
          });
      } else {
        removeEl(banner);
      }
    };

    banner._secondaryBtn.onclick = function () {
      lsSet(INSTALL_DISMISSED_KEY, '1');
      deferredPrompt = null;
      removeEl(banner);
    };
  }

  window.addEventListener('beforeinstallprompt', function (e) {
    // If you call preventDefault, Chrome will not auto-show the mini-infobar.
    // You must later call prompt() from a user gesture.
    e.preventDefault();
    deferredPrompt = e;
    showInstallBanner();
  });

  // ---------------------------------------------------------------------------
  // Boot
  // ---------------------------------------------------------------------------

  document.addEventListener('DOMContentLoaded', function () {
    registerServiceWorker();
  });

})();
