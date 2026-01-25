<?php

namespace Drupal\ch_property\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\ch_property\Form\HomeSearchForm;

/**
 * Provides a 'Home Search' Block.
 *
 * @Block(
 *   id = "home_search_block",
 *   admin_label = @Translation("Home Search Block"),
 *   category = @Translation("Custom")
 * )
 */
class HomeSearchBlock extends BlockBase
{

  /**
   * {@inheritdoc}
   */
  public function build()
  {
    return [
      '#theme' => 'home_search_block',
      '#form' => \Drupal::formBuilder()->getForm(HomeSearchForm::class),
    ];
  }
}
