/*
  Category page enhancements
  - View toggle (grid/list) with persistence
  - Load more (AJAX) if endpoint returns JSON { html, has_more, total_pages }
*/

'use strict';

const VIEW_KEY = 'gdy_category_view_v1';

const safeFragmentFromHTML = (html) => {
  const tpl = document.createElement('template');
  tpl.innerHTML = String(html || '');

  // Remove dangerous elements
  tpl.content.querySelectorAll('script, iframe, object, embed, style, link, meta').forEach((n) => n.remove());

  // Remove inline handlers + javascript: urls
  tpl.content.querySelectorAll('*').forEach((el) => {
    Array.from(el.attributes).forEach((a) => {
      const name = a.name.toLowerCase();
      const val = String(a.value || '');
      if (name.startsWith('on')) el.removeAttribute(a.name);
      if ((name === 'href' || name === 'src') && /^\s*javascript:/i.test(val)) el.removeAttribute(a.name);
    });
  });

  return tpl.content;
};

const fadeInImages = (root) => {
  const scope = root?.querySelectorAll ? root : document;
  scope.querySelectorAll('img').forEach((img) => {
    try {
      img.style.transition = img.style.transition || 'opacity .2s ease';
      if (!img.style.opacity) img.style.opacity = '0';
      const done = () => {
        img.style.opacity = '1';
      };
      if (img.complete) done();
      else {
        img.addEventListener('load', done, { once: true });
        img.addEventListener('error', done, { once: true });
      }
    } catch (_) {
      // ignore
    }
  });
};

const setupViewToggle = () => {
  const grid = document.getElementById('newsGrid');
  if (!grid) return;

  const btnGrid = document.getElementById('categoryViewGrid') || document.querySelector('[data-category-view="grid"]');
  const btnList = document.getElementById('categoryViewList') || document.querySelector('[data-category-view="list"]');

  const apply = (mode) => {
    const m = mode === 'list' ? 'list' : 'grid';
    grid.classList.toggle('is-list', m === 'list');
    grid.classList.toggle('is-grid', m === 'grid');
    try {
      window.localStorage.setItem(VIEW_KEY, m);
    } catch (_) {
      // ignore
    }
    btnGrid?.classList.toggle('active', m === 'grid');
    btnList?.classList.toggle('active', m === 'list');
  };

  // Load persisted mode
  try {
    const saved = window.localStorage.getItem(VIEW_KEY);
    if (saved) apply(saved);
  } catch (_) {
    // ignore
  }

  btnGrid?.addEventListener('click', (e) => {
    e.preventDefault();
    apply('grid');
  });

  btnList?.addEventListener('click', (e) => {
    e.preventDefault();
    apply('list');
  });
};

const safeJson = async (res) => {
  const txt = await res.text();
  const t = (txt || '').trim();
  if (!t) return {};
  try {
    return JSON.parse(t);
  } catch (_) {
    return { ok: false, message: t, status: res.status };
  }
};

const setupLoadMore = () => {
  const btn = document.getElementById('gdyCategoryLoadMore') || document.querySelector('[data-load-more]');
  const newsGrid = document.getElementById('newsGrid');
  const statusEl = document.getElementById('gdyLoadMoreStatus') || document.querySelector('[data-load-more-status]');

  if (!btn || !newsGrid || !statusEl) return;

  const baseUrl = btn.getAttribute('data-endpoint') || btn.getAttribute('data-url') || btn.getAttribute('href') || window.location.pathname;

  const parseIntSafe = (v, def) => {
    const n = Number.parseInt(String(v || ''), 10);
    return Number.isFinite(n) ? n : def;
  };

  const onClick = async (e) => {
    e.preventDefault();

    const currentPage = parseIntSafe(btn.dataset.currentPage, 1);
    const totalPages = parseIntSafe(btn.dataset.totalPages, 1);
    const nextPage = currentPage + 1;

    const url = new URL(baseUrl, window.location.origin);
    url.searchParams.set('page', String(nextPage));

    btn.disabled = true;
    statusEl.textContent = 'جارٍ تحميل المزيد...';

    try {
      const res = await fetch(url.toString(), {
        method: 'GET',
        headers: { Accept: 'application/json' },
        credentials: 'same-origin'
      });

      const data = await safeJson(res);
      const html = data?.html ? String(data.html) : '';

      if (!html) throw new Error(data?.message || 'empty');

      const frag = safeFragmentFromHTML(html);
      newsGrid.appendChild(frag);
      fadeInImages(newsGrid);

      btn.dataset.currentPage = String(nextPage);

      const hasMore = typeof data.has_more === 'boolean' ? data.has_more : nextPage < totalPages;

      if (!hasMore || nextPage >= totalPages) {
        btn.remove();
        statusEl.textContent = 'تم عرض كل الأخبار.';
      } else {
        btn.disabled = false;
        statusEl.textContent = '';
      }
    } catch (_) {
      btn.disabled = false;
      statusEl.textContent = 'تعذر تحميل المزيد. حاول مرة أخرى.';
    }
  };

  btn.addEventListener('click', onClick);
};

document.addEventListener('DOMContentLoaded', () => {
  fadeInImages(document);
  setupViewToggle();
  setupLoadMore();
});
