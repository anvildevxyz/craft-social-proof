/**
 * discount layout behavior — copy-to-clipboard for the code display, with
 * graceful fallback when navigator.clipboard is unavailable.
 */
(function () {
  'use strict';

  function attach(popupEl, candidate) {
    var btn = popupEl.querySelector('[data-smart-popup-copy]');
    var codeEl = popupEl.querySelector('[data-smart-popup-code]');
    if (!btn || !codeEl) return;

    var label = btn.querySelector('.smart-popup__copy-label');
    var code = codeEl.textContent;

    btn.addEventListener('click', async function () {
      var ok = false;
      if (navigator.clipboard && window.isSecureContext) {
        try {
          await navigator.clipboard.writeText(code);
          ok = true;
        } catch (e) { ok = false; }
      }
      if (!ok) {
        // Fallback: select + execCommand
        try {
          var range = document.createRange();
          range.selectNode(codeEl);
          var sel = window.getSelection();
          sel.removeAllRanges();
          sel.addRange(range);
          ok = document.execCommand('copy');
          sel.removeAllRanges();
        } catch (e) { ok = false; }
      }

      if (ok) {
        btn.setAttribute('data-copied', 'true');
        var prev = label ? label.textContent : '';
        if (label) label.textContent = 'Copied!';
        setTimeout(function () {
          btn.removeAttribute('data-copied');
          if (label) label.textContent = prev;
        }, 1500);
      }
    });
  }

  window.SmartPopupsLayouts = window.SmartPopupsLayouts || {};
  window.SmartPopupsLayouts['discount'] = { attach: attach };
})();
