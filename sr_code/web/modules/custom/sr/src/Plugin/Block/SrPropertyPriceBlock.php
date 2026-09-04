<?php
namespace Drupal\sr\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\common_utilities\Utilities\commonUtil;

/**
* Provides a block with Property price.
*
* @Block(
*   id = "sr_property_price_block",
*   admin_label = @Translation("SR Property Price block: basic"),
*   category = "Custom"
* )
*/
class SrPropertyPriceBlock extends BlockBase {

  var $arr_request_param = array(
    'country_code' => '', 'pid' => '',
    'price_min' => '', 'price_max' => '',
    'bathroom' => '', 'bedroom' => '',
    'date_range' => '', 'town_city' => '',
    'adult' => '', 'kid' => '',
    'amenities' => '', 'sort' => '',
  );

 /**
  * {@inheritdoc}
  */
 public function build($param = array()) {
  $node = \Drupal::routeMatch()->getParameter('node');
  if ($node instanceof \Drupal\node\NodeInterface) {
    $node_id = $node->id();

    // Create an entity query for nodes.
    $query = \Drupal::entityQuery('node')
    ->condition('status', 1)
    ->condition('type', 'property')
    ->condition('field_parent_id', $node_id)
    ->accessCheck(FALSE);

    // Execute the query to get node IDs.
    $nids = $query->execute();

    if (!empty($nids)) {
      // Load child property if available.
      return \Drupal::formBuilder()->getForm('Drupal\sr\Form\PropertySubForm');
    } else {
      $node = \Drupal::routeMatch()->getParameter('node');
      // Rategain properties show their room types via the "Available Rates"
      // section (Rategain::buildRoomRateList()) further down the page instead —
      // skip the sidebar's APIPriceForm to avoid showing the same rooms twice.
      if ($node->get('field_property_source')->value == 'rategain' && $node->get('field_reference_id')->value) {
         return [];
      } else {
        return \Drupal::formBuilder()->getForm('Drupal\sr\Form\PriceForm');
      }
    }
  } else {
    // redirect user to error page.
  }
 }

  public function getCacheMaxAge() {
    return 0;
  }

}