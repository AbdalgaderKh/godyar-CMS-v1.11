/**
 * Godyar Admin CSP Helper
 * Prevents CSP errors and ensures nonce-safe execution
 * Version: v1.0
 */

(function () {
  'use strict';

  // تأكد أن السكربت يعمل مرة واحدة فقط
  if (window.__GODYAR_CSP_LOADED__) return;
  window.__GODYAR_CSP_LOADED__ = true;

  /**
   * الحصول على nonce من أي سكربت موجود
   */
  function getNonce() {
    const s = document.querySelector('script[nonce]');
    return s ? s.getAttribute('nonce') : null;
  }

  const nonce = getNonce();

  /**
   * إضافة nonce تلقائي لأي سكربت يتم إنشاؤه ديناميكياً
   */
  if (nonce) {
    const originalCreateElement = document.createElement;
    document.createElement = function (tagName) {
      const el = originalCreateElement.call(document, tagName);
      if (tagName && tagName.toLowerCase() === 'script') {
        el.setAttribute('nonce', nonce);
      }
      return el;
    };
  }

  /**
   * حماية console في حال تم تعطيله
   */
  if (!window.console) {
    window.console = {
      log: function () {},
      warn: function () {},
      error: function () {},
    };
  }

  console.log('[Godyar] admin-csp.js loaded successfully');
})();
