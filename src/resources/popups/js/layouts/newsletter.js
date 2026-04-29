/**
 * newsletter layout behavior — intercepts form submit, POSTs to webhookUrl,
 * shows success/error feedback, fires `convert` event on 2xx.
 */
(function () {
  'use strict';

  function attach(popupEl, candidate) {
    var form = popupEl.querySelector('.smart-popup__form');
    if (!form) return;

    var webhookUrl = form.getAttribute('data-webhook-url');
    var successText = form.getAttribute('data-success-text') || 'Thanks!';
    var submit = form.querySelector('.smart-popup__submit');
    var errorEl = form.querySelector('.smart-popup__form-error');
    var successEl = form.querySelector('.smart-popup__form-success');

    form.addEventListener('submit', async function (e) {
      e.preventDefault();
      errorEl.hidden = true;
      errorEl.textContent = '';

      if (!webhookUrl) {
        errorEl.textContent = 'Form not configured.';
        errorEl.hidden = false;
        return;
      }

      var email = form.querySelector('input[name="email"]').value.trim();
      if (!email) return;

      submit.disabled = true;

      var controller = typeof AbortController !== 'undefined' ? new AbortController() : null;
      var timeoutId = controller ? setTimeout(function () { controller.abort(); }, 10000) : null;

      try {
        var resp = await fetch(webhookUrl, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
          body: JSON.stringify({ email: email, popupId: candidate.id, pageUrl: window.location.pathname }),
          credentials: 'omit',
          signal: controller ? controller.signal : undefined,
        });
        if (timeoutId) clearTimeout(timeoutId);

        if (resp.ok) {
          form.style.display = 'none';
          successEl.textContent = successText;
          successEl.hidden = false;

          // Fire convert event via the shared event sender
          if (window.SmartPopupsEventSender && window.smartPopupsEventEndpoint) {
            window.SmartPopupsEventSender.send(window.smartPopupsEventEndpoint, candidate.id, 'convert');
          }
        } else {
          errorEl.textContent = 'Could not subscribe. Please try again later.';
          errorEl.hidden = false;
          submit.disabled = false;
        }
      } catch (err) {
        if (timeoutId) clearTimeout(timeoutId);
        errorEl.textContent = 'Network error. Please try again.';
        errorEl.hidden = false;
        submit.disabled = false;
      }
    });
  }

  window.SmartPopupsLayouts = window.SmartPopupsLayouts || {};
  window.SmartPopupsLayouts['newsletter'] = { attach: attach };
})();
