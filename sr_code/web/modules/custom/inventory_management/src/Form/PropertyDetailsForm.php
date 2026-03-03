<?php

namespace Drupal\inventory_management\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\common_utilities\Utilities\commonUtil;
use Drupal\Core\Url;
use Drupal\Core\Link;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\file\Entity\File;

/**
 * Property Details Form with optimized validation.
 */
class PropertyDetailsForm extends FormBase {

  // Constants for better maintainability
  private const MAX_FILE_SIZE = 25600000; // 25MB
  private const ALLOWED_EXTENSIONS = 'png jpg jpeg';
  private const PROPERTY_NAME_MIN_LENGTH = 3;
  private const PROPERTY_NAME_MAX_LENGTH = 100;
  private const PINCODE_LENGTH = 6;

  public function getFormId() {
    return 'property_details_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {

    $form['back_link'] = [
      '#type' => 'link',
      '#title' => $this->t('Back'),
      '#url' => \Drupal\Core\Url::fromRoute('inventory_management.property_list'),
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
        '#default_value' => [],
      ];
    }

    $arr_property_type = commonUtil::get_term_list('property_type');

    $form['property_basic_info']['markup_type_wrapper']['property_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Property type'),
      '#options' => $arr_property_type,
      '#required' => TRUE,
      '#attributes' => ['class' => ['form-half']],
    ];

    // Wraping property name inside wrapper
    $form['property_basic_info']['property_name_age_wrapper'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['property-name-age-wrapper']],
    ];

    $form['property_basic_info']['property_name_age_wrapper']['property_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Property name'),
      '#required' => TRUE,
      '#attributes' => [
        'class' => ['form-half'],
        'placeholder' => $this->t('Enter property name'),
      ],
    ];

    $form['property_basic_info']['property_description'] = [
      '#type' => 'text_format',
      '#title' => $this->t('Property Description'),
      '#required' => TRUE,
      '#format' => 'full_html', // Or 'full_html', depending on allowed formats
      '#rows' => 5,
      '#default_value' => '',
      '#allowed_formats' => ['full_html'], // Limit dropdown to one format
    ];

    $form['property_basic_info']['lat_lng_row'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['lat-lng-wrapper']],
    ];

    $form['property_basic_info']['lat_lng_row']['latitude'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Latitude'),
      '#required' => TRUE,
      '#attributes' => [
        'class' => ['lat-field'],
        'placeholder' => $this->t('Enter latitude'),
      ],
    ];

    $form['property_basic_info']['lat_lng_row']['longitude'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Longitude'),
      '#required' => TRUE,
      '#attributes' => [
        'class' => ['lng-field'],
        'placeholder' => $this->t('Enter longitude'),
      ],
    ];

    $form['property_basic_info']['address'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Address'),
      '#required' => TRUE,
    ];

    $arr_country = commonUtil::getCountryList();
    $arr_city = commonUtil::get_term_list('town_city');

    // Wrapper for first row: Country & State
    $form['property_basic_info']['location_row_1'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['location-wrapper']],
    ];

    $form['property_basic_info']['location_row_1']['country'] = [
      '#type' => 'select',
      '#title' => $this->t('Country'),
      '#options' => $arr_country,
      '#required' => TRUE,
      '#attributes' => [
        'class' => ['form-half'],
      ],
    ];

    // Wrapper for second row: City & Pincode
    $form['property_basic_info']['location_row_2'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['location-wrapper']],
    ];

    $form['property_basic_info']['location_row_2']['city'] = [
      '#type' => 'select',
      '#title' => $this->t('City'),
      '#options' => $arr_city,
      '#required' => TRUE,
      '#attributes' => [
        'class' => ['form-half'],
      ],
    ];
    $form['property_basic_info']['location_row_2']['pincode'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Pincode'),
      '#required' => TRUE,
      '#attributes' => [
        'class' => ['form-half'],
        'placeholder' => $this->t('Enter pincode'),
      ],
    ];

    // Property Details
    $form['property_details'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Property Details'),
    ];

    $form['property_details']['media'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Property Photos'),
      '#description' => $this->t('Upload one or more images.'),
      '#upload_location' => 'public://property_media/',
      '#multiple' => TRUE,
      '#upload_validators' => [
        'file_validate_extensions' => ['png jpg jpeg webp'],
        'file_validate_size' => [25600000], // 25MB max per file
      ],
    ];

    $arr_property_amenities = commonUtil::getPropertyAmenities();

    $form['property_details']['property_amenities'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Property Amenities'),
      '#options' => $arr_property_amenities,
      '#required' => TRUE,
      "#attributes" => ['class' => ['hosting-type-wrapper']],
    ];

    // Submit button
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
    ];
    $form['#theme'] = 'property_details_form';

    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    
    // Validate all fields using optimized methods
    $this->validateRequiredField($values, 'property_name', 3, 100, '', 'Property name', $form_state);
  
    
    $this->validateOptionalPincodeField($values, 'pincode', $form_state);
    $this->validateCoordinates($values, $form_state);
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
    
    if ($pattern != "" && !preg_match($pattern, $value)) {
      $form_state->setErrorByName($field_name, $this->t('@field contains invalid characters.', ['@field' => $field_label]));
    }
  }

  
  /**
   * Validate property publishing - ensures property has published rooms.
   * Note: For new properties, this validation is skipped (property doesn't exist yet).
   * The property will be auto-unpublished in submitForm if no rooms exist.
   */
  private function validatePropertyPublishing(array $values, string $field_name, FormStateInterface $form_state): void {
    // Check if admin is trying to publish the property
    $published = (isset($values[$field_name]) && isset($values[$field_name][1]) && $values[$field_name][1] == "1") ? TRUE : FALSE;
    
    if (!$published) {
      return; // Not trying to publish, no validation needed
    }
    
    // For new properties (PropertyDetailsForm), we can't check rooms yet (property doesn't exist)
    // Validation is skipped here - property will be auto-unpublished in submitForm if no rooms exist
    // This validation is mainly for PropertyDetailsEditForm where property already exists
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

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    //foreach (array_keys($values) as $key) {echo "<br>".$key . " --- ".$values[$key];}exit;

    // Save Photos
    $photo_fids = $form_state->getValue(['media']);
    //print_r($photo_fids['fids']);exit;

    // Mark files as permanent
    if (isset($photo_fids) && !empty($photo_fids)) {
      foreach ($photo_fids as $fid) {
        $file = \Drupal\file\Entity\File::load($fid);
        if ($file) {
          $file->setPermanent();
          $file->save();
        }
      }
    }

    $current_user = \Drupal::currentUser();
    $uid = $current_user->id();
    $user = \Drupal\user\Entity\User::load($uid);

    $arr_property = [
      'type' => 'property',
      'field_property_id' => commonUtil::generate_property_id($values['property_name'], $values['latitude'], $values['longitude']),
      'title' => $values['property_name'],
      'field_property_type' => $values['property_type'],
      'field_location_coords_latitude' => $values['latitude'] ?? '',
      'field_location_coords_longitude' => $values['longitude'] ?? '',
      'field_display_address' => $values['address'] ?? '',
      'field_location_country_code' => $values['country'] ?? '',
      'field_town_city' => $values['city'] ?? '',
      'field_location_postal_code' => $values['pincode'] ?? '',
      'field_property_source' => 'stayrelive',
    ];

    // Create a new node of type 'property'.
    $node = \Drupal\node\Entity\Node::create($arr_property);

    $node->set('field_amenities', array_filter($values['property_amenities'] ?? []));


    // Create paragraph
    $paragraph = Paragraph::create([
      'type' => 'property_description',
      'field_heading' => $values['property_name'] . 'Property overview',
      'field_text' => [
        'value' => $values['property_description']['value'] ?? '',
        'format' => 'full_html',
      ],
    ]);
    $paragraph->save();

    // Attach to node
    $node->field_description[] = [
      'target_id' => $paragraph->id(),
      'target_revision_id' => $paragraph->getRevisionId(),
    ];

    $arr_file_media = array();
    if (isset($photo_fids) && !empty($photo_fids)) {
      // Set the media field with the uploaded photos.
      foreach ($photo_fids as $key_fid => $val_fid) {
        $file = File::load($fid);
        if ($file) {
          $uri = $file->getFileUri();

          $url = \Drupal::service('file_url_generator')
          ->generateAbsoluteString($uri);

          $arr_file_media[$key_fid]['type'] = "image";
          $arr_file_media[$key_fid]['url'] = $url;
          $arr_file_media[$key_fid]['fid'] = $val_fid;
        }
      }

      $node->set('field_media', serialize($arr_file_media));
    }

    // Check if admin wants to publish
    $published = (isset($values['published']) && isset($values['published'][1]) && $values['published'][1] == "1") ? TRUE : FALSE;
    
    // Auto-unpublish if no published rooms exist (even if admin checked published)
    $has_published_rooms = $this->propertyHasPublishedRooms($node->id());
    if ($published && !$has_published_rooms) {
      // Admin tried to publish, but no published rooms exist - force unpublish
      $node->setUnpublished();
      \Drupal::messenger()->addWarning($this->t('Property was not published because no published rooms exist. Please add and publish at least one room first.'));
    } elseif ($published && $has_published_rooms) {
      // Has published rooms, allow publishing
      $node->setPublished(TRUE);
    } else {
      // Not trying to publish or no published rooms
      $node->setUnpublished();
    }

    $node->save();
    // Load Pathauto service.
    \Drupal::service('pathauto.generator')->updateEntityAlias($node, 'update');

    \Drupal::messenger()->addMessage($this->t('Property saved with property ID: @id', ['@id' => $node->id()]));
    $form_state->setRedirect('inventory_management.property_edit', ['id' => $node->id(),]);
  }

}
