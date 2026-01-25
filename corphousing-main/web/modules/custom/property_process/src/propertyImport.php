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
    $nodes = \Drupal::entityTypeManager()->getStorage('node')->loadByProperties([
      'field_property_id' => $propertyData['field_property_id'],
    ]);
    $node = reset($nodes);
    if(!$node) {
      $node = Node::create(['type' => 'property']);
      $flag_insert = true;
    }
    //print_r($propertyData);exit;

    foreach($propertyData as $key => $value) {
      if (!is_array($value) && !is_object($value)) {
        $value = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
      }
      $node->set($key, $value);
      //echo "Set field $key to value: ";
      //print_r($value);
      //echo "\n";
    }
    $node->setOwnerId(1);
    if ($flag_insert) {
      $node->set('status', 0);
    }
    $node->save();

    $clean_reference_id = \Drupal::service('pathauto.alias_cleaner')->cleanString($propertyData['field_property_id']);
    $clean_uri = \Drupal::service('pathauto.alias_cleaner')->cleanString($propertyData['title']);
    $source = $propertyData['field_vendor_id'];
    $node_url = "/" . $source . "/" .$clean_reference_id."/".$clean_uri;
    $path_alias = PathAlias::create([
        'path' => '/node/' . $node->id(),
        'alias' => $node_url,
    ]);
    $path_alias->save();
    return array($node->id(), $flag_insert, $propertyData['field_property_id']);
  }

  /**
   * Creates the property node type and adds the necessary fields.
   */
  public function createRoom($roomData) {
    //print_r($roomData);
    $flag_insert = false;
    $nodes = \Drupal::entityTypeManager()->getStorage('node')->loadByProperties([
      'field_parent_id' => $roomData['field_parent_id'],
      'field_room_type' => $roomData['field_room_type'],
    ]);
    $node = reset($nodes);
    if(!$node) {
      $node = Node::create(['type' => 'room']);
      $flag_insert = true;
    }
    //print_r($roomData);exit;

    foreach($roomData as $key => $value) {
      if ($key == "vendor_id") {
        continue;
      }
      if (!is_array($value) && !is_object($value)) {
        $value = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
      }
      if ($key == "field_room_price") {
        $value = (float) str_replace(',', '', $value);
      }
      $node->set($key, $value);
      //echo "Set field $key to value: ";
      //print_r($value);
      //echo "\n";
    }
    $node->setOwnerId(1);
    if ($flag_insert) {
      $node->set('status', 0);
    }

    $node->save();
    $clean_reference_id = \Drupal::service('pathauto.alias_cleaner')->cleanString($roomData['field_parent_id']);
    $clean_uri = \Drupal::service('pathauto.alias_cleaner')->cleanString($roomData['title']);
    $source = $roomData['vendor_id'];
    $node_url = "/" . $source . "/" .$clean_reference_id."/".$clean_uri;
    $path_alias = PathAlias::create([
        'path' => '/node/' . $node->id(),
        'alias' => $node_url,
    ]);
    $path_alias->save();
    return array($node->id(), $flag_insert, $roomData['field_parent_id']);
  }





}
