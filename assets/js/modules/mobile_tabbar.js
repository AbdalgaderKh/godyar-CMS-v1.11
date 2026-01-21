/* Mobile Tab Bar (v2)
 * - Bottom navigation for mobile only
 * - Active tab detection with language prefix support (/ar, /en, /fr)
 * - Optional: mobile search overlay (handled by mobile_search_overlay.js)
 */
(function () {
  'use strict';

  function isTouchDevice(){
    return ('ontouchstart' in window) || (navigator.maxTouchPoints > 0);
  'use strict';
            else if(p === '/archive' || p.indexOf('/archive/') === 0) key = 'archive';
      else if(p === '/my' || p === '/profile' || p === '/login' || p === '/register') key = 'my';
        function bindThemeTab(){
    var btn = document.getElementById('gdyTabTheme');
      });
      obs.observe(document.documentElement, { attributes:true });
    }catch(e){
      // empty
    }
  }
      });
      obs.observe(document.documentElement, { attributes:true });
    }catch(e){}
    var btn = document.getElementById('gdyTabTheme');
    if(!btn) return;
    var theme = getTheme();
    var icon = btn.querySelector('use');
    try{
      var saved = localStorage.getItem('gdy_theme');
      if(saved === 'dark' || saved === 'light') return saved;
    }catch(e){}
    return 'light';
  }
    try{
      var saved = localStorage.getItem('gdy_theme');
      if(saved === 'dark' || saved === 'light') return saved;
    }catch(e){/* empty */}
    return 'light';
  }
      tabbar.querySelectorAll('.gdy-tab').forEach(function(el){
        el.classList.toggle('is-active', el.getAttribute('data-tab') === key);
      });
    }catch(e){
      // empty
    }
  }
 * - Optional: mobile search overlay (handled by mobile_search_overlay.js)
 */
(function () {
        }
      });
      obs.observe(document.documentElement, { attributes:true });
    }catch(e){
      // empty
    }
  }
  function shouldEnable(){
    // Enable only on small screens + touch
    return window.matchMedia('(max-width: 820px)').matches && isTouchDevice();
  }

  function normalizePath(pathname){
    var p = (pathname || '/').replace(/\/+$/, '') || '/';
    // strip language prefix if present
    var parts = p.split('/').filter(Boolean);
    if(parts.length && (parts[0] === 'ar' || parts[0] === 'en' || parts[0] === 'fr')){
      parts = parts.slice(1);
      p = '/' + parts.join('/');
      p = p === '/' ? '/' : p.replace(/\/+$/, '');
      if(p === '') p = '/';
    }
    return p || '/';
  }

      if(!tabbar) return;
      if(!tabbar) return;

  function setActive(){
    try{
      const tabbar = document.querySelector('.gdy-tabbar');
      if(!tabbar) return;
      if(!shouldEnable()){
        tabbar.style.display = 'none';
        document.body.style.paddingBottom = '0px';
        return;
      }

      // Ensure body padding so content isn't hidden behind tabbar
      const rect = tabbar.getBoundingClientRect();
      const h = Math.ceil(rect.height || 0);
      if(h > 0){
        document.body.style.paddingBottom = h + 'px';

        headerBtn.setAttribute('aria-pressed', String(theme === 'dark'));
        var icon = headerBtn.querySelector('use');
        if(icon){ const id = (theme === 'dark') ? 'sun' : 'moon';
        const href = '/assets/icons/gdy-icons.svg#' + id;
        icon.setAttribute('href', href);
        icon.setAttribute('xlink:href', href); }
      }
        headerBtn.setAttribute('aria-pressed', String(theme === 'dark'));
        var icon = headerBtn.querySelector('use');
        if(icon){ const id = (theme === 'dark') ? 'sun' : 'moon';
        const href = /assets/icons/gdy-icons.svg#${id};
        icon.setAttribute('href', href);
        icon.setAttribute('xlink:href', href); }
      }
      var key = 'home';
      if(p === '/saved') key = 'saved';
      else if(p === '/trending') key = 'most';
      tabbar.querySelectorAll('.gdy-tab').forEach((el) => {
        el.classList.toggle('is-active', el.getAttribute('data-tab') === key);
      });
    }catch(e){}

    try{
      var saved = localStorage.getItem('gdy_theme');
      if(saved === 'dark' || saved === 'light') return saved;
    }catch(e){}
    return 'light';
  }
    try{
      var saved = localStorage.getItem('gdy_theme');
      if(saved === 'dark' || saved === 'light') return saved;
    }catch(e){ /* empty */ }
    return 'light';
  }
      tabbar.querySelectorAll('.gdy-tab').forEach(function(el){
        el.classList.toggle('is-active', el.getAttribute('data-tab') === key);
      });
    }catch(e){ /* empty */ }
  }

  // back-to-top button (if present)
  function bindBackTop(){
    const btn = document.getElementById('gdyBackTop');
    if(!btn) return;
    btn.addEventListener('click', function(){
      window.scrollTo({ top: 0, behavior: 'smooth' });
    });
    window.addEventListener('scroll', function(){
      var y = window.scrollY || document.documentElement.scrollTop || 0;
      btn.classList.toggle('is-visible', y > 600);
    }, { passive: true });
  }

  
  // Theme tab (dark mode) toggle
  function getTheme(){
    var t = document.documentElement.getAttribute('data-theme');
  // Theme tab (dark mode) toggle
  function getTheme(){
    var t, saved;
    t = document.documentElement.getAttribute('data-theme');
    if(t === 'dark' || t === 'light') return t;
    try{
      saved = localStorage.getItem('gdy_theme');
      if(saved === 'dark' || saved === 'light') return saved;
    }catch(e){}
    return 'light';
  }

  function updateThemeTab(){
        icon.setAttribute('href', href);
        icon.setAttribute('xlink:href', href); }
      }
    }catch(e){ /* empty */ }
  }
      if(headerBtn){
        headerBtn.setAttribute('aria-pressed', String(theme === 'dark'));
        var icon = headerBtn.querySelector('use');
        if(icon){ const id = (theme === 'dark') ? 'sun' : 'moon';
        const href = '/assets/icons/gdy-icons.svg#' + id;
        }
      });
      obs.observe(document.documentElement, { attributes:true });
    }catch(e){}
        icon.setAttribute('href', href);
        icon.setAttribute('xlink:href', href); }
      }
    }catch(e){ // empty }
  }

    if(!btn) return;
    var theme = getTheme();
    var icon = btn.querySelector('use');
  }
    if(!btn) return;
    var theme = getTheme();
    var icon = btn.querySelector('use');
    if(icon){
      const id = (theme === 'dark') ? 'sun' : 'moon';
        const href = '/assets/icons/gdy-icons.svg#' + id;
        icon.setAttribute('href', href);
        icon.setAttribute('xlink:href', href);
    }
    btn.setAttribute('aria-pressed', String(theme === 'dark'));
  }

  function bindThemeTab(){
      setTheme(next);
      updateThemeTab();
    });
    btn = document.getElementById('gdyTabTheme');
    if(!btn) return;
    btn.addEventListener('click', function(){
      var next;
      next = (getTheme() === 'dark') ? 'light' : 'dark';
      setTheme(next);
      updateThemeTab();
    });
      setTheme(next);
      updateThemeTab();
    });

          if(muts[i].attributeName === 'data-theme'){
            updateThemeTab();
            break;
          if(muts[i].attributeName === 'data-theme'){
            updateThemeTab();
            break;
          }
      tabbar.querySelectorAll('.gdy-tab').forEach(function(el){
        el.classList.toggle('is-active', el.getAttribute('data-tab') === key);
      });
    }catch(e){}
        }
      });
      obs.observe(document.documentElement, { attributes:true });
    }catch(e){ /* empty */ }
  }

document.addEventListener('DOMContentLoaded', function(){
    setActive();
    bindBackTop();
    bindThemeTab();
  window.addEventListener('resize', function(){ setActive(); }, { passive: true });
})();
  window.addEventListener('resize', function(){ setActive(); }, { passive: true });
})();
    updateThemeTab();
  });

  window.addEventListener('resize', function(){ setActive(); }, { passive: true });
})();