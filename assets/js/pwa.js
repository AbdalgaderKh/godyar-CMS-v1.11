/*
 * PWA helper (stable)
 * - Registers the service worker
 * - Handles beforeinstallprompt with an explicit user gesture (button)
 * - Prompts for update when a new service worker is waiting
 */

'use strict';

const INSTALL_DISMISSED_KEY = 'pwa_install_dismissed_v1';
const UPDATE_DISMISSED_KEY = 'pwa_update_dismissed_v1';

const lsGet = (key) => {
  try {
    return window.localStorage.getItem(key);
  } catch (_) {
    return null;
  }
};

const lsSet = (key, val) => {
  try {
    window.localStorage.setItem(key, val);
  } catch (_) {
    // ignore (private mode / quota)
  }
};

const removeEl = (el) => el?.parentNode?.removeChild(el);

const createBanner = (id, titleText, bodyText, primaryText, secondaryText) => {
  // Avoid duplicates
  const existing = document.getElementById(id);
  if (existing) return existing;

  const wrap = document.createElement('div');
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

  const title = document.createElement('div');
  title.style.fontWeight = '700';
  title.style.marginBottom = '6px';
  title.textContent = titleText;

  const body = document.createElement('div');
  body.style.opacity = '0.9';
  body.style.marginBottom = '12px';
  body.textContent = bodyText;

  const actions = document.createElement('div');
  actions.style.display = 'flex';
  actions.style.gap = '10px';

  const primary = document.createElement('button');
  primary.type = 'button';
  primary.textContent = primaryText;
  primary.style.flex = '0 0 auto';
  primary.style.padding = '10px 14px';
  primary.style.borderRadius = '10px';
  primary.style.border = '0';
  primary.style.background = '#22c55e';
  primary.style.color = '#0b1220';
  primary.style.cursor = 'pointer';
  primary.style.fontWeight = '700';

  const secondary = document.createElement('button');
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

  // stash references for handlers
  wrap._primaryBtn = primary; // eslint-disable-line no-underscore-dangle
  wrap._secondaryBtn = secondary; // eslint-disable-line no-underscore-dangle

  document.body.appendChild(wrap);
  return wrap;
};

// ---------------------------------------------------------------------------
// Install prompt flow
// ---------------------------------------------------------------------------

let deferredPrompt = null;

const showInstallBanner = () => {
  if (!deferredPrompt) return;
  if (lsGet(INSTALL_DISMISSED_KEY) === '1') return;

  const banner = createBanner(
    'pwa-install-banner',
    'تثبيت التطبيق',
    'يمكنك تثبيت الموقع كتطبيق للوصول السريع.',
    'تثبيت',
    'لاحقاً'
  );

  banner._primaryBtn.onclick = () => {
    // Must be triggered by a user gesture
    const promptEvent = deferredPrompt;
    deferredPrompt = null;

    if (!promptEvent?.prompt) {
      removeEl(banner);
      return;
    }

    promptEvent
      .prompt()
      .then(() => {
        // userChoice may not exist in some browsers
        if (promptEvent.userChoice?.then) {
          return promptEvent.userChoice.then((choice) => {
            if (choice?.outcome !== 'accepted') lsSet(INSTALL_DISMISSED_KEY, '1');
          });
        }
        return null;
      })
      .finally(() => removeEl(banner));
  };

  banner._secondaryBtn.onclick = () => {
    lsSet(INSTALL_DISMISSED_KEY, '1');
    removeEl(banner);
  };
};

window.addEventListener('beforeinstallprompt', (e) => {
  // If you call preventDefault, Chrome will not auto-show the mini-infobar.
  // You must later call prompt() from a user gesture.
  e.preventDefault();
  deferredPrompt = e;
  showInstallBanner();
});

// ---------------------------------------------------------------------------
// Update prompt flow
// ---------------------------------------------------------------------------

const showUpdateBanner = (reg) => {
  if (!reg?.waiting) return;
  if (lsGet(UPDATE_DISMISSED_KEY) === '1') return;

  const banner = createBanner(
    'pwa-update-banner',
    'تحديث متوفر',
    'يوجد إصدار جديد. هل تريد تحديث الموقع الآن؟',
    'تحديث الآن',
    'لاحقاً'
  );

  banner._primaryBtn.onclick = () => {
    try {
      reg.waiting?.postMessage?.({ type: 'SKIP_WAITING' });
    } catch (_) {
      // ignore
    }
    removeEl(banner);
  };

  banner._secondaryBtn.onclick = () => {
    lsSet(UPDATE_DISMISSED_KEY, '1');
    removeEl(banner);
  };
};

// ---------------------------------------------------------------------------
// Service worker registration
// ---------------------------------------------------------------------------

const registerServiceWorker = () => {
  if (!('serviceWorker' in navigator)) return;

  navigator.serviceWorker
    .register('/sw.js', { scope: '/' })
    .then((reg) => {
      // If there's already a waiting SW (after refresh), offer update
      if (reg.waiting) showUpdateBanner(reg);

      reg.addEventListener('updatefound', () => {
        const newWorker = reg.installing;
        if (!newWorker) return;

        newWorker.addEventListener('statechange', () => {
          // Installed + controller exists => update ready
          if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
            showUpdateBanner(reg);
          }
        });
      });

      // Reload on controller change
      let reloaded = false;
      navigator.serviceWorker.addEventListener('controllerchange', () => {
        if (reloaded) return;
        reloaded = true;
        window.location.reload();
      });
    })
    .catch(() => {
      // ignore
    });
};

// Boot
document.addEventListener('DOMContentLoaded', () => {
  registerServiceWorker();
});
