<?php

namespace Drupal\ch_property\Controller;

use Drupal\Component\Utility\Tags;
use Drupal\Component\Utility\Unicode;
use Drupal\Core\Controller\ControllerBase;
use Drupal\taxonomy\Entity\Term;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Defines a route controller for town/city taxonomy autocomplete.
 */
class TownCityAutocompleteController extends ControllerBase {

  /**
   * Handler for autocomplete request.
   */
  public function getCities() {
    $query = \Drupal::entityQuery('taxonomy_term')
      ->condition('vid', 'city')
      ->sort('name')
      ->accessCheck(TRUE)
      ->execute();

    $terms = Term::loadMultiple($query);
    $cities = [];

    foreach ($terms as $term) {
      $cities[] = $term->getName();
    }

    return new JsonResponse($cities);
  }
  public function build() {
    $build['#attached']['library'][] = 'ch_property/autocomplete';
    // $build['#attached']['drupalSettings']['city_town'] = $results;

    return $build;
  } 
}
