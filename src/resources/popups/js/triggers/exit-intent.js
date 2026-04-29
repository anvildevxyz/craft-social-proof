/**
 * exit-intent trigger — desktop: fires on mouseleave at the top of the viewport.
 * No reliable mobile equivalent; no-op on mobile.
 */
(function () {
  'use strict';

  var isMobile = /Mobi|Android|iPhone/i.test(navigator.userAgent);

  function register(candidate, onFire) {
    var params = candidate.trigger || {};
    if (params.type !== 'exit-intent') return;
    if (isMobile) return;

    var fired = false;
    function handler(e) {
      if (fired) return;
      if (e.clientY <= 0) {
        fired = true;
        cleanup();
        onFire(candidate);
      }
    }

    function cleanup() {
      document.removeEventListener('mouseleave', handler);
    }

    document.addEventListener('mouseleave', handler);
  }

  window.SmartPopupsTriggers = window.SmartPopupsTriggers || {};
  window.SmartPopupsTriggers['exit-intent'] = { register: register };
})();
