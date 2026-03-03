<?php
namespace Drupal\vendor_management\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\vendor_management\vendor;
use Drupal\user\Entity\User;


class VendorEditForm extends FormBase {

  public function getFormId() {
    return 'vendor_add_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state, $vendor_id = 0) {

    $form['error'] = array(
      '#title_display' => 'invisible',
      '#markup' => "Sorry, something went wrong. Please try again later.",
      '#prefix' => '<div class="form-error">',
      '#suffix' => '</div>',
    );

    # Load from node id
    $vendor_entity = \Drupal::entityTypeManager()->getStorage('propertyvendor')->load($vendor_id);
    if ($vendor_entity == NULL) {
      return $form;
    }
    unset($form['error']);

    $info = $vendor_entity->get('info')->getString();
    $arr_info = json_decode($info, true);
    $email = $arr_info['email'];
    $phone = $arr_info['phone'];

    $author = User::load($vendor_entity->get('uid')->target_id);
    $author_email = $author ? $author->getEmail() : 'Unknown';
    $created_timestamp = date('Y-m-d H:i:s', $vendor_entity->get('created')->value);

    $form['back_link'] = [
      '#type' => 'link',
      '#title' => $this->t('Back'),
      '#url' => \Drupal\Core\Url::fromRoute('vendor_management.vendor_list'),
    ];

    $form['vendor_id'] = [
      '#type' => 'hidden',
      '#value' => $vendor_id,
    ];

    $form['author_id'] = [
      '#type' => 'hidden',
      '#value' => $vendor_entity->get('uid')->target_id,
    ];

    $form['requested_by'] = [
      '#type' => 'item',
      '#title' => t('Requested By'),
      '#markup' => $author_email,
    ];

    $form['requested_on'] = [
      '#type' => 'item',
      '#title' => t('Requested On'),
      '#markup' => $created_timestamp,
    ];

    $form['name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Vendor Name'),
      '#required' => TRUE,
      '#default_value' => $vendor_entity->get('name')->getString(),
      '#maxlength' => 255,
      '#size' => 60,
      '#placeholder' => $this->t('Vendor Name'),
      '#attributes' => [
        'autocomplete' => 'off',
      ],
    ];

    $form['email'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Vendor email'),
      '#required' => TRUE,
      '#placeholder' => $this->t('Enter Vendor email'),
      '#attributes' => [
        'maxlength' => 100,  // Optional
      ],
      '#default_value' => $email,
    ];

    $form['phone'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Vendor phone number'),
      '#required' => TRUE,
      '#placeholder' => $this->t('Enter Vendor phone number'),
      '#attributes' => [
        'maxlength' => 100,  // Optional
      ],
      '#default_value' => $phone,
    ];

    $form['status'] = [
      '#type' => 'radios',
      '#title' => $this->t('Status'),
      '#options' => [
        1 => $this->t('Active'),
        0 => $this->t('Inactive'),
      ],
      '#default_value' => $vendor_entity->get('status')->getString(),
      '#required' => TRUE,
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
    ];

    $form['user'] = array(
      '#type' => 'fieldset',
      '#title' => $this
        ->t('User Information'),
      '#attributes' => array(
          'class' => array('SectionContainer'),
      )
    );

    $form['user']['useremail'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Email'),
      '#description' => $this->t('Enter the email of the user.'),
      '#maxlength' => 255,
      '#size' => 60,
      '#placeholder' => $this->t('Enter the email of the user'),
      '#attributes' => [
        'autocomplete' => 'off',
      ],
    );
    
    $form['user']['submit_user'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add User'),
      '#validate' => ['::validateUserForm'],
      '#submit' => ['::submitUserForm'],
    ];

    $uids = \Drupal::entityQuery('user')
      ->accessCheck(TRUE) // or FALSE if you are running as admin and want to skip checks
      ->condition('status', 1)
      ->condition('field_vendor_id', $vendor_id)
      ->execute();

    if (empty($uids)) {
      $form['user']['search_result']['no_result'] = [
        '#type' => 'item',
        '#title' => t('Result'),
        '#markup' => t('Sorry, no user found!'),
      ];
    } else {
      $form['user']['search_result'] = array(
        '#type' => 'fieldset',
        '#attributes' => array(
            'class' => array('SectionContainer'),
        )
      );
      $users = User::loadMultiple($uids);

      $form['user']['search_result']['result'] = array(
          '#type' => 'table',
          '#header' => array(
              $this->t('ID'),
              $this->t('Name'),
              $this->t('Email'),
          ),
          '#attributes' => array(
              'class' => array(
              'tbl-vendor',
              ),
          ),
      );
      $counter = 0;
      foreach ($users as $user) {
          $form['user']['search_result']['result'][$counter]['id'] = array(
            '#title_display' => 'invisible',
            '#markup' => "".$user->id()."",
          );
          $form['user']['search_result']['result'][$counter]['name'] = array(
            '#title_display' => 'invisible',
            '#markup' => "".$user->getAccountName()."",
          );
          $form['user']['search_result']['result'][$counter]['email'] = array(
            '#title_display' => 'invisible',
            '#markup' => "".$user->getEmail()."",
          );
          $counter++;
      }
    }
    
    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state) {
    $vendor_id = $form_state->getValue('vendor_id');
    $author_id = $form_state->getValue('author_id');
    $status = $form_state->getValue('status');

    // Ensure name has at least 3 characters
    if (strlen($form_state->getValue('name')) < 3) {
      $form_state->setErrorByName('name', $this->t('Vendor Name must be at least 3 characters long.'));
    }

    //  Check email
    if (!filter_var($form_state->getValue('email'), FILTER_VALIDATE_EMAIL)) {
      $form_state->setErrorByName('email', $this->t('Please enter a valid email address.'));
    }

    // Simple regex for digits, spaces, dashes, and parentheses
    if (!preg_match('/^\+?[0-9\s\-\(\)]+$/', $form_state->getValue('phone'))) {
      $form_state->setErrorByName('phone', $this->t('Please enter a valid phone number.'));
    }

    // Validate vendor name is duplicate 
    $query = \Drupal::entityQuery('propertyvendor')
      ->condition('name', $form_state->getValue('name'))
      ->condition('id', $vendor_id, '!=')
      ->accessCheck(TRUE);

    $entity_ids = $query->execute();
    if (!empty($entity_ids)) {
      $form_state->setErrorByName('name', $this->t('Vendor Name already exists.'));
    }

    // This is a validation to make sure we validate if the author of the vendor is not assigned to another vendor.
    if ($status) {
      $obj_author = User::load($author_id);
      $author_vendor_id = $obj_author->field_vendor_id->value;

      if ($author_vendor_id != "" && $vendor_id != $author_vendor_id) {

        $vendor_entity = \Drupal::entityTypeManager()->getStorage('propertyvendor')->load($author_vendor_id);
        $name = "";

        if ($vendor_entity) {
          $name = $vendor_entity->get('name')->getString();
        }
        $form_state->setErrorByName('name', $this->t('User '.$obj_author->getEmail().' to already linked to Vendor '.$name));
      }
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Save Vendor info
    $vendor_id = $form_state->getValue('vendor_id');
    $status = $form_state->getValue('status');

    $vendor_entity = \Drupal::entityTypeManager()->getStorage('propertyvendor')->load($vendor_id);
    if ($vendor_entity == NULL) {
      $this->messenger()->addError($this->t('Vendor not found.'));
      return;
    }

    $arr_info = array(
      "email" => $form_state->getValue('email'),
      "phone" => $form_state->getValue('phone')
    );
    $vendor_entity->set('name', $form_state->getValue('name'));
    $vendor_entity->set('info', json_encode($arr_info));
    $vendor_entity->set('status', $form_state->getValue('status'));

    $vendor_entity->save();
    
    // Link the auother to the vendor
    if ($vendor_entity && $vendor_entity->hasField('uid') && $status) {
      $author_id = $vendor_entity->get('uid')->target_id;
      $user = User::load($author_id);

      if ($user) {
        if (!$user->hasRole('vendor')) {
          $user->addRole('vendor');
        }
        $user->set('field_vendor_id', $vendor_id);
        $user->save();
      }
    }

    $this->messenger()->addMessage($this->t('Vendor %name has been updated.', [
      '%name' => $form_state->getValue('name'),
    ]));
  }


    public function validateUserForm(array &$form, FormStateInterface $form_state) {
      $vendor_id = $form_state->getValue('vendor_id');
      // Check if status is active before assigning
      if (!$form_state->getValue('status')) {
        $form_state->setErrorByName('status', $this->t('Vendor status should be active before assigning users.'));
      }

      //  Check email
      if (!filter_var($form_state->getValue('useremail'), FILTER_VALIDATE_EMAIL)) {
        $form_state->setErrorByName('useremail', $this->t('Please enter a valid email address.'));
      }
      $uids = \Drupal::entityQuery('user')
        ->condition('mail', $form_state->getValue('useremail'))
        ->accessCheck(TRUE) // Set to FALSE if you're running backend scripts
        ->execute();

      if (empty($uids)) {
        $form_state->setErrorByName('useremail', $this->t($form_state->getValue('useremail') . ' does not exists in the system.'));
      } else {
          // Checking if user is alrady assigned a vendor or not.
          $uid = reset($uids);
          $user = User::load($uid);
          $user_vendor_id = $user->field_vendor_id->value;

          if($user_vendor_id != "") {
            if ($vendor_id == $user_vendor_id) {
              // User is already assigned same vendor.
              $form_state->setErrorByName('useremail', $this->t('User is already assigned to the current vendor.'));
            } else {
              // User is already assigned other vendor.
              $form_state->setErrorByName('useremail', $this->t('User is already assigned to the other vendor.'));
            }
          }
      }
    }

    public function submitUserForm(array &$form, FormStateInterface $form_state) {
      $vendor_id = $form_state->getValue('vendor_id');

      $uids = \Drupal::entityQuery('user')
        ->condition('mail', $form_state->getValue('useremail'))
        ->accessCheck(TRUE) // Set to FALSE if you're running backend scripts
        ->execute();
      $uid = reset($uids);
      $user = User::load($uid);

      if ($user) {
        if (!$user->hasRole('vendor')) {
          $user->addRole('vendor');
        }
        $user->set('field_vendor_id', $vendor_id);
        $user->save();
      }

      $this->messenger()->addMessage($this->t('User %name is now assigned to current vendor.', [
        '%name' => $form_state->getValue('useremail'),
      ]));

    }
}
