/**
 * CP editor — shows/hides trigger- and layout-specific form fields based on
 * the active selection in the trigger and layout selects.
 *
 * Progressive enhancement: without JS, all fields are visible (fallback).
 */
(function () {
  'use strict';

  function applyConditional(rootAttr, currentValue) {
    var nodes = document.querySelectorAll('[' + rootAttr + ']');
    nodes.forEach(function (el) {
      var whenList = el.getAttribute(rootAttr).split(/\s*,\s*/);
      var shouldShow = whenList.indexOf(currentValue) !== -1;
      el.hidden = !shouldShow;
    });
  }

  function wirePreview() {
    document.querySelectorAll('[data-preview-width]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var iframe = document.querySelector('[data-preview-iframe]');
        if (iframe) iframe.style.maxWidth = btn.getAttribute('data-preview-width') + 'px';
      });
    });
    var refresh = document.querySelector('[data-preview-refresh]');
    if (refresh) {
      refresh.addEventListener('click', function () {
        var iframe = document.querySelector('[data-preview-iframe]');
        if (iframe) iframe.src = iframe.src;
      });
    }
  }

  function wire() {
    var triggerSelect = document.getElementById('triggerType');
    var layoutSelect = document.getElementById('layout');
    if (!triggerSelect && !layoutSelect) return;

    if (triggerSelect) {
      applyConditional('data-show-when-trigger', triggerSelect.value);
      triggerSelect.addEventListener('change', function () {
        applyConditional('data-show-when-trigger', triggerSelect.value);
      });
    }
    if (layoutSelect) {
      applyConditional('data-show-when-layout', layoutSelect.value);
      layoutSelect.addEventListener('change', function () {
        applyConditional('data-show-when-layout', layoutSelect.value);
      });
    }
    wirePreview();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', wire);
  } else {
    wire();
  }
})();
