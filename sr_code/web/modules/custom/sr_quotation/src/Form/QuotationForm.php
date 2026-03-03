<?php

namespace Drupal\sr_quotation\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\file\Entity\File;
use Drupal\Core\File\FileSystemInterface;
use Drupal\user\Entity\User;

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
      '#maxlength' => 255,
      '#attributes' => [
        'placeholder' => $this->t('Enter Booker Name'),
        'class' => ['booker-name-field'],
        'maxlength' => 255, 
      ],
      '#description' => $this->t(''),
    ];

    $form['booking_info']['travel_partner'] = [
      '#type' => 'select',
      '#title' => $this->t('Enquired by'),
      '#required' => TRUE,
      '#options' => [
        '' => $this->t('- Select -'),
        'Travel Partner' => 'Travel Partner',
        'Corporate' => 'Corporate',
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
      '#attributes' => [
        'class' => ['datepicker'],
        'data-date-type' => 'check-in',
      ],
    ];
    $form['booking_info']['dates']['check_out_date'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Check-out Date'),
      '#required' => TRUE,
      '#attributes' => [
        'class' => ['datepicker'],
        'data-date-type' => 'check-out',
      ],
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
      '#min' => 0,
      '#max' => 9999999999,
      '#attributes' => [
        'placeholder' => $this->t('Auto-filled from dates'),
        'class' => ['nights-field'],
        'maxlength' => 10,
        'readonly' => 'readonly',
      ],
      '#description' => $this->t('Auto-filled from check-in and check-out dates.'),
    ];

    $form['booking_info']['enquired_location'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Enquired Location'),
      '#maxlength' => 255,
      '#attributes' => [
        'placeholder' => $this->t('Enter enquired location'),
        'class' => ['enquired-location-field'],
        'maxlength' => 255, 
      ],
      '#description' => $this->t(''),
    ];

    // Get current user's customer information
    $current_user = \Drupal::currentUser();
    $user = User::load($current_user->id());
    
    $agent_name = '';
    $agent_email = '';
    $agent_phone = '';
    
    if ($user) {
      $first_name = $user->hasField('field_first_name') ? $user->get('field_first_name')->value : '';
      $last_name = $user->hasField('field_last_name') ? $user->get('field_last_name')->value : '';
      $agent_name = trim($first_name . ' ' . $last_name);
      if ($agent_name === '') {
        $agent_name = $user->getDisplayName();
      }
      $agent_email = $user->getEmail();

      $phone_number = '';
      if ($user->hasField('field_phone_number') && !$user->get('field_phone_number')->isEmpty()) {
        $phone_number = $user->get('field_phone_number')->value;
      } elseif ($user->hasField('field_contact_number') && !$user->get('field_contact_number')->isEmpty()) {
        $phone_number = $user->get('field_contact_number')->value;
      } elseif ($user->hasField('field_agent_contact_number') && !$user->get('field_agent_contact_number')->isEmpty()) {
        $phone_number = $user->get('field_agent_contact_number')->value;
      }

      $agent_phone = $phone_number;
    }

    // Agent Information (Read-only fields)
    $form['booking_info']['agent_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Agent Name'),
      '#default_value' => $agent_name,
      '#disabled' => TRUE,
      '#attributes' => [
        'readonly' => 'readonly',
      ],
    ];

    $form['booking_info']['agent_phone'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Agent Phone Number'),
      '#default_value' => $agent_phone,
      '#disabled' => TRUE,
      '#attributes' => [
        'readonly' => 'readonly',
      ],
    ];

    $form['booking_info']['agent_email'] = [
      '#type' => 'email',
      '#title' => $this->t('Agent Email'),
      '#default_value' => $agent_email,
      '#disabled' => TRUE,
      '#attributes' => [
        'readonly' => 'readonly',
      ],
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
      '#maxlength' => 100,
      '#attributes' => [
        'class' => ['room-type-field'],
        'maxlength' => 100, 
        'placeholder' => $this->t('Enter room type'),
      ],
    ];
    $form['property_info']['room_details']['bathrooms'] = [
      '#type' => 'select',
      '#title' => $this->t('No. of Bathrooms'),
      '#options' => [
            '1' => '1', 
            '2' => '2', 
            '3' => '3', 
            '4' => '4',
            '5' => '5',
            '6' => '6',
            '7' => '7',
            '8' => '8',
            '9' => '9',
            '10' => '10',

      ],
      '#required' => TRUE,
    ];
    $form['property_info']['apartment_size'] = [
      '#type' => 'number',
      '#title' => $this->t('Apartment Size (sqm)'),
      '#min' => 0,
      '#max' => 4999999999,
      '#attributes' => [
        'class' => ['apartment-size-field'],
        'maxlength' => 50, 
      ],
      '#description' => $this->t(''),
    ];

    $form['property_info']['occupancy'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['form-row']],
    ];
    $form['property_info']['occupancy']['adults'] = [
      '#type' => 'number',
      '#title' => $this->t('No. of Adults'),
      '#min' => 0,
      '#max' => 9999999999,
      '#attributes' => [
        'class' => ['adults-field'],
        'maxlength' => 10, 
      ],
      '#description' => $this->t(''),
    ];
    $form['property_info']['occupancy']['kids'] = [
      '#type' => 'textarea',
      '#title' => $this->t('No. of Kids'),
      '#description' => $this->t(''),
      // '#rows' => 3,
      '#attributes' => [
        'class' => ['kids-field'],
      ],
    ];

    $form['property_info']['location_details'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['form-row']],
    ];
    $form['property_info']['location_details']['location'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Location'),
      '#description' => $this->t(''),
      '#maxlength' => 255,
      '#attributes' => [
        'class' => ['enquired-location-field'],
        'maxlength' => 255, 
      ],
    ];
    
    $form['property_info']['location_details']['distance'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Distance (in km)'),
      '#maxlength' => 255,
      '#attributes' => [
        'class' => ['distance-field'],
        'maxlength' => 255, 
      ],
      '#description' => $this->t(''),
    ];
    $form['property_info']['map_link'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Map Link'),
      '#description' => $this->t(''),
      '#maxlength' => 255,
      '#attributes' => [
        'class' => ['map-link-field'],
        'maxlength' => 255, 
      ],      
    ];
    $form['property_info']['description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Property Description'),
    ];

    $form['property_info']['location_screenshot'] = [
      '#type' => 'dropzonejs',
      '#title' => $this->t('Location Screenshot'),
      '#description' => $this->t('Upload location screenshot/image (JPEG, PNG, WEBP, AVIF).'),
      '#dropzone_description' => $this->t('Drag & drop images or click to upload'),
      '#multiple' => TRUE,
      '#upload_location' => 'public://quotation_location_images/',
      '#max_files' => 10,
      '#extensions' => 'png jpg jpeg webp avif',
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
      '#maxlength' => 255,
      '#attributes' => [
        'class' => ['quote-field'],
        'maxlength' => 255, 
      ],
      '#description' => $this->t(''),
    ];
    $form['financials']['taxes'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Taxes'),
      '#maxlength' => 100,
      '#attributes' => [
        'class' => ['taxes-field'],
        'maxlength' => 100, 
      ],
      '#description' => $this->t(''),
    ];
    $form['financials']['fx_rate'] = [
      '#type' => 'textarea',
      '#title' => $this->t('FX Rate'),
      '#rows' => 3,
      '#attributes' => [
        'class' => ['fx-rate-field'],
      ],
      '#description' => $this->t(''),
    ];
    $form['financials']['addon_prices'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Add-on Prices'),
      '#maxlength' => 300,
      '#rows' => 4,
      '#attributes' => [
        'class' => ['addon-prices-field'],
        'maxlength' => 300,
      ],
      '#description' => $this->t(''),
    ];

    // Financial Calculation Fields
    $form['financials']['avg_nightly_rate'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Avg. Nightly / Monthly Rate'),
      '#maxlength' => 255,
      '#attributes' => [
        'class' => ['avg-nightly-rate-field'],
      ],
      '#description' => $this->t(''),
    ];

    $form['financials']['extras_tax'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Extras (Tax)'),
      '#maxlength' => 255,
      '#attributes' => [
        'class' => ['extras-tax-field'],
      ],
      '#description' => $this->t(''),
    ];

    $form['financials']['total_outlay'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Total Outlay'),
      '#maxlength' => 255,
      '#attributes' => [
        'class' => ['total-outlay-field'],
      ],
      '#description' => $this->t(''),
    ];

    $form['financials']['markup_client_percentage'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Mark-Up to Client (%)'),
      '#maxlength' => 255,
      '#attributes' => [
        'class' => ['markup-client-percentage-field'],
      ],
      '#description' => $this->t(''),
    ];

    $form['financials']['markup_client_value'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Mark-up to Client (Value)'),
      '#maxlength' => 255,
      '#attributes' => [
        'class' => ['markup-client-value-field'],
      ],
      '#description' => $this->t(''),
    ];

    $form['financials']['total_profit'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Total Profit'),
      '#maxlength' => 255,
      '#attributes' => [
        'class' => ['total-profit-field'],
      ],
      '#description' => $this->t(''),
    ];

    $form['financials']['commission_sr_percentage'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Commission to StayRelive (%)'),
      '#maxlength' => 255,
      '#attributes' => [
        'class' => ['commission-sr-percentage-field'],
      ],
      '#description' => $this->t(''),
    ];

    $form['financials']['commission_sr_value'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Commission to StayRelive (Value)'),
      '#maxlength' => 255,
      '#attributes' => [
        'class' => ['commission-sr-value-field'],
      ],
      '#description' => $this->t(''),
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
      '#type' => 'text_format',
      '#title' => $this->t('Cancellation Policy'),
      '#format' => 'full_html',
      '#allowed_formats' => ['full_html', 'basic_html', 'restricted_html'],
    ];
    $form['extras']['rules'] = [
      '#type' => 'text_format',
      '#title' => $this->t('Rules & Regulations'),
      '#format' => 'full_html',
      '#allowed_formats' => ['full_html'],
    ];
    $form['extras']['note'] = [
      '#type' => 'text_format',
      '#title' => $this->t('Note (Extra Details)'),
      '#format' => 'full_html',
      '#allowed_formats' => ['full_html'],
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
      '#required' => FALSE,
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
      '#extensions' => 'png jpg jpeg webp avif',
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

    if (empty($files)) {
      \Drupal::logger('sr_quotation')->warning('No apartment_images files received in validation');
    }

    // Also check location_screenshot in validation
    $location_files = $form_state->getValue('location_screenshot', []);

    if (empty($location_files)) {
      \Drupal::logger('sr_quotation')->warning('No location_screenshot files received in validation');
    }

    // Validate quote character limit
    $quote = $form_state->getValue('quote', '');
    if (!empty($quote) && mb_strlen($quote) > 255) {
      $form_state->setError(
        $form['financials']['quote'],
        $this->t(
          'Quote must not exceed 255 characters. Current length: @length',
          ['@length' => mb_strlen($quote)]
        )
      );
    }

    // Validate taxes character limit
    $taxes = $form_state->getValue('taxes', '');
    if (!empty($taxes) && mb_strlen($taxes) > 100) {
      $form_state->setError(
        $form['financials']['taxes'],
        $this->t(
          'Taxes must not exceed 100 characters. Current length: @length',
          ['@length' => mb_strlen($taxes)]
        )
      );
    }

    // Validate addon_prices character limit
    $addon_prices = $form_state->getValue('addon_prices', '');
    if (!empty($addon_prices) && mb_strlen($addon_prices) > 300) {
      $form_state->setError(
        $form['financials']['addon_prices'],
        $this->t(
          'Add-on Prices must not exceed 300 characters. Current length: @length',
          ['@length' => mb_strlen($addon_prices)]
        )
      );
    }

  }

  public function submitForm(array &$form, FormStateInterface $form_state)
  {
    $values = $form_state->getValues();

    // Recalculate nights from check-in and check-out dates (d-m-Y format).
    $check_in = $values['check_in_date'] ?? '';
    $check_out = $values['check_out_date'] ?? '';
    if ($check_in !== '' && $check_out !== '') {
      $d1 = \DateTime::createFromFormat('d-m-Y', trim($check_in));
      $d2 = \DateTime::createFromFormat('d-m-Y', trim($check_out));
      if ($d1 && $d2 && $d2 >= $d1) {
        $values['nights'] = (int) $d1->diff($d2)->days;
      }
    }

    $uid = \Drupal::currentUser()->id();
    $user_input = $form_state->getUserInput();

    \Drupal::logger('sr_quotation_debug')->notice('<pre>VALUES: @v</pre>', [
      '@v' => print_r($values, TRUE),
    ]);

    \Drupal::logger('sr_quotation_debug')->notice('<pre>USER_INPUT: @u</pre>', [
      '@u' => print_r($user_input, TRUE),
    ]);

    $image_fids = [];

    $uploaded_apartment = [];

    if (!empty($values['apartment_images']['uploaded_files'])) {

      $uploaded_apartment = $values['apartment_images']['uploaded_files'];

    } elseif (!empty($user_input['apartment_images']['uploaded_files'])) {

      $raw_value = $user_input['apartment_images']['uploaded_files'];

      $file_names = array_filter(explode(';', $raw_value));

      $tmp_upload_scheme = \Drupal::configFactory()
        ->get('dropzonejs.settings')
        ->get('tmp_upload_scheme') ?: 'temporary';

      foreach ($file_names as $name) {

        $name_without_txt = preg_replace('/\.txt$/', '', trim($name));

        $temp_path = $tmp_upload_scheme . '://' . $name_without_txt;

        if (file_exists($temp_path)) {

          $uploaded_apartment[] = [
            'path' => $temp_path,
            'filename' => basename($name_without_txt),
          ];
        }
      }
    }

    $directory = 'public://quotation_images/';
    \Drupal::service('file_system')->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY);

    foreach ($uploaded_apartment as $item) {

      $data = file_get_contents($item['path']);

      $destination = $directory . $item['filename'];

      $saved_uri = \Drupal::service('file_system')
        ->saveData($data, $destination, FileSystemInterface::EXISTS_RENAME);

      if ($saved_uri) {

        $file = File::create([
          'uri' => $saved_uri,
          'filename' => $item['filename'],
        ]);

        $file->setPermanent();
        $file->save();

        $image_fids[] = $file->id();

        \Drupal::logger('sr_quotation_my_image')->notice('Saved file FID: @fid', [
          '@fid' => $file->id()
        ]);
      }
    }
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

    // STRICT check ONLY correct field
    $location_screenshot_fids = [];
    $uploaded_location = [];

    $user_input = $form_state->getUserInput();

    if (!empty($user_input['location_screenshot']['uploaded_files'])) {

      $raw_location_value = $user_input['location_screenshot']['uploaded_files'];

      \Drupal::logger('sr_quotation_debug')->notice('Raw uploaded_files: @val', [
        '@val' => $raw_location_value
      ]);

      $file_names = array_filter(explode(';', $raw_location_value));

      $tmp_upload_scheme = \Drupal::configFactory()
        ->get('dropzonejs.settings')
        ->get('tmp_upload_scheme') ?: 'temporary';

      $directory = 'public://quotation_location_images/';
      \Drupal::service('file_system')->prepareDirectory(
        $directory,
        FileSystemInterface::CREATE_DIRECTORY
      );

      foreach ($file_names as $name) {

        $name_without_txt = preg_replace('/\.txt$/', '', trim($name));

        $temp_path = $tmp_upload_scheme . '://' . $name_without_txt;

        \Drupal::logger('sr_quotation_debug')->notice('Checking temp path: @path', [
          '@path' => $temp_path
        ]);

        if (file_exists($temp_path)) {

          $data = file_get_contents($temp_path);

          $destination = $directory . basename($name_without_txt);

          $saved_uri = \Drupal::service('file_system')
            ->saveData($data, $destination, FileSystemInterface::EXISTS_RENAME);

          if ($saved_uri) {

            $file = File::create([
              'uri' => $saved_uri,
              'filename' => basename($name_without_txt),
            ]);

            $file->setPermanent();
            $file->save();

            $location_screenshot_fids[] = $file->id();

            \Drupal::logger('sr_quotation')->notice(
              'Saved screenshot FID: @fid',
              ['@fid' => $file->id()]
            );
          }
        }
      }
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
        'enquired_location' => $values['enquired_location'] ?? '',
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
        'cancellation_policy' => $values['cancellation_policy']['value'] ?? '',
        'images' => !empty($image_fids) ? implode(',', $image_fids) : '',
        'rules' => $values['rules']['value'] ?? '',
        'note' => $values['note']['value'] ?? '',
        'confirmation_status' => $values['confirmation_status'] ?? '',
        'supplier_reference' => $supplier_reference_combined,
        'avg_nightly_rate' => $values['avg_nightly_rate'] ?? '',
        'extras_tax' => $values['extras_tax'] ?? '',
        'total_outlay' => $values['total_outlay'] ?? '',
        'markup_client_percentage' => $values['markup_client_percentage'] ?? '',
        'markup_client_value' => $values['markup_client_value'] ?? '',
        'total_profit' => $values['total_profit'] ?? '',
        'commission_sr_percentage' => $values['commission_sr_percentage'] ?? '',
        'commission_sr_value' => $values['commission_sr_value'] ?? '',
        'created' => \Drupal::time()->getRequestTime(),
      ])
      ->execute();

    $image_count = count($image_fids);
    $screenshot_count = count($location_screenshot_fids);

    $message = $this->t('Quotation submitted successfully.');
    \Drupal::messenger()->addMessage($message);
    $form_state->setRedirect('sr_quotation.list');
  }
}