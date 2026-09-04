/**
 * Currency Hider for Quotation Preview Pages
 * Hides the currency dropdown from the header on quotation preview pages
 */

(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.srQuotationCurrencyHider = {
    attach: function (context, settings) {
      once('srQuotationCurrencyHider', 'body', context).forEach(function () {
        // Hide currency dropdown on quotation pages
        var hideCurrencyDropdown = function() {
          // Method 1: Find currency dropdown by class
          var currencyDropdown = document.querySelector('.dropdown-currency');
          if (currencyDropdown) {
            var parentLi = currencyDropdown.closest('li');
            if (parentLi) {
              parentLi.style.display = 'none';
            }
          }
          
          // Method 2: Find by link text containing currency
          var currencyLinks = document.querySelectorAll('a[href*="currency"]');
          currencyLinks.forEach(function(link) {
            var parentLi = link.closest('li');
            if (parentLi) {
              parentLi.style.display = 'none';
            }
          });

          // Method 3: Find by nav-notification2 class (specific to currency)
          var currencyNotifications = document.querySelectorAll('.nav-notification2');
          currencyNotifications.forEach(function(element) {
            // Check if this is the currency dropdown by looking for currency-related content
            if (element.textContent && element.textContent.toLowerCase().includes('currency')) {
              var parentLi = element.closest('li');
              if (parentLi) {
                parentLi.style.display = 'none';
              }
            }
          });
        };

        // Run immediately
        hideCurrencyDropdown();
        
        // Also run after a short delay to ensure everything is loaded
        setTimeout(hideCurrencyDropdown, 100);
        
        // Run again after DOM is fully ready (backup)
        if (document.readyState === 'loading') {
          document.addEventListener('DOMContentLoaded', hideCurrencyDropdown);
        }
      });
    }
  };

})(Drupal, once);
