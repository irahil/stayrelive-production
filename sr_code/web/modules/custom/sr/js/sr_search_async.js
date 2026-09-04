(function ($, Drupal, drupalSettings, once) {
  'use strict';

  Drupal.behaviors.srSearchAsync = {
    attach: function (context, settings) {
      once('sr-search-async', '#sr-async-results-container, .results-region', context).forEach(function (container) {
        if (!settings.sr_search || !settings.sr_search.params) {
          return;
        }

        var queryParams = settings.sr_search.params;
        var resultWrapper = $(container);

        // Show skeleton loading state
        resultWrapper.html(
          '<div class="sr-skeleton-loader" style="padding: 20px; text-align: center;">' +
          '  <i class="fa-solid fa-spinner fa-spin fa-2x" style="color: #0B8D9F;"></i>' +
          '  <p style="margin-top: 10px; font-weight: 500;">Searching properties...</p>' +
          '</div>'
        );

        $.ajax({
          url: '/api/property/search',
          type: 'GET',
          data: queryParams,
          dataType: 'json',
          success: function (data) {
            if (!data || !data.search_result || data.search_result.length === 0) {
              resultWrapper.html(
                '<div style="text-align: center; padding: 30px;">' +
                '  <p><i class="fa-solid fa-triangle-exclamation fa-2x"></i></p>' +
                '  <h3>We did not find anything matching your search.</h3>' +
                '  <p>Try a different search or contact us.</p>' +
                '</div>'
              );
              return;
            }

            var html = '<table class="tbl-property"><tbody>';
            data.search_result.forEach(function (item) {
              var priceText = item.field_price ? 'Price USD ' + Math.ceil(item.field_price).toLocaleString() : 'Price on Request';
              var imageSrc = item.field_media || 'https://images.unsplash.com/photo-1517840901100-8179e982acb7?w=500';

              html += '<tr>';
              html += '  <td><div class="result-image"><img src="' + imageSrc + '" width="200" height="200" loading="lazy"/><span class="more-icon"><i class="fa-solid fa-circle-info"></i></span></div></td>';
              html += '  <td><div class="result-title"><h3><a href="' + item.link + '">' + item.title + '</a></h3></div></td>';
              html += '  <td>';
              if (item.field_property_source !== 'ratehawk' && item.field_property_source !== 'rategain') {
                html += '<div class="result-room"><span><i class="fa-solid fa-bed"></i> ' + (item.field_total_bedrooms || 0) + ' bedroom & </span>';
                html += '<span><i class="fa-solid fa-shower"></i> ' + (item.field_total_bathrooms || 0) + ' bathroom</span></div>';
              }
              html += '  </td>';
              html += '  <td><div class="result-price"><span><i class="fa-solid fa-credit-card"></i> ' + priceText + '</span></div></td>';
              html += '</tr>';
            });
            html += '</tbody></table>';

            if (data.search_pager) {
              html += '<div class="search-pager">' + data.search_pager + '</div>';
            }

            resultWrapper.html(html);

            // Trigger window resize or custom event to update Google Maps markers if needed
            $(document).trigger('sr_search_results_loaded', [data]);
          },
          error: function () {
            resultWrapper.html(
              '<div style="text-align: center; padding: 20px; color: #d9534f;">' +
              '  <p><i class="fa-solid fa-circle-exclamation fa-2x"></i></p>' +
              '  <p>Unable to load properties at this time. Please try refreshing.</p>' +
              '</div>'
            );
          }
        });
      });
    }
  };

})(jQuery, Drupal, drupalSettings, once);
