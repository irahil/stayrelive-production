// (function ($, Drupal, once) {
//   Drupal.behaviors.srQuotationTimepicker = {
//     attach: function (context, settings) {
//       once('srQuotationTimepicker', '.timepicker', context).forEach(function (element) {
//         flatpickr(element, {
//           enableTime: true,
//           noCalendar: true,
//           dateFormat: "H:i",
//           time_24hr: true,
//           minuteIncrement: 5,
//           allowInput: true,
//           clickOpens: true,
//           wrap: false,
//           theme: "material_blue", // (optional) if theme added
//           onReady: function (selectedDates, dateStr, instance) {
//             // Add clock icon if not already present
//             if (!$(instance.input).siblings('.time-icon').length) {
//               $(instance.input).wrap('<div class="timepicker-wrapper"></div>');
//               $('<span class="time-icon"><i class="far fa-clock"></i></span>').insertAfter(instance.input);
//             }
//           },
//         });
//       });
//     }
//   };
// })(jQuery, Drupal, once);

(function ($, Drupal, once) {
  Drupal.behaviors.srQuotationTimepicker = {
    attach: function (context, settings) {

      // ---------- TIME PICKER ----------
      once('srQuotationTimepicker', '.timepicker', context).forEach(function (element) {
        flatpickr(element, {
          enableTime: true,
          noCalendar: true,
          dateFormat: "H:i",
          time_24hr: true,
          minuteIncrement: 5,
          allowInput: true,
          clickOpens: true,
          wrap: false,
          theme: "material_blue",
          onReady: function (selectedDates, dateStr, instance) {
            // Add clock icon if not already present
            if (!$(instance.input).siblings('.time-icon').length) {
              $(instance.input).wrap('<div class="timepicker-wrapper"></div>');
              $('<span class="time-icon"><i class="far fa-clock"></i></span>').insertAfter(instance.input);
            }
          },
        });
      });

      // ---------- DATE PICKER ----------
      once('srQuotationDatepicker', '.datepicker', context).forEach(function (element) {
        flatpickr(element, {
          dateFormat: "d-m-Y",
          allowInput: true,
          minDate: "today", // optional
          theme: "material_blue", // if you use themed CSS
          onReady: function (selectedDates, dateStr, instance) {
            // Add calendar icon if not already present
            if (!$(instance.input).siblings('.date-icon').length) {
              $(instance.input).wrap('<div class="datepicker-wrapper"></div>');
              $('<span class="date-icon"><i class="far fa-calendar-alt"></i></span>').insertAfter(instance.input);
            }
          },
          onChange: function (selectedDates, dateStr, instance) {
            updateNightsFromDates(instance.input);
          },
        });
      });

      // ---------- NIGHTS AUTO-FILL FROM CHECK-IN / CHECK-OUT ----------
      function parseDMY(str) {
        if (!str || typeof str !== 'string') return null;
        var parts = str.trim().split('-');
        if (parts.length !== 3) return null;
        var day = parseInt(parts[0], 10);
        var month = parseInt(parts[1], 10) - 1;
        var year = parseInt(parts[2], 10);
        if (isNaN(day) || isNaN(month) || isNaN(year)) return null;
        var d = new Date(year, month, day);
        if (isNaN(d.getTime())) return null;
        return d;
      }
      function updateNightsFromDates(changedInput) {
        var form = changedInput && changedInput.closest ? changedInput.closest('form') : null;
        if (!form) return;
        var checkInEl = form.querySelector('.datepicker[data-date-type="check-in"]');
        var checkOutEl = form.querySelector('.datepicker[data-date-type="check-out"]');
        var nightsEl = form.querySelector('.nights-field');
        if (!checkInEl || !checkOutEl || !nightsEl) return;
        var checkInVal = (checkInEl.value || '').trim();
        var checkOutVal = (checkOutEl.value || '').trim();
        if (!checkInVal || !checkOutVal) return;
        var start = parseDMY(checkInVal);
        var end = parseDMY(checkOutVal);
        if (!start || !end || end < start) return;
        var diffMs = end - start;
        var nights = Math.round(diffMs / (1000 * 60 * 60 * 24));
        nightsEl.value = nights >= 0 ? nights : '';
      }
      // Run on input change too (e.g. manual typing)
      once('srQuotationNightsFromDates', 'form .datepicker', context).forEach(function (element) {
        element.addEventListener('change', function () {
          updateNightsFromDates(element);
        });
        element.addEventListener('blur', function () {
          updateNightsFromDates(element);
        });
      });

    }
  };
})(jQuery, Drupal, once);

// amenities search functionality 
(function (Drupal) {
  Drupal.behaviors.amenitiesSearch = {
    attach(context) {

      const searchInput = context.querySelector('.amenities-search');
      if (!searchInput) return;

      const items = context.querySelectorAll(
        '.amenities-scroll .js-form-type-checkbox'
      );

      searchInput.addEventListener('keyup', function () {
        const keyword = this.value.toLowerCase();

        items.forEach(item => {
          const label = item.textContent.toLowerCase();
          item.style.display = label.includes(keyword) ? 'flex' : 'none';
        });
      });
    }
  };
})(Drupal);

(function ($, Drupal) {
  Drupal.behaviors.financialFieldsValidation = {
    attach: function (context) {

      $('.fx-rate-field, .quote-field, .taxes-field, .booker-name-field, .room-type-field, .enquired-location-field, .nights-field, .apartment-size-field, .adults-field, .kids-field, .distance-field, .map-link-field, .addon-prices-field', context).each(function () {

        if ($(this).hasClass('financial-processed')) {
          return;
        }

        $(this).addClass('financial-processed');

        const field = $(this);
        const max = parseInt(field.attr('maxlength'));
        const description = field.closest('.form-item').find('.description');

        // Store original description (important for room type)
        const originalDescription = description.html();

        function updateFieldState() {

          const length = field.val().length;

          if (length >= max) {
            field.addClass('fx-rate-error');
            description.html(
              '<span style="color:red;">Maximum ' + max + ' characters reached.</span>'
            );
          }
          else {
            field.removeClass('fx-rate-error');

            // Restore original description ONLY if exists
            if (originalDescription) {
              description.html(originalDescription);
            } else {
              description.text('');
            }
          }
        }

        field.on('input', function () {

  let value = field.val().toString();

  if (max && value.length > max) {
    field.val(value.substring(0, max));
  }

  updateFieldState();

});

        field.on('keydown', function (e) {
          if (field.val().length >= max && e.key.length === 1) {
            description.html(
              '<span style="color:red;">You cannot enter more than ' + max + ' characters.</span>'
            );
          }
        });

        updateFieldState();

      });

    }
  };
})(jQuery, Drupal);