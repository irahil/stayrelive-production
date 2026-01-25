<?php

namespace Drupal\inventory_management\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\common_utilities\Utilities\commonUtil;

class RoomEditForm extends FormBase {

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

      $form['room_info']['title'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Title'),
        '#default_value' => $node->getTitle(),
        '#attributes' => [
          'class' => ['form-full'],
        ],
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

    $form['room_price']['room_price_tax_wrapper'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['room-price-tax-wrapper']],
    ];

    $form['room_price']['room_price_tax_wrapper']['price'] = [
      '#type' => 'number',
      '#title' => $this->t('Average Price'),
      '#min' => 0,
      '#step' => 1,
      '#default_value' => $node->get('field_room_price')->getString(),
      '#required' => TRUE,
      '#attributes' => [
        'class' => ['form-half'],
      ],
    ];

    $form['room_price']['room_price_tax_wrapper']['tax_details'] = [
      '#type' => 'select',
      '#title' => $this->t('Tax Details (GST/VAT)'),
      '#options' => ['0' => "0%", '5' => "5%", '12' => "12%", '18' => "18%", '28' => "28%"],
      '#default_value' => $node->get('field_tax_details')->getString(),
      '#attributes' => [
        'class' => ['form-half'],
      ],
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Update'),
    ];

    // Save the node to form_state for use in submit
    if ($node) {
      $form_state->set('room_node', $node);
    }
    $form['#theme'] = 'room_edit_form';

    return $form;
  }


  public function validateForm(array &$form, FormStateInterface $form_state) {
    // $contact_name = $form_state->getValue('contact_name');
    $contact_number = $form_state->getValue('contact_mobile');

    if (!is_numeric($form_state->getValue('number_of_units'))) {
      $form_state->setErrorByName('number_of_units', $this->t('Please enter a valid number for units.'));
    }
    if (!is_numeric($form_state->getValue('price'))) {
      $form_state->setErrorByName('price', $this->t('Please enter a valid number for price.'));
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

    if (!isset($values['title']) || $values['title'] == "") {
      $arr_city = commonUtil::get_term_list('city');
      $arr_room_type = commonUtil::get_term_list('room_type');

      $city_id = $parent_node->get('field_city')->getString();
      $values['title'] = $arr_room_type[$values['room_type']] . " in " . $arr_city[$city_id];
    }

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

    $node->set('field_room_price', $values['price']);
    $node->set('field_tax_details', $values['tax_details']);

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
