<?php
namespace Drupal\sr\Plugin\Block;

use Drupal\Core\Block\BlockBase;


/**
* Provides a block with Property Suffix.
*
* @Block(
*   id = "sr_property_suffix_block",
*   admin_label = @Translation("SR Property Suffix block: basic"),
*   category = "Custom"
* )
*/
class SrPropertySuffix extends BlockBase {

 /**
  * {@inheritdoc}
  */
 public function build() {
  return [
    '#theme' => 'sr_property_suffix_block',
    '#text' => '',
  ];
 }

}