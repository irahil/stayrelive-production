<?php
namespace Drupal\property_process;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\path_alias\Entity\PathAlias;

/**
 * Class to create a Property node type.
 */
class propertyImport {

  /**
   * Creates the property node type and adds the necessary fields.
   */

  public function createProperty($propertyData) {
    $flag_insert = false;

    // Load existing node by reference id
    $nodes = \Drupal::entityTypeManager()->getStorage('node')->loadByProperties([
      'field_reference_id' => $propertyData['field_reference_id'],
    ]);
    $node = reset($nodes);

    if (!$node) {
      $node = Node::create(['type' => 'property']);
      $flag_insert = true;
    }

    foreach ($propertyData as $key => $value) {

      // Normalize scalar values
      if (!is_array($value) && !is_object($value)) {
        $value = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
      }

      if ($key === 'field_description' && !empty($value)) {

        $existing_paragraph = NULL;

        // 🔍 Check if node already has a paragraph
        if (!$flag_insert && !$node->get($key)->isEmpty()) {
          $existing_paragraph = $node->get($key)->entity;
        }

        if ($existing_paragraph) {
          // UPDATE existing paragraph
          $existing_paragraph->set('field_heading', $propertyData['title'] . ' Property overview');
          $existing_paragraph->set('field_text', [
            'value' => $value,
            'format' => 'basic_html',
          ]);
          $existing_paragraph->save();

          \Drupal::logger('property')->notice('Paragraph UPDATED: @id', [
            '@id' => $existing_paragraph->id(),
          ]);
        }
        else {
          // CREATE new paragraph
          $paragraph = $this->createParagraph([
            'type' => 'property_description',
            'field_data' => [
              'field_heading' => $propertyData['title'] . ' Property overview',
              'field_text' => [
                'value' => $value,
                'format' => 'basic_html',
              ],
            ],
          ]);

          if ($paragraph) {
            $paragraph->save();

            $node->set($key, [
              [
                'target_id' => $paragraph->id(),
                'target_revision_id' => $paragraph->getRevisionId(),
              ],
            ]);

            \Drupal::logger('property')->notice('Paragraph CREATED: @id', [
              '@id' => $paragraph->id(),
            ]);
          }
        }

        continue;
      }


      // Skip empty values (optional but recommended)
      if ($value !== NULL && $value !== '') {
        $node->set($key, $value);
      }
    }

    // Owner & status
    $node->setOwnerId(1);
    if ($flag_insert) {
      $node->set('status', 0);
    }

    $node->save();

    // Path alias (avoid duplicates)
    if (!empty($propertyData['field_reference_id']) && !empty($propertyData['title'])) {

      $clean_reference_id = \Drupal::service('pathauto.alias_cleaner')
        ->cleanString($propertyData['field_reference_id']);

      $clean_uri = \Drupal::service('pathauto.alias_cleaner')
        ->cleanString($propertyData['title']);

      $source = $propertyData['field_property_source'] ?? 'property';

      $node_url = "/" . $source . "/" . $clean_reference_id . "/" . $clean_uri;

      $path_alias_storage = \Drupal::entityTypeManager()->getStorage('path_alias');
      $existing_alias = $path_alias_storage->loadByProperties([
        'path' => '/node/' . $node->id(),
      ]);

      if (!$existing_alias) {
        $path_alias = PathAlias::create([
          'path' => '/node/' . $node->id(),
          'alias' => $node_url,
        ]);
        $path_alias->save();
      }
    }

    return [$node->id(), $flag_insert, $propertyData['field_reference_id']];
  }


  /**
   * Creates the property node type and adds the necessary fields.
   */

  public function createRoom($roomData) {
    $flag_insert = false;

    // Load existing node
    $nodes = \Drupal::entityTypeManager()->getStorage('node')->loadByProperties([
      'field_parent_id' => $roomData['field_parent_id'],
      'field_total_bedrooms' => $roomData['field_total_bedrooms'],
    ]);
    $node = reset($nodes);

    if (!$node) {
      $node = Node::create(['type' => 'property']);
      $flag_insert = true;
    }

    foreach ($roomData as $key => $value) {

      // Normalize scalar values
      if (!is_array($value) && !is_object($value)) {
        $value = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
      }

      // Handle room description → maps to field_description
      if ($key === 'field_room_description' && !empty($value)) {

        $field_name = 'field_description';
        $existing_paragraph = NULL;

        // 🔍 Check existing paragraph
        if (!$flag_insert && !$node->get($field_name)->isEmpty()) {
          $existing_paragraph = $node->get($field_name)->entity;
        }

        if ($existing_paragraph) {
          // UPDATE
          $existing_paragraph->set('field_heading', $roomData['title'] . ' Room overview');
          $existing_paragraph->set('field_text', [
            'value' => $value,
            'format' => 'basic_html',
          ]);
          $existing_paragraph->save();

          \Drupal::logger('room')->notice('Room paragraph UPDATED: @id', [
            '@id' => $existing_paragraph->id(),
          ]);
        }
        else {
          // CREATE
          $paragraph = $this->createParagraph([
            'type' => 'property_description',
            'field_data' => [
              'field_heading' => $roomData['title'] . ' Room overview',
              'field_text' => [
                'value' => $value,
                'format' => 'basic_html',
              ],
            ],
          ]);

          if ($paragraph) {
            $paragraph->save();

            $node->set($field_name, [
              [
                'target_id' => $paragraph->id(),
                'target_revision_id' => $paragraph->getRevisionId(),
              ],
            ]);

            \Drupal::logger('room')->notice('Room paragraph CREATED: @id', [
              '@id' => $paragraph->id(),
            ]);
          }
        }

        continue; // critical
      }

      // Skip empty values
      if ($value !== NULL && $value !== '') {
        $node->set($key, $value);
      }
    }

    $node->setOwnerId(1);

    if ($flag_insert) {
      $node->set('status', 0);
    }

    $node->save();

    // Alias handling (safe)
    if (!empty($roomData['field_parent_id']) && !empty($roomData['title'])) {

      $clean_reference_id = \Drupal::service('pathauto.alias_cleaner')
        ->cleanString($roomData['field_parent_id']);

      $clean_uri = \Drupal::service('pathauto.alias_cleaner')
        ->cleanString($roomData['title']);

      $source = $roomData['field_property_source'] ?? 'room';

      $node_url = "/" . $source . "/" . $clean_reference_id . "/" . $clean_uri;

      $path_alias_storage = \Drupal::entityTypeManager()->getStorage('path_alias');
      $existing_alias = $path_alias_storage->loadByProperties([
        'path' => '/node/' . $node->id(),
      ]);

      if (!$existing_alias) {
        $path_alias = PathAlias::create([
          'path' => '/node/' . $node->id(),
          'alias' => $node_url,
        ]);
        $path_alias->save();
      }
    }

    return [$node->id(), $flag_insert, $roomData['field_parent_id']];
  }


  /**
   * Create paragraph for nodes.
   *
   */

function createParagraph($paragraphData) {
  $values = [
    'type' => $paragraphData['type'],
  ];

  // Map all fields correctly
  if (!empty($paragraphData['field_data'])) {
    foreach ($paragraphData['field_data'] as $field_name => $field_value) {
      $values[$field_name] = $field_value;
    }
  }

  $paragraph = \Drupal\paragraphs\Entity\Paragraph::create($values);
  $paragraph->save();

  return $paragraph;
}


}
