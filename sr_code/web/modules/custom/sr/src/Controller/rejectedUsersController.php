<?php

namespace Drupal\sr\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\user\Entity\User;
use Drupal\Core\Pager\PagerManagerInterface;
use Drupal\Core\Pager\PagerManager;

/**
 * Controller for displaying rejected users.
 *
 * @package Drupal\sr\Controller
 */
class rejectedUsersController extends ControllerBase {

  /**
   * Displays a list of rejected users.
   */
  public function rejectedUsers() {
    // Table headers.
    $header = [
      'id' => $this->t('ID'),
      'field_first_name' => $this->t('First Name'),
      'field_last_name' => $this->t('Last Name'),
      'username' => $this->t('Username'), 
      'email' => $this->t('Email'), 
      'field_phone_number' => $this->t('Phone Number'),  
      'actions' => $this->t('Actions'),
    ];

    // Query to fetch rejected (blocked) users.
    $query = \Drupal::entityQuery('user')
      ->condition('status', 0)
      ->condition('field_is_agent', 1)
      ->accessCheck(FALSE)
      ->sort('created', 'DESC');
      
    $uids = $query->execute();
    $users = User::loadMultiple($uids);

    // Set up pagination parameters
    $page = \Drupal::request()->query->get('page', 0);
    $limit = 10; // Set the number of items per page
    $offset = $page * $limit; // Calculate the offset

    $user_ids = array_slice($uids, $offset, $limit);
    $users = User::loadMultiple($user_ids);

    // Generate table rows.
    $rows = [];
    foreach ($users as $user) {
      $view_url = Url::fromRoute('sr.view_user', ['user' => $user->id()]);
      $approve_url = Url::fromRoute('sr.approve_user', ['user' => $user->id()]);

      $rows[] = [
        'ID' => $user->id(),
        'field_first_name' => $user->get('field_first_name')->value,
        'field_last_name' => $user->get('field_last_name')->value,
        'username' => $user->getAccountName(),
        'email' => $user->getEmail(),
        'field_phone_number' => $user->get('field_phone_number')->value,
        'actions' => [
          'data' => [
            [
              '#type' => 'link',
              '#title' => $this->t('View'),
              '#url' => $view_url,
              '#attributes' => ['class' => ['button']],
            ],
            [
              '#type' => 'link',
              '#title' => $this->t('Approve'),
              '#url' => $approve_url,
              '#attributes' => ['class' => ['button']],
            ],
          ],
        ],
      ];
    }

    $total_count = count($uids);
    $pager = [
      '#type' => 'pager',
      '#quantity' => 2,
    ];

    $table = [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#empty' => $this->t('No rejected agents found.'),
    ];

    // Create the pager for total count
    $pager_manager = \Drupal::service('pager.manager');
    $pager_manager->createPager($total_count, $limit);

    return [
      $table,
      $pager,
    ];
  }

    /**
   * Approves a pending user.
   */
  public function approveUser(User $user) {
    $user->activate();
    $user->save();
    $this->messenger()->addMessage($this->t('User %name has been approved.', ['%name' => $user->getAccountName()]));

    // Redirect to the pending users list after approval
    return $this->redirect('sr.rejected_users_page'); // Adjust this to your correct route
  }
}