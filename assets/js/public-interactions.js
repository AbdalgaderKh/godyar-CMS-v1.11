    if (el?.matches?.('select.js-auto-submit')) {
      var form = el.form;
      form?.submit();
    if (el?.matches?.('select.js-auto-submit')) {
      var form = el.form;
      form?.submit();
// Public interactions without inline event handlers
(function () {

  // Auto-submit selects
  document.addEventListener('change', function (e) {
    var el = e.target;
    if (el?.matches?.('select.js-auto-submit')) {
      var form = el.form;
      form?.submit();
    }
    if (!btn) return;
    if (!btn) return;
    if (!btn) return;
    if (!btn) return;
  function copyToClipboard(text, onSuccess) {
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(text).then(function () {
        if (typeof onSuccess === 'function') onSuccess();
      }).catch(function () {
  // Copy buttons (generic)
  document.addEventListener('click', function (e) {
    var btn = e.target?.closest?.('[data-copy-url]') || null;
    if (!btn) return;
  // Password toggle buttons
  document.addEventListener('click', function (e) {
    var btn = e.target?.closest('.password-toggle-btn');
    if (!btn) return;
  });

  // Copy buttons (generic)
  document.addEventListener('click', function (e) {
    var btn, url;
    btn = e.target?.closest?.('[data-copy-url]') || null;
    if (!btn) return;

    copyToClipboard(url, function () {
    btn = e.target?.closest?.('[data-copy-url]') || null;
    if (!btn) return;

    copyToClipboard(url, function () {
      var okMsg = btn.getAttribute('data-copy-success') || 'تم نسخ الرابط';
      var okMsg = btn.getAttribute('data-copy-success') || 'تم نسخ الرابط';
      customAlert(okMsg);
    });
  });

  // Password toggle buttons
  document.addEventListener('click', function (e) {
    var btn, inputId, input, icon;
    btn = e.target?.closest('.password-toggle-btn');
    if (!btn) return;

    var icon = document.getElementById(btn.getAttribute('data-icon') || 'passwordToggleIcon');
    if (!btn) return;

    var icon = document.getElementById(btn.getAttribute('data-icon') || 'passwordToggleIcon');

    try {
      var ta = document.createElement('textarea');
      ta.value = text;
  }
    try {
      var ta = document.createElement('textarea');
      ta.value = text;
      ta.setAttribute('readonly', '');
      ta.style.position = 'fixed';
      if (icon) icon.className = 'fa-regular fa-eye-slash';
    } else {
      input.type = 'password';
      if (icon) icon.className = 'fa-regular fa-eye';
    }
  });

  function copyToClipboard(text, onSuccess) {
    if (navigator.clipboard?.writeText) {
      navigator.clipboard.writeText(text).then(function () {
        onSuccess?.();
      }).catch(function () {
        fallbackCopy(text, onSuccess);
      });
    } else {
      fallbackCopy(text, onSuccess);
    }
  }

  function fallbackCopy(text, onSuccess) {
    try {
      var ta = document.createElement('textarea');
      ta.value = text;
      ta.setAttribute('readonly', '');
      ta.style.position = 'fixed';
      ta.style.left = '-9999px';
      document.body.appendChild(ta);
      ta.select();
      document.execCommand('copy');
      document.body.removeChild(ta);
      onSuccess?.();
    } catch (err) {
      // ignore
    }
  }
})();
