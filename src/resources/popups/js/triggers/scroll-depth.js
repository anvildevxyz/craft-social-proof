/**
 * scroll-depth trigger — fires when vertical scroll crosses a percentage
 * threshold of document height. Fires once per page load.
 */
(function () {
  'use strict';

  function register(candidate, onFire) {
    var params = candidate.trigger || {};
    if (params.type !== 'scroll-depth') return;

    var threshold = params.percent != null ? Number(params.percent) : 50;
    var fired = false;

    function check() {
      if (fired) return;
      var docH = document.documentElement.scrollHeight;
      if (docH <= 0) return;
      var pct = (window.scrollY + window.innerHeight) / docH * 100;
      if (pct >= threshold) {
        fired = true;
        window.removeEventListener('scroll', check);
        onFire(candidate);
      }
    }

    window.addEventListener('scroll', check, { passive: true });
    // Fire immediately if the page already satisfies the threshold (short pages)
    check();
  }

  window.SmartPopupsTriggers = window.SmartPopupsTriggers || {};
  window.SmartPopupsTriggers['scroll-depth'] = { register: register };
})();
