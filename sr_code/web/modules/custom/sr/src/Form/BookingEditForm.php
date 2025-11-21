<?php
namespace Drupal\sr\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\Entity\Node;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\user\Entity\User;
use Drupal\Core\Entity\EntityInterface;
use Drupal\views\Views;
use Drupal\common_utilities\Utilities\commonUtil;
use Drupal\sr\Controller\SrController;
use Drupal\booking\Entity\Booking;
use Symfony\Component\HttpFoundation;
use Drupal\Core\Datetime\DrupalDateTime;

class BookingEditForm extends FormBase {

  var $global_form_param = array(
    'name' => '', 'email' => '', 'phone_code' => '', 'phone_number' => '', 'adults' => '', 'kids' => '',
    'currency_code' => '', 'price' => '', 'property_id' => '', 'remarks' => '',
    'status' => '', 'date_range' => '',
  );

  public function getFormId() {
    return 'booking edit';
  }

  public function buildForm(array $form, FormStateInterface $form_state, $booking_id = 0) {
    $form_param = $this->global_form_param;
    $current_user = \Drupal::currentUser();
    $current_roles = $current_user->getRoles();
    $current_uid = $current_user->id();
    $flag_is_admin = false;

    $form['error'] = array(
      '#title_display' => 'invisible',
      '#markup' => "Sorry, something went wrong. Please try again later.",
      '#prefix' => '<div class="form-error">',
      '#suffix' => '</div>',
    );

    if (in_array('sr_admin', $current_roles) || in_array('administrator', $current_roles)) {
      $flag_is_admin = true;
    }
    if ($booking_id <= 0 || !$flag_is_admin) {
      return $form;
    }

    # Load from node id
    $booking = \Drupal::entityTypeManager()->getStorage('booking')->load($booking_id);
    if ($booking == NULL) {
      return $form;
    }

    foreach ($form_param as $key_param => $val_param) {
      if ($key_param == 'date_range') {
        $from_date = $booking->get('field_from_date')->getString();
        $to_date = $booking->get('field_to_date')->getString();
        $form_param[$key_param] = $from_date . " to " . $to_date;
      } else {
        $form_param[$key_param] = $booking->get('field_' . $key_param)->getString();
      }
    }
//print_r($form_param);exit;
    if ($form_param['property_id'] <= 0) {
      return $form;
    }

    # Load from node id
    $node_property = Node::load($form_param['property_id']);
    if ($node_property == NULL || !$node_property->isPublished()) {
      return $form;
    }

    $arr_image = unserialize($node_property->get('field_media')->getString());
    if(isset($arr_image[0]['url'])) {
      $image = "<img src='".$arr_image[0]['url']."' width='200' hight='200'/>";
    }
    $form['property_summary']['address'] = ['#markup' => $node_property->get('field_display_address')->getString()];
    $form['property_summary']['image'] = ['#markup' => $image];
    $form['property_summary']['url'] = ['#markup' => $node_property->toUrl()->toString()];
    $form['property_summary']['bedrooms'] = ['#markup' => $node_property->get('field_total_bedrooms')->getString()];
    $form['property_summary']['bathrooms'] = ['#markup' => $node_property->get('field_total_bathrooms')->getString()];

    unset($form['error']);
    //$property_alias = \Drupal::service('path_alias.manager')->getAliasByPath('/node/'.$form_param['property_id']);

    $arr_currency_list = commonUtil::get_term_list('currency');

    $arr_status_list = array(
      'pending_booking' => 'Pending Booking',
      'confirmed' => 'Confirmed Booking',
      'canceled_booking' => 'Canceled Booking',
    );

    $from_date = new DrupalDateTime($from_date);
    $from_date = date('Y-m-d', strtotime($from_date));

    $to_date = new DrupalDateTime($to_date);
    $to_date = date('Y-m-d', strtotime($to_date));

    $arr_param = [
      'date_range' => $form_param['date_range'],
      'adult' => $form_param['adults'],
      'kid' => $form_param['kids'],
      'pid' => $form_param['property_id'],
    ];
    $arr_block_param = \Drupal::service('sr.services')->generatePriceInfo($form_param['property_id'], $arr_param);
    $arr_block_param['final_price'] = ($arr_block_param['final_price'] != '') ? commonUtil::formatCurrency($arr_block_param['final_price']) : '';

    // Convert date
    if ($form_param['date_range'] != '') {
      list($from_date, $to_date) = explode(" to ", $form_param['date_range']);
      list($from_date, $time) = explode("T", $from_date);
      list($to_date, $time) = explode("T", $to_date);
      $form_param['date_range'] = $from_date . " to " . $to_date;
    }

    $form['#attached']['library'][] = 'sr/sr_lib';

    $form['booking_id'] = [
      '#type' => 'hidden',
      '#attributes' => array(
        'readonly' => 'readonly',
      ),
      '#default_value' => $booking_id,
      '#required' => TRUE,
    ];

    $form['pid'] = [
      '#type' => 'hidden',
      '#attributes' => array(
        'readonly' => 'readonly',
      ),
      '#default_value' => $form_param['property_id'],
      '#required' => TRUE,
    ];

    $form['currency_code'] = [
      '#type' => 'hidden',
      '#attributes' => array(
        'readonly' => 'readonly',
      ),
      '#default_value' => $form_param['currency_code'],
      '#required' => TRUE,
    ];

    $form['price'] = [
      '#type' => 'hidden',
      '#attributes' => array(
        'readonly' => 'readonly',
      ),
      '#default_value' => $form_param['price'],
      '#required' => TRUE,
    ];

    $form['hidden_status'] = [
      '#type' => 'hidden',
      '#attributes' => array(
        'readonly' => 'readonly',
      ),
      '#default_value' => $form_param['status'],
      '#required' => TRUE,
    ];

    $form['booking'] = array(
      '#type' => 'fieldset',
      '#title' => $this
        ->t('Booking Information'),
      '#attributes' => array(
          'class' => array('SectionContainer'),
      )
    );

    $form['booking']['status'] = [
      '#type' => 'select',
      '#title' => $this->t('Status'),
      '#options' => $arr_status_list,
      '#default_value' => $form_param['status'],
    ];

    $form['booking']['name'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Name'),
      '#required' => TRUE,
      '#default_value' => $form_param['name'],
    );

    $form['booking']['email'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Email'),
      '#required' => TRUE,
      '#default_value' => $form_param['email'],
    );

    $form['booking']['phone_code'] = array(
      '#type' => 'select',
      '#required' => TRUE,
      '#title' => $this->t('Country Code'),
      '#options' => array('' => 'Select') + commonUtil::fn_get_phone_code(),
      '#default_value' => $form_param['phone_code'],
    );

    $form['booking']['phone_number'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Phone Number'),
      '#required' => TRUE,
      '#default_value' => $form_param['phone_number'],
    );
    $selected_currency = (isset($arr_currency_list[$form_param['currency_code']])) ? $arr_currency_list[$form_param['currency_code']] : 'NA'."";
    $form_param['price'] = ($form_param['price']!= '') ? commonUtil::formatCurrency($form_param['price']) : '';

    $form['booking']['price_info'] = [
      '#type' => 'item',
      '#title' => $this->t('Total Price'),
      '#markup' => $selected_currency ." ".$form_param['price'] ." for total number of duration in days " . $arr_block_param['days'] . '.',
    ];

    $form['booking']['date'] = array(
      '#type' => 'fieldset',
      '#title' => $this
        ->t('Date'),
      '#attributes' => array(
          'class' => array('SectionContainer'),
      )
    );

    $form['booking']['date']['date_range'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Date Range'),
      '#date_date_format' => 'Y-m-d',
      '#default_value' => $form_param['date_range'],
      '#attributes' => array(
        'id' => array('flatpickr_date_range'),
        'placeholder' => array('Dates'),
      )
    ];

    $form['booking']['occupants'] = array(
      '#type' => 'fieldset',
      '#title' => $this
        ->t('Occupants'),
      '#attributes' => array(
          'class' => array('SectionContainer'),
      )
    );

    $form['booking']['occupants']['adults'] = array(
      '#type' => 'number',
      '#title' => $this->t('Adult Count'),
      '#default_value' => $form_param['adults'],
      '#required' => TRUE,
    );

    $form['booking']['occupants']['kids'] = array(
      '#type' => 'number',
      '#title' => $this->t('Kids Count'),
      '#default_value' => $form_param['kids'],
      '#required' => TRUE,
    );

    $form['booking']['remarks'] = array(
      '#type' => 'item',
      '#title' => $this->t('Additional Information'),
      '#markup' => $form_param['remarks']
    );

    $form['booking']['actions'] = [
      '#type' => 'actions',
    ];

    $form['booking']['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Update'),
      '#weight' => 110,
    ];

    $form['#theme'] = 'sr_booking_edit_form';

    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state) {
    $email = $form_state->getValue('email');
    if ($email !== '' && !\Drupal::service('email.validator')->isValid($email)) {
      $form_state->setErrorByName('email', t('The email address %mail is not valid.', ['%mail' => $email,]));
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $form_param = $this->global_form_param;

    $booking_id = ($form_state->getValue('booking_id'));

    $booking_entity = \Drupal::entityTypeManager()->getStorage('booking')->load($booking_id);

    foreach ($form_param as $key_param => $val_param) {
      if ($form_state->getValue($key_param) != NULL) {
        if ($key_param == 'date_range') {
          $date_range = trim($form_state->getValue($key_param));
          list($from_date, $to_date) = \Drupal::service('sr.services')->convertDateRange($date_range);
          $from_date = new DrupalDateTime($from_date);
          $to_date = new DrupalDateTime($to_date);
          $from_date = date('Y-m-d\TH:i:s', strtotime($from_date));
          $to_date = date('Y-m-d\TH:i:s', strtotime($to_date));
          $booking_entity->set('field_from_date', $from_date);
          $booking_entity->set('field_to_date', $to_date);
        } else {
          $form_param[$key_param] = trim($form_state->getValue($key_param));
          $booking_entity->set('field_'.$key_param, $form_param[$key_param]);
        }
      }
    }

    $booking_entity->save();
    \Drupal::messenger()->addStatus("Booking saved successfully!\n");

    // Send email if status has changed.
    if ($form_state->getValue('hidden_status') != $form_state->getValue('status')) {
      \Drupal::service('sr.services')->emailBooking($booking_id);
    }
  }
}
