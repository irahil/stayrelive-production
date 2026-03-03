<?php

namespace Drupal\inventory_management\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\file\Entity\File;
use Drupal\propertyfile\Entity\Propertyfile;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\vendor_management\vendor;
class CsvUploadForm extends FormBase {

  public function getFormId() {
    return 'inventory_upload_form';
  }
  public function buildForm(array $form, FormStateInterface $form_state) {

    $arr_admin_roles = array('administrator', 'site_admin');
    $user = \Drupal::currentUser();
    $roles = $user->getRoles();
    if (!array_intersect($roles, $arr_admin_roles)) {
        $form['error'] = array(
        '#title_display' => 'invisible',
        '#markup' => "Sorry, you do not have permission to access this page.",
        '#prefix' => '<div class="form-error">',
        '#suffix' => '</div>',
        );
        return $form;
    }

    $form['back_link'] = [
      '#type' => 'link',
      '#title' => 'Back',
      '#weight' => -10,
      '#url' => \Drupal\Core\Url::fromRoute('inventory_management.file_list'),
    ];

    $form['upload_file'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Upload a file'),
      '#upload_location' => 'private://property_upload/tmp',
      '#required' => TRUE,
      '#upload_validators' => [
        'file_validate_extensions' => ['csv'],
        'file_validate_size' => [25600000],
      ],
    ];

    $obj_vendor = new vendor(); 
    $arg_data = array('query' => array());
    $arr_vendor = $obj_vendor->searchVendor($arg_data, 5000);
    $arr_vendor_list = array('' => "Select Vendor");
    if (is_array($arr_vendor['search_result']) && count($arr_vendor['search_result']) > 0) {
        foreach ($arr_vendor['search_result'] as $val_vendor) {
            $arr_vendor_list[$val_vendor['id']] = $val_vendor['name'];
        }
        
        $form['vendor_id'] = [
          '#type' => 'select',
          '#title' => $this->t('Vendor'),
          '#options' => $arr_vendor_list,
          '#default_value' => '',
          '#required' => TRUE,
        ];
    }


    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Upload and Save'),
    ];

    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state) {

  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $vendor_id = $form_state->getValue('vendor_id');

    $fid = $form_state->getValue('upload_file')[0] ?? NULL;
    if ($fid && $vendor_id) {
      $file = File::load($fid);
      $directory_path = 'private://property_upload/' . $vendor_id . '/';

      \Drupal::service('file_system')->prepareDirectory(
        $directory_path,
        FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS
      );

      // Rename the file to avoid conflicts.
      $destination = $directory_path . \Drupal::service('file_system')->basename($file->getFileUri());

      $file_system = \Drupal::service('file_system');

      // Move and rename if file already exists
      $new_uri = $file_system->move($file->getFileUri(), $destination, FileSystemInterface::EXISTS_RENAME);
      $file->setFileUri($new_uri);
      $file->setPermanent();
      $file->save();

      $current_user = \Drupal::currentUser();
      $uid = $current_user->id();

      $entity = Propertyfile::create([
        'file' => ['target_id' => $file->id()],
        'uid' => $uid,
        'uploaded_date' => date('Y-m-d\TH:i:s'),
        'file_status' => 'uploaded',
        'vendor_id' => $vendor_id,
        'label' => 'File '. date('Y-m-d\TH:i:s'),
      ]);
      $entity->save();
      \Drupal::messenger()->addMessage($this->t('File uploaded and saved.'));
      $form_state->setRedirect('inventory_management.file_list');
    }
    else {
      \Drupal::messenger()->addError($this->t('File upload failed.'));
    }
  }
}
