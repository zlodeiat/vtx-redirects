(function ($) {
  'use strict';

  var lastFocusedElement = null;
  var lastDrawerFocus = null;

  function escapeHtml(value) {
    return $('<div>').text(value == null ? '' : String(value)).html();
  }

  function openModal(html) {
    lastFocusedElement = document.activeElement;
    $('.vtx-usage-content').html(html);
    $('.vtx-usage-modal').addClass('is-open').attr('aria-hidden', 'false');
    $('.vtx-modal-close').trigger('focus');
  }

  function closeModal() {
    $('.vtx-usage-modal').removeClass('is-open').attr('aria-hidden', 'true');
    if (lastFocusedElement) {
      $(lastFocusedElement).trigger('focus');
    }
  }

  function activateTab(tab) {
    $('.vtx-tab').removeClass('is-active').attr('aria-selected', 'false');
    $('.vtx-panel').removeClass('is-active').attr('hidden', true);

    var $tab = $('[data-vtx-tab="' + tab + '"]');
    var $panel = $('[data-vtx-panel="' + tab + '"]');

    $tab.addClass('is-active').attr('aria-selected', 'true');
    $panel.addClass('is-active').removeAttr('hidden');
  }

  function openDrawer(view) {
    var title = view === 'settings' ? VTXRedirects.drawerSettings : VTXRedirects.drawerAdd;

    lastDrawerFocus = document.activeElement;
    $('[data-vtx-drawer-view]').attr('hidden', true);
    $('[data-vtx-drawer-view="' + view + '"]').removeAttr('hidden');
    $('[data-vtx-drawer-title]').text(title);
    $('.vtx-drawer-overlay').addClass('is-open').attr('aria-hidden', 'false');
    $('body').addClass('vtx-drawer-open');
    window.requestAnimationFrame(function () {
      $('.vtx-drawer-close').trigger('focus');
    });
  }

  function closeDrawer() {
    $('.vtx-drawer-overlay').removeClass('is-open').attr('aria-hidden', 'true');
    $('body').removeClass('vtx-drawer-open');
    if (lastDrawerFocus) {
      $(lastDrawerFocus).trigger('focus');
    }
  }

  function filterRows() {
    var query = String($('#vtx-search').val() || '').toLowerCase().trim();
    var status = String($('#vtx-status-filter').val() || 'all');

    $('#vtx-rules-table tbody tr').each(function () {
      var $row = $(this);
      var matchesQuery = !query || String($row.data('search') || '').indexOf(query) !== -1;
      var matchesStatus = status === 'all' || String($row.data('vtx-state') || '') === status;
      $row.toggle(matchesQuery && matchesStatus);
    });
  }

  $(document).on('click', '.vtx-tab', function () {
    activateTab($(this).data('vtx-tab'));
  });

  $(document).on('keydown', '.vtx-tab', function (event) {
    var $tabs = $('.vtx-tab');
    var index = $tabs.index(this);

    if (event.key === 'ArrowRight') {
      event.preventDefault();
      $tabs.eq((index + 1) % $tabs.length).trigger('focus').trigger('click');
    } else if (event.key === 'ArrowLeft') {
      event.preventDefault();
      $tabs.eq((index - 1 + $tabs.length) % $tabs.length).trigger('focus').trigger('click');
    }
  });

  $(document).on('click', '[data-vtx-drawer]', function () {
    openDrawer(String($(this).data('vtx-drawer') || 'add'));
  });

  $(document).on('click', '.vtx-drawer-close, .vtx-drawer-cancel', function () {
    closeDrawer();
  });

  $(document).on('click', '.vtx-drawer-overlay', function (event) {
    if ($(event.target).is('.vtx-drawer-overlay')) {
      closeDrawer();
    }
  });

  $('#vtx-search').on('input', filterRows);
  $('#vtx-status-filter').on('change', filterRows);

  $(document).on('click', '.vtx-delete-btn', function (event) {
    if (!window.confirm(VTXRedirects.confirmDelete)) {
      event.preventDefault();
    }
  });

  $(document).on('click', '.vtx-usage-btn', function () {
    var $button = $(this);
    $button.prop('disabled', true).text(VTXRedirects.scanning);
    openModal('<p>' + escapeHtml(VTXRedirects.scanning) + '</p>');

    $.post(VTXRedirects.ajaxUrl, {
      action: 'vtx_redirects_usage',
      nonce: VTXRedirects.nonce,
      source: $button.data('source'),
      destination: $button.data('destination')
    }).done(function (response) {
      if (!response || !response.success) {
        openModal('<p>' + escapeHtml(VTXRedirects.scanFailed) + '</p>');
        return;
      }

      var rows = response.data.results || [];
      if (!rows.length) {
        openModal('<p>' + escapeHtml(VTXRedirects.noResults) + '</p>');
        return;
      }

      var html = '<div class="vtx-usage-table-wrap"><table class="vtx-usage-table"><thead><tr><th>' + escapeHtml(VTXRedirects.columnItem) + '</th><th>' + escapeHtml(VTXRedirects.columnType) + '</th><th>' + escapeHtml(VTXRedirects.columnStatus) + '</th><th>' + escapeHtml(VTXRedirects.columnLinks) + '</th></tr></thead><tbody>';
      rows.forEach(function (row) {
        var edit = row.edit ? '<a class="button" href="' + escapeHtml(row.edit) + '">' + escapeHtml(VTXRedirects.edit) + '</a> ' : '';
        var view = row.view ? '<a class="button" target="_blank" rel="noopener noreferrer" href="' + escapeHtml(row.view) + '">' + escapeHtml(VTXRedirects.view) + '</a>' : '';
        html += '<tr><td><strong>' + escapeHtml(row.title) + '</strong><br><small>ID: ' + escapeHtml(row.id) + '</small></td><td>' + escapeHtml(row.type) + '</td><td>' + escapeHtml(row.status) + '</td><td>' + edit + view + '</td></tr>';
      });
      html += '</tbody></table></div>';
      openModal(html);
    }).fail(function () {
      openModal('<p>' + escapeHtml(VTXRedirects.scanFailed) + '</p>');
    }).always(function () {
      $button.prop('disabled', false).html('<span class="dashicons dashicons-search" aria-hidden="true"></span>' + escapeHtml(VTXRedirects.findUsage));
    });
  });

  $(document).on('click', '.vtx-modal-close, .vtx-usage-modal', function (event) {
    if ($(event.target).is('.vtx-modal-close') || $(event.target).is('.vtx-usage-modal')) {
      closeModal();
    }
  });

  $(document).on('keydown', function (event) {
    if (event.key !== 'Escape') {
      return;
    }

    if ($('.vtx-usage-modal').hasClass('is-open')) {
      closeModal();
    } else if ($('.vtx-drawer-overlay').hasClass('is-open')) {
      closeDrawer();
    }
  });

  $(document).on('click', '.vtx-clear-logs', function () {
    if (!window.confirm(VTXRedirects.confirmLogs)) {
      return;
    }

    $.post(VTXRedirects.ajaxUrl, {
      action: 'vtx_redirects_clear_logs',
      nonce: VTXRedirects.nonce
    }).done(function (response) {
      if (response && response.success) {
        $('.vtx-log-list').html('<div class="vtx-empty-compact"><span class="dashicons dashicons-backup" aria-hidden="true"></span><p>' + escapeHtml(VTXRedirects.noLogs) + '</p></div>');
      }
    });
  });
})(jQuery);
