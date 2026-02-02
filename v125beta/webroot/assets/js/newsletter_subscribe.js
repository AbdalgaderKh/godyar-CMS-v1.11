/*
 * Newsletter subscribe (idempotent)
 *
 * This script may be included on more than one layout/partial.
 * To avoid "Identifier has already been declared" errors we:
 *  - avoid top-level const/let
 *  - guard against double-initialization
 */

(function () {
  window.__godyar_js_loaded = window.__godyar_js_loaded || {};
  if (window.__godyar_js_loaded['newsletter_subscribe']) return;
  window.__godyar_js_loaded['newsletter_subscribe'] = true;

  if (window.__gdyNewsletterSubscribeLoaded) return;
  window.__gdyNewsletterSubscribeLoaded = true;

  function init() {
    var form = document.getElementById('newsletter-form');
    if (!form) return;

    // If the same DOM is rendered twice (edge-cases), don't bind twice
    if (form.dataset.gdyBound === '1') return;
    form.dataset.gdyBound = '1';

    form.addEventListener('submit', function (e) {
      e.preventDefault();
      var emailInput = document.getElementById('newsletter-email');
      var message = document.getElementById('newsletter-message');

      if (!emailInput || !message) return;

      var email = (emailInput.value || '').trim();
      if (!email) {
        message.textContent = 'يرجى إدخال بريد إلكتروني صحيح.';
        return;
      }

      fetch('/ajax/newsletter_subscribe.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ email: email }).toString(),
        credentials: 'same-origin'
      })
        .then(function (response) { return response.json(); })
        .then(function (data) {
          if (data && data.success) {
            message.textContent = 'تم الاشتراك بنجاح!';
            emailInput.value = '';
          } else {
            message.textContent = (data && data.message) ? data.message : 'حدث خطأ. حاول مرة أخرى.';
          }
        })
        .catch(function () {
          message.textContent = 'تعذر الاتصال بالخادم. حاول مرة أخرى.';
        });
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
