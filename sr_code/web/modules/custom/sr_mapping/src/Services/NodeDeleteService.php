<?php

namespace Drupal\sr_mapping\Services;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;

class NodeDeleteService {

  protected $entityTypeManager;

  public function __construct(EntityTypeManagerInterface $entityTypeManager) {
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * Deletes all nodes of a given content type where field_source equals a given value.
   *
   * @param string $content_type
   *   The machine name of the content type.
   * @param string $source_value
   *   The value to match in the 'field_source' field.
   *
   * @return int
   *   The number of nodes deleted.
   */
  public function deleteNodesBySource(string $content_type, string $source_value): int {
    $storage = $this->entityTypeManager->getStorage('node');

    $query = $storage->getQuery()
      ->condition('type', $content_type)
      ->accessCheck(TRUE)
      ->condition('field_property_source', $source_value);

    $nids = $query->execute();

    if (!empty($nids)) {
      $nodes = $storage->loadMultiple($nids);
      foreach ($nodes as $node) {
        if ($node instanceof NodeInterface) {
          $node->delete();
          echo "Deleted NID = " . $node->id();
        }
      }
      return count($nids);
    }

    return 0;
  }

}
