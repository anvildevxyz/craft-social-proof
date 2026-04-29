/**
 * Async boot variant — fetches candidates from /api/candidates after DOMContentLoaded
 * instead of reading an inline script tag. Used by initPopupsAsync() for
 * full-page-cached hosts where the inline payload would be stale.
 */
(function () {
  'use strict';

  async function bootAsync() {
    var endpoint = '/social-proof/popups/api/candidates';
    try {
      var r = await fetch(endpoint, {
        headers: { 'Accept': 'application/json' },
        credentials: 'same-origin',
      });
      if (!r.ok) return;
      var data = await r.json();

      // Inject the candidate data where popups.js expects it
      var s = document.createElement('script');
      s.id = 'social-proof-popups-data';
      s.type = 'application/json';
      s.textContent = JSON.stringify(data);
      document.body.appendChild(s);

      if (window.SmartPopupsBoot) {
        window.SmartPopupsBoot();
      }
    } catch (e) { /* silent */ }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootAsync);
  } else {
    bootAsync();
  }
})();
