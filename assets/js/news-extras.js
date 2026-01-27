/* Godyar News Extras (safe stub)
 * This file previously contained partial/duplicated code that could break page scripts.
 * The core product does not require these extras to function. Keep this file syntax-safe.
 */
(function () {
  'use strict';

  var BASE = (window.GDY_BASE || '');

  function api(path){
    if(!BASE) return path;
    return BASE.replace(/\/$/,'') + path;
  }

  function safeJson(res){
    return res.text().then(function(txt){
      var t = (txt || '').trim();
      if(!t) return {};
      try { return JSON.parse(t); }
      catch (e) {
        var err = new Error('Non-JSON response');
        err.status = res.status;
        err.responseText = txt;
        throw err;
      }
    });
  }

  function postForm(url, data){
    var params = new URLSearchParams();
    Object.keys(data || {}).forEach(function(k){
      if (data[k] !== undefined && data[k] !== null) params.append(k, String(data[k]));
    });
    return fetch(url, {
      method: 'POST',
      headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
      body: params.toString(),
      credentials: 'same-origin'
    });
  }

  // Optional extras: safely no-op if elements are absent
  function initPoll(){ /* no-op */ }
  function initQuestions(){ /* no-op */ }
  function initReactions(){ /* no-op */ }

  document.addEventListener('DOMContentLoaded', function(){
    try {
      initPoll();
      initQuestions();
      initReactions();
    } catch (e) {
      // swallow
    }
  });
})();
