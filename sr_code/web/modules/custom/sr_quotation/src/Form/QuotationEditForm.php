<?php

namespace Drupal\sr_quotation\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;
use Drupal\file\Entity\File;
use Drupal\Core\File\FileSystemInterface;

class QuotationEditForm extends QuotationForm
{

  protected $id;

  public function getFormId()
  {
    return 'sr_quotation_edit_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state, $id = NULL)
  {

    $this->id = $id;

    // Load record
    $record = Database::getConnection()
      ->select('sr_quotation', 'q')
      ->fields('q')
      ->condition('id', $id)
      ->execute()
      ->fetchAssoc();

    if (!$record) {
      \Drupal::messenger()->addError($this->t('Quotation not found.'));
      return $form;
    }

    // Build the parent form
    $form = parent::buildForm($form, $form_state);

    /* ------------------------------------------------
     * BOOKING INFO
     * ------------------------------------------------ */
    $form['booking_info']['booker_name']['#default_value'] = $record['booker_name'];
    $form['booking_info']['travel_partner']['#default_value'] = $record['travel_partner'];

    $form['booking_info']['dates']['check_in_date']['#default_value'] = $record['checkin_date'];
    $form['booking_info']['dates']['check_out_date']['#default_value'] = $record['checkout_date'];

    $form['booking_info']['times']['check_in_time']['#default_value'] = $record['checkin_time'];
    $form['booking_info']['times']['check_out_time']['#default_value'] = $record['checkout_time'];

    $form['booking_info']['nights']['#default_value'] = $record['nights'];

    /* ------------------------------------------------
     * PROPERTY INFO
     * ------------------------------------------------ */
    $form['property_info']['unit_type']['#default_value'] = $record['unit_type'];
    $form['property_info']['room_details']['room_type']['#default_value'] = $record['room_type'];
    $form['property_info']['room_details']['room_category']['#default_value'] = $record['room_category'] ?? '';
    $form['property_info']['room_details']['bathrooms']['#default_value'] = $record['bathrooms'];

    $form['property_info']['apartment_size']['#default_value'] = $record['apartment_size'];

    $form['property_info']['occupancy']['adults']['#default_value'] = $record['adults'];
    $form['property_info']['occupancy']['kids']['#default_value'] = $record['kids'];

    $form['property_info']['location_details']['location']['#default_value'] = $record['location'];
    $form['property_info']['location_details']['distance']['#default_value'] = $record['distance'];

    $form['property_info']['map_link']['#default_value'] = $record['map_link'];
    $form['property_info']['description']['#default_value'] = $record['description'];
    
    // Set default value for location_screenshot if it exists (similar to apartment_images)
    if (!empty($record['location_screenshot'])) {
      // Handle both old single int format and new comma-separated format
      if (is_numeric($record['location_screenshot'])) {
        $fids = [(int) $record['location_screenshot']];
      } else {
        $fids = array_map('intval', array_filter(explode(',', $record['location_screenshot'])));
      }
      
      if (!empty($fids)) {
        $form['property_info']['location_screenshot']['#default_value'] = $fids;
        
        // Preload files for JS display (similar to apartment_images)
        $files_data = [];
        foreach ($fids as $fid) {
          $file = File::load($fid);
          if ($file) {
            $uri = $file->getFileUri();
            $url = \Drupal::service('file_url_generator')->generateAbsoluteString($uri);
            
            $files_data[] = [
              'fid' => $fid,
              'name' => $file->getFilename(),
              'size' => filesize($uri) ?: 0,
              'mime' => $file->getMimeType(),
              'url' => $url,
            ];
          }
        }
        
        if (!empty($files_data)) {
          // Attach files data to drupalSettings - must match apartment_images structure exactly
          // Use array_merge to ensure proper merging with existing dropzonejs settings
          $existing_dropzonejs = $form['#attached']['drupalSettings']['dropzonejs'] ?? [];
          $form['#attached']['drupalSettings']['dropzonejs'] = array_merge($existing_dropzonejs, [
            'location_screenshot' => [
              'files' => $files_data,
            ],
          ]);
          \Drupal::logger('sr_quotation')->notice('Preloaded location_screenshot files for edit: @files', [
            '@files' => print_r($files_data, TRUE),
          ]);
          \Drupal::logger('sr_quotation')->notice('DEBUG - drupalSettings structure after location_screenshot: @settings', [
            '@settings' => print_r($form['#attached']['drupalSettings']['dropzonejs'], TRUE),
          ]);
        } else {
          \Drupal::logger('sr_quotation')->warning('No location_screenshot files_data to preload. FIDs: @fids', [
            '@fids' => print_r($fids, TRUE),
          ]);
        }
      }
    }

    /* ------------------------------------------------
     * FINANCIALS
     * ------------------------------------------------ */
    $currency_options = $form['financials']['currency']['#options'];

    if (!isset($currency_options[$record['currency']])) {
      $form['financials']['currency']['#default_value'] = 'Other';
      $form['financials']['currency_other_wrapper']['currency_other']['#default_value'] = $record['currency'];
    } else {
      $form['financials']['currency']['#default_value'] = $record['currency'];
    }

    $form['financials']['quote']['#default_value'] = $record['quote'];
    $form['financials']['taxes']['#default_value'] = $record['taxes'];
    $form['financials']['fx_rate']['#default_value'] = $record['fx_rate'];
    $form['financials']['addon_prices']['#default_value'] = $record['addon_prices'] ?? '';

    /* ------------------------------------------------
     * AMENITIES
     * ------------------------------------------------ */
    if (!empty($record['amenities'])) {
      $selected = array_map('trim', explode(',', $record['amenities']));
      $form['extras']['amenities']['#default_value'] = $selected;
    }

    $form['extras']['cancellation_policy']['#default_value'] = $record['cancellation_policy'];
    $form['extras']['rules']['#default_value'] = $record['rules'];
    $form['extras']['note']['#default_value'] = $record['note'];

    /* ------------------------------------------------
     * SUPPLIER REFERENCE
     * ------------------------------------------------ */
    $supplier_dropdown = '';
    $supplier_text = '';

    if (strpos($record['supplier_reference'], ' | ') !== FALSE) {
      list($supplier_dropdown, $supplier_text) = explode(' | ', $record['supplier_reference'], 2);
    } else {
      $supplier_dropdown = $record['supplier_reference'];
    }

    $form['extras']['supplier_reference']['#default_value'] = trim($supplier_dropdown);
    $form['extras']['supplier_reference_text']['#default_value'] = trim($supplier_text);

    $form['extras']['confirmation_status']['#default_value'] = $record['confirmation_status'];

    /* ------------------------------------------------
     * IMAGES (Load Existing FIDs)
     * ------------------------------------------------ */

    if (!empty($record['images'])) {

      $fids = array_map('intval', explode(',', $record['images']));
      $files_data = [];

      foreach ($fids as $fid) {
        $file = File::load($fid);
        if ($file) {
          $uri = $file->getFileUri();
          $url = \Drupal::service('file_url_generator')->generateAbsoluteString($uri);

          $files_data[] = [
            'fid' => $fid,
            'name' => $file->getFilename(),
            'size' => filesize($uri) ?: 0,
            'mime' => $file->getMimeType(),
            'url' => $url,
          ];
        }
      }
      \Drupal::logger('files_data')->warning('<pre><code>' . print_r($files_data, TRUE) . '</code></pre>');

      // Load for display
      $form['images']['apartment_images']['#default_value'] = $fids;

      // *** IMPORTANT FIX ***
      // Ensure existing FIDs come back in $form_state->getValues()
      // $form['images']['apartment_images']['#value'] = $fids;

      // For JS preload - merge with existing dropzonejs settings to preserve location_screenshot
      $existing_dropzonejs = $form['#attached']['drupalSettings']['dropzonejs'] ?? [];
      $form['#attached']['drupalSettings']['dropzonejs'] = array_merge($existing_dropzonejs, [
        'apartment_images' => [
          'files' => $files_data,
        ],
      ]);
    }
    $form['images']['apartment_images']['removed_files'] = [
  '#type' => 'hidden',
  '#default_value' => '',
];




    /* ------------------------------------------------
     * Change submit button
     * ------------------------------------------------ */
    $form['actions']['submit']['#value'] = $this->t('Update Quotation');

    $form['id'] = [
      '#type' => 'hidden',
      '#value' => $id,
    ];

    return $form;
  }

  /* ===============================================
   * SUBMIT HANDLER (UPDATED WITH NEW FILE LOGIC)
   * =============================================== */
  public function submitForm(array &$form, FormStateInterface $form_state)
{
  $values = $form_state->getValues();
  $id = $values['id'] ?? NULL;
  $uid = \Drupal::currentUser()->id();

  /* ------------------------------------------------------------
   * 1. LOAD EXISTING IMAGES FROM DB (SOURCE OF TRUTH)
   * ------------------------------------------------------------ */
  $existing_images = Database::getConnection()
    ->select('sr_quotation', 'q')
    ->fields('q', ['images'])
    ->condition('id', $id)
    ->execute()
    ->fetchField();

  $old_fids = !empty($existing_images)
    ? array_values(array_filter(array_map('intval', explode(',', $existing_images))))
    : [];

  \Drupal::logger('sr_quotation')->debug('Old DB FIDs: @f', ['@f' => print_r($old_fids, TRUE)]);

  /* ------------------------------------------------------------
   * 1b. LOAD EXISTING LOCATION SCREENSHOTS FROM DB (SOURCE OF TRUTH)
   * ------------------------------------------------------------ */
  $existing_location_screenshots = Database::getConnection()
    ->select('sr_quotation', 'q')
    ->fields('q', ['location_screenshot'])
    ->condition('id', $id)
    ->execute()
    ->fetchField();

  /* ------------------------------------------------------------
   * 2. READ REMOVED FIDS FROM JS (AUTHORITATIVE)
   * ------------------------------------------------------------ */
  $removed_fids = [];
  $apartment_images = $values['apartment_images'] ?? [];
   \Drupal::logger('sr_quotation')->debug('Apartment Images: @r', ['@r' => print_r($apartment_images, TRUE)]);

  if (!empty($apartment_images['removed_files'])) {
    $removed_fids = array_values(
      array_filter(
        array_map('intval', explode(';', $apartment_images['removed_files']))
      )
    );
  }

  \Drupal::logger('sr_quotation')->debug('Removed FIDs: @r', ['@r' => print_r($removed_fids, TRUE)]);

  /* ------------------------------------------------------------
   * 3. REMOVE ONLY SELECTED IMAGES
   * ------------------------------------------------------------ */
  if (!empty($removed_fids)) {
    $old_fids = array_values(array_diff($old_fids, $removed_fids));
  }

  /* ------------------------------------------------------------
   * 4. HANDLE NEW UPLOADS
   * ------------------------------------------------------------ */
  $new_fids = [];

  if (!empty($apartment_images['uploaded_files']) && is_array($apartment_images['uploaded_files'])) {

    foreach ($apartment_images['uploaded_files'] as $entry) {

      if (empty($entry['path']) || empty($entry['filename'])) {
        continue;
      }

      $data = @file_get_contents($entry['path']);
      if ($data === FALSE) {
        continue;
      }

      $directory = 'public://quotation_images/';
      \Drupal::service('file_system')->prepareDirectory(
        $directory,
        FileSystemInterface::CREATE_DIRECTORY
      );

      $file = \Drupal::service('file.repository')->writeData(
        $data,
        $directory . $entry['filename'],
        FileSystemInterface::EXISTS_RENAME
      );

      if ($file instanceof File) {
        $file->setPermanent();
        $file->save();
        $new_fids[] = (int) $file->id();
      }
    }
  }

  \Drupal::logger('sr_quotation')->debug('New FIDs: @n', ['@n' => print_r($new_fids, TRUE)]);

  /* ------------------------------------------------------------
   * 5. FINAL IMAGE SET
   * ------------------------------------------------------------ */
  $final_fids = array_values(array_unique(array_merge($old_fids, $new_fids)));

  \Drupal::logger('sr_quotation')->debug('Final FIDs saved: @f', ['@f' => print_r($final_fids, TRUE)]);

  // ------------------------------------------------------------
  // 5. SAVE FORM FIELDS AND IMAGES
  // ------------------------------------------------------------
  $amenities = (!empty($values['amenities'])) ? implode(', ', array_filter($values['amenities'])) : '';

  $supplier_reference_combined = trim($values['supplier_reference']);
  if (!empty($values['supplier_reference_text'])) {
    $supplier_reference_combined .= ' | ' . trim($values['supplier_reference_text']);
  }

  $currency = ($values['currency'] === 'Other' && !empty($values['currency_other']))
    ? $values['currency_other']
    : $values['currency'];

  Database::getConnection()
    ->update('sr_quotation')
    ->fields([
      'booker_name' => $values['booker_name'],
      'travel_partner' => $values['travel_partner'],
      'uid' => $uid,
      'checkin_date' => $values['check_in_date'],
      'checkout_date' => $values['check_out_date'],
      'checkin_time' => $values['check_in_time'],
      'checkout_time' => $values['check_out_time'],
      'nights' => $values['nights'],
      'unit_type' => $values['unit_type'],
      'room_type' => $values['room_type'],
      'room_category' => $values['room_category'] ?? '',
      'bathrooms' => $values['bathrooms'],
      'apartment_size' => $values['apartment_size'],
      'adults' => $values['adults'],
      'kids' => $values['kids'],
      'location' => $values['location'],
      'distance' => $values['distance'],
      'map_link' => $values['map_link'],
      'description' => $values['description'],
      'location_screenshot' => $this->getLocationScreenshotFid($values, $existing_location_screenshots ?? NULL),
      'currency' => $currency,
      'quote' => $values['quote'],
      'taxes' => $values['taxes'],
      'fx_rate' => $values['fx_rate'],
      'addon_prices' => $values['addon_prices'] ?? '',
      'amenities' => $amenities,
      'cancellation_policy' => $values['cancellation_policy'],
      'rules' => $values['rules'],
      'note' => $values['note'],
      'supplier_reference' => $supplier_reference_combined,
      'confirmation_status' => $values['confirmation_status'],
      'images' => implode(',', $final_fids),
    ])
    ->condition('id', $id)
    ->execute();

  \Drupal::messenger()->addMessage($this->t('Quotation updated successfully.'));
  $form_state->setRedirect('sr_quotation.list');
}

  /**
   * Get location screenshot file IDs from form values (using DropzoneJS like apartment_images).
   */
  protected function getLocationScreenshotFid($values, $existing_screenshots = NULL) {
    // Load existing FIDs from DB (handle both old single int and new comma-separated format)
    $old_fids = [];
    if (!empty($existing_screenshots)) {
      if (is_numeric($existing_screenshots)) {
        $old_fids = [(int) $existing_screenshots];
      } else {
        $old_fids = array_values(array_filter(array_map('intval', explode(',', $existing_screenshots))));
      }
    }
    
    // Try nested path first (property_info > location_screenshot)
    $location_screenshot = $values['property_info']['location_screenshot'] ?? $values['location_screenshot'] ?? [];
    
    \Drupal::logger('sr_quotation')->debug('Edit - location_screenshot from values: @data', [
      '@data' => print_r($location_screenshot, TRUE),
    ]);
    \Drupal::logger('sr_quotation')->debug('Edit - Old FIDs before processing: @fids', [
      '@fids' => print_r($old_fids, TRUE),
    ]);
    
    // Read removed FIDs from JS
    $removed_fids = [];
    if (!empty($location_screenshot['removed_files'])) {
      $removed_fids = array_values(
        array_filter(
          array_map('intval', explode(';', $location_screenshot['removed_files']))
        )
      );
    }
    
    \Drupal::logger('sr_quotation')->debug('Edit - Removed FIDs: @fids', [
      '@fids' => print_r($removed_fids, TRUE),
    ]);
    
    // Remove only selected images
    if (!empty($removed_fids)) {
      $old_fids = array_values(array_diff($old_fids, $removed_fids));
    }
    
    \Drupal::logger('sr_quotation')->debug('Edit - Old FIDs after removal: @fids', [
      '@fids' => print_r($old_fids, TRUE),
    ]);
    
    // Handle new uploads
    $new_fids = [];
    if (!empty($location_screenshot['uploaded_files']) && is_array($location_screenshot['uploaded_files'])) {
      \Drupal::logger('sr_quotation')->debug('Edit - Processing new uploaded files: @count', [
        '@count' => count($location_screenshot['uploaded_files']),
      ]);
      $directory = 'public://quotation_location_images/';
      \Drupal::service('file_system')->prepareDirectory(
        $directory,
        FileSystemInterface::CREATE_DIRECTORY
      );
      
      foreach ($location_screenshot['uploaded_files'] as $entry) {
        if (empty($entry['path']) || empty($entry['filename'])) {
          continue;
        }
        
        $data = @file_get_contents($entry['path']);
        if ($data === FALSE) {
          continue;
        }
        
        $file = \Drupal::service('file.repository')->writeData(
          $data,
          $directory . $entry['filename'],
          FileSystemInterface::EXISTS_RENAME
        );
        
        if ($file instanceof File) {
          $file->setPermanent();
          $file->save();
          $new_fids[] = (int) $file->id();
        }
      }
    } else {
      \Drupal::logger('sr_quotation')->debug('Edit - No new uploaded files found in location_screenshot');
    }
    
    \Drupal::logger('sr_quotation')->debug('Edit - New FIDs: @fids', [
      '@fids' => print_r($new_fids, TRUE),
    ]);
    
    // Combine old and new FIDs
    $final_fids = array_merge($old_fids, $new_fids);
    
    \Drupal::logger('sr_quotation')->debug('Edit - Final combined FIDs: @fids', [
      '@fids' => print_r($final_fids, TRUE),
    ]);
    
    return !empty($final_fids) ? implode(',', $final_fids) : '';
  }
}
