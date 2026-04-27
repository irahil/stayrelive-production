<?php

namespace Drupal\ch_property\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\views\Views;
use Drupal\Component\Utility\Xss;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Returns responses for Property Search routes.
 */
class PropertySearchController extends ControllerBase
{

  /**
   * Builds the property search page.
   *
   * @return array
   *   Render array for the property search view.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
   *   Throws exception if view is not found or accessible.
   */
  public function searchPage()
  {
    $view = Views::getView('property_search');
    \Drupal::logger('ch_property')->debug('Custom route /property/search accessed');

    if (!$view) {
      throw new NotFoundHttpException();
    }

    // 🔥 USE PAGE DISPLAY (recommended)
    $view->setDisplay('block_1');

    // Pass exposed filters
    $view->setExposedInput(\Drupal::request()->query->all());

    // Execute view
    $view->execute();

    return [
      '#type' => 'container',
      '#attributes' => [
        'id' => 'block-srdesign-views-block-property-search-block-1',
        'class' => [
          'block',
          'block-views',
          'block-views-blockproperty-search-block-1',
        ],
      ],
      'content' => $view->buildRenderable(),
      '#cache' => [
        'contexts' => [
          'url.query_args:field_city_target_id',
          'url.query_args:date',
          'url.query_args:rooms',
        ],
      ],
    ];

  }

}