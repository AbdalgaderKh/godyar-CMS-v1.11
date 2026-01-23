/* admin/assets/js/saved-filters.js
 * Saved Filters client helper (clean build)
 * Exposes window.GdySavedFilters with { list, create, del, setDefault }
 */
(function () {
  'use strict';

  function apiUrl(action, pageKey) {
    var base = (window.GDY_ADMIN_BASE || '/admin').replace(/\/+$/, '');
    var u = new URL(base + '/api/saved_filters.php', window.location.origin);
    u.searchParams.set('action', action);
    if (pageKey) u.searchParams.set('page_key', pageKey);
    return u.toString();
  }

  function encodeForm(obj) {
    var parts = [];
    Object.keys(obj || {}).forEach(function (k) {
      parts.push(encodeURIComponent(k) + '=' + encodeURIComponent(obj[k] == null ? '' : String(obj[k])));
    });
    return parts.join('&');
  }

  function post(url, data) {
    return fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
      body: encodeForm(data)
    }).then(function (r) { return r.json(); });
  }

  function normalizeListResponse(json) {
    if (!json || !json.ok) return { filters: [], supports_default: false, default_id: null };
    // legacy: data = []
    if (Array.isArray(json.data)) return { filters: json.data, supports_default: false, default_id: null };
    // new: data = {filters:[], supports_default:bool, default_id:int|null}
    if (json.data && typeof json.data === 'object' && Array.isArray(json.data.filters)) {
      return {
        filters: json.data.filters,
        supports_default: !!json.data.supports_default,
        default_id: json.data.default_id == null ? null : Number(json.data.default_id)
      };
    }
    return { filters: [], supports_default: false, default_id: null };
  }

  window.GdySavedFilters = {
    list: function (pageKey) {
      return fetch(apiUrl('list', pageKey))
        .then(function (r) { return r.json(); })
        .then(normalizeListResponse)
        .catch(function () { return { filters: [], supports_default: false, default_id: null }; });
    },

    create: function (pageKey, name, querystring, csrfToken, makeDefault) {
      return post(apiUrl('create', pageKey), {
        csrf_token: csrfToken || '',
        page_key: pageKey || '',
        name: name || '',
        querystring: querystring || '',
        make_default: makeDefault ? '1' : '0'
      });
    },

    del: function (pageKey, id, csrfToken) {
      return post(apiUrl('delete', pageKey), {
        csrf_token: csrfToken || '',
        page_key: pageKey || '',
        id: id || 0
      });
    },

    setDefault: function (pageKey, id, csrfToken) {
      return post(apiUrl('set_default', pageKey), {
        csrf_token: csrfToken || '',
        page_key: pageKey || '',
        id: id || 0
      });
    }
  };
})();
