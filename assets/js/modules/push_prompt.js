/*
  PWA install prompt helper (safe, minimal)
  Looks for a button/link with attribute: data-pwa-install
*/
(() => {
  'use strict';

  let deferredPrompt = null;

  window.addEventListener('beforeinstallprompt', (e) => {
    // Prevent the default mini-infobar
    e.preventDefault();
    deferredPrompt = e;

    const btn = document.querySelector('[data-pwa-install]');
    if (!btn) return;

    btn.style.display = '';

    btn.addEventListener(
      'click',
      async () => {
        if (!deferredPrompt) return;
        try {
          deferredPrompt.prompt();
          // userChoice exists on most browsers; ignore if missing
          if (deferredPrompt.userChoice) {
            await deferredPrompt.userChoice;
          }
        } catch (err) {
          // ignore
        } finally {
          deferredPrompt = null;
          btn.style.display = 'none';
        }
      },
      { once: true }
    );
  });

  window.addEventListener('appinstalled', () => {
    deferredPrompt = null;
    const btn = document.querySelector('[data-pwa-install]');
    if (btn) btn.style.display = 'none';
  });
})();
