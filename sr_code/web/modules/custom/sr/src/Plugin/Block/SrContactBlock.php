<?php
namespace Drupal\sr\Plugin\Block;

use Drupal\Core\Block\BlockBase;


/**
* Provides a block with contact.
*
* @Block(
*   id = "sr_contact_block",
*   admin_label = @Translation("SR Contact block: basic"),
*   category = "Custom"
* )
*/
class SrContactBlock extends BlockBase {

 /**
  * {@inheritdoc}
  */
 public function build() {
  return [
    '#theme' => 'sr_contact_block',
    '#text' => '',
  ];
 }

}