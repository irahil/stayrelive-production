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
use Drupal\sr\Rategain;
use Drupal\booking\Entity\Booking;
use Symfony\Component\HttpFoundation;
use Drupal\Core\Datetime\DrupalDateTime;


class BookingForm extends FormBase {
  /**
   * Days-to-arrival cutoff for RateGain bookings.
   *
   * At or below this many days to arrival, payment is forced immediately at
   * booking time. Above it, the booking is reserved without payment; if it's
   * still unpaid once arrival drops to this cutoff, sr_paytabs_cron() (see
   * sr_paytabs.module) auto-cancels it.
   */
  const RATEGAIN_PAYMENT_DUE_THRESHOLD_DAYS = 30;

  /**
   * Temporary kill switch for RateGain bookings while the production API
   * cutover (destination sync, live pricing/precheck) is still being
   * verified — flip to FALSE once confirmed safe to take real bookings.
   */
  const RATEGAIN_BOOKING_DISABLED = TRUE;

  var $arr_request_param = array(
    'country_code' => '', 'pid' => '',
    'price_min' => '', 'price_max' => '',
    'bathroom' => '', 'bedroom' => '',
    'date_range' => '', 'town_city' => '',
    'adult' => '', 'kid' => '', 'child_age' => '',
    'amenities' => '', 'sort' => '',
    'rg_price' => '', 'rg_currency' => '', 'rg_cancellation' => '',
    'rg_room_selection' => '',
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

    $property_source = $node->get('field_property_source')->getString();

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

    // Carry the RateGain room's selected rate through to submitForm(), so the
    // saved booking price matches the room the guest picked rather than the
    // property's static field_price.
    $form['booking']['rg_price'] = [
      '#type' => 'hidden',
      '#default_value' => $form_param['rg_price'],
    ];

    $form['booking']['rg_currency'] = [
      '#type' => 'hidden',
      '#default_value' => $form_param['rg_currency'],
    ];

    // Carries the rate/room key(s) picked on the property page through to
    // PreCheckReservation in validateForm() — one JSON-encoded entry per
    // room needed (see Rategain::buildRoomRateList() / APIPriceForm.php).
    $form['booking']['rg_room_selection'] = [
      '#type' => 'hidden',
      '#default_value' => $form_param['rg_room_selection'],
    ];

    if ($property_source === 'rategain') {
      // RateGain's Guest object requires an address for PreCheckReservation
      // (and later CommitReservation) — nothing else in this booking flow
      // collects one today, so it's gathered here, only for rategain
      // properties.
      $form['booking']['address_line1'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Address'),
        '#required' => TRUE,
        '#attributes' => ['placeholder' => 'Street address'],
      ];

      $form['booking']['address_city'] = [
        '#type' => 'textfield',
        '#title' => $this->t('City'),
        '#required' => TRUE,
      ];

      $form['booking']['address_state_code'] = [
        '#type' => 'textfield',
        '#title' => $this->t('State Code'),
        '#required' => TRUE,
        '#attributes' => ['placeholder' => 'e.g. NY'],
      ];

      $form['booking']['address_country_code'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Country Code'),
        '#required' => TRUE,
        '#attributes' => ['placeholder' => 'e.g. US', 'maxlength' => 2],
      ];

      $form['booking']['address_postal_code'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Postal Code'),
        '#required' => TRUE,
      ];
    }

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

    $submit_label = $this->t('Continue Booking');
    if ($property_source === 'plumguide') {
      $submit_label = $this->t('Proceed to Payment');
    }
    elseif ($property_source === 'rategain') {
      $days_to_arrival = (int) floor((strtotime($from_date) - strtotime('today')) / 86400);
      $submit_label = ($days_to_arrival > self::RATEGAIN_PAYMENT_DUE_THRESHOLD_DAYS)
        ? $this->t('Reserve Booking')
        : $this->t('Proceed to Payment');
    }

    $booking_disabled = ($property_source === 'rategain' && self::RATEGAIN_BOOKING_DISABLED);

    $form['booking']['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $submit_label,
      '#weight' => 110,
      '#disabled' => $booking_disabled,
    ];

    if ($booking_disabled) {
      $form['booking']['actions']['disabled_notice'] = [
        '#weight' => 109,
        '#markup' => '<p class="booking-disabled-notice">' . $this->t('Online booking for this property is temporarily unavailable. Please check back shortly.') . '</p>',
      ];
    }

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

    $kid_count = (int) $form_param['kid'];
    $child_ages = $kid_count > 0 ? Rategain::parseChildAges($form_param['child_age'], $kid_count) : [];
    $form['booking']['occupants_markup']['child_age'] = [
      '#markup' => !empty($child_ages) ? ' (age' . (count($child_ages) > 1 ? 's' : '') . ' ' . implode(', ', $child_ages) . ')' : '',
    ];

    // Only rategain rooms carry a cancellation policy through rg_cancellation
    // (set on the property page's room card) — last chance to see it before
    // paying.
    $form['booking']['cancellation_markup'] = [
      '#markup' => !empty($form_param['rg_cancellation']) ? htmlspecialchars($form_param['rg_cancellation']) : '',
    ];

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

    $property_node = Node::load($form_state->getValue('property_id'));
    if (!$property_node || $property_node->get('field_property_source')->getString() !== 'rategain') {
      return;
    }

    // Belt-and-suspenders for the disabled submit button above — a direct
    // POST could otherwise bypass the '#disabled' UI state.
    if (self::RATEGAIN_BOOKING_DISABLED) {
      $form_state->setErrorByName('', $this->t('Online booking for this property is temporarily unavailable. Please check back shortly.'));
      return;
    }

    $room_selection = json_decode($form_state->getValue('rg_room_selection') ?? '', TRUE);
    if (empty($room_selection)) {
      // No rate key carried through (e.g. an older link) — nothing to
      // re-validate against, so let it through as before rather than block
      // a booking PreCheck simply can't run for.
      return;
    }

    $extra_info = $property_node->get('field_extra_info')->value
      ? unserialize($property_node->get('field_extra_info')->value)
      : [];

    list($checkin, $checkout) = \Drupal::service('sr.services')->convertDateRange($form_state->getValue('date_range'));

    // Prefer the account's own first/last name fields over splitting the
    // free-text 'name' field the guest can edit — more reliable for
    // multi-word names, and only falls back when there's no logged-in user.
    $current_user = \Drupal\user\Entity\User::load(\Drupal::currentUser()->id());
    if ($current_user && $current_user->get('field_first_name')->value) {
      $first_name = $current_user->get('field_first_name')->value;
      $last_name = $current_user->get('field_last_name')->value;
    }
    else {
      $name_parts = explode(' ', trim($form_state->getValue('name')), 2);
      $first_name = $name_parts[0] ?? '';
      $last_name = $name_parts[1] ?? '';
    }

    $precheck_params = [
      'property_id'    => $extra_info['property_id'] ?? '',
      'property_code'  => $extra_info['property_code'] ?? '',
      'brand_code'     => $extra_info['brand_code'] ?? '',
      'checkin'        => $checkin,
      'checkout'       => $checkout,
      'country_code'   => $extra_info['country_code'] ?? 'US',
      'currency'       => $form_state->getValue('rg_currency') ?: 'USD',
      'room_selection' => $room_selection,
      'guest'          => [
        'first_name'   => $first_name,
        'last_name'    => $last_name,
        'email'        => $form_state->getValue('email'),
        'phone'        => $form_state->getValue('phone_code') . $form_state->getValue('phone_number'),
        'line1'        => $form_state->getValue('address_line1'),
        'city'         => $form_state->getValue('address_city'),
        'state_code'   => $form_state->getValue('address_state_code'),
        'country_code' => $form_state->getValue('address_country_code'),
        'postal_code'  => $form_state->getValue('address_postal_code'),
      ],
    ];

    $result = Rategain::preCheckReservation($precheck_params);
    if (empty($result['success'])) {
      $form_state->setErrorByName('', $result['error'] ?? $this->t('This rate is no longer available. Please choose another room.'));
      return;
    }

    // Reused by submitForm() so the guest is charged the just-reconfirmed
    // price and PreCheck isn't called a second time for the same submission.
    $form_state->set('rg_precheck_response', $result['response']);
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
    $arr_booking_data['field_status'] = 'payment_pending';
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

    // If validateForm() re-confirmed this rate via PreCheckReservation, use
    // its price instead of the possibly-stale one carried from the room
    // list — the guest is charged what was *just* reconfirmed, not what
    // was quoted whenever they first viewed the room.
    $rg_price = $form_state->getValue('rg_price');
    $precheck_response = $form_state->get('rg_precheck_response');
    if ($precheck_response) {
      $nights = max(1, (int) round((strtotime($arr_booking_data['field_to_date']) - strtotime($arr_booking_data['field_from_date'])) / 86400));
      $confirmed_total = 0.0;
      foreach ($precheck_response['rooms'] ?? [] as $room) {
        foreach ($room['rates'] ?? [] as $rate) {
          if (is_numeric($rate['totalPrice'] ?? null)) {
            $confirmed_total += (float) $rate['totalPrice'];
          }
        }
      }
      if ($confirmed_total > 0) {
        $rg_price = $confirmed_total / $nights;
      }
    }

    $arr_param = [
      'date_range' => $date_range,
      'adult' => $arr_booking_data['field_adults'],
      'kid' => $arr_booking_data['field_kids'],
      'pid' => $arr_booking_data['field_property_id'],
      'rg_price' => $rg_price,
      'rg_currency' => $form_state->getValue('rg_currency'),
    ];
    $arr_block_param = \Drupal::service('sr.services')->generatePriceInfo($arr_booking_data['field_property_id'], $arr_param);

    $arr_booking_data['field_price'] = $arr_block_param['final_price'];

    $property_node = Node::load($arr_booking_data['field_property_id']);
    $property_source = $property_node ? $property_node->get('field_property_source')->getString() : '';

    if ($property_source === 'rategain') {
      // Stashed for a future CommitReservation call — field_extra is an
      // otherwise-unused string_long column on the Booking entity, so no
      // new field-storage config is needed to hold this.
      if ($precheck_response) {
        $arr_booking_data['field_extra'] = json_encode(['rategain_precheck' => $precheck_response]);
      }

      $days_to_arrival = (int) floor((strtotime($arr_booking_data['field_from_date']) - strtotime('today')) / 86400);

      if ($days_to_arrival > self::RATEGAIN_PAYMENT_DUE_THRESHOLD_DAYS) {
        // Arrival is far enough out: reserve the booking without forcing
        // payment now. sr_paytabs_cron() will auto-cancel it if it's still
        // unpaid once arrival gets within the threshold.
        $arr_booking_data['field_status'] = 'reserved_unpaid';
        $booking_entity = Booking::create($arr_booking_data);
        $booking_entity->save();
        $booking_id = $booking_entity->id();

        \Drupal::service('sr.services')->emailBooking($booking_id);
        $url = Url::fromRoute('sr.booking.search', [], ['absolute' => TRUE])->toString();
        commonUtil::my_goto($url);
        return;
      }

      // Arrival is close: payment is mandatory before the booking is confirmed.
      $arr_booking_data['field_status'] = 'payment_pending';
      $booking_entity = Booking::create($arr_booking_data);
      $booking_entity->save();
      $booking_id = $booking_entity->id();

      $url = Url::fromRoute('sr_paytabs.initiate', ['booking_id' => $booking_id], ['absolute' => TRUE])->toString();
      commonUtil::my_goto($url);
    }
    elseif ($property_source === 'plumguide') {
      $arr_booking_data['field_status'] = 'payment_pending';
      $booking_entity = Booking::create($arr_booking_data);
      $booking_entity->save();
      $booking_id = $booking_entity->id();

      $url = Url::fromRoute('sr_paytabs.initiate', ['booking_id' => $booking_id], ['absolute' => TRUE])->toString();
      commonUtil::my_goto($url);
    }
    else {
      $arr_booking_data['field_status'] = 'pending_booking';
      $booking_entity = Booking::create($arr_booking_data);
      $booking_entity->save();
      $booking_id = $booking_entity->id();

      \Drupal::service('sr.services')->emailBooking($booking_id);
      $url = Url::fromRoute('sr.booking.search', [], ['absolute' => TRUE])->toString();
      commonUtil::my_goto($url);
    }
  }

}
