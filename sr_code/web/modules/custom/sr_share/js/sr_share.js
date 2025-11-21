(function ($, Drupal, drupalSettings) {
    'use strict';

    jQuery('#copy-url').on('click', function() {
        var currentUrl = window.location.href;
      
        // Use the Clipboard API to copy the URL
        navigator.clipboard.writeText(currentUrl).then(function() {
          // Success callback
          jQuery('#copy-url').text('Link Copied!');

          setTimeout(function(){
            jQuery('#copy-url').text('Copy Link');
          },2000)
        });
      });
      jQuery('#share-whatsapp').on('click', function() {
        var currentUrl = window.location.href;
        var whatsappUrl = 'https://wa.me/?text=' + encodeURIComponent(currentUrl);
        
        // Redirect to WhatsApp
        window.open(whatsappUrl, '_blank');
      });
            
  })(jQuery, Drupal, drupalSettings);
  