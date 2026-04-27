<?php

namespace Drupal\inventory_management\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\common_utilities\Utilities\commonUtil;
use Drupal\Core\Url;
use Drupal\Core\Link;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\file\Entity\File;
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
  
    // Ensure $id is an integer and greater than 0
    $id = (int) $id;
    if ($id <= 0) {
      throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException();
    }
    $node = \Drupal::entityTypeManager()
      ->getStorage('node')
      ->load($id);
    if (!$node || !$node instanceof \Drupal\node\NodeInterface
     || $node->bundle() !== 'property') {
      throw new \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException();
    }

     // Check if property has rooms created
  $property = $node;

  if ($property && $property->bundle() === 'property') {
    // Load related rooms where field_parent_id matches property ID
    $rooms = \Drupal::entityTypeManager()->getStorage('node')->loadByProperties([
      'type' => 'property',
      'field_parent_id' => $property->id(),
      'field_property_source' => 'stayrelive',
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
      ->condition('type', 'property')
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

    $form['property_basic_info']['property_name_age_wrapper'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['property-name-age-wrapper']],
    ];
    $form['property_basic_info']['property_name_age_wrapper']['property_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Property name'),
      '#required' => TRUE,
      '#default_value' => $node->get('title')->getString(),
      '#attributes' => ['class' => ['form-half']],
    ];

    $paragraph_body = "";
    $paragraph_items = $node->get('field_description')->getValue();
    foreach ($paragraph_items as $paragraph_key => $paragraph_value) {
      $paragraph = Paragraph::load($paragraph_value['target_id']);

      if ($paragraph) {
        $paragraph_body  = $paragraph->get('field_text')->value;
      }
    }
    
    $form['property_basic_info']['property_description'] = [
      '#type' => 'text_format',
      '#title' => $this->t('Property Description'),
      '#default_value' => $paragraph_body,
      '#required' => TRUE,
      '#format' => 'full_html', // Or 'full_html', depending on allowed formats
      '#rows' => 5,
      '#allowed_formats' => ['full_html'], // Limit dropdown to one format
    ];

    $form['property_basic_info']['lat_lng_row'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['lat-lng-wrapper']],
    ];

    $form['property_basic_info']['lat_lng_row']['latitude'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Latitude'),
      '#default_value' => $node->get('field_location_coords_latitude')->getString(),
      '#attributes' => ['class' => ['lat-field']],
    ];
    $form['property_basic_info']['lat_lng_row']['longitude'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Longitude'),
      '#default_value' => $node->get('field_location_coords_longitude')->getString(),
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
    $arr_country = [];
    $arr_country_term = commonUtil::get_term_list('country');
    $arr_country_term = array_flip($arr_country_term);

    $countries = \Drupal::service('country_manager')->getList();

    foreach ($countries as $key => $val) {
      if (isset($arr_country_term[$key])) {
        $arr_country[$arr_country_term[$key]] = $val;
      }
    }

    $arr_city = commonUtil::get_term_list('town_city');

    $form['property_basic_info']['location_row_1'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['location-wrapper']],
    ];

    $form['property_basic_info']['location_row_1']['country'] = [
      '#type' => 'select',
      '#title' => $this->t('Country'),
      '#options' => $arr_country,
      '#default_value' => $node->get('field_location_country_code')->getString(),
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
      '#default_value' => $node->get('field_town_city')->getString(),
      '#required' => TRUE,
      '#attributes' => [
        'class' => ['form-half'],
      ],
    ];
    $form['property_basic_info']['location_row_2']['pincode'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Pincode'),
      '#default_value' => $node->get('field_location_postal_code')->getString(),
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

    $existing_fids = [];

    if ($node instanceof \Drupal\node\NodeInterface && $node->hasField('field_media')) {
      $stg_media = $node->get('field_media')->getValue();
      if (isset($stg_media[0]['value']) && !empty($stg_media[0]['value'])) {
        $arr_media = unserialize($stg_media[0]['value']);

        foreach ($arr_media as $val_media) {
          if (isset($val_media['fid'])) {
            $existing_fids[] = $val_media['fid'];
          }
        }
      }
    }

    $form['property_details']['media'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Property Photos'),
      '#description' => $this->t('Upload one or more images.'),
      '#upload_location' => 'public://property_media/',
      '#multiple' => TRUE,
      '#default_value' => $existing_fids,
      '#existing_fids' => $existing_fids,
      '#upload_validators' => [
        'file_validate_extensions' => ['png jpg jpeg webp'],
        'file_validate_size' => [25600000], // 25MB max per file
      ],
    ];

    $arr_property_amenities = commonUtil::getPropertyAmenities();

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
    $this->validateRequiredField($values, 'property_name', 3, 100, '', 'Property name', $form_state);
    
    // Validate Markup Price if user is admin
    if (commonUtil::isSiteAdmin()) {
      
      // Validate property publishing - must have published rooms
      $id = $form_state->getValue(['id']);
      $this->validatePropertyPublishing($values, 'published', $id, $form_state);
    }
    
    $this->validateOptionalPincodeField($values, 'pincode', $form_state);
    $this->validateCoordinates($values, $form_state);
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

    $node->set('title', ($values['property_name'] ?? 'Untitled Property'));
    $node->set('field_reference_id', commonUtil::generate_property_id($values['property_name'], $values['latitude'], $values['longitude']));
    $node->set('title', $values['property_name'] ?? 'Untitled Property');
    $node->set('field_property_type', $values['property_type'] ?? '');
    // Basic Info - Clean and format markup price value
    $node->set('field_location_coords_latitude', $values['latitude'] ?? '');
    $node->set('field_location_coords_longitude', $values['longitude'] ?? '');
    $node->set('field_display_address', $values['address'] ?? '');
    $node->set('field_location_country_code', $values['country'] ?? '');
    $node->set('field_town_city', $values['city'] ?? '');
    $node->set('field_location_postal_code', $values['pincode'] ?? '');

    $node->set('field_amenities', array_filter($values['property_amenities'] ?? []));

    foreach ($node->get('field_description') as $item) {
      $paragraph = $item->entity;
      if ($paragraph) {
        $paragraph->set('field_text', $values['property_description'] ?? '');
        $paragraph->save();
      }
    }

    // Get new uploaded fids
    $new_fids = $form_state->getValue(['media']) ?? [];

    // Get old fids (you must pass this from buildForm)
    $old_fids = $form['property_details']['media']['#existing_fids'] ?? [];

    $new_fids = array_filter($new_fids);
    $old_fids = array_filter($old_fids);

    // 1. Delete removed files
    $removed_fids = array_diff($old_fids, $new_fids);

    foreach ($removed_fids as $fid) {
      $file = File::load($fid);
      if ($file) {
        $file->delete(); // or setTemporary()
      }
    }

    // 2. Mark new files as permanent
    foreach ($new_fids as $fid) {
      $file = File::load($fid);
      if ($file && $file->isTemporary()) {
        $file->setPermanent();
        $file->save();
      }
    }

    // 3. Build media array
    $arr_file_media = [];

    foreach ($new_fids as $key => $fid) {
      $file = File::load($fid);

      if ($file) {
        $uri = $file->getFileUri();

        $url = \Drupal::service('file_url_generator')
          ->generateAbsoluteString($uri);

        $arr_file_media[$key] = [
          'type' => 'image',
          'url' => $url,
          'fid' => $fid,
        ];
      }
    }

    // 4. Save to node
    if (!empty($arr_file_media)) {
      $node->set('field_media', serialize($arr_file_media));
    }
    else {
      $node->set('field_media', NULL);
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
    //$form_state->setRedirect('inventory_management.property_list');
  }

  /**
   * Check if property has published rooms.
   */
  private function propertyHasPublishedRooms($property_id) {
    if (empty($property_id)) {
      return FALSE;
    }
    
    $room_nids = \Drupal::entityQuery('node')
      ->condition('type', 'property')
      ->condition('field_parent_id', $property_id)
      ->condition('status', 1) // Only published rooms
      ->accessCheck(FALSE)
      ->execute();

    return !empty($room_nids);
  }

}
