/**
 * Queue — enforces "one popup per slot at a time, once per page load".
 * Two slots: modal (covers most layouts) and bar (bottom-bar only).
 * Routes the candidate by candidate.layout.
 *
 * Exposes window.SmartPopupsQueue.
 */
(function () {
  'use strict';

  var shownThisPageLoad = new Set();

  function show(candidate, onDismiss, onClick) {
    if (!candidate || typeof candidate.id === 'undefined') return false;
    if (shownThisPageLoad.has(candidate.id)) return false;

    var isBar = candidate.layout === 'bottom-bar';
    var slotBusy = isBar ? window.SmartPopupsRenderer.isBarBusy() : window.SmartPopupsRenderer.isModalBusy();
    if (slotBusy) return false;

    var popupEl = isBar
      ? window.SmartPopupsRenderer.renderBar(candidate, onDismiss, onClick)
      : window.SmartPopupsRenderer.renderModal(candidate, onDismiss, onClick);

    if (popupEl) {
      shownThisPageLoad.add(candidate.id);
      // Notify layout modules (e.g. newsletter.js) that their popup is live
      if (window.SmartPopupsLayouts && window.SmartPopupsLayouts[candidate.layout]) {
        try {
          window.SmartPopupsLayouts[candidate.layout].attach(popupEl, candidate);
        } catch (e) { /* layout attach failures don't break the popup */ }
      }
      return true;
    }
    return false;
  }

  window.SmartPopupsQueue = { show: show };
})();
