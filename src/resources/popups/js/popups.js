/**
 * Smart Popups — main entry.
 *
 * Boots on DOMContentLoaded: parses the inline candidate JSON, instantiates
 * the right trigger per candidate, and wires firing → queue → renderer →
 * event-sender. Also exposes window.smartPopupsEventEndpoint so layout
 * behavior modules (newsletter, discount) can POST convert events.
 */
(function () {
  'use strict';

  function boot() {
    var dataEl = document.getElementById('social-proof-popups-data');
    if (!dataEl) return;

    var data;
    try { data = JSON.parse(dataEl.textContent); }
    catch (e) { return; }

    var candidates = data.candidates || [];
    var endpoint = data.eventEndpoint || '/social-proof/popups/api/event';
    window.smartPopupsEventEndpoint = endpoint;

    candidates.forEach(function (candidate) {
      var triggerType = (candidate.trigger || {}).type;
      var mod = window.SmartPopupsTriggers && window.SmartPopupsTriggers[triggerType];
      if (!mod) return;
      mod.register(candidate, onFire);
    });

    function onFire(candidate) {
      var shown = window.SmartPopupsQueue.show(
        candidate,
        function onDismiss(id) {
          window.SmartPopupsEventSender.send(endpoint, id, 'dismiss');
        },
        function onClick(id) {
          window.SmartPopupsEventSender.send(endpoint, id, 'click');
        }
      );
      if (shown) {
        window.SmartPopupsEventSender.send(endpoint, candidate.id, 'impression');
      }
    }
  }

  // Expose boot so async variant can re-run it after candidate fetch
  window.SmartPopupsBoot = boot;

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
