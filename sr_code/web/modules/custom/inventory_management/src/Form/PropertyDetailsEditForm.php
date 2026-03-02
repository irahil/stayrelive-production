<?php

namespace Drupal\inventory_management\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\common_utilities\Utilities\commonUtil;
use Drupal\Core\Url;
use Drupal\Core\Link;

class PropertyDetailsEditForm extends FormBase {

  // Constants for better maintainability
  private const MAX_FILE_SIZE = 25600000; // 25MB
  private const ALLOWED_EXTENSIONS = 'png jpg jpeg';
  private const PROPERTY_NAME_MIN_LENGTH = 3;
  private const PROPERTY_NAME_MAX_LENGTH = 100;
  private const PINCODE_LENGTH = 6;

  public function getFormId() {
    return 'property_details_edit_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state, $id = 0) {
    commonUtil::validateVendor();
    // Ensure $id is an integer and greater than 0
    $id = (int) $id;
    if ($id <= 0) {
      throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException();
    }
    $node = \Drupal::entityTypeManager()
      ->getStorage('node')
      ->load($id);
    if (!$node || !$node->hasField('field_vendor_id') || !commonUtil::checkVendor($node) || !$node instanceof \Drupal\node\NodeInterface
     || $node->bundle() !== 'property') {
      throw new \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException();
    }

     // ✅ Check if property has rooms created
  $property = $node;

  if ($property && $property->bundle() === 'property') {
    // Load related rooms where field_parent_id matches property ID
    $rooms = \Drupal::entityTypeManager()->getStorage('node')->loadByProperties([
      'type' => 'room',
      'field_parent_id' => $property->id(),
      // 'status' => 1, // Optional: only count published rooms
    ]);

    if (empty($rooms)) {
      $add_room_link = Link::fromTextAndUrl(
        $this->t('Add Room'),
        Url::fromRoute('inventory_management.property_room_add', ['pid' => $property->id()])
      )->toString();

      \Drupal::messenger()->addMessage(
        $this->t('⚠️ This property currently has no rooms. Please @link to complete the property setup.', ['@link' => $add_room_link]),
    'warning'
      );
    }
  }

    $form['back_link'] = [
      '#type' => 'link',
      '#title' => $this->t('Back'),
      '#url' => \Drupal\Core\Url::fromRoute('inventory_management.property_list'),
    ];

    // Property Room Info
    $form['property_room_info'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Room Info'),
    ];

    $add_room_url = Url::fromRoute('inventory_management.property_room_add', [
                'pid' =>$id,
                ], ['absolute' => TRUE])->toString();

    $link = Link::fromTextAndUrl(
            $this->t('Add Room'),
        Url::fromUri( $add_room_url,
            array('absolute' => TRUE,)))->toString();

    $form['property_room_info']['add_room_link'] = [
        '#markup' => $link,
    ];

    $nids = \Drupal::entityQuery('node')
      ->condition('type', 'room')
      ->condition('field_parent_id', $id)
      ->accessCheck(TRUE)
      ->execute();

    if (!empty($nids)) {
      $form['property_room_info']['room_table'] = [
        '#type' => 'table',
        '#header' => [
          'title' => $this->t('Room Name'),
          'edit' => $this->t('Edit'),
          'delete' => $this->t('Delete'),
        ],
        '#empty' => $this->t('No rooms found'),
      ];

      $nodes = \Drupal\node\Entity\Node::loadMultiple($nids);
      foreach ($nodes as $child_node) {
        $edit_url = Url::fromRoute('inventory_management.property_room_edit', [
          'pid' => $id,
          'id' => $child_node->id(),
        ]);
        
        $delete_url = Url::fromRoute('inventory_management.property_room_delete', [
          'pid' => $id,
          'id' => $child_node->id(),
        ]);

        $form['property_room_info']['room_table'][$child_node->id()] = [
          'title' => [
            '#markup' => $child_node->label(),
          ],
          'edit' => [
            '#type' => 'link',
            '#title' => $this->t('Manage'),
            '#url' => $edit_url,
          ],
          'delete' => [
            '#type' => 'link',
            '#title' => $this->t('Delete'),
            '#url' => $delete_url,
            '#attributes' => [
              'class' => ['room-delete-link'],
              'data-delete-url' => $delete_url->toString(),
            ],
          ],
        ];
      }
    } else {
      $form['property_room_info']['no_rooms'] = [
        '#markup' => $this->t('No rooms found for this property.'),
      ];
    }
    $form['id'] = [
      '#type' => 'hidden',
      '#value' => $id
    ];

    // Property Basic Info
    $form['property_basic_info'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Property Basic Info'),
    ];

    if (commonUtil::isSiteAdmin()) {
      $form['property_basic_info']['published'] = [
        '#type' => 'checkboxes',
        '#title' => $this->t('Published Status'),
        '#options' => ['1' => 'Published'],
        '#default_value' => $node->isPublished() ? ['1'] : [],
      ];
      $form['property_basic_info']['markup_type_wrapper'] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['markup-type-wrapper']],
      ];
      
      // Format the current value for display (remove unnecessary decimals)
      $current_markup = $node->get('field_markup_price')->getString();
      $formatted_markup = $this->formatMarkupPriceForDisplay($current_markup);
      
      $form['property_basic_info']['markup_type_wrapper']['markup_price'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Markup Price (%)'),
        '#description' => $this->t('Enter a value between 0 and 100. Only numbers are allowed (e.g., enter "25" for 25%).'),
        '#default_value' => $formatted_markup,
        '#required' => TRUE,
        '#attributes' => [
          'class' => ['form-half'],
          'placeholder' => $this->t('e.g., 25 for 25%'),
          'pattern' => '^[0-9]+\.?[0-9]*$',
        ],
      ];
    }

    $arr_property_type = commonUtil::get_term_list('property_type');

    $form['property_basic_info']['markup_type_wrapper']['property_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Property type'),
      '#options' => $arr_property_type,
      '#default_value' => $node->get('field_property_type')->getString(),
      '#required' => TRUE,
      '#attributes' => ['class' => ['form-half']],
    ];

    $arr_room_type = commonUtil::get_term_list('room_type');

    $selected_values = ($node->get('field_room_types')->getValue() != "") ? 
      array_map('trim', explode(",", $node->get('field_room_types')->getString())) : 
      array();

    $form['property_basic_info']['room_types'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Room Types'),
      '#options' => $arr_room_type,
      '#default_value' => $selected_values,
      '#required' => TRUE,
      "#attributes" => ['class' => ['hosting-type-wrapper']],
    ];

    $form['property_basic_info']['property_name_age_wrapper'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['property-name-age-wrapper']],
    ];
    $form['property_basic_info']['property_name_age_wrapper']['property_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Property name'),
      '#required' => TRUE,
      '#default_value' => $node->get('field_property_name')->getString(),
      '#attributes' => ['class' => ['form-half']],
    ];


    $form['property_basic_info']['property_name_age_wrapper']['area_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Area name'),
      '#required' => TRUE,
      '#default_value' => $node->get('field_area_name')->getString(),
      '#attributes' => [
        'class' => ['form-half'],
        'placeholder' => $this->t('Enter Area name'),
      ],
    ];

    $form['property_basic_info']['property_name_age_wrapper']['display_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Display name'),
      '#required' => TRUE,
      '#default_value' => $node->get('field_display_name')->getString(),
      '#attributes' => [
        'class' => ['form-half'],
        'placeholder' => $this->t('Enter generic display name'),
      ],
      '#access' => commonUtil::isSiteAdmin(),
    ];

    $form['property_basic_info']['property_name_age_wrapper']['property_age'] = [
      '#type' => 'number',
      '#title' => $this->t('Property age'),
      '#min' => 0,
      '#max' => 50,
      '#step' => 1,
      '#default_value' => (int) $node->get('field_property_age')->getString(),
      '#attributes' => ['class' => ['form-half']],
    ];
    
    $form['property_basic_info']['property_description'] = [
      '#type' => 'text_format',
      '#title' => $this->t('Property Description'),
      '#default_value' => $node->get('field_description')->getString(),
      '#required' => TRUE,
      '#format' => 'plain_text', // Or 'full_html', depending on allowed formats
      '#rows' => 5,
      '#allowed_formats' => ['plain_text'], // Limit dropdown to one format
    ];

    $form['property_basic_info']['lat_lng_row'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['lat-lng-wrapper']],
    ];

    $form['property_basic_info']['lat_lng_row']['latitude'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Latitude'),
      '#default_value' => $node->get('field_latitude')->getString(),
      '#attributes' => ['class' => ['lat-field']],
    ];
    $form['property_basic_info']['lat_lng_row']['longitude'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Longitude'),
      '#default_value' => $node->get('field_longitude')->getString(),
      '#attributes' => ['class' => ['lng-field']],
    ];
    $form['property_basic_info']['address'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Address'),
      '#default_value' => $node->get('field_display_address')->getString(),
      '#required' => TRUE,
      '#attributes' => [
        'class' => ['form-full'],
      ],
    ];

    $arr_country = commonUtil::get_term_list('country');
    $arr_state = commonUtil::get_term_list('state');
    $arr_city = commonUtil::get_term_list('city');

    $form['property_basic_info']['location_row_1'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['location-wrapper']],
    ];

    $form['property_basic_info']['location_row_1']['country'] = [
      '#type' => 'select',
      '#title' => $this->t('Country'),
      '#options' => $arr_country,
      '#default_value' => $node->get('field_country')->getString(),
      '#attributes' => [
        'class' => ['form-half'],
      ],
    ];
    $form['property_basic_info']['location_row_1']['state'] = [
      '#type' => 'select',
      '#title' => $this->t('State'),
      '#options' => $arr_state,
      '#default_value' => $node->get('field_state')->getString(),
      '#required' => TRUE,
      '#attributes' => [
        'class' => ['form-half'],
      ],
    ];

    $form['property_basic_info']['location_row_2'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['location-wrapper']],
    ];

    $form['property_basic_info']['location_row_2']['city'] = [
      '#type' => 'select',
      '#title' => $this->t('City'),
      '#options' => $arr_city,
      '#default_value' => $node->get('field_city')->getString(),
      '#required' => TRUE,
      '#attributes' => [
        'class' => ['form-half'],
      ],
    ];
    $form['property_basic_info']['location_row_2']['pincode'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Pincode'),
      '#default_value' => $node->get('field_pincode')->getString(),
      '#required' => TRUE,
      '#attributes' => [
        'class' => ['form-half'],
      ],
    ];

    // Property Details
    $form['property_details'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Property Details'),
    ];

    $existing_primary_fids = [];

    if ($node instanceof \Drupal\node\NodeInterface && $node->hasField('field_primary_media')) {
      foreach ($node->get('field_primary_media')->getValue() as $fid_val) {
        $existing_primary_fids[] = $fid_val['target_id'];
      }
    }

    $form['property_details']['primary_media'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Property Primary Photos'),
      '#description' => $this->t('Upload the primary image.'),
      '#upload_location' => 'public://property_primary_media/',
      '#multiple' => FALSE,
      '#default_value' => $existing_primary_fids,
      '#upload_validators' => [
        'file_validate_extensions' => ['png jpg jpeg webp'],
        'file_validate_size' => [25600000], // 25MB max per file
      ],
    ];

    $existing_fids = [];

    if ($node instanceof \Drupal\node\NodeInterface && $node->hasField('field_media')) {
      foreach ($node->get('field_media')->getValue() as $fid_val) {
        $existing_fids[] = $fid_val['target_id'];
      }
    }

    $form['property_details']['media'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Property Photos'),
      '#description' => $this->t('Upload one or more images.'),
      '#upload_location' => 'public://property_media/',
      '#multiple' => TRUE,
      '#default_value' => $existing_fids,
      '#upload_validators' => [
        'file_validate_extensions' => ['png jpg jpeg webp'],
        'file_validate_size' => [25600000], // 25MB max per file
      ],
    ];

    $form['property_details']['on_site_staffing'] = [
      '#type' => 'radios',
      '#title' => $this->t('On-Site Staffing'),
      '#options' => [
        'Reception Available' => $this->t('Reception Available'),
        'Caretaker Available' => $this->t('Caretaker Available'),
      ],
      '#default_value' => $node->get('field_on_site_staffing')->getString(),
      "#attributes" => ['class' => ['hosting-type-wrapper']],
    ];

    $arr_property_amenities = commonUtil::get_term_list('property_amenities');

    $selected_values = ($node->get('field_amenities')->getValue() != "") ? 
      array_map('trim', explode(",", $node->get('field_amenities')->getString())) : 
      array();

    $form['property_details']['property_amenities'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Property Amenities'),
      '#options' => $arr_property_amenities,
      '#default_value' => $selected_values,
      '#required' => TRUE,
      "#attributes" => ['class' => ['hosting-type-wrapper']],
    ];

    $form['property_details']['food_beverages'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Food & Beverages'),
      '#options' => [
        'restaurant' => $this->t('Restaurant Available'),
        'room_service' => $this->t('Room Service'),
        'breakfast' => $this->t('Breakfast Provided'),
      ],
      '#default_value' => $node->get('field_food_beverages')->getValue() ? array_column($node->get('field_food_beverages')->getValue(), 'value'): [],
      '#required' => FALSE,
      "#attributes" => ['class' => ['hosting-type-wrapper']],
    ];

    $form['property_details']['breakfast_provides'] = [
      '#type' => 'radios',
      '#title' => $this->t('Breakfast Provides'),
      '#options' => [
        'Complementary' => $this->t('Complementary'),
        'Chargeable' => $this->t('Chargeable'),
        'None' => $this->t('None'),
      ],
      '#default_value' => $node->hasField('field_breakfast_provides') ? $node->get('field_breakfast_provides')->getString() : 'None',
      '#required' => FALSE,
      "#attributes" => ['class' => ['hosting-type-wrapper']],
    ];

    $form['property_details']['breakfast_price'] = [
      '#type' => 'number',
      '#title' => $this->t('Breakfast Price'),
      '#description' => $this->t('Enter the price for breakfast if it is chargeable.'),
      '#min' => 0,
      '#step' => 1,
      '#default_value' => $node->hasField('field_breakfast_price') ? (int) $node->get('field_breakfast_price')->getString() : 0,
      '#attributes' => [
        'class' => ['form-half'],
        'placeholder' => $this->t('Enter breakfast price'),
      ],
    ];

    // Host Details
    $form['host_details'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Host Details'),
    ];
    $form['host_details']['hosting_type'] = [
      '#type' => 'radios',
      '#title' => $this->t('Hosting type'),
      '#options' => [
        'Individual' => $this->t('Individual'),
        'Business' => $this->t('Business'),
      ],
      '#default_value' => $node->get('field_hosting_type')->getString(),
      "#attributes" => ['class' => ['hosting-type-wrapper']],
    ];
    $form['host_details']['owner_contact_wrapper'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['owner-contact-wrapper']],
    ];
    $form['host_details']['owner_contact_wrapper']['owner_contact_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Property Contact Person'),
      '#default_value' => $node->get('field_contact_person')->getString(),
      '#attributes' => [
        'class' => ['form-half'],
      ],
    ];
    $form['host_details']['owner_contact_wrapper']['owner_contact_email'] = [
      '#type' => 'email',
      '#title' => $this->t('Property Contact Email'),
      '#default_value' => $node->get('field_contact_email_id')->getString(),
      '#attributes' => [
        'class' => ['form-half'],
      ],
    ];
    $form['host_details']['owner_contact_mobile'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Property Contact Number (Mobile/Landline)'),
      '#default_value' => $node->get('field_contact_number')->getString(),
      '#attributes' => [
        'class' => ['form-single-half'],
      ],
    ];

    $form['host_details']['designation'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Designation'),
      '#default_value' => $node->hasField('field_contact_designation') ? $node->get('field_contact_designation')->getString() : '',
      '#attributes' => [
        'class' => ['form-single-half'],
        'placeholder' => $this->t('Enter designation'),
      ],
    ];

   // Policy
    $form['policy_info'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Policies'),
    ];

    $form['policy_info']['cancellation_info'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Cancellation Policy'),
    ];

    $form['policy_info']['cancellation_info']['cancellation_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Cancellation Policy type'),
      '#options' => array('refundable' => $this->t('Refundable'), 'non_refundable' => $this->t('Non Refundable')),
      '#required' => TRUE,
      '#default_value' => $node->get('field_cancellation_type')->getString(),
    ];

    $form['policy_info']['cancellation_info']['refundable_days'] = [
      '#type' => 'number',
      '#title' => $this->t('Refundable Days'),
      '#description' => $this->t('Enter the number of days until which cancellation is allowed.'),
      '#min' => 0,
      '#step' => 1,
      '#default_value' => (int) $node->get('field_refundable_days')->getString(),
    ];

    $form['policy_info']['early_checkout'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Early Checkout Policy'),
    ];

    $form['policy_info']['early_checkout']['early_checkout'] = [
      '#type' => 'select',
      '#title' => $this->t('Early Checkout Allowed'),
      '#options' => array(
        'yes' => $this->t('Yes'),
        'no' => $this->t('No'),
      ),
      '#required' => TRUE,
      '#default_value' => $node->get('field_early_checkout')->getString(),
    ];

    $form['policy_info']['early_checkout']['early_checkout_days'] = [
      '#type' => 'number',
      '#title' => $this->t('Early Checkout is allowed, Intimation of'),
      '#description' => $this->t('Enter the number of days before which early checkout intimation is required.'),
      '#min' => 0,
      '#step' => 1,
      '#default_value' => (int) $node->get('field_early_checkout_days')->getString(),
    ];

    // Security Deposit with Currency
    $form['policy_info']['security_deposit_wrapper'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['security-deposit-wrapper']],
    ];

    $form['policy_info']['security_deposit_wrapper']['security_deposit'] = [
      '#type' => 'number',
      '#title' => $this->t('Security Deposit Amount'),
      '#min' => 0,
      '#step' => 1,
      '#default_value' => (int) $node->get('field_security_deposit')->getString(),
      '#attributes' => [
        'class' => ['form-half'],
        'placeholder' => $this->t('Enter deposit amount'),
      ],
      '#description' => $this->t("Enter '0' if no Security Deposit is required; otherwise, specify the Security Deposit amount."),
    ];

    $form['policy_info']['security_deposit_wrapper']['security_deposit_currency'] = [
      '#type' => 'select',
      '#title' => $this->t('Currency'),
      '#options' => [
        'INR' => $this->t('INR (Indian Rupee)'),  
        'AED' => $this->t('AED (UAE Dirham)'),
        'SAR' => $this->t('SAR (Saudi Riyal)'),
      ],
      '#default_value' => $node->hasField('field_security_deposit_currency') ? $node->get('field_security_deposit_currency')->getString() : 'INR',
      '#attributes' => [
        'class' => ['form-half'],
      ],
    ];

    // Finance Details
    $form['finance_details'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Finance Details'),
    ];
    $form['finance_details']['billing_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Billing name'),
      '#default_value' => $node->get('field_billing_name')->getString(),
      '#attributes' => ['class' => ['form-full']],
    ];

    // Bank Details
    $form['finance_details']['bank_details'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Bank details'),
      '#attributes' => ['class' => ['bank-details-wrapper']]
    ];
    $form['finance_details']['bank_details']['bank_account_wrapper'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['bank-account-wrapper']],
    ];
    $form['finance_details']['bank_details']['bank_account_wrapper']['account_holder_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Account Holder Name'),
      '#default_value' => $node->get('field_account_holder_name')->getString(),
      '#attributes' => ['class' => ['form-half']],
    ];
    $form['finance_details']['bank_details']['bank_account_wrapper']['account_number'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Account Number'),
      '#default_value' => $node->get('field_account_number')->getString(),
      '#attributes' => ['class' => ['form-half']],
    ];
    $form['finance_details']['bank_details']['bank_ifsc_wrapper'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['bank-ifsc-wrapper']],
    ];
    $form['finance_details']['bank_details']['bank_ifsc_wrapper']['bank_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Bank Name'),
      '#default_value' => $node->get('field_bank_name')->getString(),
      '#attributes' => ['class' => ['form-half']],
    ];
    $form['finance_details']['bank_details']['bank_ifsc_wrapper']['ifsc_code'] = [
      '#type' => 'textfield',
      '#title' => $this->t('IFSC Code'),
      '#default_value' => $node->get('field_ifsc_code')->getString(),
      '#attributes' => ['class' => ['form-half']],
    ];
    $form['finance_details']['bank_details']['bank_gst_wrapper'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['bank-gst-wrapper']],
    ];
    $form['finance_details']['bank_details']['bank_gst_wrapper']['gst_number'] = [
      '#type' => 'textfield',
      '#title' => $this->t('GST Number'),
      '#default_value' => $node->get('field_gst_number')->getString(),
      '#attributes' => ['class' => ['form-full']],
    ];

    // Submit button
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Update'),
    ];
    $form['#theme'] = 'property_details_edit_form';
    $form['#attached']['library'][] = 'inventory_management/property_list';

    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    
    // Validate all fields using optimized methods
    $this->validateRequiredField($values, 'property_name', 3, 100, '/^[a-zA-Z\s]+$/', 'Property name', $form_state);
    $this->validateRequiredField($values, 'area_name', 3, 100, '/^[a-zA-Z\s]+$/', 'Area name', $form_state);
    
    // Validate Markup Price if user is admin
    if (commonUtil::isSiteAdmin()) {
      $this->validateMarkupPrice($values, 'markup_price', $form_state);
      
      // Validate property publishing - must have published rooms
      $id = $form_state->getValue(['id']);
      $this->validatePropertyPublishing($values, 'published', $id, $form_state);
    }
    
    $this->validateOptionalNameField($values, 'owner_contact_name', 'Contact person name', $form_state);
    $this->validateOptionalEmailField($values, 'owner_contact_email', $form_state);
    $this->validateOptionalPhoneField($values, 'owner_contact_mobile', $form_state);
    $this->validateOptionalPincodeField($values, 'pincode', $form_state);
    $this->validateOptionalNumericField($values, 'property_age', 0, 50, 'Property age', $form_state);
    $this->validateCoordinates($values, $form_state);
    $this->validateBillingAndBankDetails($values, $form_state);
    $this->validatePolicyFields($values, $form_state);
  }

  /**
   * Validate required field with length and pattern constraints.
   */
  private function validateRequiredField(array $values, string $field_name, int $min_length, int $max_length, string $pattern, string $field_label, FormStateInterface $form_state): void {
    $value = trim($values[$field_name] ?? '');
    
    if (empty($value)) {
      $form_state->setErrorByName($field_name, $this->t('@field is required.', ['@field' => $field_label]));
      return;
    }
    
    if (strlen($value) < $min_length) {
      $form_state->setErrorByName($field_name, $this->t('@field must be at least @min characters long.', ['@field' => $field_label, '@min' => $min_length]));
      return;
    }
    
    if (strlen($value) > $max_length) {
      $form_state->setErrorByName($field_name, $this->t('@field cannot exceed @max characters.', ['@field' => $field_label, '@max' => $max_length]));
      return;
    }
    
    if (!preg_match($pattern, $value)) {
      $form_state->setErrorByName($field_name, $this->t('@field contains invalid characters.', ['@field' => $field_label]));
    }
  }

  /**
   * Validate optional name field.
   */
  private function validateOptionalNameField(array $values, string $field_name, string $field_label, FormStateInterface $form_state): void {
    $value = trim($values[$field_name] ?? '');
    
    if (empty($value)) {
      return; // Optional field, no validation needed
    }
    
    if (strlen($value) < 2) {
      $form_state->setErrorByName($field_name, $this->t('@field must be at least 2 characters long.', ['@field' => $field_label]));
      return;
    }
    
    if (strlen($value) > 100) {
      $form_state->setErrorByName($field_name, $this->t('@field cannot exceed 100 characters.', ['@field' => $field_label]));
      return;
    }
    
    if (!preg_match('/^[a-zA-Z\s\-\'\.]+$/', $value)) {
      $form_state->setErrorByName($field_name, $this->t('@field can only contain letters, spaces, hyphens, apostrophes, and periods.', ['@field' => $field_label]));
    }
  }

  /**
   * Validate optional email field.
   */
  private function validateOptionalEmailField(array $values, string $field_name, FormStateInterface $form_state): void {
    $value = trim($values[$field_name] ?? '');
    
    if (empty($value)) {
      return; // Optional field, no validation needed
    }
    
    if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
      $form_state->setErrorByName($field_name, $this->t('Please enter a valid email address.'));
      return;
    }
    
    if (strlen($value) > 254) {
      $form_state->setErrorByName($field_name, $this->t('Email address is too long.'));
    }
  }

  /**
   * Validate optional phone field.
   */
  private function validateOptionalPhoneField(array $values, string $field_name, FormStateInterface $form_state): void {
    $value = trim($values[$field_name] ?? '');
    
    if (empty($value)) {
      return; // Optional field, no validation needed
    }
    
    $clean_number = preg_replace('/[\s\-\(\)\+]/', '', $value);
    
    if (!preg_match('/^\d+$/', $clean_number)) {
      $form_state->setErrorByName($field_name, $this->t('Contact number can only contain digits, spaces, hyphens, parentheses, and plus signs.'));
      return;
    }
    
    if (strlen($clean_number) < 7) {
      $form_state->setErrorByName($field_name, $this->t('Contact number must be at least 7 digits long.'));
      return;
    }
    
    if (strlen($clean_number) > 15) {
      $form_state->setErrorByName($field_name, $this->t('Contact number cannot exceed 15 digits.'));
    }
  }

  /**
   * Validate optional pincode field.
   */
  private function validateOptionalPincodeField(array $values, string $field_name, FormStateInterface $form_state): void {
    $value = trim($values[$field_name] ?? '');
    
    if (empty($value)) {
      return; // Optional field, no validation needed
    }
    
    if (!preg_match('/^\d{6}$/', $value)) {
      $form_state->setErrorByName($field_name, $this->t('Pincode must be exactly 6 digits.'));
    }
  }

  /**
   * Validate optional numeric field.
   */
  private function validateOptionalNumericField(array $values, string $field_name, int $min_value, int $max_value, string $field_label, FormStateInterface $form_state): void {
    $value = trim($values[$field_name] ?? '');
    
    if (empty($value)) {
      return; // Optional field, no validation needed
    }
    
    if (!is_numeric($value)) {
      $form_state->setErrorByName($field_name, $this->t('@field must be a valid number.', ['@field' => $field_label]));
      return;
    }
    
    if ($value < $min_value) {
      $form_state->setErrorByName($field_name, $this->t('@field cannot be less than @min.', ['@field' => $field_label, '@min' => $min_value]));
      return;
    }
    
    if ($value > $max_value) {
      $form_state->setErrorByName($field_name, $this->t('@field cannot be more than @max.', ['@field' => $field_label, '@max' => $max_value]));
      return;
    }
    
    if (!preg_match('/^\d+$/', $value)) {
      $form_state->setErrorByName($field_name, $this->t('@field must be a whole number (no decimals).', ['@field' => $field_label]));
    }
  }

  /**
   * Validate Markup Price field.
   * Ensures it's numeric and within 0-100 range.
   */
  private function validateMarkupPrice(array $values, string $field_name, FormStateInterface $form_state): void {
    $value = trim($values[$field_name] ?? '');
    
    // Markup price is required for admins
    if (empty($value) && $value !== '0') {
      $form_state->setErrorByName($field_name, $this->t('Markup Price is required. Please enter a value between 0 and 100.'));
      return;
    }
    
    // Remove percentage sign if user entered it
    $value = str_replace('%', '', $value);
    $value = trim($value);
    
    // Check if it's numeric
    if (!is_numeric($value)) {
      $form_state->setErrorByName($field_name, $this->t('Markup Price must be a valid number. Please enter only the number (e.g., 10 for 10%), not "10%".'));
      return;
    }
    
    $numeric_value = (float) $value;
    
    // Check range (0 to 100)
    if ($numeric_value < 0) {
      $form_state->setErrorByName($field_name, $this->t('Markup Price cannot be negative. Please enter a value between 0 and 100.'));
      return;
    }
    
    if ($numeric_value > 100) {
      $form_state->setErrorByName($field_name, $this->t('Markup Price cannot exceed 100%. Please enter a value between 0 and 100.'));
      return;
    }
  }

  /**
   * Clean and format markup price value.
   * Removes % sign and formats to remove unnecessary decimals.
   */
  private function cleanMarkupPriceValue($value) {
    // Handle null, empty string, or empty arrays
    if ($value === null || $value === '' || (is_array($value) && empty($value))) {
      return '0';
    }
    
    // Convert to string and remove percentage sign if present
    $value = str_replace('%', '', (string) $value);
    $value = trim($value);
    
    // Return 0 if empty after cleaning
    if ($value === '') {
      return '0';
    }
    
    // Validate numeric before conversion
    if (!is_numeric($value)) {
      return '0';
    }
    
    // Convert to float and round to 2 decimal places
    $numeric_value = round((float) $value, 2);
    
    // If it's a whole number, return as integer string to avoid ".00"
    if ($numeric_value == (int) $numeric_value) {
      return (string) ((int) $numeric_value);
    }
    
    // Otherwise return with 2 decimal places, removing trailing zeros in one pass
    $formatted = number_format($numeric_value, 2, '.', '');
    return rtrim(rtrim($formatted, '0'), '.');
  }

  /**
   * Format markup price for display in form field.
   * Removes unnecessary decimals (e.g., 2.00 becomes 2).
   */
  private function formatMarkupPriceForDisplay($value) {
    // Handle null, empty string, or empty arrays
    if ($value === null || $value === '' || (is_array($value) && empty($value))) {
      return '0';
    }
    
    // Convert to string and remove percentage sign if present
    $value = str_replace('%', '', (string) $value);
    $value = trim($value);
    
    // Return 0 if empty after cleaning
    if ($value === '') {
      return '0';
    }
    
    // Validate numeric before conversion
    if (!is_numeric($value)) {
      return '0';
    }
    
    // Convert to float and round to 2 decimal places
    $numeric_value = round((float) $value, 2);
    
    // If it's a whole number, return as integer string to avoid ".00"
    if ($numeric_value == (int) $numeric_value) {
      return (string) ((int) $numeric_value);
    }
    
    // Otherwise return with 2 decimal places, removing trailing zeros in one pass
    $formatted = number_format($numeric_value, 2, '.', '');
    return rtrim(rtrim($formatted, '0'), '.');
  }

  /**
   * Validate property publishing - ensures property has published rooms.
   * Note: We don't set validation error here because it would prevent form submission.
   * Instead, we handle the unpublishing in submitForm and show a warning message.
   */
  private function validatePropertyPublishing(array $values, string $field_name, $property_id, FormStateInterface $form_state): void {
    // Check if admin is trying to publish the property
    $published = (isset($values[$field_name]) && isset($values[$field_name][1]) && $values[$field_name][1] == "1") ? TRUE : FALSE;
    
    if (!$published) {
      return; // Not trying to publish, no validation needed
    }
    
    // Check if property has published rooms
    // Note: We don't set error here to allow form submission and redirect.
    // The property will be auto-unpublished in submitForm if no published rooms exist.
    // This ensures the redirect still works.
  }

  /**
   * Check if property has published rooms.
   */
  private function propertyHasPublishedRooms($property_id) {
    if (empty($property_id)) {
      return FALSE;
    }
    
    $room_nids = \Drupal::entityQuery('node')
      ->condition('type', 'room')
      ->condition('field_parent_id', $property_id)
      ->condition('status', 1) // Only published rooms
      ->accessCheck(FALSE)
      ->execute();
    
    return !empty($room_nids);
  }

  /**
   * Validate coordinates.
   */
  private function validateCoordinates(array $values, FormStateInterface $form_state): void {
    $latitude = trim($values['latitude'] ?? '');
    $longitude = trim($values['longitude'] ?? '');
    
    // Validate Latitude
    if (!empty($latitude)) {
      if (!is_numeric($latitude)) {
        $form_state->setErrorByName('latitude', $this->t('Latitude must be a valid number.'));
      }
      elseif ($latitude < -90 || $latitude > 90) {
        $form_state->setErrorByName('latitude', $this->t('Latitude must be between -90 and 90 degrees.'));
      }
    }
    
    // Validate Longitude
    if (!empty($longitude)) {
      if (!is_numeric($longitude)) {
        $form_state->setErrorByName('longitude', $this->t('Longitude must be a valid number.'));
      }
      elseif ($longitude < -180 || $longitude > 180) {
        $form_state->setErrorByName('longitude', $this->t('Longitude must be between -180 and 180 degrees.'));
      }
    }
    
    // Check if both coordinates are provided together
    if ((!empty($latitude) && empty($longitude)) || (empty($latitude) && !empty($longitude))) {
      $form_state->setErrorByName('latitude', $this->t('Both latitude and longitude must be provided together.'));
      $form_state->setErrorByName('longitude', $this->t('Both latitude and longitude must be provided together.'));
    }
  }

  /**
   * Validate policy fields.
   */
  private function validatePolicyFields(array $values, FormStateInterface $form_state): void {
    if ($values['cancellation_type'] == "refundable" && $values['refundable_days'] <= 0) {
      $form_state->setErrorByName('refundable_days', $this->t('Refundable days must be greater than 0 if cancellation type is refundable.'));
    }
    
    if ($values['early_checkout'] == "yes" && $values['early_checkout_days'] <= 0) {
      $form_state->setErrorByName('early_checkout_days', $this->t('Early checkout days must be greater than 0 if early checkout is allowed.'));
    }
  }

  /**
   * Validate billing and bank details fields.
   * 
   * @param array $values
   *   Form values.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state object.
   */
  private function validateBillingAndBankDetails(array $values, FormStateInterface $form_state) {
    // Validate Billing Name
    $billing_name = trim($values['billing_name'] ?? '');
    if (!empty($billing_name)) {
      if (strlen($billing_name) < 2) {
        $form_state->setErrorByName('billing_name', $this->t('Billing name must be at least 2 characters long.'));
      }
      elseif (strlen($billing_name) > 100) {
        $form_state->setErrorByName('billing_name', $this->t('Billing name cannot exceed 100 characters.'));
      }
      elseif (!preg_match('/^[a-zA-Z\s\-\'\.]+$/', $billing_name)) {
        $form_state->setErrorByName('billing_name', $this->t('Billing name can only contain letters, spaces, hyphens, apostrophes, and periods.'));
      }
    }

    // Validate Account Holder Name
    $account_holder = trim($values['account_holder_name'] ?? '');
    if (!empty($account_holder)) {
      if (strlen($account_holder) < 2) {
        $form_state->setErrorByName('account_holder_name', $this->t('Account holder name must be at least 2 characters long.'));
      }
      elseif (strlen($account_holder) > 100) {
        $form_state->setErrorByName('account_holder_name', $this->t('Account holder name cannot exceed 100 characters.'));
      }
      elseif (!preg_match('/^[a-zA-Z\s\-\'\.]+$/', $account_holder)) {
        $form_state->setErrorByName('account_holder_name', $this->t('Account holder name can only contain letters, spaces, hyphens, apostrophes, and periods.'));
      }
    }

    // Validate Account Number
    $account_number = trim($values['account_number'] ?? '');
    if (!empty($account_number)) {
      if (!preg_match('/^\d+$/', $account_number)) {
        $form_state->setErrorByName('account_number', $this->t('Account number can only contain digits.'));
      }
      elseif (strlen($account_number) < 8) {
        $form_state->setErrorByName('account_number', $this->t('Account number must be at least 8 digits long.'));
      }
      elseif (strlen($account_number) > 20) {
        $form_state->setErrorByName('account_number', $this->t('Account number cannot exceed 20 digits.'));
      }
    }

    // Validate Bank Name
    $bank_name = trim($values['bank_name'] ?? '');
    if (!empty($bank_name)) {
      if (strlen($bank_name) < 2) {
        $form_state->setErrorByName('bank_name', $this->t('Bank name must be at least 2 characters long.'));
      }
      elseif (strlen($bank_name) > 100) {
        $form_state->setErrorByName('bank_name', $this->t('Bank name cannot exceed 100 characters.'));
      }
      elseif (!preg_match('/^[a-zA-Z\s\-\'\.&]+$/', $bank_name)) {
        $form_state->setErrorByName('bank_name', $this->t('Bank name can only contain letters, spaces, hyphens, apostrophes, periods, and ampersands.'));
      }
    }

    // Validate IFSC Code
    $ifsc_code = trim($values['ifsc_code'] ?? '');
    if (!empty($ifsc_code)) {
      if (!preg_match('/^[A-Z]{4}0[A-Z0-9]{6}$/', $ifsc_code)) {
        $form_state->setErrorByName('ifsc_code', $this->t('IFSC code must be 11 characters: 4 letters + 0 + 6 alphanumeric characters.'));
      }
    }

    // Validate GST Number
    $gst_number = trim($values['gst_number'] ?? '');
    if (!empty($gst_number)) {
      if (!preg_match('/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/', $gst_number)) {
        $form_state->setErrorByName('gst_number', $this->t('GST number must be in valid format: 2 digits + 5 letters + 4 digits + 1 letter + 1 alphanumeric + Z + 1 alphanumeric.'));
      }
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    //foreach (array_keys($values) as $key) {echo "<br>".$key . " --- ".$values[$key];}exit;

    $id = $form_state->getValue(['id']);

    $node = \Drupal::entityTypeManager()
      ->getStorage('node')
      ->load($id);
    if (!$node) {
      throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException();
    }

    if (!$node instanceof \Drupal\node\NodeInterface || $node->bundle() !== 'property') {
      \Drupal::messenger()->addError($this->t('Unable to load the property node.'));
      return;
    }

    // Save Photos
    $primary_photo_fids = $form_state->getValue(['primary_media']);
    //print_r($primary_photo_fids['fids']);exit;

    // Mark files as permanent
    if (isset($primary_photo_fids) && !empty($primary_photo_fids)) {
      foreach ($primary_photo_fids as $fid) {
        $file = \Drupal\file\Entity\File::load($fid);
        if ($file) {
          $file->setPermanent();
          $file->save();
        }
      }
    }

    // Save Photos
    $photo_fids = $form_state->getValue(['media']) ?? [];

    if (isset($photo_fids) && !empty($photo_fids)) {
      foreach ($photo_fids as $fid) {
        $file = \Drupal\file\Entity\File::load($fid);
        if ($file) {
          $file->setPermanent();
          $file->save();
        }
      }
    }

    $node->set('title', !empty($values['display_name']) ? $values['display_name'] : ($values['property_name'] ?? 'Untitled Property'));
    $node->set('field_property_id', commonUtil::generate_property_id($values['property_name'], $values['area_name']));
    $node->set('field_property_name', $values['property_name'] ?? 'Untitled Property');
    $node->set('field_display_name', $values['display_name'] ?? '');
    $node->set('field_area_name', $values['area_name'] ?? '');
    // Basic Info - Clean and format markup price value
    $node->set('field_markup_price', $this->cleanMarkupPriceValue($values['markup_price'] ?? 0));
    $node->set('field_property_type', $values['property_type'] ?? NULL);
    $node->set('field_room_types', array_filter($values['room_types'] ?? []));
    $node->set('field_property_age', !empty($values['property_age']) ? (int) $values['property_age'] : NULL);
    $node->set('field_description', $values['property_description'] ?? '');
    $node->set('field_latitude', $values['latitude'] ?? '');
    $node->set('field_longitude', $values['longitude'] ?? '');
    $node->set('field_display_address', $values['address'] ?? '');
    $node->set('field_country', $values['country'] ?? '');
    $node->set('field_state', $values['state'] ?? '');
    $node->set('field_city', $values['city'] ?? '');
    $node->set('field_pincode', $values['pincode'] ?? '');

    // Property Details
    $node->set('field_on_site_staffing', $values['on_site_staffing'] ?? NULL);
    
    $raw_values = $values['food_beverages'] ?? [];
    $selected = array_filter($raw_values);

    $final_values = [];
    foreach ($selected as $val) {
      $final_values[] = ['value' => $val];
    }

    $node->set('field_food_beverages', $final_values);
    $node->set('field_breakfast_provides', $values['breakfast_provides'] ?? NULL);
    $node->set('field_breakfast_price', $values['breakfast_price'] ? (int) $values['breakfast_price'] : 0);
    $node->set('field_amenities', array_filter($values['property_amenities'] ?? []));

    // Host Details
    $node->set('field_hosting_type', $values['hosting_type'] ?? NULL);
    $node->set('field_contact_person', $values['owner_contact_name'] ?? '');
    $node->set('field_contact_email_id', $values['owner_contact_email'] ?? '');
    $node->set('field_contact_number', $values['owner_contact_mobile'] ?? '');
    $node->set('field_contact_designation', $values['designation'] ?? '');

    // Policy Info
    $node->set('field_cancellation_type', $values['cancellation_type'] ?? NULL);
    $node->set('field_refundable_days', $values['refundable_days'] ? (int) $values['refundable_days'] : 0);
    $node->set('field_early_checkout', $values['early_checkout'] ?? NULL);
    $node->set('field_early_checkout_days', $values['early_checkout_days'] ? (int) $values['early_checkout_days'] : 0);
    $node->set('field_security_deposit', $values['security_deposit'] ? (int) $values['security_deposit'] : 0);
    $node->set('field_security_deposit_currency', $values['security_deposit_currency'] ?? 'INR'); 

    // Finance & Bank Details
    $node->set('field_billing_name', $values['billing_name'] ?? '');
    $node->set('field_account_holder_name', $values['account_holder_name'] ?? '');
    $node->set('field_account_number', $values['account_number'] ?? '');
    $node->set('field_bank_name', $values['bank_name'] ?? '');
    $node->set('field_ifsc_code', $values['ifsc_code'] ?? '');
    $node->set('field_gst_number', $values['gst_number'] ?? '');

    if (isset($primary_photo_fids) && !empty($primary_photo_fids)) {
      // Set the media field with the uploaded photos.
      $node->set('field_primary_media', array_map(function ($fid) {
            return ['target_id' => $fid];
          }, $primary_photo_fids));
    }

    if (isset($photo_fids) && !empty($photo_fids)) {
      // Set the media field with the uploaded photos.
      $node->set('field_media', array_map(function ($fid) {
            return ['target_id' => $fid];
          }, $photo_fids));
    }

    // Check if admin wants to publish
    $published = (isset($values['published']) && isset($values['published'][1]) && $values['published'][1] == "1") ? TRUE : FALSE;
    
    // Check if property has published rooms
    $has_published_rooms = $this->propertyHasPublishedRooms($node->id());
    
    // Auto-unpublish if no published rooms exist (even if admin checked published)
    if ($published && !$has_published_rooms) {
      // Admin tried to publish, but no published rooms exist - force unpublish
      $node->setUnpublished();
      \Drupal::messenger()->addWarning($this->t('Property was not published because no published rooms exist. Please add and publish at least one room first.'));
    } elseif ($published && $has_published_rooms) {
      // Has published rooms, allow publishing
      $node->setPublished(TRUE);
    } elseif (!$published) {
      // Not trying to publish
      $node->setUnpublished();
    } else {
      // Last resort: no published rooms, unpublish
      $node->setUnpublished();
    }

    // Save node
    $node->save();

    \Drupal::messenger()->addMessage($this->t('Property updated successfully. ID: @id', ['@id' => $node->id()]));
    $form_state->setRedirect('inventory_management.property_list');
  }

}
