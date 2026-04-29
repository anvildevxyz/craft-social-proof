/**
 * Social Proof - Statistics Page AJAX
 *
 * Handles period switching via AJAX, auto-refresh, and CSV export.
 */
(function () {
  'use strict';

  var config = window.spStatsConfig;
  if (!config) return;

  var currentPeriod = document.getElementById('sp-period-selector').dataset.currentPeriod || '7d';
  var refreshInterval = null;
  var REFRESH_MS = 60000;

  document.getElementById('sp-period-selector').addEventListener('click', function (e) {
    var btn = e.target.closest('[data-period]');
    if (!btn) return;

    var period = btn.dataset.period;
    if (period === currentPeriod) return;

    currentPeriod = period;

    // Update active button
    this.querySelectorAll('.sp-period-btn').forEach(function (b) {
      b.classList.toggle('active', b.dataset.period === period);
    });

    // Update browser URL without reload
    var url = new URL(window.location);
    url.searchParams.set('period', period);
    window.history.replaceState(null, '', url);

    fetchStats();
  });

  document.getElementById('sp-export-csv').addEventListener('click', function () {
    var rows = [['Date', 'Impressions', 'Clicks', 'Dismisses']];
    var tbody = document.querySelector('#sp-daily-table tbody');

    tbody.querySelectorAll('tr').forEach(function (tr) {
      var cells = tr.querySelectorAll('td');
      rows.push([
        cells[0].textContent.trim().replace(/\s+/g, ' '),
        cells[1].textContent.trim(),
        cells[2].textContent.trim(),
        cells[3].textContent.trim()
      ]);
    });

    var csv = rows.map(function (r) { return r.join(','); }).join('\n');
    var blob = new Blob([csv], { type: 'text/csv' });
    var url = URL.createObjectURL(blob);
    var a = document.createElement('a');
    a.href = url;
    a.download = 'social-proof-stats-' + currentPeriod + '.csv';
    a.click();
    URL.revokeObjectURL(url);
  });

  function fetchStats() {
    var url = config.dataUrl + (config.dataUrl.indexOf('?') > -1 ? '&' : '?') + 'period=' + encodeURIComponent(currentPeriod);

    fetch(url, {
      headers: { 'Accept': 'application/json' },
      credentials: 'same-origin'
    })
      .then(function (res) { return res.json(); })
      .then(function (data) {
        if (!data.success) return;
        updateCards(data.stats, data.recentOrderCount);
        updateDailyTable(data.stats.dailyStats);
        updateTopTable(data.stats.topNotifications);
      })
      .catch(function () {
        // Silent fail — stats will refresh on next interval
      });
  }

  function formatNumber(n) {
    return Number(n).toLocaleString();
  }

  function updateCards(stats, orderCount) {
    var el;
    el = document.getElementById('sp-val-impressions');
    if (el) el.textContent = formatNumber(stats.impressions);

    el = document.getElementById('sp-val-clicks');
    if (el) el.textContent = formatNumber(stats.clicks);

    el = document.getElementById('sp-val-ctr');
    if (el) el.textContent = stats.ctr + '%';

    el = document.getElementById('sp-val-visitors');
    if (el) el.textContent = formatNumber(stats.uniqueVisitors);

    el = document.getElementById('sp-val-orders');
    if (el) el.textContent = formatNumber(orderCount);
  }

  function updateDailyTable(dailyStats) {
    var tbody = document.querySelector('#sp-daily-table tbody');
    if (!tbody) return;

    var maxImpressions = 1;
    dailyStats.forEach(function (d) {
      if (d.impressions > maxImpressions) maxImpressions = d.impressions;
    });

    var reversed = dailyStats.slice().reverse();
    var html = '';

    reversed.forEach(function (day) {
      var date = new Date(day.date + 'T00:00:00');
      var dateStr = date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
      var weekday = date.toLocaleDateString('en-US', { weekday: 'short' });
      var barWidth = Math.round((day.impressions / maxImpressions) * 100);

      html += '<tr>' +
        '<td class="sp-date"><span class="sp-date-day">' + dateStr + '</span><span class="sp-date-weekday">' + weekday + '</span></td>' +
        '<td class="right sp-num">' + formatNumber(day.impressions) + '</td>' +
        '<td class="right sp-num">' + formatNumber(day.clicks) + '</td>' +
        '<td class="right sp-num">' + formatNumber(day.dismisses) + '</td>' +
        '<td class="sp-bar-cell"><div class="sp-mini-bar"><div class="sp-mini-bar-track"><div class="sp-mini-bar-fill" style="width:' + barWidth + '%"></div></div></div></td>' +
        '</tr>';
    });

    tbody.innerHTML = html;
  }

  function updateTopTable(topNotifications) {
    var section = document.getElementById('sp-top-section');
    if (!section) return;

    if (!topNotifications || !topNotifications.length) {
      section.innerHTML = '';
      return;
    }

    var html = '<div class="sp-panel">' +
      '<div class="sp-panel__header"><h2 class="sp-panel__title">Top Performing Notifications</h2></div>' +
      '<div class="sp-table-wrap"><table class="sp-table" id="sp-top-table"><thead><tr>' +
      '<th>Notification</th><th class="right">Impressions</th><th class="right">Clicks</th><th class="right">CTR</th>' +
      '</tr></thead><tbody>';

    topNotifications.forEach(function (item) {
      html += '<tr>' +
        '<td class="sp-notification-id">#' + item.notificationId + '</td>' +
        '<td class="right sp-num">' + formatNumber(item.impressions) + '</td>' +
        '<td class="right sp-num">' + formatNumber(item.clicks) + '</td>' +
        '<td class="right sp-num sp-ctr-val">' + item.ctr + '%</td>' +
        '</tr>';
    });

    html += '</tbody></table></div></div>';
    section.innerHTML = html;
  }

  // Auto-refresh: start when visible, stop when hidden
  function startAutoRefresh() {
    stopAutoRefresh();
    refreshInterval = setInterval(fetchStats, REFRESH_MS);
  }

  function stopAutoRefresh() {
    if (refreshInterval) {
      clearInterval(refreshInterval);
      refreshInterval = null;
    }
  }

  document.addEventListener('visibilitychange', function () {
    if (document.hidden) {
      stopAutoRefresh();
    } else {
      startAutoRefresh();
    }
  });

  startAutoRefresh();
})();
