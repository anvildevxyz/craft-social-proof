/**
 * Event sender — posts {popupId, eventType, pageUrl} to the event endpoint.
 * Best-effort (fire-and-forget). Uses sendBeacon when available.
 */
(function () {
  'use strict';

  function send(endpoint, popupId, eventType) {
    var cfg = window.socialProofPopupConfig;
    if (cfg && typeof cfg.onBeforeTrack === 'function') {
      var allow;
      try { allow = cfg.onBeforeTrack(eventType, popupId); }
      catch (e) { allow = false; }
      if (!allow) return;
    }

    var payload = {
      popupId: popupId,
      eventType: eventType,
      pageUrl: window.location.pathname,
    };

    if (navigator.sendBeacon) {
      var blob = new Blob([JSON.stringify(payload)], { type: 'application/json' });
      var ok = navigator.sendBeacon(endpoint, blob);
      if (ok) return;
    }

    fetch(endpoint, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
      body: JSON.stringify(payload),
      credentials: 'same-origin',
      keepalive: true,
    }).catch(function () {
      fetch(endpoint, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify(payload),
        credentials: 'same-origin',
      }).catch(function () { /* give up */ });
    });
  }

  window.SmartPopupsEventSender = { send: send };
})();
