/**
 * time-on-page trigger — fires after N seconds of *active* time on the page.
 * Pauses counting when document.hidden.
 */
(function () {
  'use strict';

  function register(candidate, onFire) {
    var params = candidate.trigger || {};
    if (params.type !== 'time-on-page') return;

    var targetMs = (params.seconds || 10) * 1000;
    var accumulated = 0;
    var lastTick = document.hidden ? null : Date.now();

    function check() {
      if (!document.hidden && lastTick !== null) {
        accumulated += Date.now() - lastTick;
        lastTick = Date.now();
      }
      if (accumulated >= targetMs) {
        cleanup();
        onFire(candidate);
      }
    }

    var intervalId = setInterval(check, 500);

    function visibilityChange() {
      if (document.hidden) {
        if (lastTick !== null) {
          accumulated += Date.now() - lastTick;
          lastTick = null;
        }
      } else {
        lastTick = Date.now();
      }
    }

    function cleanup() {
      clearInterval(intervalId);
      document.removeEventListener('visibilitychange', visibilityChange);
    }

    document.addEventListener('visibilitychange', visibilityChange);
  }

  window.SmartPopupsTriggers = window.SmartPopupsTriggers || {};
  window.SmartPopupsTriggers['time-on-page'] = { register: register };
})();
