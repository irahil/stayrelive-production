<?php

namespace Drupal\inventory_management\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\common_utilities\Utilities\commonUtil;
use Drupal\paragraphs\Entity\Paragraph;

class RoomForm extends FormBase {

  public function getFormId() {
    return 'room_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state, $pid = 0) {
    // Ensure $pid is an integer and greater than 0
    $pid = (int) $pid;
    if ($pid <= 0) {
      throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException();
    }

    // Load the parent node to validate vendor ID
    $parent_node = \Drupal::entityTypeManager()
      ->getStorage('node')
      ->load($pid);
    if (!$parent_node || $parent_node->bundle() !== 'property') {
      throw new \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException();
    }

    $form['back_link'] = [
      '#type' => 'link',
      '#title' => $this->t('Back'),
      '#url' => \Drupal\Core\Url::fromRoute('inventory_management.property_edit', ['id' => $pid]),
    ];

    $form['pid'] = [
      '#type' => 'hidden',
      '#value' => $pid
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
        '#default_value' => [],
      ];
      
      $form['room_info']['title'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Title'),
        '#attributes' => [
          'class' => ['form-full'],
          'placeholder' => $this->t('Enter Room Title'),
        ],
      ];
    }

    // Get from taxanomy
    $arr_property_type = commonUtil::get_term_list('property_type');

    $form['room_info']['property_type'] = [
      '#type' => 'radios',
      '#title' => $this->t('Property Type'),
      '#options' => $arr_property_type,
      '#default_value' => array_key_first($arr_property_type),
      "#attributes" => ['class' => ['hosting-type-wrapper']],
    ];

    $form['room_info']['total_bedrooms'] = [
      '#type' => 'select',
      '#title' => $this->t('Total Bedrooms'),
      '#options' => [
        '1' => "1",
        '2' => "2",
        '3' => "3",
        '4' => "4",
        '5' => "5"
      ],
      '#attributes' => [
        'class' => ['form-half'],
      ],
    ];

    $form['room_info']['total_bathrooms'] = [
      '#type' => 'select',
      '#title' => $this->t('Total Bathrooms'),
      '#options' => [
        '1' => "1",
        '2' => "2",
        '3' => "3",
        '4' => "4",
        '5' => "5"
      ],
      '#attributes' => [
        'class' => ['form-half'],
      ],
    ];


    $form['room_info']['room_description'] = [
      '#type' => 'text_format',
      '#title' => $this->t('Room Description'),
      '#format' => 'full_html', // Fixed format
      '#allowed_formats' => ['full_html'], // Limit dropdown to one format
      '#rows' => 5,
      '#default_value' => '',
    ];

    $arr_room_amenities = commonUtil::getRoomAmenities();

    $form['room_info']['room_amenities'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Room Amenities'),
      '#options' => $arr_room_amenities,
      '#required' => TRUE,
      "#attributes" => ['class' => ['hosting-type-wrapper']],
    ];

    // Room Price Fieldset
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
      '#required' => TRUE,
      '#attributes' => [
        'class' => ['form-half'],
      ],
    ];

    $form['room_price']['room_price_tax_wrapper']['price'] = [
      '#type' => 'number',
      '#title' => $this->t('Average Price'),
      '#min' => 0,
      '#step' => 1,
      '#required' => TRUE,
      '#default_value' => 1,
      '#attributes' => [
        'class' => ['form-half'],
      ],
    ];

    // Submit button
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
    ];
    $form['#theme'] = 'room_form';
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

    if ($values['pid'] <= 0) {
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
      $arr_city = commonUtil::get_term_list('town_city');
      $arr_property_type = commonUtil::get_term_list('property_type');

      $city_id = $parent_node->get('field_city')->getString();
      $values['title'] = $arr_property_type[$values['property_type']] . " in " . $arr_city[$city_id];
    }

    // Build room node fields array
    $room_fields = [
      'type' => 'property',
      'title' => $values['title'],

      'field_parent_id' => $values['pid'], // assumes entity reference field

      // Room Info
      'field_property_type' => $values['property_type'] ?? '',
      'field_total_bedrooms' => $values['total_bedrooms'] ?? 0,
      'field_total_bathrooms' => $values['total_bathrooms'] ?? 0,
      'field_currency_code' => $values['currency'] ?? '',
      'field_property_source' => 'stayrelive',
      // Room Price
      'field_price' => $values['price'] ?? 0,
      'field_has_price' => $values['price'] > 0 ? 1 : 0,
      'field_available_from' => date('Y-m-d',  time()),
      'field_amenities' => array_filter($values['room_amenities'] ?? []),
    ];

    // Save new room node
    $node = \Drupal\node\Entity\Node::create($room_fields);

    // Create paragraph
    $paragraph = Paragraph::create([
      'type' => 'property_description',
      'field_heading' => $values['title'] . ' room overview',
      'field_text' => [
        'value' => $values['room_description']['value'] ?? '',
        'format' => 'full_html',
      ],
    ]);
    $paragraph->save();

    // Attach to node
    $node->field_description[] = [
      'target_id' => $paragraph->id(),
      'target_revision_id' => $paragraph->getRevisionId(),
    ];

    $published = (isset($values['published']) && $values['published'][1] == "1" ) ? TRUE : FALSE;
    if ($published) {
      $node->setPublished(TRUE);
    } else {
      $node->setUnpublished();
    }

    $node->save();

    // Update property status based on published rooms
    $this->updatePropertyStatusBasedOnRooms($values['pid']);

    \Drupal::messenger()->addMessage($this->t('Room details saved successfully. ID: @id', ['@id' => $node->id()]));
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
      ->condition('type', 'property')
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
