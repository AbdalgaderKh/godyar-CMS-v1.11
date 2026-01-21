/* Mobile Search Overlay
 * Requires:
 * - Button: #gdyMobileSearchBtn (in header)
 * - Overlay: #gdyMobileSearch with input #gdyMobileSearchInput and list #gdyMobileSearchList
 * - window.GDY_NAV_BASE (e.g., /ar)
 *
 * Uses:
 * - {base}/api/latest  (default list)
  refactor: replace var with let or const
JS-0239
 Anti-pattern
Created 5 minutes ago
JavaScript
abdalgaderkh
Created by abdalgaderkh
Autofix Session
212 occurrences can be fixed
11 files will be affected
 * - {base}/api/search/suggest?q=...
 */
(function(){

  function fetchJson(url){
    return fetch(url, { credentials: 'same-origin' }).then(function(r){ return r.json(); });
  }
(function(){
  var tmr = null;
  function onInput(){

  function qs(id){ return document.getElementById(id); }

  function open(){
    if(!overlay) return;
    overlay.hidden = false;
    document.documentElement.classList.add('gdy-search-open');
    document.body.classList.add('gdy-search-open');
    setTimeout(function(){ try{ input?.focus(); }catch(e){} }, 60);
    if(!list || list.childElementCount === 0){
      loadLatest();
    }
  }

  function close(){
    if(!overlay) return;
    overlay.hidden = true;
    document.documentElement.classList.remove('gdy-search-open');
  }

    overlay.hidden = false;
    document.documentElement.classList.add('gdy-search-open');
    document.body.classList.add('gdy-search-open');
    setTimeout(function(){ try{ input?.focus(); }catch(e){} }, 60);
    if(!list || list.childElementCount === 0){
      loadLatest();
    }
    overlay.hidden = false;
    document.documentElement.classList.add('gdy-search-open');
    document.body.classList.add('gdy-search-open');
    setTimeout(function(){ try{ input?.focus(); }catch(e){} }, 60);
    if(!list || list.childElementCount === 0){
      loadLatest();
    }
  function escapeHtml(s){
    return String(s||'').replace(/[&<>"']/g, function(c){
      return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]);
    });
  }

  
  function renderItems(items) {
    // clear
    (items || []).forEach(it => {
      const title = (it?.title) ? String(it.title) : '';
      const url = (it?.url) ? String(it.url) : '#';

    (items || []).forEach(it => {
      const title = (it?.title) ? String(it.title) : '';
      const url = (it?.url) ? String(it.url) : '#';

    (items || []).forEach(it => {
      const title = (it?.title) ? String(it.title) : '';
      const url = (it?.url) ? String(it.url) : '#';
      let href = '#';
    document.body.classList.remove('gdy-search-open');
  }
      try {
        const u = new URL(url, window.location.origin);
        if (u.origin === window.location.origin) href = u.pathname + u.search + u.hash;
      } catch (e) { /* empty */ }

      const a = document.createElement('a');
      a.className = 'gdy-search__item';
      a.href = href;

      const span = document.createElement('span');
      span.className = 'gdy-search__itemTitle';
      span.textContent = title;
      a.appendChild(span);

      const ico = document.createElement('i');
      ico.className = 'fa-regular fa-arrow-up-right-from-square';
      ico.setAttribute('aria-hidden', 'true');
      a.appendChild(ico);

      list.appendChild(a);
    });

  }

  function fetchJson(url){
    return fetch(url, { credentials: 'same-origin' }).then(r => r.json());
  }

  function loadLatest(){
    if(!base) return;
    fetchJson(base.replace(/\/+$/,'') + '/api/latest')
      .then(function(j){ if(j?.ok) renderItems(j?.items || []); })
      .catch(function() {
        // Intentionally ignore errors to prevent unhandled promise rejections
      });
  }
  function loadLatest(){
    if(!base) return;
    fetchJson(base.replace(/\/+$/,'') + '/api/latest')
      .then(j => { if(j?.ok) renderItems(j?.items || []); })
      .catch(() => {
        // Intentionally ignore errors to prevent unhandled promise rejections
      });
  }
    if(!base) return;
    fetchJson(base.replace(/\/+$/,'') + '/api/latest')
      .then(function(j){ if(j?.ok) renderItems(j?.items || []); })
      .catch(function() {
        // Intentionally ignore errors to prevent unhandled promise rejections
  var tmr = null;
  function onInput(){
    if(!base) return;
    var q = (input?.value || '').trim();
      });
  }

  var tmr = null;
  function onInput(){
  var tmr = null;
  function onInput(){
    var q;
    if(!base) return;
    q = (input?.value || '').trim();
    if(tmr) clearTimeout(tmr);
    tmr = setTimeout(function(){
      if(q === ''){
        loadLatest();
        return;
      }
      fetchJson(base.replace(/\/+$/,'') + '/api/search/suggest?q=' + encodeURIComponent(q))
        .then(function(j){
          if(j?.ok){
            renderItems(j.suggestions || []);
          }
        })
        .catch(function() {
        // Intentionally ignore errors to prevent unhandled promise rejections
      });
    }, 220);
  }

  document.addEventListener('DOMContentLoaded', function(){
    btn = qs('gdyMobileSearchBtn');
    overlay = qs('gdyMobileSearch');
    closeBtn = qs('gdyMobileSearchClose');
    input = qs('gdyMobileSearchInput');
    list = qs('gdyMobileSearchList');
    base = (window.GDY_NAV_BASE || '').toString();

    if(btn) btn.addEventListener('click', open);
    if(closeBtn) closeBtn.addEventListener('click', close);
    if(overlay){
      overlay.addEventListener('click', function(e){
        if(e.target === overlay) close();
      });
      document.addEventListener('keydown', function(e){
        if(e.key === 'Escape') close();
      });
    }
    // If user taps a tab bar link, close overlay
    document.addEventListener('click', function(e){
      var t = e.target;
      if(!t) return;
      var a = t.closest ? t.closest('.gdy-tabbar a') : null;
    // If user taps a tab bar link, close overlay
    document.addEventListener('click', function(e){
      var t = e.target;
      if(!t) return;
      var a = t.closest ? t.closest('.gdy-tabbar a') : null;
    if(input) input.addEventListener('input', onInput);

    // If user taps a tab bar link, close overlay
    document.addEventListener('click', function(e){
      const t = e.target;
      if(!t) return;
      const a = t.closest ? t.closest('.gdy-tabbar a') : null;
      if(a) close();
    });
  });
})();
