(function ($, Drupal, drupalSettings) {
  'use strict';

  // Copy URL functionality
  $('#copy-url-btn').on('click', function() {
    var shareUrl = $('#property-share-url').val();
    
    // Use the Clipboard API to copy the URL
    navigator.clipboard.writeText(shareUrl).then(function() {
      // Success callback
      $('#copy-url-btn').text('✓ Link Copied!');
      
      setTimeout(function() {
        $('#copy-url-btn').text('Copy Link');
      }, 2000);
    }).catch(function(err) {
      // Fallback for older browsers
      var urlInput = document.getElementById('property-share-url');
      urlInput.select();
      urlInput.setSelectionRange(0, 99999);
      
      try {
        document.execCommand('copy');
        $('#copy-url-btn').text('✓ Link Copied!');
        
        setTimeout(function() {
          $('#copy-url-btn').text('Copy Link');
        }, 2000);
      } catch (error) {
        console.error('Failed to copy: ', error);
        alert('Failed to copy URL. Please copy manually.');
      }
    });
  });

  // WhatsApp Share functionality
  $('#share-whatsapp-btn').on('click', function() {
    var shareUrl = $('#property-share-url').val();
    var shareTitle = $('#sharePropertyModalTitle').text().replace('Share ', '');
    var message = 'Check out ' + shareTitle + ': ' + shareUrl;
    var whatsappUrl = 'https://wa.me/?text=' + encodeURIComponent(message);
    
    // Open WhatsApp
    window.open(whatsappUrl, '_blank');
  });

})(jQuery, Drupal, drupalSettings);