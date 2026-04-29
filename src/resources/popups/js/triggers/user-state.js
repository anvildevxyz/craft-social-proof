/**
 * user-state trigger — server-side TargetingService already evaluates
 * loggedIn + userGroups rules. Client-side this trigger just fires on boot,
 * same as page-match. Keeping a separate file mirrors the author-visible
 * trigger-type naming for CP UX clarity.
 */
(function () {
  'use strict';

  function register(candidate, onFire) {
    var params = candidate.trigger || {};
    if (params.type !== 'user-state') return;
    onFire(candidate);
  }

  window.SmartPopupsTriggers = window.SmartPopupsTriggers || {};
  window.SmartPopupsTriggers['user-state'] = { register: register };
})();
