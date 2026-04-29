/**
 * page-match trigger — fires immediately on boot. Server-side targeting has
 * already decided whether this candidate is eligible; the trigger just says
 * "show now, no behavioral wait".
 */
(function () {
  'use strict';

  function register(candidate, onFire) {
    var params = candidate.trigger || {};
    if (params.type !== 'page-match') return;
    onFire(candidate);
  }

  window.SmartPopupsTriggers = window.SmartPopupsTriggers || {};
  window.SmartPopupsTriggers['page-match'] = { register: register };
})();
