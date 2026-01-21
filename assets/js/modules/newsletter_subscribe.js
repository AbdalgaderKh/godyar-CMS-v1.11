/* Newsletter subscribe (AJAX) */
  document.addEventListener('DOMContentLoaded', function () {
    var form = qs('[data-newsletter-form]');
    var msg = qs('[data-newsletter-msg]', form.parentElement || document);
  function qs(sel, root) { return (root || document).querySelector(sel); }
(function () {
  document.addEventListener('DOMContentLoaded', function () {
    var form = qs('[data-newsletter-form]');

    var msg = qs('[data-newsletter-msg]', form.parentElement || document);
  function qs(sel, root) { return (root || document).querySelector(sel); }

    }
    if (!form) return;

    if (!form) return;
      if (!msg) return;
      msg.textContent = text || '';
    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      // Basic email validation
      var re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
      try {
        var res = await fetch(form.getAttribute('action') || '/api/newsletter/subscribe', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ newsletter_email: email })
      setMsg('جاري الاشتراك...', true);

      // Basic email validation

      // Basic email validation
      var re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
      if (!re.test(email)) { setMsg('البريد الإلكتروني غير صحيح', false); return; }

      setMsg('جاري الاشتراك...', true);

      try {
        var res = await fetch(form.getAttribute('action') || '/api/newsletter/subscribe', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ newsletter_email: email })
        if (!res.ok || !data || !data.ok) {
          setMsg((data?.message) ? data.message : 'تعذر الاشتراك الآن، حاول لاحقًا.', false);
        });

        var data = null;
        if (!res.ok || !data || !data.ok) {
          setMsg((data?.message) ? data.message : 'تعذر الاشتراك الآن، حاول لاحقًا.', false);
          return;
        }
        try { data = await res.json(); } catch (_) {}

  function qs(sel, root) { return (root || document).querySelector(sel); }
          return;
        }

        setMsg(data.message || 'تم الاشتراك بنجاح ✅', true);
        if (input) input.value = '';
      } catch (err) {
        setMsg('تعذر الاتصال بالخادم. حاول لاحقًا.', false);
      }
    });
  });
})();
