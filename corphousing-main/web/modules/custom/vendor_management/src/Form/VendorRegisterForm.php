<?php
namespace Drupal\vendor_management\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\propertyvendor\Entity\Propertyvendor;
use Drupal\Core\Session\AccountInterface;
use Drupal\user\Entity\User;

class VendorRegisterForm extends FormBase {

  public function getFormId() {
    return 'vendor_register_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    if ($form_state->get('submitted')) {
      $form['message'] = [
        '#markup' => $this->t('The request has been send for approval. We will notify you once it is approved.'),
      ];
      return $form;
    }

    // Get current user
    $current_user = \Drupal::currentUser();

    // 🔹 If anonymous user → just skip vendor check and show normal form
    if ($current_user->isAnonymous()) {
      // Just continue and show full form
    } else {
      // 🔹 If logged in → Check vendor field
      $current_uid = $current_user->id();
      $user = User::load($current_uid);

      if ($user->hasField('field_vendor_id') && !$user->get('field_vendor_id')->isEmpty()) {

        $vendor_id = $user->get('field_vendor_id')->value;

        $vendor_entity = \Drupal::entityTypeManager()
          ->getStorage('propertyvendor')
          ->load($vendor_id);

        if ($vendor_entity) {
          $name = $vendor_entity->get('name')->getString();

          $form['message'] = [
            '#markup' => $this->t('You are already registered with vendor "@name".', ['@name' => $name]),
          ];
          return $form;
        }
      }
    }

    $form['login_link'] = [
      '#markup' => '<div class="login-link-wrapper">' . 
        $this->t('If you are already a registered user please ') . 
        '<a href="/user/login">' . $this->t('click here') . '</a>' . 
        $this->t(' to login.') . 
        '</div>',
      '#weight' => -10,
    ];

    $form['name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Vendor Name'),
      '#required' => TRUE,
      '#placeholder' => $this->t('Enter Vendor name'),
      '#attributes' => [
        'maxlength' => 100,  // Optional
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
    ];

    $form['phone'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Vendor phone number'),
      '#required' => TRUE,
      '#placeholder' => $this->t('Enter Vendor phone number'),
      '#attributes' => [
        'maxlength' => 100,  // Optional
      ],
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
    ];

    return $form;
  }


  public function validateForm(array &$form, FormStateInterface $form_state) {
    // Ensure name has at least 3 characters
    if (strlen($form_state->getValue('name')) < 3) {
      $form_state->setErrorByName('name', $this->t('Vendor Name must be at least 3 characters long.'));
    }

    //  Check email
    $email = $form_state->getValue('email');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
      $form_state->setErrorByName('email', $this->t('Please enter a valid email address.'));
    }

    // Simple regex for digits, spaces, dashes, and parentheses
    if (!preg_match('/^\+?[0-9\s\-\(\)]+$/', $form_state->getValue('phone'))) {
      $form_state->setErrorByName('phone', $this->t('Please enter a valid phone number.'));
    }

    // Validate vendor name is duplicate 
    $query = \Drupal::entityQuery('propertyvendor')
      ->condition('name', $form_state->getValue('name'))
      ->accessCheck(TRUE);

    $entity_ids = $query->execute();
    if (!empty($entity_ids)) {
      $form_state->setErrorByName('name', $this->t('Vendor Name already exists.'));
    }

  }

  public function submitForm(array &$form, FormStateInterface $form_state) {

    $arr_info = array(
      "email" => $form_state->getValue('email'),
      "phone" => $form_state->getValue('phone')
    );
    Propertyvendor::create([
      'name' => $form_state->getValue('name'),
      'info' => json_encode($arr_info),
      'status' => false,
    ])->save();
    
    // Prevent the form from rendering again
    $form_state->setRebuild(TRUE);
    $form_state->set('submitted', TRUE);
  }
}
