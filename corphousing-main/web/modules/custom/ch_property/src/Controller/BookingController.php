<?php

namespace Drupal\ch_property\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Drupal\webform\Entity\WebformSubmission;

class BookingController extends ControllerBase {

  public function cancelBooking($submission_id, Request $request) {
    $current_user = $this->currentUser();
    $submission = WebformSubmission::load($submission_id);

    if (!$submission) {
      return new JsonResponse(['status' => 'error', 'message' => 'Booking not found'], 404);
    }

    // Ensure the booking belongs to the current user
    if ($submission->getOwnerId() != $current_user->id()) {
      return new JsonResponse(['status' => 'error', 'message' => 'Access denied'], 403);
    }

    try {
      $submission->delete();
      return new JsonResponse(['status' => 'success', 'message' => 'Booking cancelled successfully']);
    }
    catch (\Exception $e) {
      \Drupal::logger('sr_share')->error($e->getMessage());
      return new JsonResponse(['status' => 'error', 'message' => 'An error occurred while cancelling the booking']);
    }
  }
}
