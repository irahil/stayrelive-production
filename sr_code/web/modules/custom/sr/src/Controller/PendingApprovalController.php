<?php

namespace Drupal\sr\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\user\Entity\User;
use Drupal\Core\Pager\PagerManagerInterface;
use Drupal\Core\Pager\PagerManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Link;
use Drupal\file\Entity\File;
use Symfony\Component\HttpFoundation\Response;

class PendingApprovalController extends ControllerBase {

  public function pendingUsers() {
    // Table headers
    $header = [
      'id' => $this->t('ID'), 
      'field_first_name' => $this->t('First Name'), 
      'field_last_name' => $this->t('Last Name'), 
      'username' => $this->t('Username'), 
      'email' => $this->t('Email'), 
      'field_phone_number' => $this->t('Phone Number'), 
      'actions' => $this->t('Actions')
    ];
  
    // Query to fetch pending users
    $query = \Drupal::entityQuery('user')
    ->condition('status', 0)
    ->condition('field_is_agent', 1)
    ->accessCheck(FALSE)
    ->sort('created', 'DESC');
    $uids = $query->execute();

    // Set up pagination parameters
    $page = \Drupal::request()->query->get('page', 0);
    $limit = 10; // Set the number of items per page
    $offset = $page * $limit; // Calculate the offset

    $user_ids = array_slice($uids, $offset, $limit);
    $users = User::loadMultiple($user_ids);
    
  
    $rows = [];
    foreach ($users as $user) {
      $approve_url = Url::fromRoute('sr.approve_user', ['user' => $user->id()]);
      $reject_url = Url::fromRoute('sr.reject_user', ['user' => $user->id()]);
      $view_url = Url::fromRoute('sr.view_user', ['user' => $user->id()]);
  
      // Render links using render arrays directly in the rows
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
            [
              '#type' => 'link',
              '#title' => $this->t('Reject'),
              '#url' => $reject_url,
              '#attributes' => ['class' => ['button']],
            ],
          ],
        ],
      ];
    }
  
    $table = [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#empty' => $this->t('No pending agents found.'),
    ];

    $total_count = count($uids);
    $pager = [
      '#type' => 'pager',
      '#quantity' => 2,
    ];

    $biuld =  [
      $table,
      $pager,
    ];

  // Create the pager for total count
  $pager_manager = \Drupal::service('pager.manager');
  $pager_manager->createPager($total_count, $limit);

    return $biuld;
  }

  /**
   * Displays user details in a modal.
   */
  public function viewUser(User $user) {
    // Prepare the user details for display in a table
    $header = [
        $this->t('User'),
        $this->t('Details'),
    ];

    $rows = [
    [
        $this->t('ID'),
        $user->id(),
    ],
    [
      $this->t('First Name'),
      $user->hasField('field_first_name') ? $user->get('field_first_name')->value : $this->t('N/A'),
    ],
    [
      $this->t('Last Name'),
      $user->hasField('field_last_name') ? $user->get('field_last_name')->value : $this->t('N/A'),
    ],
    [
      $this->t('State'),
      $user->hasField('field_state') ? $user->get('field_state')->value : $this->t('N/A'),
    ],
    [
      $this->t('County Code'),
      $user->hasField('field_phone_code') ? $user->get('field_phone_code')->value : $this->t('N/A'),
    ],
    [
      $this->t('Phone Number'),
      $user->hasField('field_phone_number') ? $user->get('field_phone_number')->value : $this->t('N/A'),
    ],
    [
        $this->t('Username'),
        $user->getAccountName(),
    ],
    [
        $this->t('Email'),
        $user->getEmail(),
    ],
    [
        $this->t('Status'),
        $user->isActive() ? $this->t('Active') : $this->t('Blocked'),
    ],
    [
        $this->t('Created'),
        date('Y-m-d', $user->getCreatedTime()),
    ],
    [
        $this->t('Roles'),
        implode(', ', $user->getRoles()), // List user roles
    ],
    [
        $this->t('Agency Name'),
        $user->hasField('field_agency_name') ? $user->get('field_agency_name')->value : $this->t('N/A'),
    ],
    [
        $this->t('Branch Name'),
        $user->hasField('field_branch_name') ? $user->get('field_branch_name')->value : $this->t('N/A'),
    ],
    [
        $this->t('Agent Email Address'),
        $user->hasField('field_agent_email_address') ? $user->get('field_agent_email_address')->value : $this->t('N/A'),
    ],
    [
        $this->t('Agent Contact Number'),
        $user->hasField('field_agent_contact_number') ? $user->get('field_agent_contact_number')->value : $this->t('N/A'),
    ],
    [
        $this->t('IATA Status'),
        $user->hasField('field_iata_status') ? $user->get('field_iata_status')->value : $this->t('N/A'),
    ],
    [
        $this->t('Address'),
        $user->hasField('field_address') ? $user->get('field_address')->value : $this->t('N/A'),
    ],
    [
        $this->t('Pin Code'),
        $user->hasField('field_pin_code') ? $user->get('field_pin_code')->value : $this->t('N/A'),
    ],
    [
        $this->t('Registered Consultant Name'),
        $user->hasField('field_registered_consultant_name') ? $user->get('field_registered_consultant_name')->value : $this->t('N/A'),
    ],
    [
        $this->t('Registered Consultant Contact'),
        $user->hasField('field_registered_consultant_cont') ? $user->get('field_registered_consultant_cont')->value : $this->t('N/A'),
    ],
    [
        $this->t('Finance Manager Name'),
        $user->hasField('field_finance_manager_name') ? $user->get('field_finance_manager_name')->value : $this->t('N/A'),
    ],
    [
        $this->t('Finance Manager Email'),
        $user->hasField('field_finance_manager_email') ? $user->get('field_finance_manager_email')->value : $this->t('N/A'),
    ],
    [
      $this->t('Company Registration Certificate'),
      $user->hasField('field_company_registration_certi') && !$user->get('field_company_registration_certi')->isEmpty()
          ? \Drupal\Core\Link::fromTextAndUrl(
              $this->t('View Certificate'),
              Url::fromUri(\Drupal::service('file_url_generator')->generateAbsoluteString($user->get('field_company_registration_certi')->entity->getFileUri())),
              ['attributes' => ['target' => '_blank']]
          )->toString()
          : $this->t('N/A'),
    ],
    [
      $this->t('Company URL'),
      $user->hasField('field_company_url') && !$user->get('field_company_url')->isEmpty()
          ? (strpos($user->get('field_company_url')->value, 'http') === 0
              ? \Drupal\Core\Link::fromTextAndUrl(
                  $this->t('Visit Company Site'),
                  Url::fromUri($user->get('field_company_url')->value, ['attributes' => ['target' => '_blank']])
                )->toString()
              : $user->get('field_company_url')->value)
          : $this->t('N/A'),
    ],
  ];

  // Build the table render array
  $table = [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#attributes' => ['class' => ['user-details-table']],
      '#empty' => $this->t('No details available.')
  ];

  // Create the Back button
  $back_button = [
    '#type' => 'link',
    '#title' => $this->t('Go back'),
    '#url' => Url::fromRoute('sr.pending_users_page'),
    '#attributes' => [
        'class' => ['button'],
    ],
  ];

  return [
    $table,
    $back_button,
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
    return $this->redirect('sr.pending_users_page'); // Adjust this to your correct route
  }

  /**
  * Rejects a pending user.
  */
  public function rejectUser(User $user) {
    $user->block();
    $user->save();
    $this->messenger()->addMessage($this->t('User %name has been rejected.', ['%name' => $user->getAccountName()]));

    // Redirect to the pending users list after rejection
    return $this->redirect('sr.pending_users_page'); // Adjust this to your correct route
  }

}
