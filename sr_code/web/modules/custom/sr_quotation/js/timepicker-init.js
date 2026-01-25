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
          dateFormat: "Y-m-d",
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