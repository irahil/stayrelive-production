<?php

namespace Drupal\sr_quotation\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\file\Entity\File;
use Drupal\Core\Database\Database;

class QuotationEditForm extends QuotationForm {

  protected $id;

  public function getFormId() {
    return 'sr_quotation_edit_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state, $id = NULL) {
    $this->id = $id;

    // Load data from database.
    $record = Database::getConnection()
      ->select('sr_quotation', 'q')
      ->fields('q')
      ->condition('id', $id)
      ->execute()
      ->fetchAssoc();

    if (!$record) {
      \Drupal::messenger()->addError($this->t('Quotation not found.'));
      return $form;
    }

    // Build base form (inherited from QuotationForm)
    $form = parent::buildForm($form, $form_state);

    // Prepopulate values.
    $form['booking_info']['dates']['check_in_date']['#default_value'] = $record['checkin_date'];
    $form['booking_info']['dates']['check_out_date']['#default_value'] = $record['checkout_date'];
    $form['booking_info']['times']['check_in_time']['#default_value'] = $record['checkin_time'];
    $form['booking_info']['times']['check_out_time']['#default_value'] = $record['checkout_time'];
    $form['booking_info']['nights']['#default_value'] = $record['nights'];
    $form['property_info']['unit_type']['#default_value'] = $record['unit_type'];
    $form['property_info']['room_details']['room_type']['#default_value'] = $record['room_type'];
    $form['property_info']['room_details']['bathrooms']['#default_value'] = $record['bathrooms'];
    $form['property_info']['apartment_size']['#default_value'] = $record['apartment_size'];
    $form['property_info']['occupancy']['adults']['#default_value'] = $record['adults'];
    $form['property_info']['occupancy']['kids']['#default_value'] = $record['kids'];
    $form['property_info']['location_details']['location']['#default_value'] = $record['location'];
    $form['property_info']['location_details']['distance']['#default_value'] = $record['distance'];
    $form['property_info']['map_link']['#default_value'] = $record['map_link'];
    $form['property_info']['description']['#default_value'] = $record['description'];
    $form['financials']['quote']['#default_value'] = $record['quote'];
    $form['financials']['taxes']['#default_value'] = $record['taxes'];
    $form['financials']['fx_rate']['#default_value'] = $record['fx_rate'];
    $form['extras']['cancellation_policy']['#default_value'] = $record['cancellation_policy'];
    $form['extras']['rules']['#default_value'] = $record['rules'];
    $form['extras']['note']['#default_value'] = $record['note'];

    // Handle amenities (comma-separated values)
    if (!empty($record['amenities'])) {
      $amenities = explode(', ', $record['amenities']);
      $form['extras']['amenities']['#default_value'] = array_combine($amenities, $amenities);
    }

    // Handle images
    if (!empty($record['images'])) {
      $image_fids = explode(',', $record['images']);
      $form['images']['apartment_images']['#default_value'] = $image_fids;
    }

    // Change button text
    $form['actions']['submit']['#value'] = $this->t('Update Quotation');

    // Hidden ID field
    $form['id'] = [
      '#type' => 'hidden',
      '#value' => $id,
    ];

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    $id = $values['id'];

    // Handle files
    $image_fids = [];
    if (!empty($values['apartment_images'])) {
      foreach ($values['apartment_images'] as $fid) {
        if ($fid) {
          $file = File::load($fid);
          if ($file) {
            $file->setPermanent();
            $file->save();
            $image_fids[] = $fid;
          }
        }
      }
    }

    // Prepare amenities
    $amenities = '';
    if (is_array($values['amenities'])) {
      $selected = array_filter($values['amenities']);
      $amenities = implode(', ', $selected);
    }

    // Update database
    Database::getConnection()
      ->update('sr_quotation')
      ->fields([
        'checkin_date' => $values['check_in_date'],
        'checkout_date' => $values['check_out_date'],
        'checkin_time' => $values['check_in_time'],
        'checkout_time' => $values['check_out_time'],
        'nights' => $values['nights'],
        'unit_type' => $values['unit_type'],
        'apartment_size' => $values['apartment_size'],
        'room_type' => $values['room_type'],
        'bathrooms' => $values['bathrooms'],
        'adults' => $values['adults'],
        'kids' => $values['kids'],
        'location' => $values['location'],
        'distance' => $values['distance'],
        'map_link' => $values['map_link'],
        'description' => $values['description'],
        'quote' => $values['quote'],
        'taxes' => $values['taxes'],
        'fx_rate' => $values['fx_rate'],
        'amenities' => $amenities,
        'cancellation_policy' => $values['cancellation_policy'],
        'rules' => $values['rules'],
        'note' => $values['note'],
        'images' => implode(',', $image_fids),
      ])
      ->condition('id', $id)
      ->execute();

    \Drupal::messenger()->addMessage($this->t('Quotation updated successfully.'));
    $form_state->setRedirect('sr_quotation.list');
  }
}
