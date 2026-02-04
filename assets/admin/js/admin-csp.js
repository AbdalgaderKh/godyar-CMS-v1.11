/**
 * Admin CSP/CSRF helpers
 * - Exposes AdminSecurity namespace:
 *    - getNonce()
 *    - getCsrfToken()
 *    - setNonce(el)
 * - Wraps fetch() for same-origin requests to include standard headers + CSRF token.
 *
 * This is a *best-effort* helper; it must never break admin pages if something fails.
 */

'use strict';

const getMetaContent = (name) => {
  try {
    const el = document.querySelector(`meta[name="${name}"]`);
    return el ? el.getAttribute('content') || '' : '';
  } catch (_) {
    return '';
  }
};

const getNonce = () => getMetaContent('csp-nonce');

const getCsrfToken = () => {
  try {
    const meta = getMetaContent('csrf-token');
    if (meta) return meta;

    const inp = document.querySelector('input[name="csrf_token"], input[name="_csrf_token"]');
    return inp ? inp.value || '' : '';
  } catch (_) {
    return '';
  }
};

const setNonce = (el) => {
  try {
    const nonce = getNonce();
    if (!nonce || !el?.setAttribute) return;
    if (!el.getAttribute('nonce')) el.setAttribute('nonce', nonce);
  } catch (_) {
    // ignore
  }
};

// Expose a tiny namespace for other admin scripts.
window.AdminSecurity = window.AdminSecurity || { getNonce, getCsrfToken, setNonce };

// Wrap fetch() to include standard headers & CSRF.
(() => {
  if (typeof window.fetch !== 'function') return;
  if (window.fetch.__csrfWrapped) return;

  const originalFetch = window.fetch;

  const wrapped = (input, init) => {
    try {
      init = init || {};
      // Ensure cookies for same-origin requests.
      if (!init.credentials) init.credentials = 'same-origin';

      // Normalise headers.
      const headers = new Headers(init.headers || {});
      if (!headers.has('X-Requested-With')) headers.set('X-Requested-With', 'XMLHttpRequest');

      const csrf = getCsrfToken();
      if (csrf && !headers.has('X-CSRF-Token')) headers.set('X-CSRF-Token', csrf);

      init.headers = headers;
    } catch (_) {
      // If something fails, fall back to the original request.
    }

    return originalFetch(input, init);
  };

  wrapped.__csrfWrapped = true;
  window.fetch = wrapped;
})();
