<?php
namespace Drupal\vendor_management\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\propertyvendor\Entity\Propertyvendor;

class VendorForm extends FormBase {

  public function getFormId() {
    return 'vendor_add_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Vendor Name'),
      '#required' => TRUE,
      '#maxlength' => 255,
      '#size' => 60,
      '#placeholder' => $this->t('Vendor Name'),
      '#attributes' => [
        'autocomplete' => 'off',
      ],
    ];

    $form['status'] = [
      '#type' => 'radios',
      '#title' => $this->t('Status'),
      '#options' => [
        1 => $this->t('Active'),
        0 => $this->t('Inactive'),
      ],
      '#default_value' => 1,
      '#required' => TRUE,
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
      '#value' => $this->t('Add Vendor'),
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
    $this->messenger()->addMessage($this->t('Vendor %name has been added.', [
      '%name' => $form_state->getValue('name'),
    ]));

    $arr_info = array(
      "email" => $form_state->getValue('email'),
      "phone" => $form_state->getValue('phone')
    );

    Propertyvendor::create([
      'name' => $form_state->getValue('name'),
      'info' => json_encode($arr_info),
      'status' => $form_state->getValue('status'),
      'uid' => \Drupal::currentUser()->id(),
    ])->save();
  }
}
