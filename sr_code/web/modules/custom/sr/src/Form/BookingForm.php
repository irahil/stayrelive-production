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


class BookingForm extends FormBase {
  var $arr_request_param = array(
    'country_code' => '', 'pid' => '',
    'price_min' => '', 'price_max' => '',
    'bathroom' => '', 'bedroom' => '',
    'date_range' => '', 'town_city' => '',
    'adult' => '', 'kid' => '',
    'amenities' => '', 'sort' => '',
  );

  public function getFormId() {
    return 'booking';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['error'] = array(
      '#title_display' => 'invisible',
      '#markup' => "Sorry, something went wrong. Please try again later.",
      '#prefix' => '<div class="form-error">',
      '#suffix' => '</div>',
    );

    $pid = \Drupal::request()->query->get('pid');
    if ($pid <= 0) {
      return $form;
    }

    # Load from node id
    $node = Node::load($pid);
    if ($node == NULL || !$node->isPublished()) {
      return $form;
    }

    unset($form['error']);

    $current_user = \Drupal\user\Entity\User::load(\Drupal::currentUser()->id());

    $name = '';
    $email = '';
    $phone_number = '';
    if ($current_user) {
      $name = $current_user->get('field_first_name')->value . " " . $current_user->get('field_last_name')->value;
      $email = $current_user->get('mail')->value;
      $phone_number = $current_user->get('field_phone_number')->value;
      $phone_code = $current_user->get('field_phone_code')->value;
    }

    $form_param = array();

    foreach ($this->arr_request_param as $key_param => $val_param) {
      $query_value = \Drupal::request()->query->get($key_param);
      if ($query_value != '') {
        $form_param[$key_param] = urldecode(trim($query_value));
      } else {
        $form_param[$key_param] = '';
      }
    }

    $arr_block_param = \Drupal::service('sr.services')->generatePriceInfo($pid, $form_param);
    $arr_block_param['final_price'] = ($arr_block_param['final_price'] != '') ? commonUtil::formatCurrency($arr_block_param['final_price']) : '';

    $form['#attached']['library'][] = 'sr/sr_lib';
    $form['#attached']['library'][] = 'sr/sr_occupants';
    $form['#attached']['library'][] = 'sr/sr_autocomplete_lib';

    $form['property_id'] = [
      '#type' => 'hidden',
      '#attributes' => array(
        'readonly' => 'readonly',
      ),
      '#default_value' => $form_param['pid'],
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

    $form['booking']['name'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Name'),
      '#required' => TRUE,
      '#default_value' => $name,
      '#attributes' => array(
        'placeholder' => array('Name'),
      )
    );

    $form['booking']['email'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Email'),
      '#required' => TRUE,
      '#default_value' => $email,
      '#attributes' => array(
        'placeholder' => array('Email'),
      )
    );

    $form['booking']['phone_code'] = array(
      '#type' => 'select',
      '#required' => TRUE,
      '#title' => $this->t('Country Code'),
      '#options' => array('' => 'Select') + commonUtil::fn_get_phone_code(),
      '#default_value' => $phone_code,
    );

    $form['booking']['phone_number'] = array(
      '#type' => 'number',
      '#title' => $this->t('Phone Number'),
      '#required' => TRUE,
      '#default_value' => $phone_number,
      '#attributes' => array(
        'placeholder' => array('Phone Number'),
      )
    );

    $form_param['date_range'] = (isset($form_param['date_range'])) ? $form_param['date_range'] : '';
    list($from_date, $to_date) = \Drupal::service('sr.services')->convertDateRange($form_param['date_range']);
    $form_param['date_range'] = $from_date . ' to ' .$to_date;

    $form['booking']['date_range'] = [
      '#type' => 'hidden',
      '#title' => $this->t('Date Range'),
      '#date_date_format' => 'Y-m-d',
      '#default_value' => $form_param['date_range'],
    ];

    $form['booking']['adults'] = array(
      '#type' => 'select',
      '#title' => $this->t('Adult Count'),
      '#options' => [
        1 => '1',
        2 => '2',
        3 => '3',
        4 => '4',
        5 => '5',
      ],
      '#default_value' => $form_param['adult'],
      '#required' => TRUE,
    );

    $form['booking']['kids'] = array(
      '#type' => 'select',
      '#title' => $this->t('Kids Count'),
      '#options' => [
        0 => '0',
        1 => '1',
        2 => '2',
        3 => '3',
        4 => '4',
        5 => '5',
      ],
      '#default_value' => $form_param['kid'],
      '#required' => TRUE,
    );

    $form['booking']['remarks'] = array(
      '#type' => 'textarea',
      '#title' => $this->t('Additional Information'),
    );

    $form['booking']['actions'] = [
      '#type' => 'actions',
    ];

    $form['booking']['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Continue Booking'),
      '#weight' => 110,
    ];

    $arr_image = unserialize($node->get('field_media')->getString());
    if(isset($arr_image[0]['url'])) {
      $image = "<img src='".$arr_image[0]['url']."' width='200' hight='200'/>";
    }

    $form['booking']['price_markup']['currency'] = ['#markup' => $arr_block_param['currency_code']];
    $form['booking']['price_markup']['final_price'] = ['#markup' => $arr_block_param['final_price']];
    $form['booking']['price_markup']['days'] = ['#markup' => $arr_block_param['days']];

    $form['booking']['dates_markup']['from'] = ['#markup' => date("jS F y", strtotime($from_date))];
    $form['booking']['dates_markup']['to'] = ['#markup' => date("jS F y", strtotime($to_date))];

    $form['booking']['occupants_markup']['adults'] = ['#markup' => $form_param['adult']];
    $form['booking']['occupants_markup']['kids'] = ['#markup' => $form_param['kid']];

    $form['property_summary']['address'] = ['#markup' => $node->get('field_display_address')->getString()];
    $form['property_summary']['image'] = ['#markup' => $image];
    $form['property_summary']['url'] = ['#markup' => $node->toUrl()->toString()];
    $form['property_summary']['bedrooms'] = ['#markup' => $node->get('field_total_bedrooms')->getString()];
    $form['property_summary']['bathrooms'] = ['#markup' => $node->get('field_total_bathrooms')->getString()];

/*
    $block = \Drupal\block\Entity\Block::load('srdesign_srpropertypriceblock');
    if (!$block) {
      // Since in PROD the block machine name is different.
      $block = \Drupal\block\Entity\Block::load('srdesign_srpropertypriceblockbasic');
    }

    if ($block) {
      $plugin = $block->getPlugin();
      $build = $plugin->build();
      $build['#weight'] = 4;
      $form['price_block'] = $build;
    }
*/
    $form['#theme'] = 'sr_booking_form';

    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state) {
    $email = $form_state->getValue('email');
    if ($email !== '' && !\Drupal::service('email.validator')->isValid($email)) {
      $form_state->setErrorByName('email', t('The email address %mail is not valid.', ['%mail' => $email,]));
    }

    if (!filter_var($form_state->getValue('phone_number'), FILTER_SANITIZE_NUMBER_INT)) {
      $form_state->setErrorByName('phone_number', t('The Phone number %phone is not valid.', ['%phone' => $form_state->getValue('phone_number'),]));
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $arr_field_param = array(
      'name' => '',
      'email' => '',
      'phone_code' => '',
      'phone_number' => '',
      'date_range' => '',
      'adults' => '',
      'kids' => '',
      'remarks' => '',
      'ip' => '',
      'property_id' => '',
    );

    // Get currency id
    $currency_name = \Drupal::service('currency_layer_integration.services')->CFgetUserSessionCurrency();
    if ($currency_name != '') {
      $terms = \Drupal::entityTypeManager()
      ->getStorage('taxonomy_term')
      ->loadByProperties([
              'vid' => 'currency',
              'name' => $currency_name,
          ]);
      foreach ($terms as $term) {
        $currency_code_id = $term->id();
      }
    }
    $arr_booking_data = array();
    $arr_booking_data['status'] = true;
    $arr_booking_data['field_status'] = 'pending_booking';
    $arr_booking_data['type'] = 'booking';
    $arr_booking_data['uid'] = \Drupal::currentUser()->id();
    $arr_booking_data['field_currency_code'] = $currency_code_id;
    $arr_booking_data['field_ip'] = \Drupal::request()->getClientIp();

    foreach ($arr_field_param as $key_param => $val_param) {
      if ($form_state->getValue($key_param) != NULL) {
        if ($key_param == 'date_range') {
          $date_range = trim($form_state->getValue($key_param));
          list($from_date, $to_date) = \Drupal::service('sr.services')->convertDateRange($date_range);
          $from_date = new DrupalDateTime($from_date);
          $to_date = new DrupalDateTime($to_date);
          $arr_booking_data['field_from_date'] = date('Y-m-d\TH:i:s', strtotime($from_date));
          $arr_booking_data['field_to_date'] = date('Y-m-d\TH:i:s', strtotime($to_date));
        } else {
          $arr_booking_data['field_'.$key_param] = trim($form_state->getValue($key_param));
        }
      }
    }

    $arr_param = [
      'date_range' => $date_range,
      'adult' => $arr_booking_data['field_adults'],
      'kid' => $arr_booking_data['field_kids'],
      'pid' => $arr_booking_data['field_property_id'],
    ];
    $arr_block_param = \Drupal::service('sr.services')->generatePriceInfo($arr_booking_data['field_property_id'], $arr_param);

    $arr_booking_data['field_price'] = $arr_block_param['final_price'];

    $booking_entity = Booking::create($arr_booking_data);
    $booking_entity->save();
    $booking_id = $booking_entity->id();

    \Drupal::messenger()->addStatus("Your booking request has been received. We will get back to you within 24 hours.\n");

    \Drupal::service('sr.services')->emailBooking($booking_id);

    foreach ($this->arr_request_param as $key_param => $val_param) {
      $query_value = \Drupal::request()->query->get($key_param);
      if ($query_value != '') {
        $form_param[$key_param] = urldecode(trim($query_value));
      } else {
        $form_param[$key_param] = '';
      }
    }
    unset($form_param['pid']);
    $url = Url::fromRoute('sr.search', $form_param, ['absolute' => TRUE])->toString();
    commonUtil::my_goto($url);

    //$url = Url::fromRoute('sr.booking.confirmation', [], ['absolute' => TRUE])->toString();
    //commonUtil::my_goto($url);
  }

}
