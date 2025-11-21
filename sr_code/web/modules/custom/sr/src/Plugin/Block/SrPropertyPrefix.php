<?php
namespace Drupal\sr\Plugin\Block;

use Drupal\Core\Block\BlockBase;


/**
* Provides a block with Property Prefix.
*
* @Block(
*   id = "sr_property_prefix_block",
*   admin_label = @Translation("SR Property Prefix block: basic"),
*   category = "Custom"
* )
*/
class SrPropertyPrefix extends BlockBase {

 /**
  * {@inheritdoc}
  */
 public function build() {
  return [
    '#theme' => 'sr_property_prefix_block',
    '#text' => '',
  ];
 }

}