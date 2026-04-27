<?php

namespace Drupal\inventory_management\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\common_utilities\Utilities\commonUtil;

class RoomEditForm extends FormBase {

  /**
   * Returns GST amount for a given rate and percentage.
   */
  private function calculateVatAmount($rate, $vat_percent) {
    $rate = (float) $rate;
    $vat_percent = (float) $vat_percent;
    return round(($rate * $vat_percent) / 100, 2);
  }

  /**
   * Validates an optional numeric field with range checks.
   */
  private function validateNumericField(FormStateInterface $form_state, $field_name, $label, $required = FALSE, $min = 0, $max = NULL) {
    $value = $form_state->getValue($field_name);
    if ($value === '' || $value === NULL) {
      if ($required) {
        $form_state->setErrorByName($field_name, $this->t('@label is required.', ['@label' => $label]));
      }
      return;
    }
    if (!is_numeric($value)) {
      $form_state->setErrorByName($field_name, $this->t('Please enter a valid number for @label.', ['@label' => $label]));
      return;
    }
    $numeric_value = (float) $value;
    if ($numeric_value < $min) {
      $form_state->setErrorByName($field_name, $this->t('@label cannot be less than @min.', ['@label' => $label, '@min' => $min]));
      return;
    }
    if ($max !== NULL && $numeric_value > $max) {
      $form_state->setErrorByName($field_name, $this->t('@label cannot be greater than @max.', ['@label' => $label, '@max' => $max]));
    }
  }

  /**
   * Gets value from field candidates: first non-empty (legacy pairs may both exist).
   */
  private function getFirstAvailableFieldValue($node, array $field_candidates, $default = '') {
    foreach ($field_candidates as $field_name) {
      if (!$node->hasField($field_name)) {
        continue;
      }
      $raw = $node->get($field_name)->getString();
      if ($raw !== '' && $raw !== NULL) {
        return $raw;
      }
    }
    return $default;
  }

  /**
   * Sets value on every existing candidate field (keeps legacy duplicate fields in sync).
   */
  private function setFirstAvailableFieldValue($node, array $field_candidates, $value) {
    foreach ($field_candidates as $field_name) {
      if ($node->hasField($field_name)) {
        $node->set($field_name, $value);
      }
    }
  }

  /**
   * Returns TRUE when room is marked "Rate on request".
   */
  private function isRateOnRequestSelected(array $values): bool {
    $raw_value = $values['rate_on_request_toggle']
      ?? ($values['room_price']['rate_on_request_toggle'] ?? 0);
    return (string) $raw_value === '1';
  }

  /**
   * Apply radio-driven visibility to all pricing fields.
   */
  private function applyRateFieldsVisibility(array &$elements): void {
    foreach ($elements as $key => &$element) {
      if (!is_array($element) || strpos((string) $key, '#') === 0) {
        continue;
      }
      $element['#states']['visible'][':input[name="rate_on_request_toggle"]'] = ['checked' => FALSE];
    }
  }

  public function getFormId() {
    return 'room_edit_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state, $pid = 0, $id = 0) {

    commonUtil::validateVendor();
    // Ensure $pid is an integer and greater than 0
    $pid = (int) $pid;
    $id = (int) $id;

    if ($pid <= 0 || $id <= 0) {
      throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException();
    }

    // Load the parent node to validate vendor ID
    $parent_node = \Drupal::entityTypeManager()
      ->getStorage('node')
      ->load($pid);
    if (!$parent_node || !commonUtil::checkVendor($parent_node)) {
      throw new \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException();
    }
    
    // Load the room node to edit
    $node = \Drupal::entityTypeManager()
      ->getStorage('node')
      ->load($id);
    if (!$node || !$node->hasField('field_parent_id') || $node->get('field_parent_id')->getString() != $pid
     || !$node instanceof \Drupal\node\NodeInterface || $node->bundle() !== 'room') {
      throw new \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException();
    }
  
    $form['back_link'] = [
      '#type' => 'link',
      '#title' => $this->t('Back'),
      '#url' => \Drupal\Core\Url::fromRoute('inventory_management.property_edit', ['id' => $pid]),
    ];

    $form['pid'] = [
      '#type' => 'hidden',
      '#value' => $pid,
    ];

    $form['id'] = [
      '#type' => 'hidden',
      '#value' => $id,
    ];

    // Room Info Fieldset
    $form['room_info'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Room Info'),
    ];

    if (commonUtil::isSiteAdmin()) {
      $form['room_info']['published'] = [
        '#type' => 'checkboxes',
        '#title' => $this->t('Published Status'),
        '#options' => ['1' => 'Published'],
        '#default_value' => $node->isPublished() ? ['1'] : [],
      ];
    }

    // Get from taxanomy
    $arr_room_type = commonUtil::get_term_list('room_type');

    $arr_parent_room_type = ($parent_node->get('field_room_types')->getValue() != "") ? 
      array_map('trim', explode(",", $parent_node->get('field_room_types')->getString())) : 
      array();

    // Get listed select in parent.
    $arr_final_room_type = array();
    foreach($arr_parent_room_type as $val_parent_room_type) {
      if ($arr_room_type[$val_parent_room_type] != "") {
        $arr_final_room_type[$val_parent_room_type] = $arr_room_type[$val_parent_room_type];
      } 
    }
    $form['room_info']['room_type'] = [
      '#type' => 'radios',
      '#title' => $this->t('Room Type'),
      '#options' => $arr_final_room_type,
      '#default_value' => (!empty($node->get('field_room_type')->getString())) ? $node->get('field_room_type')->getString() : array_key_first($arr_final_room_type),
      "#attributes" => ['class' => ['hosting-type-wrapper']],
    ];

    $form['room_info']['number_of_units'] = [
      '#type' => 'number',
      '#title' => $this->t('Number of units'),
      '#min' => 1,
      '#step' => 1,
      '#default_value' => $node->get('field_number_of_units')->getString(),
      '#required' => TRUE,
    ];

     $form['room_info']['total_bedrooms'] = [
      '#type' => 'select',
      '#title' => $this->t('Total Bedrooms'),
      '#options' => [
        '0' => "0",
        '1' => "1",
        '2' => "2",
        '3' => "3",
        '4' => "4",
        '5' => "5"
      ],
      '#attributes' => ['class' => ['form-half']],
      '#default_value' => $node->get('field_bedrooms')->getString(),
    ];

    $form['room_info']['total_bathrooms'] = [
      '#type' => 'select',
      '#title' => $this->t('Total Bathrooms'),
      '#options' => [
        '0' => "0",
        '1' => "1",
        '2' => "2",
        '3' => "3",
        '4' => "4",
        '5' => "5"
      ],
      '#attributes' => ['class' => ['form-half']],
      '#default_value' => $node->get('field_bathrooms')->getString(),
    ];

    $form['room_info']['room_description'] = [
      '#type' => 'text_format',
      '#title' => $this->t('Room Description'),
      '#format' => 'basic_html', // Fixed format
      '#allowed_formats' => ['basic_html'], // Limit dropdown to one format
      '#rows' => 5,
      '#default_value' => $node->get('field_room_description')->getString(),
    ];

/*
    $existing_fids = [];

    if ($node instanceof \Drupal\node\NodeInterface && $node->hasField('field_room_media')) {
      foreach ($node->get('field_room_media')->getValue() as $fid_val) {
        $existing_fids[] = $fid_val['target_id'];
      }
    }

    $form['room_info']['room_media'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Room Photos'),
      '#description' => $this->t('Upload one or more images.'),
      '#upload_location' => 'public://room_media/',
      '#multiple' => TRUE,
      '#upload_validators' => [
        'file_validate_extensions' => ['png jpg jpeg'],
        'file_validate_size' => [25600000],
      ],
      '#default_value' => $existing_fids,
    ];
*/
    $arr_room_amenities = commonUtil::get_term_list('room_amenities');

    $selected_values = ($node->get('field_room_amenities')->getValue() != "") ? 
      array_map('trim', explode(",", $node->get('field_room_amenities')->getString())) : 
      array();

    $form['room_info']['room_amenities'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Room Amenities'),
      '#options' => $arr_room_amenities,
      '#default_value' => $selected_values,
      "#attributes" => ['class' => ['hosting-type-wrapper']],
    ];

    // $form['room_info']['breakfast_provides'] = [
    //   '#type' => 'radios',
    //   '#title' => $this->t('Breakfast Provides'),
    //   '#options' => ['Complementary' => 'Complementary', 'Chargeable' => 'Chargeable', 'None' => 'None'],
    //   '#default_value' => $node->get('field_breakfast_provides')->getString() ?? 'None',
    //   "#attributes" => ['class' => ['hosting-type-wrapper']],
    // ];

    // $form['room_info']['breakfast_price'] = [
    //   '#type' => 'number',
    //   '#title' => $this->t('Breakfast Price'),
    //   '#description' => $this->t('Enter the price for breakfast if it is chargeable.'),
    //   '#min' => 0,
    //   '#step' => 0.01,
    //   '#default_value' => $node->get('field_breakfast_price')->getString() ?? 0,
    //   "#attributes" => ['class' => ['hosting-type-wrapper']],
    // ];

    // Room Contact
    $form['room_contact'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Room Contact'),
    ];

    $form['room_contact']['room_name_number_wrapper'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['room-name-number-wrapper']],
    ];

    $form['room_contact']['room_name_number_wrapper']['contact_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Contact Person Name'),
      '#default_value' => $node->get('field_room_contact_name')->getString(),
      '#attributes' => [
        'class' => ['form-full'],
      ],
    ];

    $form['room_contact']['room_name_number_wrapper']['contact_mobile'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Contact Person Number'),
      '#default_value' => $node->get('field_room_contact_mobile')->getString(),
      '#attributes' => [
        'class' => ['form-full'],
      ],
    ];

    // Room Price
    $form['room_price'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Room Price'),
    ];
    $form['#attached']['library'][] = 'inventory_management/room_price';

    $form['room_price']['room_price_tax_wrapper'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['room-price-tax-wrapper']],
      '#states' => [
        'visible' => [
          ':input[name="rate_on_request_toggle"]' => ['checked' => FALSE],
        ],
      ],
    ];

    $single_nightly_rate = $this->getFirstAvailableFieldValue($node, ['field_single_nightly_rate'], '');
    $single_monthly_rate = $this->getFirstAvailableFieldValue($node, ['field_single_monthly_rate'], '');
    $nightly_vat_percent_value = $this->getFirstAvailableFieldValue($node, ['field_nightly_rate_vat_', 'field_nightly_vat_percent'], 0);
    $monthly_vat_percent_value = $this->getFirstAvailableFieldValue($node, ['field_monthly_rate_vat_', 'field_monthly_vat_percent'], 0);
    $default_nightly_vat_amount = $this->getFirstAvailableFieldValue($node, ['field_nightly_vat_amount'], $this->calculateVatAmount($single_nightly_rate, $nightly_vat_percent_value));
    $default_monthly_vat_amount = $this->getFirstAvailableFieldValue($node, ['field_monthly_vat_amount'], $this->calculateVatAmount($single_monthly_rate, $monthly_vat_percent_value));

    $double_nightly_rate = $this->getFirstAvailableFieldValue($node, ['field_double_nightly_rate'], '');
    $double_monthly_rate = $this->getFirstAvailableFieldValue($node, ['field_double_monthly_rate'], '');
    $double_nightly_vat_pt = $this->getFirstAvailableFieldValue($node, ['field_double_nightly_rate_vat_pt'], 0);
    $double_monthly_vat_pt = $this->getFirstAvailableFieldValue($node, ['field_double_monthly_rate_vat_pt'], 0);
    $default_double_nightly_vat_am = $this->getFirstAvailableFieldValue($node, ['field_double_nightly_rate_vat_am'], $this->calculateVatAmount($double_nightly_rate, $double_nightly_vat_pt));
    $default_double_monthly_vat_am = $this->getFirstAvailableFieldValue($node, ['field_double_monthly_rate_vat_am'], $this->calculateVatAmount($double_monthly_rate, $double_monthly_vat_pt));
    $is_rate_on_request_default = (string) $this->getFirstAvailableFieldValue($node, ['field_rate_on_request'], '0') === '1';

    $form['room_price']['rate_on_request_toggle'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Rate on request'),
      '#default_value' => $is_rate_on_request_default ? 1 : 0,
      '#weight' => -10,
    ];

    $form['room_price']['room_price_tax_wrapper']['single_occupancy_heading'] = [
      '#type' => 'item',
      '#title' => $this->t('Single Occupancy'),
      '#attributes' => ['class' => ['room-price-section-title']],
    ];
    $form['room_price']['room_price_tax_wrapper']['single_nightly_rate'] = [
      '#type' => 'number',
      '#title' => $this->t('Nightly Rate'),
      '#required' => FALSE,
      '#min' => 0,
      '#step' => 0.01,
      '#default_value' => $single_nightly_rate,
      '#attributes' => ['class' => ['form-half']],
    ];
    $form['room_price']['room_price_tax_wrapper']['single_monthly_rate'] = [
      '#type' => 'number',
      '#title' => $this->t('Monthly Rate'),
      '#required' => FALSE,
      '#min' => 0,
      '#step' => 0.01,
      '#default_value' => $single_monthly_rate,
      '#attributes' => ['class' => ['form-half']],
    ];

    $form['room_price']['room_price_tax_wrapper']['double_occupancy_heading'] = [
      '#type' => 'item',
      '#title' => $this->t('Double Occupancy'),
      '#attributes' => ['class' => ['room-price-section-title']],
    ];
    $form['room_price']['room_price_tax_wrapper']['double_nightly_rate'] = [
      '#type' => 'number',
      '#title' => $this->t('Nightly Rate'),
      '#required' => FALSE,
      '#min' => 0,
      '#step' => 0.01,
      '#default_value' => $double_nightly_rate,
      '#attributes' => ['class' => ['form-half']],
    ];
    $form['room_price']['room_price_tax_wrapper']['double_nightly_rate_vat_pt'] = [
      '#type' => 'number',
      '#title' => $this->t('Nightly Rate GST %'),
      '#min' => 0,
      '#max' => 100,
      '#step' => 0.01,
      '#default_value' => $double_nightly_vat_pt,
      '#attributes' => ['class' => ['form-half']],
    ];
    $form['room_price']['room_price_tax_wrapper']['double_nightly_rate_vat_am'] = [
      '#type' => 'number',
      '#title' => $this->t('Nightly Rate GST Amount'),
      '#default_value' => $default_double_nightly_vat_am,
      '#step' => 0.01,
      '#attributes' => [
        'class' => ['form-half'],
        'readonly' => 'readonly',
      ],
    ];
    $form['room_price']['room_price_tax_wrapper']['double_monthly_rate'] = [
      '#type' => 'number',
      '#title' => $this->t('Monthly Rate'),
      '#min' => 0,
      '#step' => 0.01,
      '#default_value' => $double_monthly_rate,
      '#attributes' => ['class' => ['form-half']],
    ];
    $form['room_price']['room_price_tax_wrapper']['double_monthly_rate_vat_pt'] = [
      '#type' => 'number',
      '#title' => $this->t('Monthly Rate GST %'),
      '#min' => 0,
      '#max' => 100,
      '#step' => 0.01,
      '#default_value' => $double_monthly_vat_pt,
      '#attributes' => ['class' => ['form-half']],
    ];
    $form['room_price']['room_price_tax_wrapper']['double_monthly_rate_vat_am'] = [
      '#type' => 'number',
      '#title' => $this->t('Monthly Rate GST Amount'),
      '#default_value' => $default_double_monthly_vat_am,
      '#step' => 0.01,
      '#attributes' => [
        'class' => ['form-half'],
        'readonly' => 'readonly',
      ],
    ];

    $form['room_price']['room_price_tax_wrapper']['vat_heading'] = [
      '#type' => 'item',
      '#title' => $this->t('GST (%)'),
      '#attributes' => ['class' => ['room-price-section-title']],
    ];
    $form['room_price']['room_price_tax_wrapper']['nightly_vat_percent'] = [
      '#type' => 'number',
      '#title' => $this->t('Nightly Rate GST %'),
      '#min' => 0,
      '#max' => 100,
      '#step' => 0.01,
      '#default_value' => $nightly_vat_percent_value,
      '#attributes' => ['class' => ['form-half']],
    ];
    $form['room_price']['room_price_tax_wrapper']['monthly_vat_percent'] = [
      '#type' => 'number',
      '#title' => $this->t('Monthly Rate GST %'),
      '#min' => 0,
      '#max' => 100,
      '#step' => 0.01,
      '#default_value' => $monthly_vat_percent_value,
      '#attributes' => ['class' => ['form-half']],
    ];

    $form['room_price']['room_price_tax_wrapper']['total_vat_heading'] = [
      '#type' => 'item',
      '#title' => $this->t('Total GST (Auto-calculated)'),
      '#attributes' => ['class' => ['room-price-section-title']],
    ];
    $form['room_price']['room_price_tax_wrapper']['nightly_vat_amount'] = [
      '#type' => 'number',
      '#title' => $this->t('Nightly Rate GST Amount'),
      '#default_value' => $default_nightly_vat_amount,
      '#step' => 0.01,
      '#attributes' => [
        'class' => ['form-half'],
        'readonly' => 'readonly',
      ],
    ];
    $form['room_price']['room_price_tax_wrapper']['monthly_vat_amount'] = [
      '#type' => 'number',
      '#title' => $this->t('Monthly Rate GST Amount'),
      '#default_value' => $default_monthly_vat_amount,
      '#step' => 0.01,
      '#attributes' => [
        'class' => ['form-half'],
        'readonly' => 'readonly',
      ],
    ];

    $this->applyRateFieldsVisibility($form['room_price']['room_price_tax_wrapper']);

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Update'),
    ];

    $form['#theme'] = 'room_edit_form';

    return $form;
  }


  public function validateForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    // $contact_name = $form_state->getValue('contact_name');
    $contact_number = $form_state->getValue('contact_mobile');

    if (!is_numeric($form_state->getValue('number_of_units'))) {
      $form_state->setErrorByName('number_of_units', $this->t('Please enter a valid number for units.'));
    }
    if (!$this->isRateOnRequestSelected($values)) {
      $this->validateNumericField($form_state, 'single_nightly_rate', $this->t('Single Occupancy Nightly Rate'), TRUE, 0);
      $this->validateNumericField($form_state, 'single_monthly_rate', $this->t('Single Occupancy Monthly Rate'), FALSE, 0);
      $this->validateNumericField($form_state, 'double_nightly_rate', $this->t('Double Occupancy Nightly Rate'), TRUE, 0);
      $this->validateNumericField($form_state, 'double_monthly_rate', $this->t('Double Occupancy Monthly Rate'), FALSE, 0);
      $this->validateNumericField($form_state, 'double_nightly_rate_vat_pt', $this->t('Double Nightly GST %'), FALSE, 0, 100);
      $this->validateNumericField($form_state, 'double_monthly_rate_vat_pt', $this->t('Double Monthly GST %'), FALSE, 0, 100);
      $this->validateNumericField($form_state, 'nightly_vat_percent', $this->t('Nightly GST %'), FALSE, 0, 100);
      $this->validateNumericField($form_state, 'monthly_vat_percent', $this->t('Monthly GST %'), FALSE, 0, 100);
    }
    // if ($form_state->getValue('breakfast_provides') == 'Chargeable' && !is_numeric($form_state->getValue('breakfast_price'))) {
    //   $form_state->setErrorByName('breakfast_price', $this->t('Please enter breakfast price.'));      
    // }
    // if (!empty($contact_name) && !preg_match("/^[a-zA-Z\s]+$/", $contact_name)) {
    //   $form_state->setErrorByName('contact_name', $this->t('Name should only contain letters and spaces.'));
    // };
    if (!empty($contact_number)) {
      if (!preg_match("/^\+?[0-9]{10,15}$/", $contact_number)) {
        $form_state->setErrorByName('contact_mobile', $this->t('Please enter a valid number.'));
      }
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    //foreach (array_keys($values) as $key) {echo "<br>".$key . " --- ".$values[$key];}exit;

    if ($values['id'] <= 0 || $values['pid'] <= 0) {
      \Drupal::messenger()->addError($this->t('Sorry, could not proceed further.'));
      return;
    }

    $parent_node = \Drupal::entityTypeManager()
    ->getStorage('node')
    ->load($values['pid']);

    if (!$parent_node instanceof \Drupal\node\NodeInterface || $parent_node->bundle() !== 'property') {
      \Drupal::messenger()->addError($this->t('Unable to load the property node.'));
      return;
    }

    $arr_city = commonUtil::get_term_list('city');
    $arr_room_type = commonUtil::get_term_list('room_type');
    $city_id = $parent_node->get('field_city')->getString();
    $values['title'] = $arr_room_type[$values['room_type']] . " in " . $arr_city[$city_id];

    $node = \Drupal::entityTypeManager()
      ->getStorage('node')
      ->load($values['id']);
    if (!$node) {
      throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException();
    }

    if (!$node instanceof \Drupal\node\NodeInterface || $node->bundle() !== 'room') {
      \Drupal::messenger()->addError($this->t('Unable to load the room node.'));
      return;
    }
/*
    // Mark room photos as permanent
    $photo_fids = $values['room_media'] ?? [];
    foreach ($photo_fids as $fid) {
      if ($file = \Drupal\file\Entity\File::load($fid)) {
        $file->setPermanent();
        $file->save();
      }
    }
*/
    // Update fields
    $node->setTitle($values['title']);
    $node->set('field_room_type', $values['room_type']);
    $node->set('field_number_of_units', $values['number_of_units']);
    $node->set('field_bedrooms', $values['total_bedrooms']);
    $node->set('field_bathrooms', $values['total_bathrooms']);
    $node->set('field_room_description', $values['room_description']);
    $node->set('field_room_amenities', array_filter($values['room_amenities'] ?? []));
    // $node->set('field_breakfast_provides', $values['breakfast_provides']);
    // $node->set('field_breakfast_price', $values['breakfast_price']);

    $node->set('field_room_contact_mobile', $values['contact_mobile']);
    $node->set('field_room_contact_name', $values['contact_name']);

    $is_rate_on_request = $this->isRateOnRequestSelected($values);
    $single_nightly_rate = $is_rate_on_request ? 0 : ($values['single_nightly_rate'] ?? 0);
    $single_monthly_rate = $is_rate_on_request ? 0 : ($values['single_monthly_rate'] ?? 0);
    $double_nightly_rate = $is_rate_on_request ? 0 : ($values['double_nightly_rate'] ?? 0);
    $double_monthly_rate = $is_rate_on_request ? 0 : ($values['double_monthly_rate'] ?? 0);
    $double_nightly_rate_vat_pt = $is_rate_on_request ? 0 : ($values['double_nightly_rate_vat_pt'] ?? 0);
    $double_monthly_rate_vat_pt = $is_rate_on_request ? 0 : ($values['double_monthly_rate_vat_pt'] ?? 0);
    $nightly_vat_percent = $is_rate_on_request ? 0 : ($values['nightly_vat_percent'] ?? 0);
    $monthly_vat_percent = $is_rate_on_request ? 0 : ($values['monthly_vat_percent'] ?? 0);

    $nightly_vat_amount = $this->calculateVatAmount($single_nightly_rate, $nightly_vat_percent);
    $monthly_vat_amount = $this->calculateVatAmount($single_monthly_rate, $monthly_vat_percent);
    $double_nightly_vat_am = $this->calculateVatAmount($double_nightly_rate, $double_nightly_rate_vat_pt);
    $double_monthly_vat_am = $this->calculateVatAmount($double_monthly_rate, $double_monthly_rate_vat_pt);
    $this->setFirstAvailableFieldValue($node, ['field_single_nightly_rate'], $single_nightly_rate);
    $this->setFirstAvailableFieldValue($node, ['field_single_monthly_rate'], $single_monthly_rate);
    $this->setFirstAvailableFieldValue($node, ['field_double_nightly_rate'], $double_nightly_rate);
    $this->setFirstAvailableFieldValue($node, ['field_double_monthly_rate'], $double_monthly_rate);
    $this->setFirstAvailableFieldValue($node, ['field_double_nightly_rate_vat_pt'], $double_nightly_rate_vat_pt);
    $this->setFirstAvailableFieldValue($node, ['field_double_monthly_rate_vat_pt'], $double_monthly_rate_vat_pt);
    $this->setFirstAvailableFieldValue($node, ['field_double_nightly_rate_vat_am'], $double_nightly_vat_am);
    $this->setFirstAvailableFieldValue($node, ['field_double_monthly_rate_vat_am'], $double_monthly_vat_am);
    $this->setFirstAvailableFieldValue($node, ['field_nightly_rate_vat_', 'field_nightly_vat_percent'], $nightly_vat_percent);
    $this->setFirstAvailableFieldValue($node, ['field_monthly_rate_vat_', 'field_monthly_vat_percent'], $monthly_vat_percent);
    $this->setFirstAvailableFieldValue($node, ['field_nightly_vat_amount'], $nightly_vat_amount);
    $this->setFirstAvailableFieldValue($node, ['field_monthly_vat_amount'], $monthly_vat_amount);
    $this->setFirstAvailableFieldValue($node, ['field_rate_on_request'], $is_rate_on_request ? 1 : 0);

    $published = (isset($values['published']) && $values['published'][1] == "1" ) ? TRUE : FALSE;
    if ($published) {
      $node->setPublished(TRUE);
    } else {
      $node->setUnpublished();
    }

    $node->save();

    // Update property status based on published rooms
    $this->updatePropertyStatusBasedOnRooms($values['pid']);

    \Drupal::messenger()->addMessage($this->t('Room updated successfully.'));
    $form_state->setRedirect('inventory_management.property_edit', ['id' => $values['pid']]);
  }

  /**
   * Update property status based on published rooms.
   * Auto-publishes property if it has published rooms.
   * Auto-unpublishes property if no published rooms exist.
   * Optimized: Only queries for first published room and checks status before saving.
   */
  private function updatePropertyStatusBasedOnRooms($property_id) {
    if (empty($property_id)) {
      return;
    }

    // Load the property - use static cache to avoid multiple loads if called multiple times
    $property = \Drupal::entityTypeManager()
      ->getStorage('node')
      ->load($property_id);

    if (!$property || $property->bundle() !== 'property') {
      return;
    }

    // Check if property has published rooms - optimized: only check if any exist (range 0,1)
    $room_nids = \Drupal::entityQuery('node')
      ->condition('type', 'room')
      ->condition('field_parent_id', $property_id)
      ->condition('status', 1) // Only published rooms
      ->range(0, 1) // Only need to know if ANY exists, don't load all
      ->accessCheck(FALSE)
      ->execute();

    $has_published_rooms = !empty($room_nids);
    $property_is_published = $property->isPublished();

    // Only update and save if status needs to change
    if ($has_published_rooms && !$property_is_published) {
      // Auto-publish if has published rooms and is currently unpublished
      $property->setPublished(TRUE);
      $property->save();
      \Drupal::messenger()->addMessage($this->t('Property has been automatically published because it now has published rooms.'));
    } elseif (!$has_published_rooms && $property_is_published) {
      // Auto-unpublish if no published rooms and is currently published
      $property->setUnpublished();
      $property->save();
      \Drupal::messenger()->addWarning($this->t('Property has been automatically unpublished because no published rooms exist.'));
    }
    // If status already matches, no action needed - saves unnecessary database write
  }



}
