/**
 * element-click trigger — fires on click of any element matching the configured
 * CSS selector. Uses event delegation so dynamically-added DOM triggers too.
 */
(function () {
  'use strict';

  function register(candidate, onFire) {
    var params = candidate.trigger || {};
    if (params.type !== 'element-click') return;

    var selector = params.selector;
    if (!selector || typeof selector !== 'string') return;

    var fired = false;

    function handler(e) {
      if (fired) return;
      var target;
      try {
        target = e.target.closest(selector);
      } catch (err) {
        console.warn('[smart-popups] invalid element-click selector:', selector, err);
        document.removeEventListener('click', handler);
        return;
      }
      if (target) {
        fired = true;
        document.removeEventListener('click', handler);
        onFire(candidate);
      }
    }

    document.addEventListener('click', handler);
  }

  window.SmartPopupsTriggers = window.SmartPopupsTriggers || {};
  window.SmartPopupsTriggers['element-click'] = { register: register };
})();
