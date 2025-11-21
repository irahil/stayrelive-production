<?php

namespace Drupal\sr\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormInterface;

/**
* Provides a block with search.
*
* @Block(
*   id = "sr_search_block",
*   admin_label = @Translation("SR Search block: basic"),
*   category = "Custom"
* )
*/
class SrSearchBlock extends BlockBase {

 /**
  * {@inheritdoc}
  */
  public function build() {
/*
    return [
      '#markup' => 'This is a simple block that displays some text!',
    ];
*/
/*
    $form = \Drupal::formBuilder()->getForm('Drupal\sr\Form\HomeSearchForm');
    \Drupal::logger('Sr')->info('HomeSearchForm: output '. print_r($form, true));

    return $form;
    */
  }

}