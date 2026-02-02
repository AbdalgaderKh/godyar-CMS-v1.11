/*
  Mobile tab bar active state (safe, minimal)
*/

'use strict';

const tabbar = document.querySelector('[data-mobile-tabbar]') || document.querySelector('.mobile-tabbar');
if (tabbar) {
  const current = (location.pathname || '/').replace(/\/+$/, '') || '/';
  const links = Array.from(tabbar.querySelectorAll('a[href]'));

  links.forEach((a) => {
    try {
      const url = new URL(a.getAttribute('href') || '', location.origin);
      const path = (url.pathname || '/').replace(/\/+$/, '') || '/';
      if (path === current) a.classList.add('active');
    } catch (_) {
      // ignore invalid hrefs
    }
  });
}
