<?php
namespace Drupal\sr_share\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class FavouriteController extends ControllerBase {
  public function saveNodeToSession(Request $request) {
    
    // Get the node ID from the AJAX request.
    $node_id = $request->get('node_id');
    if ($node_id) {
      $session = \Drupal::service('session');
      $session->set('flag_node_id', $node_id);
      return new JsonResponse(['success' => TRUE]);
    }
    return new JsonResponse(['success' => FALSE], 400);
  }
}
