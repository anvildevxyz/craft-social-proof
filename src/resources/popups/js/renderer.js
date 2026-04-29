/**
 * Renderer — injects popup HTML into the DOM. Two slots: modal (most layouts)
 * and bar (bottom-bar only) so a bar can coexist with a modal.
 *
 * Exposes window.SmartPopupsRenderer.
 */
(function () {
  'use strict';

  var currentModal = null;  // { backdrop, candidate }
  var currentBar = null;    // { barEl, candidate }
  var previousFocus = null;

  function focusableEls(container) {
    return container.querySelectorAll(
      'a[href], button:not([disabled]), input:not([disabled]), textarea:not([disabled]), select:not([disabled]), [tabindex]:not([tabindex="-1"])'
    );
  }

  function trapFocus(container, event) {
    if (event.key !== 'Tab') return;
    var els = focusableEls(container);
    if (els.length === 0) return;
    var first = els[0];
    var last = els[els.length - 1];
    if (event.shiftKey && document.activeElement === first) {
      event.preventDefault();
      last.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
      event.preventDefault();
      first.focus();
    }
  }

  function renderModal(candidate, onDismiss, onClick) {
    if (currentModal) return false;

    var backdrop = document.createElement('div');
    backdrop.className = 'smart-popup-backdrop';
    backdrop.innerHTML = candidate.html;

    var popupEl = backdrop.querySelector('.smart-popup');
    if (!popupEl) return false;

    document.body.appendChild(backdrop);
    currentModal = { backdrop: backdrop, candidate: candidate };
    previousFocus = document.activeElement;

    var firstFocusable = focusableEls(popupEl)[0];
    if (firstFocusable) firstFocusable.focus();

    function dismiss() {
      if (!currentModal) return;
      document.body.removeChild(currentModal.backdrop);
      var id = currentModal.candidate.id;
      currentModal = null;
      document.removeEventListener('keydown', keyHandler);
      if (previousFocus && previousFocus.focus) previousFocus.focus();
      if (onDismiss) onDismiss(id);
    }

    function keyHandler(e) {
      if (e.key === 'Escape') { e.preventDefault(); dismiss(); return; }
      trapFocus(popupEl, e);
    }
    document.addEventListener('keydown', keyHandler);

    backdrop.addEventListener('click', function (e) {
      var actionEl = e.target.closest('[data-smartpopup-action]');
      if (actionEl) {
        var action = actionEl.getAttribute('data-smartpopup-action');
        if (action === 'dismiss') { e.preventDefault(); dismiss(); return; }
        if (action === 'click') { if (onClick) onClick(candidate.id); return; }
      }
      if (e.target === backdrop) dismiss();
    });

    // Expose the popup root so layout modules (e.g. newsletter.js) can bind to it
    return popupEl;
  }

  function renderBar(candidate, onDismiss, onClick) {
    if (currentBar) return false;

    var wrapper = document.createElement('div');
    wrapper.innerHTML = candidate.html;
    var barEl = wrapper.querySelector('.smart-popup');
    if (!barEl) return false;

    document.body.appendChild(barEl);
    currentBar = { barEl: barEl, candidate: candidate };

    function dismiss() {
      if (!currentBar) return;
      document.body.removeChild(currentBar.barEl);
      var id = currentBar.candidate.id;
      currentBar = null;
      if (onDismiss) onDismiss(id);
    }

    barEl.addEventListener('click', function (e) {
      var actionEl = e.target.closest('[data-smartpopup-action]');
      if (!actionEl) return;
      var action = actionEl.getAttribute('data-smartpopup-action');
      if (action === 'dismiss') { e.preventDefault(); dismiss(); return; }
      if (action === 'click') { if (onClick) onClick(candidate.id); return; }
    });

    // Bars are NOT focus-trapped (non-modal). ESC does NOT dismiss.
    return barEl;
  }

  function isModalBusy() { return currentModal !== null; }
  function isBarBusy() { return currentBar !== null; }

  window.SmartPopupsRenderer = {
    renderModal: renderModal,
    renderBar: renderBar,
    isModalBusy: isModalBusy,
    isBarBusy: isBarBusy,
  };
})();
