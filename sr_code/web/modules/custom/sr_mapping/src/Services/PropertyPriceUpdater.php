<?php

namespace Drupal\sr_mapping\Services;

use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Service to update the "has price" field based on price value.
 */
class PropertyPriceUpdater {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs the PropertyPriceUpdater service.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager) {
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * Update the field_has_price based on field_price value.
   *
   * @param int $chunk_size
   *   The number of nodes to process at once.
   */
  public function updateHasPriceField(int $chunk_size = 50): void {
    $query = \Drupal::entityQuery('node')
      ->condition('type', 'property')
      ->accessCheck(FALSE);

    $nids = $query->execute();
    $chunks = array_chunk($nids, $chunk_size);

    foreach ($chunks as $chunk) {
      $nodes = Node::loadMultiple($chunk);
      $counter = 0;
      foreach ($nodes as $node) {
        if ($node instanceof NodeInterface && $node->hasField('field_price') && $node->hasField('field_has_price')) {
          $price = $node->get('field_price')->getValue();

          // Check if there's a valid price (greater than 0 or non-empty)
          if (!empty($price[0]['value']) && $price[0]['value'] > 0) {
            $node->set('field_has_price', 1);
          } else {
            $node->set('field_has_price', 0);
          }

          $node->save();
          usleep(50000);
          echo "\n Updated had price for node : " . $node->id();
          //\Drupal::logger('sr_mapping')->info('Updated price for node @nid', ['@nid' => $node->id()]);
        }
        $counter++;
        //if ($counter == 5 ) {exit;}
      }
    }
  }

}
