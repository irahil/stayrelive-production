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
use Drupal\sr\Rategain;

class APIPriceForm extends FormBase {
  var $arr_request_param = array(
    'country_code' => '', 'pid' => '',
    'price_min' => '', 'price_max' => '',
    'bathroom' => '', 'bedroom' => '',
    'date_range' => '', 'town_city' => '',
    'adult' => '', 'kid' => '', 'child_age' => '',
    'amenities' => '', 'sort' => '',
  );

  public function getFormId() {
    return 'price';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {

    $form['error'] = array(
      '#title_display' => 'invisible',
      '#markup' => "Sorry, could not process your request..",
      '#prefix' => '<div class="form-error">',
      '#suffix' => '</div>',
    );

    $node = \Drupal::routeMatch()->getParameter('node');
    // Check if the current node source is rategain and has property id.
    if ($node->get('field_property_source')->value == 'rategain' && $node->get('field_reference_id')->value) {
      $property_id = $node->get('field_extra_info')->value ? unserialize($node->get('field_extra_info')->value)['property_id'] : '';
      $property_code = $node->get('field_extra_info')->value ? unserialize($node->get('field_extra_info')->value)['property_code'] : '';
      $brand_code = $node->get('field_extra_info')->value ? unserialize($node->get('field_extra_info')->value)['brand_code'] : '';
      $country_code = $node->get('field_extra_info')->value ? unserialize($node->get('field_extra_info')->value)['country_code'] : '';
    } else {
      // redirect user to error page.
      return $form;
    }

    foreach ($this->arr_request_param as $key_param => $val_param) {
      $query_value = \Drupal::request()->query->get($key_param);
      if ($query_value != '') {
        $query[$key_param] = urldecode(trim($query_value));
      } else {
        $query[$key_param] = '';
      }
    }

    // Base booking params, from the unmutated query (still has date_range) —
    // RateGain rooms aren't separate Drupal nodes, so every row books this
    // same parent node. Mirrors PropertySubForm's pattern. Each row below
    // adds its own rg_price/rg_currency so the guest page reflects the rate
    // actually selected, instead of the property's static field_price.
    $booking_param = $query;
    $booking_param['pid'] = $node->id();

    $date_range = explode(' to ', $query['date_range'] ?? '');

    $query['checkin']  = $date_range[0] ?? '';
    $query['checkout'] = $date_range[1] ?? '';
    unset($query['date_range']);

    $query['property_id'] = $property_id;
    $query['property_code'] = $property_code;
    $query['brand_code'] = $brand_code;
    $query['country_code'] = $country_code;

    // Fetch details from RateGain API using $property_id
    $property_details = Rategain::fetchPropertyDetails($query);

    if (empty($property_details)) {
      return $form;
    }

    // Display the room types in the same card layout used by PropertySubForm
    // for other property sources (fieldset + nested table rows rendered via
    // table--tbl-api-price.html.twig, styled the same as table--tbl-sub-property).
    $form['property_details'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Room Types'),
      '#attributes' => array(
        'class' => array('SectionContainer'),
      ),
    ];

    $form['property_details']['result'] = [
      '#type' => 'table',
      '#header' => [$this->t(''), $this->t(''), $this->t(''), $this->t(''), $this->t(''), $this->t('')],
      '#empty' => $this->t('No property details available.'),
      '#attributes' => array(
        'class' => array(
          'tbl-api-price',
        ),
      ),
    ];

    if (!empty($property_details['raw']['body']['products'])) {
      // RateGain splits a large party into multiple rooms (see
      // Rategain::splitOccupants()) — the number of rooms actually needed
      // for this search, so room-type prices below can be totalled across
      // all of them instead of showing a single room's price.
      $rooms_needed = count(Rategain::splitOccupants(
        max(1, (int) ($query['adult'] ?? 2)),
        (int) ($query['kid'] ?? 0)
      ));

      $checkin_ts = strtotime($query['checkin'] ?? '');
      $checkout_ts = strtotime($query['checkout'] ?? '');
      $nights = ($checkin_ts !== false && $checkout_ts !== false)
        ? max(1, (int) round(($checkout_ts - $checkin_ts) / 86400))
        : 1;

      $counter = 0;
      foreach ($property_details['raw']['body']['products'] as $product) {
        $image_url = $product['images'][0] ?? $property_details['raw']['body']['images'][0] ?? '';
        $name = $product['name'] ?? 'N/A';
        $currency = $product['nativeCurrency'] ?? '';
        $quote = $this->calculateRoomTypeQuote($product['rate'] ?? [], $rooms_needed, $product['roomCode'] ?? '', $nights);
        $price = $quote['total'];
        $cancellation_text = $quote['cancellation'];
        $needs_confirmation = $quote['needs_confirmation'];

        $row_booking_param = $booking_param;
        if (is_numeric($price)) {
          $row_booking_param['rg_price'] = $price;
          $row_booking_param['rg_currency'] = $currency;
          $row_booking_param['rg_cancellation'] = $cancellation_text;
          $row_booking_param['rg_room_selection'] = json_encode($quote['room_selection']);
        }
        $link = Url::fromRoute('sr.booking', array_map('urlencode', $row_booking_param), ['absolute' => TRUE])->toString();

        $image = $image_url ? "<img src='".$image_url."' width='100' height='100' loading='lazy'/>" : 'No image';

        $form['property_details']['result'][$counter]['url'] = [
          '#title_display' => 'invisible',
          '#markup' => $link,
        ];

        $form['property_details']['result'][$counter]['image'] = [
          '#title_display' => 'invisible',
          '#markup' => $image,
          '#prefix' => '<div class="result-image-sub">',
          '#suffix' => '<span class="more-icon"><i class="fa-solid fa-circle-info"></i></span></div>',
        ];

        $form['property_details']['result'][$counter]['title'] = [
          '#title_display' => 'invisible',
          '#markup' => $name,
          '#prefix' => '<div class="result-title">',
          '#suffix' => '</div>',
        ];

        // RateGain never returns a structured bedroom/bed count (checked live:
        // absent from the product object and from roomFacilities), so this
        // cell shows the cancellation policy for the quoted rate instead —
        // the guest needs to see this before picking a room.
        $info_markup = $cancellation_text ? htmlspecialchars($cancellation_text) : '';
        if ($needs_confirmation) {
          $info_markup .= ($info_markup ? '<br>' : '') . '<span class="rg-needs-confirmation">Price subject to confirmation at booking</span>';
        }
        $form['property_details']['result'][$counter]['info'] = [
          '#markup' => $info_markup,
        ];

        $display_api_price = $price;
        $display_api_currency = !empty($currency) ? $currency : 'USD';
        if (is_numeric($price)) {
          $conv = \Drupal::service('currency_layer_integration.services')->CFConvertUserCurrency($display_api_currency, $price);
          if (!empty($conv['value']) && $conv['value'] > 0) {
            $display_api_price = $conv['value'];
            $display_api_currency = $conv['to'];
          }
        }
        $form['property_details']['result'][$counter]['price'] = [
          '#title_display' => 'invisible',
          '#markup' => is_numeric($display_api_price) ? commonUtil::formatCurrency($display_api_price) . ' ' . $display_api_currency : 'On Request',
        ];

        $form['property_details']['result'][$counter]['booking_link'] = [
          '#title_display' => 'invisible',
          '#markup' => $link,
        ];

        $counter++;
      }
    }

    unset($form['error']);
/*
    $form['error'] = array(
      '#title_display' => 'invisible',
      '#markup' => "Rategain price." . "<pre>" . print_r($property_details, TRUE) . "</pre>",
      '#prefix' => '<div class="form-error">',
      '#suffix' => '</div>',
    );
*/

    return $form;
  }

  /**
   * Total price and cancellation policy for booking a room type across
   * all rooms a party needs.
   *
   * RateGain's getproducts response returns one rate quote per room per
   * rate plan, flattened into a single array — every entry is priced for
   * exactly one room, not the whole party. Quotes are grouped by rateCode
   * (not boardCode — two rateCodes, e.g. non-refundable vs flexible, can
   * share the same boardCode but have very different cancellation terms,
   * so grouping by board alone risks summing rooms from incompatible
   * offers into one "total"). The cheapest $rooms_needed quotes within
   * the cheapest valid rateCode group are summed, and that group's
   * cancellation policy is what gets shown — so the price and the
   * cancellation text always describe the same actual offer.
   *
   * @return array{total: float|string, cancellation: string, room_selection: array}
   */
  private function calculateRoomTypeQuote(array $rates, int $rooms_needed, string $room_type_code = '', int $nights = 1) {
    if (empty($rates) || $rooms_needed < 1) {
      return ['total' => '', 'cancellation' => '', 'room_selection' => [], 'needs_confirmation' => false];
    }

    $plans = [];
    foreach ($rates as $rate) {
      if (!is_numeric($rate['totalPrice'] ?? null)) {
        continue;
      }
      $plans[$rate['rateCode'] ?? ''][] = $rate;
    }

    $best_total = null;
    $best_entries = [];
    foreach ($plans as $entries) {
      if (count($entries) < $rooms_needed) {
        continue;
      }
      usort($entries, function ($a, $b) {
        return ((float) $a['totalPrice']) <=> ((float) $b['totalPrice']);
      });
      $chosen = array_slice($entries, 0, $rooms_needed);
      $total = array_sum(array_map(function ($e) {
        return (float) $e['totalPrice'];
      }, $chosen));

      if ($best_total === null || $total < $best_total) {
        $best_total = $total;
        $best_entries = $chosen;
      }
    }

    if ($best_total === null) {
      return ['total' => '', 'cancellation' => '', 'room_selection' => [], 'needs_confirmation' => false];
    }

    // All chosen entries share one rateCode, so their cancellation terms
    // are identical — the first one speaks for the group.
    $policies = $best_entries[0]['cancellationPolicies'] ?? [];
    $native_currency = !empty($best_entries[0]['nativeCurrency']) ? $best_entries[0]['nativeCurrency'] : 'USD';
    $cancellation = $this->formatCancellationPolicy($policies, $best_total, $rooms_needed, $native_currency);

    // One RoomSelection entry per physical room in $best_entries, each
    // carrying its own rate/allocation key — PreCheckReservation validates
    // every room in the party individually, not the summed $best_total.
    $room_selection = array_map(function ($e) use ($room_type_code, $nights) {
      return [
        'room_type_code'     => $room_type_code,
        'rate_key'           => $e['rateKey'] ?? '',
        'allocation_details' => $e['allocationDetails'] ?? null,
        'adults'             => $e['adults'] ?? 1,
        'children'           => $e['children'] ?? 0,
        'board_name'         => $e['boardName'] ?? '',
        'room_rate'          => is_numeric($e['totalPrice'] ?? null) ? ((float) $e['totalPrice']) / $nights : 0,
      ];
    }, $best_entries);

    // If any room in this quote is a RECHECK rate, the whole combined price
    // isn't fully guaranteed — PreCheckReservation (BookingForm::validateForm())
    // is the real safety net at booking time; this just tells the guest
    // upfront the price could change.
    $needs_confirmation = false;
    foreach ($best_entries as $e) {
      if (($e['rateType'] ?? '') !== 'BOOKABLE') {
        $needs_confirmation = true;
        break;
      }
    }

    return ['total' => $best_total, 'cancellation' => $cancellation, 'room_selection' => $room_selection, 'needs_confirmation' => $needs_confirmation];
  }

  /**
   * Turns RateGain's cancellationPolicies tiers into a guest-facing line.
   *
   * Each tier is {amount, from} — "cancelling on/after `from` costs
   * `amount` (per room)". Tiers are evaluated against the current time:
   * if the earliest tier hasn't started yet, cancellation is genuinely
   * free until then; otherwise whichever tier already started is the
   * fee that applies right now, and the next tier (if any) is the
   * escalation the guest should know is coming.
   */
  private function formatCancellationPolicy(array $policies, float $total_price, int $rooms_needed, string $native_currency = 'USD') {
    if (empty($policies)) {
      return '';
    }

    usort($policies, function ($a, $b) {
      return strtotime($a['from'] ?? 'now') <=> strtotime($b['from'] ?? 'now');
    });

    $now = \Drupal::time()->getRequestTime();
    $current_tier = null;
    $next_tier = null;

    foreach ($policies as $tier) {
      $from = strtotime($tier['from'] ?? '');
      if ($from === false) {
        continue;
      }
      if ($from <= $now) {
        $current_tier = $tier;
      }
      elseif ($next_tier === null) {
        $next_tier = $tier;
      }
    }

    if ($current_tier === null) {
      return 'Free cancellation until ' . date('M j', strtotime($policies[0]['from']));
    }

    $current_amount = (float) ($current_tier['amount'] ?? 0) * $rooms_needed;
    $is_full_penalty = $total_price > 0 && abs($current_amount - $total_price) < 0.01;

    if ($is_full_penalty && $next_tier === null) {
      return 'Non-refundable';
    }

    $conv_curr = \Drupal::service('currency_layer_integration.services')->CFConvertUserCurrency($native_currency, $current_amount);
    $curr_amount_val = (!empty($conv_curr['value']) && $conv_curr['value'] > 0) ? $conv_curr['value'] : $current_amount;
    $user_curr = (!empty($conv_curr['to'])) ? $conv_curr['to'] : $native_currency;
    $curr_prefix = ($user_curr === 'USD') ? '$' : ($user_curr . ' ');

    if ($next_tier !== null) {
      $next_amount = (float) ($next_tier['amount'] ?? 0) * $rooms_needed;
      $conv_next = \Drupal::service('currency_layer_integration.services')->CFConvertUserCurrency($native_currency, $next_amount);
      $next_amount_val = (!empty($conv_next['value']) && $conv_next['value'] > 0) ? $conv_next['value'] : $next_amount;
      return 'Cancel now for a ' . $curr_prefix . number_format(ceil($curr_amount_val), 0)
        . ' fee — increases to ' . $curr_prefix . number_format(ceil($next_amount_val), 0)
        . ' after ' . date('M j', strtotime($next_tier['from']));
    }

    return 'Cancel now for a ' . $curr_prefix . number_format(ceil($curr_amount_val), 0) . ' fee';
  }

  public function validateForm(array &$form, FormStateInterface $form_state) {

  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $arr_param['date_range'] = $form_state->getValue('date_range');
    $current_path = \Drupal::service('path.current')->getPath();
    //$current_path = \Drupal::service('path_alias.manager')->getAliasByPath($current_path);

    $current_path = commonUtil::my_generate_url($current_path);

    $query_string = '';
    foreach ($this->arr_request_param as $key_param => $val_param) {
      if ($key_param == "date_range") {
        $query_string.= "date_range=".urldecode(trim($arr_param['date_range']))."&";
      } else {
        $query_value = \Drupal::request()->query->get($key_param);
        $query_value = ($query_value != '') ? urldecode(trim($query_value)) : '';
        $query_string.= $key_param."=".urldecode(trim($query_value))."&";
      }
    }
    $query_string = rtrim($query_string, "&");
    $current_path = $current_path . "?".$query_string;

    //echo $current_path;
    commonUtil::my_goto($current_path);
  }
}
