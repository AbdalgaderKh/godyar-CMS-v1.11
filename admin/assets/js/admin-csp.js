/**
 * Admin CSP / security helpers.
 *
 * - Adds CSP nonce to dynamically created SCRIPT/STYLE/LINK tags when a nonce exists.
 * - Wraps window.fetch to automatically include CSRF token (if present on the page).
 *
 * This file is intentionally small and defensive; it should never break the admin UI.
 */

(function () {
  'use strict';

  function getMetaContent(name) {
    try {
      var el = document.querySelector('meta[name="' + name + '"]');
      return el ? (el.getAttribute('content') || '') : '';
    } catch (e) {
      return '';
    }
  }

  function getNonce() {
    return (
      getMetaContent('csp-nonce') ||
      getMetaContent('nonce') ||
      (typeof window.__CSP_NONCE === 'string' ? window.__CSP_NONCE : '') ||
      ''
    );
  }

  function getCsrfToken() {
    try {
      var meta = getMetaContent('csrf-token');
      if (meta) return meta;

      var inp = document.querySelector('input[name="csrf_token"], input[name="_csrf_token"]');
      return inp ? (inp.value || '') : '';
    } catch (e) {
      return '';
    }
  }

  function setNonce(el) {
    try {
      var nonce = getNonce();
      if (!nonce || !el || !el.setAttribute) return;
      if (!el.getAttribute('nonce')) el.setAttribute('nonce', nonce);
    } catch (e) {
      // ignore
    }
  }

  // Expose a tiny namespace for other admin scripts.
  window.AdminSecurity = window.AdminSecurity || {
    getNonce: getNonce,
    getCsrfToken: getCsrfToken,
    setNonce: setNonce,
  };

  // Wrap fetch() to include standard headers & CSRF.
  (function wrapFetch() {
    if (typeof window.fetch !== 'function') return;
    if (window.fetch.__csrfWrapped) return;

    var originalFetch = window.fetch;

    var wrapped = function (input, init) {
      try {
        init = init || {};

        // Ensure cookies for same-origin requests.
        if (!init.credentials) init.credentials = 'same-origin';

        // Normalise headers.
        var headers = new Headers(init.headers || {});
        if (!headers.has('X-Requested-With')) headers.set('X-Requested-With', 'XMLHttpRequest');

        var csrf = getCsrfToken();
        if (csrf && !headers.has('X-CSRF-Token')) {
          headers.set('X-CSRF-Token', csrf);
        }

        init.headers = headers;
      } catch (e) {
        // If something fails, fall back to the original request.
      }

      return originalFetch(input, init);
    };

    wrapped.__csrfWrapped = true;
    window.fetch = wrapped;
  })();
})();
