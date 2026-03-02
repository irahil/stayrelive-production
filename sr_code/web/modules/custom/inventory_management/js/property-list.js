(function ($, Drupal, once) {
  'use strict';

  // Helper function to show delete confirmation dialog
  function showDeleteDialog(deleteUrl, entityType) {
    var entityTypeLabel = entityType === 'room' ? Drupal.t('Room') : Drupal.t('Property');
    var confirmMessage = Drupal.t('Are you sure you want to delete this @entity?', {'@entity': entityTypeLabel.toLowerCase()});
    var warningMessage = Drupal.t('This action cannot be undone and all associated data will be permanently removed.');
    
    var dialog = Drupal.dialog(
      '<div class="delete-confirmation ' + entityType + '-delete-confirmation inventory-delete-modal-content">' +
      '  <p><strong>' + confirmMessage + '</strong></p>' +
      '  <p>' + warningMessage + '</p>' +
      '</div>',
      {
        title: Drupal.t('Delete @entity', {'@entity': entityTypeLabel}),
        dialogClass: 'inventory-delete-modal ' + entityType + '-delete-dialog',
        resizable: false,
        width: 500,
        buttons: [
          {
            text: Drupal.t('Cancel'),
            class: 'button',
            click: function() {
              dialog.close();
            }
          },
          {
            text: Drupal.t('Delete'),
            class: 'button button--danger',
            click: function() {
              dialog.close();
              window.location.href = deleteUrl;
            }
          }
        ]
      }
    );
    
    dialog.showModal();
  }

  // Global function to handle action dropdown change (for properties)
  window.handleActionChange = function(selectElement) {
    var selectedValue = selectElement.value;
    var editUrl = selectElement.getAttribute('data-edit-url');
    var deleteUrl = selectElement.getAttribute('data-delete-url');
    
    if (selectedValue === 'manage' && editUrl) {
      window.location.href = editUrl;
    } else if (selectedValue === 'delete' && deleteUrl) {
      showDeleteDialog(deleteUrl, 'property');
      // Reset dropdown to default (empty/placeholder)
      selectElement.value = '';
    }
  };

  // Handle room delete link clicks
  Drupal.behaviors.roomDelete = {
    attach: function (context, settings) {
      // Use once() for proper behavior attachment
      var roomDeleteLinks = once('roomDelete', '.room-delete-link', context);
      if (roomDeleteLinks.length > 0) {
        roomDeleteLinks.forEach(function(link) {
          $(link).on('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            var $link = $(this);
            var deleteUrl = $link.data('delete-url') || $link.attr('data-delete-url');
            if (!deleteUrl) {
              // Fallback: get URL from href attribute
              deleteUrl = $link.attr('href');
            }
            if (deleteUrl) {
              showDeleteDialog(deleteUrl, 'room');
            }
            return false;
          });
        });
      }
    }
  };

})(jQuery, Drupal, once);

