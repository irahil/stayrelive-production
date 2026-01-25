<?php

namespace Drupal\ch_property\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormBuilderInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Cache\Cache;

/**
 * Provides a 'Home Search' Block with modal wrapper.
 *
 * @Block(
 *   id = "inner_search_block",
 *   admin_label = @Translation("Inner Search Block"),
 *   category = @Translation("Custom")
 * )
 */
class PropertySearchBlock extends BlockBase implements ContainerFactoryPluginInterface {

  protected $formBuilder;

  public function __construct(array $configuration, $plugin_id, $plugin_definition, FormBuilderInterface $form_builder) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->formBuilder = $form_builder;
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('form_builder')
    );
  }
  
  /**
   * {@inheritdoc}
   */
  public function build() {
  $build = [
    '#theme' => 'property_search_modal',
    '#form' => $this->formBuilder->getForm('Drupal\ch_property\Form\HomeSearchForm'),
    '#attached' => [
      'library' => ['ch_property/filter_toggle'],
    ],
  ];

  return $build;
}
}
