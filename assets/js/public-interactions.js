/*
  Public interactions (safe, minimal)
  - Copy link buttons
  - Share buttons using Web Share API
*/

'use strict';

// Copy-to-clipboard
document.addEventListener('click', async (e) => {
  const btn = e.target?.closest?.('[data-copy-link]') || null;
  if (!btn) return;

  e.preventDefault();
  const url = btn.getAttribute('data-copy-link') || window.location.href;

  try {
    if (navigator.clipboard?.writeText) {
      await navigator.clipboard.writeText(url);
    } else {
      const ta = document.createElement('textarea');
      ta.value = url;
      ta.style.position = 'fixed';
      ta.style.opacity = '0';
      document.body.appendChild(ta);
      ta.select();
      document.execCommand('copy');
      document.body.removeChild(ta);
    }

    btn.classList.add('copied');
    setTimeout(() => btn.classList.remove('copied'), 1500);
  } catch (_) {
    // Best-effort UX only; copying can fail due to permissions/HTTP.
  }
});

// Native share
document.addEventListener('click', async (e) => {
  const btn = e.target?.closest?.('[data-share]') || null;
  if (!btn) return;

  e.preventDefault();
  if (!navigator.share) return;

  try {
    await navigator.share({
      title: document.title,
      url: btn.getAttribute('data-share') || window.location.href
    });
  } catch (_) {
    // user cancelled / not allowed
  }
});
