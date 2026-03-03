(function ($, Drupal, once) {
  'use strict';

  Drupal.behaviors.initFlatpickr = {
    attach: function (context, settings) {
      $(once('flatpickr-init', '.date-flatpicker', context)).each(function () {
        const $input = $(this);
        const defaultDate = $input.val(); // get value from field if any

        $input.flatpickr({
          mode: 'range',
          dateFormat: 'J M y', 
          allowInput: true,
          minDate: "today",
          showMonths: 2,
          defaultDate: defaultDate ? defaultDate.split(' to ') : null,
        });

        // Manually set placeholder if no value
        if (!defaultDate) {
          $input.attr('placeholder', 'Check in - Check out');
        }
      });
    }
  };

})(jQuery, Drupal, once);