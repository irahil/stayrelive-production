<?php

namespace Drupal\sr_mapping\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

class ImportDataForm extends FormBase {

  /**
   * Build the simple form.
   *
   * A build form method constructs an array that defines how markup and
   * other form elements are included in an HTML form.
   *
   * @param array $form
   *   Default form array structure.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Object containing current form state.
   *
   * @return array
   *   The render array defining the elements of the form.
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    /*$validators = array(
    'file_validate_extensions' => array('json'),    // multiple extensions possible
    'file_validate_size' => array(1024*1280*800),
    );
    $form['sr_file'] = array(
    '#type' => 'managed_file',
    '#name' => 'sr_file',
    '#title' => t('File *'),
    '#upload_validators' => $validators,
    '#multiple' => FALSE,
    '#required' => TRUE,
    '#upload_location' => 'temporary://manual/',
    );*/
    
    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = array(
    '#type' => 'submit',
    '#value' => $this->t('Import Data'),
    '#button_type' => 'primary',
    );
    
    return $form;  
  }

  public function getFormId() {
    return 'sr_mapping_imoprt_data';
  }

  public function validateForm(array &$form, FormStateInterface $form_state) {
    // upload_validators handles the necessary validations currently
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $return = \Drupal::service('import.services')->importPropertyData();
    if($return['status'])
      $this->messenger()->addMessage($this->t($return['message']));
    else
      $this->messenger()->addError($this->t($return['message']));
  }

}
