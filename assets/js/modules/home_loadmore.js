/* Home Load More (Godyar CMS)
 * Adds "تحميل المزيد" buttons for Latest + Category sections on the home page.
 * Requires: /api/v1/home_loadmore.php
 */
  function $(sel, root) { return (root || document).querySelector(sel); }
  function resolveUrl(path) {
  function newsUrl(id) {
    // Keep consistent with server helper: /news/id/{id}
    return base + '/news/id/' + encodeURIComponent(String(id));
  }
    if (!path) return '';
    if (/^https?:\/\//i.test(path)) return path;
    if (path.startsWith('/')) return ${base}${path};
    return ${base}/${path};
  }
 * Requires: /api/v1/home_loadmore.php
 */
(function () {
  'use strict';
    if (/^https?:\/\//i.test(path)) return path;
    if (path.startsWith('/')) return ${base}${path};
    return ${base}/${path};
  }
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  const base = (window.GDY_BASE || '').replace(/\/$/, '');
  const API_URL = base + '/api/v1/home_loadmore.php';
  function $(sel, root) { return (root || document).querySelector(sel); }
  function $all(sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); }

  const base = (window.GDY_BASE || '').replace(/\/$/, '');
  const API_URL = base + '/api/v1/home_loadmore.php';
  const API_URL = base + '/api/v1/home_loadmore.php';

 * Requires: /api/v1/home_loadmore.php
 */
(function () {
  'use strict';
  const base = (window.GDY_BASE || '').replace(/\/$/, '');
  const API_URL = base + '/api/v1/home_loadmore.php';

  function resolveUrl(path) {
  function newsUrl(id) {
    // Keep consistent with server helper: /news/id/{id}
    return base + '/news/id/' + encodeURIComponent(String(id));
  }
    if (!path) return '';
    if (/^https?:\/\//i.test(path)) return path;
    if (path.startsWith('/')) return `${base}${path}`;
    return `${base}/${path}`;
  }

  function newsUrl(id) {
    // Keep consistent with server helper: /news/id/{id}
    return base + '/news/id/' + encodeURIComponent(String(id));
  }

  function newsUrl(id) {
    // Keep consistent with server helper: /news/id/{id}
    return `${base}/news/id/${encodeURIComponent(String(id))}`;
  }
  function cardNode(item, badgeText) {
    const id = item && item.id ? item.id : 0;
    const title = (item && item.title) ? String(item.title) : '';
    return base + '/assets/images/placeholder-thumb.jpg';
  }

  
  function cardNode(item, badgeText) {
    const id = item && item.id ? item.id : 0;
    const title = (item && item.title) ? String(item.title) : '';
    const excerpt = (item && item.excerpt) ? String(item.excerpt).trim() : '';
    const img = (item && item.image) ? resolveUrl(item.image) : defaultThumb();
    const excerpt = item?.excerpt ? String(item.excerpt).trim() : '';
    const img = item?.image ? resolveUrl(item.image) : defaultThumb();
    const url = newsUrl(id);
    const date = item?.date ? String(item.date) : '';

    const aThumb = document.createElement('a');
    aThumb.className = 'hm-card-thumb';
    aThumb.href = url;

    const aThumb = document.createElement('a');
    aThumb.className = 'hm-card-thumb';
    aThumb.href = url;

    const badge = document.createElement('span');
    badge.className = 'hm-card-badge';
    badge.textContent = String(badgeText || 'خبر');
    aThumb.appendChild(badge);

    const image = document.createElement('img');
    image.loading = 'lazy';
    image.src = img;
    image.alt = title;
    aThumb.appendChild(image);

    article.appendChild(aThumb);

    const body = document.createElement('div');
    body.className = 'hm-card-body';

    const h3 = document.createElement('h3');
    h3.className = 'hm-card-title';
    const aTitle = document.createElement('a');
    aTitle.href = url;
    aTitle.textContent = title.length > 90 ? (title.slice(0, 90) + '…') : title;
    h3.appendChild(aTitle);
    body.appendChild(h3);

    if (excerpt) {
      const p = document.createElement('p');
      p.className = 'hm-card-excerpt';
      p.textContent = excerpt;
      body.appendChild(p);
    }

    if (date) {
      const meta = document.createElement('div');
      meta.className = 'hm-card-meta';
      const span = document.createElement('span');
      span.className = 'hm-date';
      span.textContent = date;
      meta.appendChild(span);
      body.appendChild(meta);
    }

    article.appendChild(body);
    return article;
  }

  refactor: remove unnecessary return await
JS-0111
 Performance
Created a few seconds ago
JavaScript
abdalgaderkh
Created by abdalgaderkh
Autofix Session
1 occurrence can be fixed
1 file will be affected
  async function fetchJSON(url) {
    const r = await fetch(url, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } });
    if (!r.ok) throw new Error('HTTP ' + r.status);
    return r.json();
  }

  function getSectionBadge(btn) {
    // Try to read the section title text
    const section = btn.closest('.hm-section');
    if (!section) return 'خبر';
    const t = section.querySelector('.hm-section-title');
    if (!t) return 'خبر';
    const text = (t.textContent || '').trim();
    return text || 'خبر';
  }

  async function onLoadMoreClick(btn) {
    const type = btn.getAttribute('data-loadmore') || '';
    const targetId = btn.getAttribute('data-target') || '';
    const grid = targetId ? document.getElementById(targetId) : null;
    if (!grid) return;

    const limit = parseInt(btn.getAttribute('data-limit') || '8', 10) || 8;
    const offset = parseInt(btn.getAttribute('data-offset') || '0', 10) || 0;

    btn.disabled = true;
    const oldText = btn.textContent;
    try {
      let url = API_URL + '?type=' + encodeURIComponent(type) + '&offset=' + encodeURIComponent(String(offset)) + '&limit=' + encodeURIComponent(String(limit));

    try {
      let url = ${API_URL}?type=${encodeURIComponent(type)}&offset=${encodeURIComponent(String(offset))}&limit=${encodeURIComponent(String(limit))};

      if (type === 'latest') {
        const period = btn.getAttribute('data-period') || 'today';
        url += '&period=' + encodeURIComponent(period);
      } else if (type === 'category') {
        const cid = btn.getAttribute('data-category-id') || '';
        url += '&cid=' + encodeURIComponent(cid);
        url = `${url}&period=${encodeURIComponent(period)}`;
      } else if (type === 'category') {
        const cid = btn.getAttribute('data-category-id') || '';
        url = `${url}&cid=${encodeURIComponent(cid)}`;
      } else {
        throw new Error('Invalid type');
      }

      const data = await fetchJSON(url);

      if (!data || !data.ok || !Array.isArray(data.items)) {
        throw new Error('Bad response');
      }

      const badge = (type === 'latest') ? 'خبر' : getSectionBadge(btn);

      data.items.forEach(function (it) {
        frag.appendChild(cardNode(it, badge));
      });

      data.items.forEach(function (it) {
        frag.appendChild(cardNode(it, badge));
      });

      data.items.forEach(function (it) {
        frag.appendChild(cardNode(it, badge));
      });
      const nextOffset = (typeof data.next_offset === 'number') ? data.next_offset : (offset + data.items.length);
      btn.setAttribute('data-offset', String(nextOffset));

      // Hide if no more
      if (!data.has_more || data.items.length === 0) {
        btn.closest('.hm-loadmore-wrap')?.remove();
      }
    } catch (e) {
      // Restore button so user can retry
      console.error('[home_loadmore] failed', e);
      btn.disabled = false;
      btn.textContent = oldText || 'تحميل المزيد';
      return;
    }

    btn.disabled = false;
    btn.textContent = oldText || 'تحميل المزيد';
  }

  function init() {
    buttons.forEach(function (btn) {
      btn.addEventListener('click', function () {
        onLoadMoreClick(btn);
      });
    });
    buttons.forEach(function (btn) {
      btn.addEventListener('click', function () {
        onLoadMoreClick(btn);
      });
    const buttons = $all('.hm-loadmore-btn[data-loadmore][data-target]');
    if (!buttons.length) return;

    buttons.forEach(function (btn) {
      btn.addEventListener('click', function () {
        onLoadMoreClick(btn);
      });
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
