/* Godyar CMS - app.js (compat)
 * Prevents 404/MIME errors for pages that still include /assets/js/app.js.
 */
(function(){
  if (window.__gdy_app_loaded) return;
  window.__gdy_app_loaded = true;

  // Load main.js (if your front-end already includes it, this will be harmless.)
  var s = document.createElement('script');
  s.src = '/assets/js/main.js';
  s.defer = true;
  document.head.appendChild(s);
})();
