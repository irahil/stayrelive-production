(function (Drupal, drupalSettings) {
    'use strict';
  
    Drupal.behaviors.flatpickrInit = {
      attach: function (context, settings) {
        // Use Drupal.once() instead of jQuery.once()
        const elements = once('flatpickr-init', '.date-flatpicker', context);
        
        elements.forEach(function (element) {
          flatpickr(element, {
            mode: drupalSettings.flatpickr.mode || 'single',
            dateFormat: drupalSettings.flatpickr.dateFormat || 'Y-m-d',
            allowInput: true,
            onReady: function() {
              // Add some custom classes for Drupal theming
              const calendar = this.calendarContainer;
              calendar.classList.add('flatpickr-drupal');
            }
          });
        });
      }
    };
  
  })(Drupal, drupalSettings);