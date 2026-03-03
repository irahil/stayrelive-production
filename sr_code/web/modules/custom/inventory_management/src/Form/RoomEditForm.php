<?php

namespace Drupal\inventory_management\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\common_utilities\Utilities\commonUtil;
use Drupal\paragraphs\Entity\Paragraph;
class RoomEditForm extends FormBase {

  public function getFormId() {
    return 'room_edit_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state, $pid = 0, $id = 0) {

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
    if (!$parent_node || $parent_node->bundle() !== 'property') {
      throw new \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException();
    }
    
    // Load the room node to edit
    $node = \Drupal::entityTypeManager()
      ->getStorage('node')
      ->load($id);
    if (!$node || !$node->hasField('field_parent_id') || $node->get('field_parent_id')->getString() != $pid
     || !$node instanceof \Drupal\node\NodeInterface || $node->bundle() !== 'property') {
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
      '#default_value' => $node->get('field_total_bedrooms')->getString(),
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
      '#default_value' => $node->get('field_total_bathrooms')->getString(),
    ];

    $paragraph_body = "";
    $paragraph_items = $node->get('field_description')->getValue();
    foreach ($paragraph_items as $paragraph_key => $paragraph_value) {
      $paragraph = Paragraph::load($paragraph_value['target_id']);

      if ($paragraph) {
        $paragraph_body = $paragraph->get('field_text')->value;
      }
    }

    $form['room_info']['room_description'] = [
      '#type' => 'text_format',
      '#title' => $this->t('Room Description'),
      '#format' => 'basic_html', // Fixed format
      '#allowed_formats' => ['basic_html'], // Limit dropdown to one format
      '#rows' => 5,
      '#default_value' => $paragraph_body,
    ];

    $arr_room_amenities = commonUtil::getRoomAmenities();

    $selected_values = ($node->get('field_amenities')->getValue() != "") ? 
      array_map('trim', explode(",", $node->get('field_amenities')->getString())) : 
      array();

    $form['room_info']['room_amenities'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Room Amenities'),
      '#options' => $arr_room_amenities,
      '#default_value' => $selected_values,
      '#required' => TRUE,
      "#attributes" => ['class' => ['hosting-type-wrapper']],
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

    $arr_currency = commonUtil::get_term_list('currency');

    $form['room_price']['room_price_tax_wrapper']['currency'] = [
      '#type' => 'select',
      '#title' => $this->t('Currency'),
      '#options' => $arr_currency,
      '#default_value' => $node->get('field_currency_code')->getString(),
      '#required' => TRUE,
      '#attributes' => [
        'class' => ['form-half'],
      ],
    ];

    $form['room_price']['room_price_tax_wrapper']['price'] = [
      '#type' => 'number',
      '#title' => $this->t('Price'),
      '#min' => 0,
      '#step' => 1,
      '#default_value' => $node->get('field_price')->getString(),
      '#required' => TRUE,
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
    if (!is_numeric($form_state->getValue('price'))) {
      $form_state->setErrorByName('price', $this->t('Please enter a valid number for price.'));
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

    if (!$node instanceof \Drupal\node\NodeInterface || $node->bundle() !== 'property') {
      \Drupal::messenger()->addError($this->t('Unable to load the room node.'));
      return;
    }
    
    // Update fields
    $node->setTitle($values['title']);
    $node->set('field_property_type', $values['property_type']);
    $node->set('field_total_bedrooms', $values['total_bedrooms']);
    $node->set('field_total_bathrooms', $values['total_bathrooms']);
    $node->set('field_currency_code', $values['currency']);
    $node->set('field_price', $values['price']);
    $node->set('field_has_price', $values['price'] > 0 ? 1 : 0);
    $node->set('field_amenities', array_filter($values['room_amenities'] ?? []));

    foreach ($node->get('field_description') as $item) {
      $paragraph = $item->entity;
      if ($paragraph) {
        $paragraph->set('field_text', $values['room_description'] ?? '');
        $paragraph->save();
      }
    }

    $published = (isset($values['published']) && $values['published'][1] == "1" ) ? TRUE : FALSE;
    if ($published) {
      $node->setPublished(TRUE);
    } else {
      $node->setUnpublished();
    }

    $node->save();

    // Update property status based on published rooms
    //$this->updatePropertyStatusBasedOnRooms($values['pid']);

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
