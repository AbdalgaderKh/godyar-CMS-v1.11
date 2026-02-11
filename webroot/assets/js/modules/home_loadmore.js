/*
  Home Load More (Godyar CMS)
  - Adds "تحميل المزيد" buttons for latest/category sections on the home page.
  - Endpoint (default): /api/v1/home_loadmore.php
  - This script is defensive: if the expected markup/endpoint is absent, it does nothing.

  Expected markup (flexible):
    <button data-home-loadmore
            data-type="latest|category"
            data-category-id="123"        (for category)
            data-target="#latestList"     (CSS selector where items are appended)
            data-page="1">تحميل المزيد</button>

  Server response supported:
    - JSON with { ok:true, html:"<li>...</li>", next_page:2, has_more:true }
    - OR { ok:true, items:[...], next_page:2, has_more:true }
*/

'use strict';

const base = String(window.GDY_BASE || '').replace(/\/$/, '');
const API_URL = `${base}/api/v1/home_loadmore.php`;

const qs = (selector, rootEl = document) => rootEl.querySelector(selector);

const escapeHtml = (inputStr) =>
  String(inputStr ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');

const safeFragmentFromHTML = (html) => {
  const tpl = document.createElement('template');
  tpl.innerHTML = String(html || '');

  tpl.content.querySelectorAll('script, iframe, object, embed, style, link, meta').forEach((n) => n.remove());
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

const safeJson = async (res) => {
  const txt = await res.text();
  const trimmedText = (txt || '').trim();
  if (!trimmedText) return {};
  try {
    return JSON.parse(trimmedText);
  } catch (_) {
    return { ok: false, message: trimmedText, status: res.status };
  }
};

const newsUrl = (id) => `${base}/news/id/${encodeURIComponent(String(id))}`;

const buildItem = (item) => {
  const url = item?.url ? String(item.url) : newsUrl(item?.id);
  const title = escapeHtml(item?.title || '');
  const excerpt = escapeHtml(item?.excerpt || '');
  const thumb = item?.thumb ? String(item.thumb) : '';

  const li = document.createElement('li');
  li.className = 'gdy-home-item';

  li.innerHTML = `
    <a class="gdy-home-item__link" href="${escapeHtml(url)}">
      ${thumb ? `<img class="gdy-home-item__img" loading="lazy" src="${escapeHtml(thumb)}" alt="${title}">` : ''}
      <div class="gdy-home-item__body">
        <div class="gdy-home-item__title">${title}</div>
        ${excerpt ? `<div class="gdy-home-item__excerpt">${excerpt}</div>` : ''}
      </div>
    </a>
  `.trim();

  return li;
};

const requestMore = async (payload) => {
  const res = await fetch(API_URL, {
    method: 'POST',
    credentials: 'same-origin',
    headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    body: JSON.stringify(payload || {})
  });
  return safeJson(res);
};

const initButton = (btn) => {
  const type = (btn.getAttribute('data-type') || 'latest').toLowerCase();
  const categoryId = btn.getAttribute('data-category-id') || '';
  const targetSel = btn.getAttribute('data-target') || '';
  const target = targetSel ? qs(targetSel) : btn.closest('[data-home-section]')?.querySelector('ul,ol,div');

  if (!target) return;

  const getPage = () => {
    const pageValue = Number.parseInt(btn.getAttribute('data-page') || '1', 10);
    return Number.isFinite(pageValue) ? pageValue : 1;
  };

  const setPage = (pageNumber) => btn.setAttribute('data-page', String(pageNumber));

  btn.addEventListener('click', async (e) => {
    e.preventDefault();

    const page = getPage();
    btn.disabled = true;

    try {
      const payload = { type, page };
      if (type === 'category' && categoryId) payload.category_id = categoryId;

      const data = await requestMore(payload);

      if (!data?.ok) throw new Error(data?.message || 'Request failed');

      if (data.html) {
        target.appendChild(safeFragmentFromHTML(data.html));
      } else if (Array.isArray(data.items) && data.items.length) {
        data.items.forEach((it) => target.appendChild(buildItem(it)));
      } else {
        throw new Error('empty');
      }

      const nextPage = Number.parseInt(String(data.next_page || page + 1), 10);
      setPage(Number.isFinite(nextPage) ? nextPage : page + 1);

      const hasMore = data.has_more !== undefined ? Boolean(data.has_more) : true;
      if (!hasMore) btn.remove();
      else btn.disabled = false;
    } catch (_) {
      btn.disabled = false;
      // Keep UX minimal without console usage
      btn.textContent = 'تعذر التحميل - حاول مرة أخرى';
      setTimeout(() => {
        if (btn.isConnected) btn.textContent = 'تحميل المزيد';
      }, 2000);
    }
  });
};

document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('[data-home-loadmore]').forEach((btn) => initButton(btn));
});
