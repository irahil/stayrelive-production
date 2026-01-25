<?php

namespace Drupal\sr_quotation\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\file\Entity\File;
use Drupal\Core\File\FileSystemInterface;

class QuotationForm extends FormBase
{

  public function getFormId()
  {
    return 'sr_quotation_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state)
  {
    $form['#attached']['library'][] = 'sr_quotation/timepicker';
    $form['#attached']['library'][] = 'sr_quotation/dropzone-cropper';

    // --------------------------------------------
    // Booking Information Section
    // --------------------------------------------
    $form['booking_info'] = [
      '#type' => 'details',
      '#title' => $this->t('Booking Information'),
      '#open' => TRUE,
      '#attributes' => ['class' => ['form-section']],
    ];

    $form['booking_info']['booker_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Booker Name'),
      '#required' => TRUE,
      '#attributes' => [
        'placeholder' => $this->t('Enter Booker Name'),
      ],
    ];

    $form['booking_info']['travel_partner'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Travel Partner / Corporate'),
      '#required' => TRUE,
      '#attributes' => [
        'placeholder' => $this->t('Enter Travel Partner or Corporate'),
      ],
    ];

    $form['booking_info']['dates'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['form-row']],
    ];
    $form['booking_info']['dates']['check_in_date'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Check-in Date'),
      '#required' => TRUE,
      '#attributes' => ['class' => ['datepicker']],
    ];
    $form['booking_info']['dates']['check_out_date'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Check-out Date'),
      '#required' => TRUE,
      '#attributes' => ['class' => ['datepicker']],
    ];

    $form['booking_info']['times'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['form-row']],
    ];
    $form['booking_info']['times']['check_in_time'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Check-In Time'),
      '#attributes' => ['class' => ['timepicker']],
      '#required' => TRUE,
    ];
    $form['booking_info']['times']['check_out_time'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Check-Out Time'),
      '#attributes' => ['class' => ['timepicker']],
      '#required' => TRUE,
    ];
    $form['booking_info']['nights'] = [
      '#type' => 'number',
      '#title' => $this->t('Number of Nights'),
      '#required' => TRUE,
    ];

    // --------------------------------------------
    // Property Information Section
    // --------------------------------------------
    $form['property_info'] = [
      '#type' => 'details',
      '#title' => $this->t('Property Information'),
      '#open' => TRUE,
      '#attributes' => ['class' => ['form-section']],
    ];

    $form['property_info']['unit_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Unit Type'),
      '#options' => [
        'Serviced Apartment' => 'Serviced Apartment',
        'Aparthotel' => 'Aparthotel',
        'Hotel Apartment' => 'Hotel Apartment',
        'Hotel' => 'Hotel',
        'Chalet' => 'Chalet',
        'Villa' => 'Villa',
        'Premium Homes' => 'Premium Homes',
        'Branded Residences' => 'Branded Residences',
        'Others' => 'Others',
      ],
      '#required' => TRUE,
    ];

    $form['property_info']['room_details'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['form-row']],
    ];
    $form['property_info']['room_details']['room_type'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Room Type'),
      '#required' => TRUE,
      '#description' => $this->t('e.g., Studio, 1-bed Apartment, 2-bed Apartment, etc.'),
      '#attributes' => [
        'placeholder' => $this->t('Enter room type'),
      ],
    ];
    $form['property_info']['room_details']['bathrooms'] = [
      '#type' => 'select',
      '#title' => $this->t('No. of Bathrooms'),
      '#options' => ['1' => '1', '2' => '2', '3' => '3'],
      '#required' => TRUE,
    ];
    $form['property_info']['apartment_size'] = [
      '#type' => 'number',
      '#title' => $this->t('Apartment Size (sqm)'),
    ];

    $form['property_info']['occupancy'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['form-row']],
    ];
    $form['property_info']['occupancy']['adults'] = [
      '#type' => 'number',
      '#title' => $this->t('No. of Adults'),
    ];
    $form['property_info']['occupancy']['kids'] = [
      '#type' => 'number',
      '#title' => $this->t('No. of Kids'),
    ];

    $form['property_info']['location_details'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['form-row']],
    ];
    $form['property_info']['location_details']['location'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Location'),
    ];
    $form['property_info']['location_details']['distance'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Distance (in km)'),
    ];
    $form['property_info']['map_link'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Map Link'),
    ];
    $form['property_info']['description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Property Description'),
    ];

    $form['property_info']['location_screenshot'] = [
      '#type' => 'dropzonejs',
      '#title' => $this->t('Location Screenshot'),
      '#description' => $this->t('Upload location screenshot/image (JPEG, PNG, etc.)'),
      '#dropzone_description' => $this->t('Drag & drop images or click to upload'),
      '#multiple' => TRUE,
      '#upload_location' => 'public://quotation_location_images/',
      '#max_files' => 10,
      '#extensions' => 'png jpg jpeg webp',
      '#dropzonejs' => [
        'thumbnailWidth' => 120,
        'thumbnailHeight' => 120,
      ],
    ];
    $form['property_info']['location_screenshot']['removed_files'] = [
      '#type' => 'hidden',
      '#default_value' => '',
    ];

    // --------------------------------------------
    // Pricing and Financials
    // --------------------------------------------
    $form['financials'] = [
      '#type' => 'details',
      '#title' => $this->t('Pricing & Financials'),
      '#open' => TRUE,
      '#attributes' => ['class' => ['form-section']],
    ];

    $form['financials']['currency'] = [
      '#type' => 'select',
      '#title' => $this->t('Currency'),
      '#options' => [
        'USD' => 'USD',
        'GBP' => 'GBP',
        'EUR' => 'EUR',
        'CHF' => 'CHF',
        'AED' => 'AED',
        'SAR' => 'SAR',
        'CAD' => 'CAD',
        'INR' => 'INR',
        'SGD' => 'SGD',
        'Other' => 'Other',
      ],
      '#required' => TRUE,
      '#ajax' => [
        'callback' => '::currencyCallback',
        'event' => 'change',
        'wrapper' => 'currency-other-wrapper',
      ],
    ];
    $form['financials']['currency_other_wrapper'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'currency-other-wrapper'],
    ];

    $selected_currency = $form_state->getValue('currency');
    if ($selected_currency == 'Other') {
      $form['financials']['currency_other_wrapper']['currency_other'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Enter Custom Currency'),
        '#required' => TRUE,
      ];
    }

    $form['financials']['quote'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Quote'),
    ];
    $form['financials']['taxes'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Taxes'),
    ];
    $form['financials']['fx_rate'] = [
      '#type' => 'textfield',
      '#title' => $this->t('FX Rate'),
    ];
    $form['financials']['addon_prices'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Add-on Prices'),
      '#maxlength' => 300,
      '#rows' => 4,
      '#attributes' => [
        'maxlength' => 300,
      ],
    ];

    // --------------------------------------------
    // Amenities & Policies
    // --------------------------------------------
    $form['extras'] = [
      '#type' => 'details',
      '#title' => $this->t('Amenities & Policies'),
      '#open' => TRUE,
      '#attributes' => ['class' => ['form-section']],
    ];

    $form['extras']['amenities'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Amenities'),
      '#options' => $this->getAmenitiesOptions(),
    ];
    $form['extras']['cancellation_policy'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Cancellation Policy'),
    ];
    $form['extras']['rules'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Rules & Regulations'),
    ];
    $form['extras']['note'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Note (Extra Details)'),
    ];

    // --------------------------------------------
    // Supplier Reference & Confirmation Status
    // --------------------------------------------

    // Supplier Reference (Dropdown)
    $form['extras']['supplier_reference'] = [
      '#type' => 'select',
      '#title' => $this->t('Supplier Reference'),
      '#options' => [
        'WhatsApp' => 'WhatsApp',
        'Email' => 'Email',
        'Call' => 'Call',
        'Portal Log-ins' => 'Portal Log-ins',
        'Property Website' => 'Property Website',
      ],
      '#required' => TRUE,
      '#ajax' => [
        'callback' => '::supplierReferenceCallback',
        'wrapper' => 'supplier-reference-wrapper',
        'event' => 'change',
      ],
    ];

    // Wrapper MUST exist even if empty
    $form['extras']['supplier_reference_wrapper'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'supplier-reference-wrapper'],
    ];

    // Detect selected value correctly even on first build + AJAX rebuild
    $selected_reference =
      $form_state->getTriggeringElement()['#value']
      ?? $form_state->getValue('supplier_reference');

    // Add conditional field
    if (!empty($selected_reference)) {

      $label = in_array($selected_reference, ['WhatsApp', 'Call'])
        ? 'Enter Quoted Text'
        : 'Enter URL or Quoted Text';

      $form['extras']['supplier_reference_wrapper']['supplier_reference_text'] = [
        '#type' => 'textfield',
        '#title' => $this->t($label),
        '#required' => TRUE,
      ];
    }

    // Confirmation Status
    $form['extras']['confirmation_status'] = [
      '#type' => 'select',
      '#title' => $this->t('Confirmation Status'),
      '#options' => [
        'Confirmed' => 'Confirmed',
        'Non-Confirmed' => 'Non-Confirmed',
        'Cancelled' => 'Cancelled',
      ],
      '#required' => TRUE,
    ];

    // --------------------------------------------
    // Images
    // --------------------------------------------
    $form['images'] = [
      '#type' => 'details',
      '#title' => $this->t('Property Images'),
      '#open' => TRUE,
      '#attributes' => ['class' => ['form-section']],
    ];
    $form['images']['apartment_images'] = [
      '#type' => 'dropzonejs',
      '#title' => $this->t('Apartment Images'),
      '#description' => $this->t('All the images are used for sample and illustration purposes of the
apartment type; the design and detail may vary.'),
      '#dropzone_description' => $this->t('Drag & drop images or click to upload'),
      '#multiple' => TRUE,
      '#upload_location' => 'public://quotation_images/',
      '#max_files' => 10,
      '#extensions' => 'png jpg jpeg webp',
      '#dropzonejs' => [
        'thumbnailWidth' => 120,
        'thumbnailHeight' => 120,
      ],
    ];
    $form['images']['apartment_images']['removed_files'] = [
  '#type' => 'hidden',
  '#default_value' => '',
];


    // --------------------------------------------
    // Submit Button
    // --------------------------------------------
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save Quotation'),
      '#button_type' => 'primary',
    ];

    $form['#theme'] = 'quotation_form';

    return $form;
  }

  public function currencyCallback(array &$form, FormStateInterface $form_state)
  {
    return $form['financials']['currency_other_wrapper'];
  }
  public function supplierReferenceCallback(array &$form, FormStateInterface $form_state)
  {
    return $form['extras']['supplier_reference_wrapper'];
  }

  private function getFinalCurrencyValue($values)
  {
    // Selected currency
    $currency = $values['currency'];

    // If "Other" selected → return user-entered value
    if ($currency === 'Other' && !empty($values['currency_other'])) {
      return $values['currency_other'];
    }

    // Otherwise, return normal currency option
    return $currency;
  }

  /**
   * Load amenities taxonomy terms as options.
   */
  private function getAmenitiesOptions()
  {
    $options = [];

    // Load amenities taxonomy terms.
    $terms = \Drupal::entityTypeManager()
      ->getStorage('taxonomy_term')
      ->loadTree('amenities');

    foreach ($terms as $term) {
      $name = $term->name;

      // ------ REMOVE unwanted terms --------
      // Skip terms that look like image URLs or URLs in general.
      if (
        strpos($name, '.jpeg') !== FALSE ||
        strpos($name, '.jpg') !== FALSE ||
        strpos($name, 'plumcache.com') !== FALSE ||
        strpos($name, 'www.plumguide.com') !== FALSE ||
        filter_var($name, FILTER_VALIDATE_URL)
      ) {
        continue;
      }
      // -------------------------------------

      $options[$name] = $name;
    }

    return $options;  // <-- REQUIRED
  }

  private function normalizeFileIds($files)
  {
    $fids = [];

    foreach ($files as $item) {
      // Case 1: integer
      if (is_numeric($item)) {
        $fids[] = (int) $item;
      }
      // Case 2: ['fids' => [12]]
      elseif (is_array($item) && isset($item['fids'])) {
        foreach ($item['fids'] as $fid) {
          if (is_numeric($fid)) {
            $fids[] = (int) $fid;
          }
        }
      }
      // Case 3: ['fid' => 12]
      elseif (is_array($item) && isset($item['fid'])) {
        if (is_numeric($item['fid'])) {
          $fids[] = (int) $item['fid'];
        }
      }
    }

    return $fids;
  }


  public function validateForm(array &$form, FormStateInterface $form_state)
  {
    $files = $form_state->getValue('apartment_images', []);

    \Drupal::logger('sr_quotation')->notice('Files in validate (apartment_images): @files', [
      '@files' => print_r($files, TRUE)
    ]);

    // Check if files are being uploaded
    if (empty($files)) {
      \Drupal::logger('sr_quotation')->warning('No apartment_images files received in validation');
    }
    
    // Also check location_screenshot in validation
    $location_files = $form_state->getValue('location_screenshot', []);
    \Drupal::logger('sr_quotation')->notice('Files in validate (location_screenshot): @files', [
      '@files' => print_r($location_files, TRUE)
    ]);
    
    if (empty($location_files)) {
      \Drupal::logger('sr_quotation')->warning('No location_screenshot files received in validation');
    }
    
    // Validate addon_prices character limit
    $addon_prices = $form_state->getValue('addon_prices', '');
    if (!empty($addon_prices) && mb_strlen($addon_prices) > 300) {
      $form_state->setError($form['financials']['addon_prices'], 
        $this->t('Add-on Prices must not exceed 300 characters. Current length: @length', 
          ['@length' => mb_strlen($addon_prices)]
        )
      );
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
  $values = $form_state->getValues();
  $uid = \Drupal::currentUser()->id();

  // Collect created file IDs here.
  $image_fids = [];

  // The dropzone module (or your custom JS) stores uploaded files under
  // $values['apartment_images']['uploaded_files'] as you showed in logs.
  if (!empty($values['apartment_images']) && isset($values['apartment_images']['uploaded_files'])) {
    $uploaded = $values['apartment_images']['uploaded_files'];

    // Ensure target directory exists in public://
    $directory = 'public://quotation_images/';
    \Drupal::service('file_system')->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY);

    foreach ($uploaded as $item) {
      // Defensive checks
      if (empty($item['path'])) {
        \Drupal::logger('sr_quotation_my_image')->warning('Uploaded item missing path: @item', ['@item' => print_r($item, TRUE)]);
        continue;
      }

      // Temporary path (temporary://xxxxx)
      $temp_path = $item['path'];
      // Friendly filename (you may sanitize if needed)
      $filename = !empty($item['filename']) ? $item['filename'] : basename($temp_path);

      // Read contents from temporary file (stream wrappers supported)
      $data = @file_get_contents($temp_path);
      if ($data === FALSE) {
        \Drupal::logger('sr_quotation_my_image')->error('Unable to read temp file: @path', ['@path' => $temp_path]);
        continue;
      }

      // Build destination URI (public://)
      $destination = $directory . $filename;

      // Save bytes to destination; this returns the destination URI on success
      $saved_uri = \Drupal::service('file_system')->saveData($data, $destination, FileSystemInterface::EXISTS_RENAME);

      if ($saved_uri) {
        // Create a File entity from the saved URI
        $file = File::create([
          'uri' => $saved_uri,
          'filename' => $filename,
        ]);

        // Mark permanent and save entity
        $file->setPermanent();
        $file->save();

        $fid = $file->id();
        $image_fids[] = $fid;

        \Drupal::logger('sr_quotation_my_image')->notice('Created file entity with FID: @fid (uri: @uri)', [
          '@fid' => $fid,
          '@uri' => $saved_uri,
        ]);
      } else {
        \Drupal::logger('sr_quotation_my_image')->error('Failed to save data for file: @name (temp: @temp)', [
          '@name' => $filename,
          '@temp' => $temp_path,
        ]);
      }
    }
  }
  else {
    \Drupal::logger('sr_quotation_my_image')->warning('No Dropzone uploaded files found in form values.');
  }

  // ========== rest of your existing logic (amenities, supplier_reference, DB insert) ==========
  // Prepare amenities data
  $amenities = '';
  if (!empty($values['amenities']) && is_array($values['amenities'])) {
    $selected_amenities = array_filter($values['amenities']);
    $amenities = implode(', ', $selected_amenities);
  }

  // Combine Supplier Reference + Text into one DB field
  $supplier_reference_combined = $values['supplier_reference'] ?? '';
  if (!empty($values['supplier_reference_text'])) {
    $supplier_reference_combined .= ' | ' . $values['supplier_reference_text'];
  }

  // Handle location screenshot file upload (using DropzoneJS like apartment_images)
  $location_screenshot_fids = [];
  
  // Get user input to check raw values if valueCallback didn't process
  $user_input = $form_state->getUserInput();
  
  // The dropzone module (or your custom JS) stores uploaded files under
  // $values['property_info']['location_screenshot']['uploaded_files'] - note the nested path!
  $uploaded = [];
  
  // Try valueCallback processed path first
  if (!empty($values['property_info']['location_screenshot']) && isset($values['property_info']['location_screenshot']['uploaded_files'])) {
    // ValueCallback processed it successfully
    $uploaded = $values['property_info']['location_screenshot']['uploaded_files'];
    \Drupal::logger('sr_quotation')->notice('DEBUG - ValueCallback processed location_screenshot. Files: @count', [
      '@count' => count($uploaded),
    ]);
  } 
  // Try nested path in user input (property_info > location_screenshot > uploaded_files)
  elseif (isset($user_input['property_info']['location_screenshot']['uploaded_files']) && !empty($user_input['property_info']['location_screenshot']['uploaded_files'])) {
    // ValueCallback didn't process it - manually process the raw string (same as DropzoneJS valueCallback does)
    $raw_value = $user_input['property_info']['location_screenshot']['uploaded_files'];
    \Drupal::logger('sr_quotation')->notice('DEBUG - Manual processing location_screenshot (nested path). Raw value: @raw', [
      '@raw' => $raw_value,
    ]);
  }
  // Fallback: try flat path (in case form structure changed)
  elseif (isset($user_input['location_screenshot']['uploaded_files']) && !empty($user_input['location_screenshot']['uploaded_files'])) {
    // ValueCallback didn't process it - manually process the raw string
    $raw_value = $user_input['location_screenshot']['uploaded_files'];
    \Drupal::logger('sr_quotation')->notice('DEBUG - Manual processing location_screenshot (flat path). Raw value: @raw', [
      '@raw' => $raw_value,
    ]);
  }
  
  // Process the raw value if we found it
  if (isset($raw_value) && is_string($raw_value)) {
    $file_names = array_filter(explode(';', $raw_value));
    $tmp_upload_scheme = \Drupal::configFactory()->get('dropzonejs.settings')->get('tmp_upload_scheme') ?: 'temporary';
    
    \Drupal::logger('sr_quotation')->notice('DEBUG - File names to process: @names, Scheme: @scheme', [
      '@names' => print_r($file_names, TRUE),
      '@scheme' => $tmp_upload_scheme,
    ]);
    
    foreach ($file_names as $name) {
      $name = trim($name);
      if (empty($name)) continue;
      
      // Remove .txt extension that DropzoneJS adds for security
      $name_without_txt = preg_replace('/\.txt$/', '', $name);
      $old_filepath = $tmp_upload_scheme . '://' . $name;
      
      \Drupal::logger('sr_quotation')->notice('DEBUG - Checking file: @path', ['@path' => $old_filepath]);
      
      // Check if file exists with .txt extension first
      if (!file_exists($old_filepath)) {
        $old_filepath = $tmp_upload_scheme . '://' . $name_without_txt;
        \Drupal::logger('sr_quotation')->notice('DEBUG - File with .txt not found, trying: @path', ['@path' => $old_filepath]);
      }
      
      if (file_exists($old_filepath)) {
        $uploaded[] = [
          'path' => $old_filepath,
          'filename' => basename($name_without_txt),
        ];
        \Drupal::logger('sr_quotation')->notice('DEBUG - Found file, added to uploaded array: @path', ['@path' => $old_filepath]);
      } else {
        \Drupal::logger('sr_quotation')->warning('DEBUG - File not found at either path: @path1 or @path2', [
          '@path1' => $tmp_upload_scheme . '://' . $name,
          '@path2' => $old_filepath,
        ]);
      }
    }
    
    \Drupal::logger('sr_quotation')->notice('DEBUG - Manual processing complete. Uploaded count: @count', [
      '@count' => count($uploaded),
    ]);
  }
  
  if (!empty($uploaded)) {

    // Ensure target directory exists in public://
    $directory = 'public://quotation_location_images/';
    \Drupal::service('file_system')->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY);

    foreach ($uploaded as $item) {
      // Defensive checks
      if (empty($item['path'])) {
        \Drupal::logger('sr_quotation')->warning('Location screenshot item missing path: @item', ['@item' => print_r($item, TRUE)]);
        continue;
      }

      // Temporary path (temporary://xxxxx)
      $temp_path = $item['path'];
      // Friendly filename (you may sanitize if needed)
      $filename = !empty($item['filename']) ? $item['filename'] : basename($temp_path);

      // Read contents from temporary file (stream wrappers supported)
      $data = @file_get_contents($temp_path);
      if ($data === FALSE) {
        \Drupal::logger('sr_quotation')->error('Unable to read temp file: @path', ['@path' => $temp_path]);
        continue;
      }

      // Build destination URI (public://)
      $destination = $directory . $filename;

      // Save bytes to destination; this returns the destination URI on success
      $saved_uri = \Drupal::service('file_system')->saveData($data, $destination, FileSystemInterface::EXISTS_RENAME);

      if ($saved_uri) {
        // Create a File entity from the saved URI
        $file = File::create([
          'uri' => $saved_uri,
          'filename' => $filename,
        ]);

        // Mark permanent and save entity
        $file->setPermanent();
        $file->save();

        $fid = $file->id();
        $location_screenshot_fids[] = $fid;

        \Drupal::logger('sr_quotation')->notice('Created location screenshot file with FID: @fid (uri: @uri)', [
          '@fid' => $fid,
          '@uri' => $saved_uri,
        ]);
      } else {
        \Drupal::logger('sr_quotation')->error('Failed to save data for file: @name (temp: @temp)', [
          '@name' => $filename,
          '@temp' => $temp_path,
        ]);
      }
    }
  }
  else {
    \Drupal::logger('sr_quotation')->warning('No Dropzone uploaded files found for location_screenshot in form values.');
  }

  // Save to DB (images field stores comma separated FIDs)
  \Drupal::database()->insert('sr_quotation')
    ->fields([
      'booker_name' => $values['booker_name'] ?? '',
      'travel_partner' => $values['travel_partner'] ?? '',
      'uid' => $uid,
      'checkin_date' => $values['check_in_date'] ?? '',
      'checkout_date' => $values['check_out_date'] ?? '',
      'checkin_time' => $values['check_in_time'] ?? '',
      'checkout_time' => $values['check_out_time'] ?? '',
      'nights' => $values['nights'] ?? '',
      'unit_type' => $values['unit_type'] ?? '',
      'apartment_size' => $values['apartment_size'] ?? '',
      'room_type' => $values['room_type'] ?? '',
      'bathrooms' => $values['bathrooms'] ?? '',
      'adults' => $values['adults'] ?? '',
      'kids' => $values['kids'] ?? '',
      'location' => $values['location'] ?? '',
      'distance' => $values['distance'] ?? '',
      'map_link' => $values['map_link'] ?? '',
      'description' => $values['description'] ?? '',
      'location_screenshot' => !empty($location_screenshot_fids) ? implode(',', $location_screenshot_fids) : NULL,
      'quote' => $values['quote'] ?? '',
      'taxes' => $values['taxes'] ?? '',
      'fx_rate' => $values['fx_rate'] ?? '',
      'addon_prices' => $values['addon_prices'] ?? '',
      'currency' => $this->getFinalCurrencyValue($values),
      'amenities' => $amenities,
      'cancellation_policy' => $values['cancellation_policy'] ?? '',
      'images' => !empty($image_fids) ? implode(',', $image_fids) : '',
      'rules' => $values['rules'] ?? '',
      'note' => $values['note'] ?? '',
      'confirmation_status' => $values['confirmation_status'] ?? '',
      'supplier_reference' => $supplier_reference_combined,
      'created' => \Drupal::time()->getRequestTime(),
    ])
    ->execute();

  $image_count = count($image_fids);
  $screenshot_count = count($location_screenshot_fids);
  
  // Debug logging
  \Drupal::logger('sr_quotation')->notice('Image counts - Property: @img, Screenshots: @scr', [
    '@img' => $image_count,
    '@scr' => $screenshot_count,
    '@fids' => print_r($location_screenshot_fids, TRUE),
  ]);
  
  $message = $this->t('Quotation submitted successfully.');
  if ($image_count > 0) {
    $message .= ' ' . $this->t('Property images saved: @count', ['@count' => $image_count]);
  }
  if ($screenshot_count > 0) {
    $message .= ' ' . $this->t('Location screenshots saved: @count', ['@count' => $screenshot_count]);
  }
  \Drupal::messenger()->addMessage($message);
  $form_state->setRedirect('sr_quotation.list');
}
}
