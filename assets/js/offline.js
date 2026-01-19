/* Godyar CMS - Offline page helpers (no inline JS) */
(function(){
  function qs(sel){return document.querySelector(sel);}
  function setStatus(isOnline){
    var el = qs('.status');
    if (!el) return;
    if (isOnline){
      el.textContent = 'تم الاتصال. جارٍ إعادة التحميل...';
      // Give the browser a moment then reload
      setTimeout(function(){ try{ location.reload(); }catch(_){/*noop*/} }, 400);
    } else {
      el.textContent = 'أنت غير متصل بالإنترنت.';
    }
  }

  document.addEventListener('DOMContentLoaded', function(){
    var btn = document.getElementById('btnReload');
    if(btn){
      btn.addEventListener('click', function(ev){
        ev.preventDefault();
        try{ location.reload(); }catch(_){/*noop*/}
      });
    }
    setStatus(navigator.onLine);
  });

  window.addEventListener('online', function(){ setStatus(true); });
  window.addEventListener('offline', function(){ setStatus(false); });
})();
