(function ($) {
  'use strict';

  var lastFocusedElement = null;

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

  $('#vtx-search').on('input', function () {
    var query = $(this).val().toLowerCase().trim();
    $('#vtx-rules-table tbody tr').each(function () {
      $(this).toggle(!query || String($(this).data('search')).indexOf(query) !== -1);
    });
  });

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
      $button.prop('disabled', false).text(VTXRedirects.findUsage);
    });
  });

  $(document).on('click', '.vtx-modal-close, .vtx-usage-modal', function (event) {
    if ($(event.target).is('.vtx-modal-close') || $(event.target).is('.vtx-usage-modal')) {
      closeModal();
    }
  });

  $(document).on('keydown', function (event) {
    if (event.key === 'Escape' && $('.vtx-usage-modal').hasClass('is-open')) {
      closeModal();
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
        $('.vtx-log-list').html('<p>' + escapeHtml(VTXRedirects.noLogs) + '</p>');
      }
    });
  });
})(jQuery);
