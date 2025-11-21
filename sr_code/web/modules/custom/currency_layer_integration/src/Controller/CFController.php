<?php
namespace Drupal\currency_layer_integration\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\RedirectResponse;

class CFController extends ControllerBase {
  /**
   * Set Currency Session
   *
   */
  public function setCurrency($currency = 'USD') {
    $request = \Drupal::request();
    $session = $request->getSession();
    $destination = \Drupal::request()->query->get('destination');
    $session->set('currency_layer_integration_selected', $currency);
    $response = new RedirectResponse($destination);
    $response->send();
    exit;
  }
}