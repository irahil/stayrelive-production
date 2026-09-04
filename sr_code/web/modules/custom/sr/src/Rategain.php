<?php
namespace Drupal\sr;

use Drupal\Core\Session\AccountInterface;
use GuzzleHttp\Client;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\node\Entity\Node;
use Drupal\common_utilities\Utilities\commonUtil;

/**
 * Class CustomService
 * @package Drupal\sr\Services
 */
class Rategain {

  /**
   * Split a party of adults/children into individual rooms.
   *
   * RateGain treats each entry in 'Rooms' as one room with its own
   * occupancy — sending a single room with a NumberOfRoom multiplier
   * (e.g. 3x "6 adults, 4 children") asks for 3 rooms that *each* hold
   * 6 adults + 4 children, which no property has, so it returns zero
   * results. Splitting the party across rooms (max 2 adults per room,
   * mirroring the thresholds this replaces) lets large parties actually
   * match availability.
   *
   * @return array
   *   List of ['adults' => int, 'children' => int], one per room.
   */
  public static function splitOccupants(int $adults, int $children): array {
    $adults   = max(1, $adults);
    $children = max(0, $children);

    $rooms = (int) max(ceil($adults / 2), $children > 0 ? ceil($children / 2) : 1, 1);
    // Never more rooms than adults — every room needs at least one adult.
    $rooms = max(1, min($rooms, $adults));

    $result = array_fill(0, $rooms, ['adults' => 0, 'children' => 0]);
    for ($i = 0; $i < $adults; $i++) {
      $result[$i % $rooms]['adults']++;
    }
    for ($i = 0; $i < $children; $i++) {
      $result[$i % $rooms]['children']++;
    }
    return $result;
  }

  /**
   * Same split as splitOccupants(), but carries each child's actual age
   * into the room it lands in — RateGain prices children by age, and
   * every child was previously sent as a hardcoded age 5 regardless of
   * what was actually searched for.
   *
   * @return array
   *   List of ['adults' => int, 'children' => int, 'ages' => int[]], one
   *   per room, in the same room order as splitOccupants().
   */
  public static function splitOccupantsWithAges(int $adults, int $children, array $ages): array {
    $split = static::splitOccupants($adults, $children);
    $room_count = count($split);

    $ages_by_room = array_fill(0, $room_count, []);
    for ($i = 0; $i < $children; $i++) {
      $ages_by_room[$i % $room_count][] = $ages[$i] ?? 5;
    }

    foreach ($split as $room_index => $occupant_split) {
      $split[$room_index]['ages'] = $ages_by_room[$room_index];
    }
    return $split;
  }

  /**
   * Parses the comma-separated 'child_age' query param (one age per
   * child, in the order the child count/age fields were filled in) into
   * an int[] matching $count entries — missing/extra entries are padded
   * or trimmed, falling back to age 5 (the prior hardcoded default) for
   * any child whose age wasn't supplied.
   */
  public static function parseChildAges($raw, int $count): array {
    $parts = array_filter(array_map('trim', explode(',', (string) $raw)), fn($v) => $v !== '');
    $ages = array_slice(array_map('intval', $parts), 0, $count);
    while (count($ages) < $count) {
      $ages[] = 5;
    }
    return $ages;
  }

  /**
   * Property-type filter options shown to users, restricted to the subset
   * of RateGain's 14-value accommodationType enum (SmartDistribution API
   * spec, "AvailabilitySearch"/"ContentSearch" endpoints) that has a match
   * against the site's requested type list — 'Hotel', 'Bed & Breakfast
   * (B&B)', 'Lodge', 'Camping' and 'Specialty Stay' were dropped as
   * unrequested, and 'Riad' was dropped as having no RateGain equivalent
   * at all. Values here are RateGain's own exact strings (as returned in
   * a property's 'accTypeDesc') so they can be matched directly against
   * search results with no further translation.
   */
  public static function filterableAccommodationTypes(): array {
    return [
      'Aparthotel',
      'Apartment',
      'Boutique Hotel',
      'Guest House',
      'Luxury / Heritage Hotel',
      'Hostel',
      'Residence Motel',
      'Rural Stay',
      'Villa / Vacation Home',
      'Resort',
    ];
  }

  /**
   * Fetch destinations from RateGain API.
   *
   * @return array
   *   An array of destinations.
   */
  public static function fetchDestinations() {
    try {
      $config  = \Drupal::config('sr.settings');
      $client  = new Client(['timeout' => 120, 'connect_timeout' => 30,]);
      $request = new \GuzzleHttp\Psr7\Request(
        'GET',
        'https://smartdistribution.rategain.com/api/SmartDistribution/getDestinations',
        [
          'ApiKey'       => $config->get('rategain_api_key')    ?? '',
          'ApiSecret'    => $config->get('rategain_api_secret') ?? '',
          'Content-Type' => 'application/json',
        ]
      );

      $response = $client->sendAsync($request)->wait();
      $raw      = $response->getBody()->getContents();
      $data     = json_decode($raw, TRUE);
      return $data['body'] ?? [];
    }
    catch (\Exception $e) {
      \Drupal::logger('sr')->error('RateGain API error: ' . $e->getMessage());
      return [];
    }
  }

  public static function searchProperties($payload) {
    try {
      // Implement property search logic using RateGain API
      $config = \Drupal::config('sr.settings');

      $rategain_api_key    = $config->get('rategain_api_key')    ?? '';
      $rategain_api_secret = $config->get('rategain_api_secret') ?? '';

      $body = json_encode($payload);

      // Gated by the same 'rategain_api_debug' config SrController already
      // uses to log the outgoing payload — this adds the raw response body,
      // which nothing currently captures, so a customer's search can be
      // traced end-to-end against what RateGain actually returned.
      $rategain_api_debug = $config->get('rategain_api_debug') ?? TRUE;

      if ($rategain_api_debug) {
        \Drupal::logger('sr')->notice('RateGain property search request (uid @uid): @r', [
          '@uid' => \Drupal::currentUser()->id(),
          '@r'   => $body,
        ]);
      }

      $request = new \GuzzleHttp\Psr7\Request(
        'POST',
        'https://smartdistribution.rategain.com/api/SmartDistribution/bestproperties',
        [
          'ApiKey'       => $rategain_api_key,
          'ApiSecret'    => $rategain_api_secret,
          'Content-Type' => 'application/json',
        ],
        $body
      );

      $client = new \GuzzleHttp\Client([
        'timeout'         => 60,
        'connect_timeout' => 20,
      ]);

      $response = $client->sendAsync($request, [
        'timeout'         => 60,
        'connect_timeout' => 20,
      ])->wait();

      $raw_body = $response->getBody()->getContents();

      if ($rategain_api_debug) {
        \Drupal::logger('sr')->notice('RateGain property search response (uid @uid): @r', [
          '@uid' => \Drupal::currentUser()->id(),
          '@r'   => $raw_body,
        ]);
      }

      $data     = json_decode($raw_body, TRUE);
      return $data;
    }
    catch (\Exception $e) {
        \Drupal::logger('sr')->error('RateGain API error: @msg', ['@msg' => $e->getMessage()]);
        return [];
      }
    }
  
  public static function fetchPropertyDetails($query) {
    try {
      $config = \Drupal::config('sr.settings');

      // 'adult'/'kid' are the real search-form keys — 'adults'/'children'
      // never existed, so this always requested 2 adults + 2 children
      // regardless of the actual party searched for.
      $adults_count   = max(1, (int) ($query['adult'] ?? 2));
      $children_count = (int) ($query['kid'] ?? 0);
      $child_ages     = static::parseChildAges($query['child_age'] ?? '', $children_count);

      $rooms = [];
      foreach (static::splitOccupantsWithAges($adults_count, $children_count, $child_ages) as $occupant_split) {
        $rooms[] = [
          'numberOfRoom' => 1,
          'adults'       => $occupant_split['adults'],
          'children'     => $occupant_split['children'],
          // RateGain's API requires 'paxes' to be present even when empty —
          // omitting it entirely makes the API return zero results
          // regardless of destination.
          'paxes'        => array_map(fn($age) => ['type' => 'Child', 'age' => $age], $occupant_split['ages']),
        ];
      }

      $payload = [
        'propertyID'   => $query['property_id'] ?? '',
        'PropertyCode' => $query['property_code'] ?? '',
        'BrandCode'    => $query['brand_code']    ?? '',
        'checkin'      => $query['checkin']        ?? date('Y-m-d', strtotime('+1 week')),
        'checkout'     => $query['checkout']       ?? date('Y-m-d', strtotime('+1 week +2 days')),
        'CountryCode'  => $query['country_code']   ?? 'US',
        'Currency'     => $query['currency']       ?? 'USD',
        'Rooms'        => $rooms,
        'echoToken'    => 'detail_' . md5($query['property_id'] . serialize($query)),
      ];
      // Same 'rategain_api_debug' config gate SrController/searchProperties()
      // already use — logs both the outgoing payload and the raw response,
      // so a specific property's actual cancellationPolicies/rates can be
      // traced end-to-end instead of only inferring them from the crash
      // they cause downstream.
      $rategain_api_debug = $config->get('rategain_api_debug') ?? TRUE;

      if ($rategain_api_debug) {
        \Drupal::logger('sr')->debug('RateGain property details request: @r', ['@r' => print_r($payload, TRUE)]);
      }

      $body    = json_encode($payload);
      $request = new \GuzzleHttp\Psr7\Request(
        'POST',
        'https://smartdistribution.rategain.com/api/SmartDistribution/getproducts',
        [
          'ApiKey'       => $config->get('rategain_api_key')    ?? '',
          'ApiSecret'    => $config->get('rategain_api_secret') ?? '',
          'Content-Type' => 'application/json',
          'Accept'       => 'application/json, text/plain, */*',
        ],
        $body
      );

      $client   = new \GuzzleHttp\Client([
        'timeout'         => 60,
        'connect_timeout' => 20,
      ]);
      $response = $client->sendAsync($request)->wait();
      $raw_body = $response->getBody()->getContents();

      if ($rategain_api_debug) {
        \Drupal::logger('sr')->debug('RateGain property details response: @r', ['@r' => $raw_body]);
      }

      $data = json_decode($raw_body, TRUE);

      if (json_last_error() !== JSON_ERROR_NONE) {
        \Drupal::logger('sr')->error('RateGain JSON decode error: @e', ['@e' => json_last_error_msg()]);
        return [];
      }

      // Adjust key based on actual API response
      $property = $data['body']       ??
                  $data['property']   ??
                  $data['Product']    ??
                  $data['properties'] ??
                  $data ?? [];

      return [
        'raw'      => $data,                                         // full raw response
        'property' => $property,                                     // extracted property data
        'status'   => $response->getStatusCode(),
      ];

    }
    catch (\GuzzleHttp\Exception\RequestException $e) {
      \Drupal::logger('sr')->error('RateGain request error: @msg', ['@msg' => $e->getMessage()]);
      return [];
    }
    catch (\Exception $e) {
      \Drupal::logger('sr')->error('RateGain error: @msg', ['@msg' => $e->getMessage()]);
      return [];
    }
  }

  /**
   * Every room type and every individual rate option for a property,
   * listed as-is (not collapsed to a single "cheapest" quote per room like
   * APIPriceForm does) — for a full tariff view below the property's map.
   *
   * @param array $query
   *   Same shape as fetchPropertyDetails(): property_id, property_code,
   *   brand_code, country_code, checkin, checkout, adult, kid.
   * @param array $base_booking_param
   *   Query params to carry through to the booking route (pid + the
   *   original search params) — rg_price/rg_currency/rg_cancellation are
   *   added per rate below.
   *
   * @return array
   *   List of ['name', 'room_code', 'image', 'rates' => [
   *     ['board_code', 'board_name', 'rate_code', 'price', 'currency',
   *      'allotment', 'cancellation', 'link'],
   *   ]].
   */
  public static function buildRoomRateList(array $query, array $base_booking_param): array {
    $property_details = static::fetchPropertyDetails($query);

    if (empty($property_details['raw']['body']['products']) || !is_array($property_details['raw']['body']['products'])) {
      return [];
    }

    $body = $property_details['raw']['body'];
    // RateGain's per-room 'images' frequently comes back empty (confirmed
    // live) — fall back to the property's whole gallery, not just its first
    // photo, so a room card without room-specific shots still gives guests
    // something to browse in the lightbox instead of one static image.
    $property_images = !empty($body['images']) && is_array($body['images']) ? array_values($body['images']) : [];
    $star_rating = $body['starRating'] ?? null;
    // Same source SrController uses for the property's field_display_address —
    // one address for the whole property, repeated on every room card below.
    $property_address = $body['address'] ?? '';
    $rooms = [];

    // RateGain's totalPrice is the TOTAL for the whole searched stay, not a
    // nightly rate (confirmed: 1 night $129.47, 2 nights $258.93, 4 nights
    // $517.85 — scales linearly with nights). SRService::generatePriceInfo()
    // multiplies whatever price it's given by the number of nights again to
    // reach a final total, so passing the raw stay-total through as rg_price
    // would double- (or n-times-) charge the guest at checkout. Normalizing
    // to a genuine per-night rate here keeps rg_price consistent with what
    // every other property source already stores in field_price.
    $checkin_ts = strtotime($query['checkin'] ?? '');
    $checkout_ts = strtotime($query['checkout'] ?? '');
    $nights = ($checkin_ts !== false && $checkout_ts !== false)
      ? max(1, (int) round(($checkout_ts - $checkin_ts) / 86400))
      : 1;

    foreach ($body['products'] as $product) {
      $native_currency = !empty($product['nativeCurrency']) ? $product['nativeCurrency'] : 'USD';
      $rates = [];

      foreach ($product['rate'] ?? [] as $rate) {
        if (!is_numeric($rate['totalPrice'] ?? null)) {
          continue;
        }
        // RECHECK rates are RateGain's way of flagging that price/availability
        // isn't guaranteed and must be re-verified with a dedicated call right
        // before booking — sometimes with extra un-included tax on top of
        // totalPrice. They're shown (badged) rather than hidden now that
        // BookingForm::validateForm() calls Rategain::preCheckReservation()
        // right before booking — that's the actual safety net; a RECHECK
        // rate that's gone stale is caught there, not here.
        $needs_confirmation = ($rate['rateType'] ?? '') !== 'BOOKABLE';
        $total_price = (float) $rate['totalPrice'];
        $price = $total_price / $nights;

        // Convert per-night price from RateGain's native currency to user session currency
        $conv = \Drupal::service('currency_layer_integration.services')->CFConvertUserCurrency($native_currency, $price);
        $user_currency = (!empty($conv['to'])) ? $conv['to'] : $native_currency;
        $price_in_user_curr = (!empty($conv['value']) && $conv['value'] > 0) ? (float) $conv['value'] : $price;

        // rg_price stays the RAW (pre-commission) per-night rate —
        // BookingForm reads it via SRService::generatePriceInfo(), which
        // applies calculateCommission() itself at booking time. Commissioning
        // it here too would double it. display_price is commissioned
        // separately purely so the card shows the same total the guest will
        // actually be charged at checkout.
        $display_price = ceil((float) \Drupal::service('sr.services')->calculateCommission('rategain', $price_in_user_curr));

        // RateGain's 'offers' are promo line items already netted into
        // totalPrice (confirmed live: a discounted rate's totalPrice plus
        // its offer amount lands within pennies of an otherwise-identical
        // sibling rate with no offer) — never subtract this again from the
        // price charged. Used purely to show a "was / now / save" comparison.
        $discount_amount = static::sumOfferDiscount($rate['offers'] ?? []);
        $pre_discount_display_price = null;
        $savings_display = null;
        if ($discount_amount > 0) {
          $pre_discount_price = ($total_price + $discount_amount) / $nights;
          $conv_pre = \Drupal::service('currency_layer_integration.services')->CFConvertUserCurrency($native_currency, $pre_discount_price);
          $pre_discount_in_user_curr = (!empty($conv_pre['value']) && $conv_pre['value'] > 0) ? (float) $conv_pre['value'] : $pre_discount_price;
          $pre_discount_display_price = ceil((float) \Drupal::service('sr.services')->calculateCommission('rategain', $pre_discount_in_user_curr));
          $savings_display = max(0, $pre_discount_display_price - $display_price);
        }

        $row_booking_param = $base_booking_param;
        $row_booking_param['rg_price'] = $price;
        $row_booking_param['rg_currency'] = $native_currency;
        // Cancellation penalty amounts are quoted against the stay total, not
        // per-night, so the comparison inside needs $total_price, not $price.
        $row_booking_param['rg_cancellation'] = static::formatSingleRoomCancellation($rate['cancellationPolicies'] ?? [], $total_price, $native_currency);
        // Every rate here already represents exactly one room (RateGain's
        // 'rooms' => 1 per rate entry, occupancy split per-room on request)
        // — carried through as a single-element RoomSelection so BookingForm
        // can call PreCheckReservation with the exact rate/room the guest
        // clicked, using the adults/children RateGain itself already
        // assigned to this rate rather than re-deriving it.
        $row_booking_param['rg_room_selection'] = json_encode([[
          'room_type_code'    => $product['roomCode'] ?? '',
          'rate_key'          => $rate['rateKey'] ?? '',
          'allocation_details' => $rate['allocationDetails'] ?? null,
          'adults'            => $rate['adults'] ?? 1,
          'children'          => $rate['children'] ?? 0,
          'board_name'        => $rate['boardName'] ?? '',
          'room_rate'         => $price,
        ]]);

        $rates[] = [
          'board_code'   => $rate['boardCode'] ?? '',
          'board_name'   => $rate['boardName'] ?? '',
          'rate_code'    => $rate['rateCode'] ?? '',
          'price'        => $display_price,
          'price_formatted' => number_format($display_price, 0),
          'pre_discount_price_formatted' => $pre_discount_display_price !== null ? number_format($pre_discount_display_price, 0) : null,
          'savings_formatted' => $savings_display !== null ? number_format($savings_display, 0) : null,
          'currency'     => $user_currency,
          'allotment'    => $rate['allotment'] ?? null,
          'cancellation' => $row_booking_param['rg_cancellation'],
          'needs_confirmation' => $needs_confirmation,
          'link'         => \Drupal\Core\Url::fromRoute('sr.booking', array_map('urlencode', $row_booking_param), ['absolute' => TRUE])->toString(),
        ];
      }

      if (!empty($rates)) {
        $room_images = !empty($product['images']) ? array_values($product['images']) : $property_images;

        $rooms[] = [
          'name'        => $product['name'] ?? 'N/A',
          'room_code'   => $product['roomCode'] ?? '',
          'images'      => $room_images,
          'star_rating' => $star_rating,
          'address'     => $property_address,
          'facilities'  => $product['roomFacilities'] ?? [],
          'rates'       => $rates,
        ];
      }
    }

    return $rooms;
  }

  /**
   * Re-validates a rate/room selection immediately before booking.
   *
   * RateGain's rates can go stale between when a guest views a room and
   * when they submit the booking form (sold out, repriced, policy
   * changed) — this calls PreCheckReservation to confirm the selection is
   * still valid and get back the current price/cancellation policy plus
   * the allocationDetails a later CommitReservation call would need.
   *
   * @param array $params
   *   'property_id', 'property_code', 'brand_code', 'checkin', 'checkout',
   *   'country_code', 'currency', 'room_selection' (array, one entry per
   *   room: room_type_code, rate_key, allocation_details, adults,
   *   children, board_name, room_rate), 'guest' (first_name, last_name,
   *   email, phone, line1, city, state_code, country_code, postal_code).
   *
   * @return array
   *   ['success' => bool, 'response' => array|null, 'error' => string|null].
   *   On success, 'response' is body.preCheckResponse from RateGain.
   */
  public static function preCheckReservation(array $params): array {
    try {
      $config = \Drupal::config('sr.settings');
      $guest = $params['guest'] ?? [];

      $guest_entry = [
        'FirstName'   => $guest['first_name'] ?? '',
        'LastName'    => $guest['last_name'] ?? '',
        'Primary'     => TRUE,
        'Email'       => $guest['email'] ?? '',
        'EmailType'   => 1,
        'ProfileType' => 1,
        'Phone'       => $guest['phone'] ?? '',
        'Line1'       => $guest['line1'] ?? '',
        'City'        => $guest['city'] ?? '',
        'StateCode'   => $guest['state_code'] ?? '',
        'CountryCode' => $guest['country_code'] ?? '',
        'PostalCode'  => $guest['postal_code'] ?? '',
      ];

      $room_selection = [];
      foreach ($params['room_selection'] ?? [] as $room) {
        $entry = [
          'RoomTypeCode'      => $room['room_type_code'] ?? '',
          'NumberOfRooms'     => 1,
          'NumberOfAdults'    => (int) ($room['adults'] ?? 1),
          'NumberOfChild'     => (int) ($room['children'] ?? 0),
          'RoomSelectionKey'  => $room['rate_key'] ?? '',
          'RoomRate'          => $room['room_rate'] ?? 0,
          'BoardName'         => $room['board_name'] ?? '',
          'Guest'             => [$guest_entry],
        ];
        if (!empty($room['allocation_details'])) {
          $entry['allocationDetails'] = $room['allocation_details'];
        }
        if ((int) ($room['children'] ?? 0) > 0) {
          $entry['Children'] = array_fill(0, (int) $room['children'], [
            'type' => 'Child',
            'age'  => (int) ($room['child_age'] ?? 5),
          ]);
        }
        $room_selection[] = $entry;
      }

      $payload = [
        'BookReservation' => [
          'ResStatus'      => 1,
          'propertyID'     => $params['property_id'] ?? '',
          'PropertyCode'   => $params['property_code'] ?? '',
          'BrandCode'      => $params['brand_code'] ?? '',
          'checkin'        => $params['checkin'] ?? '',
          'checkout'       => $params['checkout'] ?? '',
          'EchoToken'      => 'precheck_' . md5(serialize($params)),
          'CountryCode'    => $params['country_code'] ?? '',
          'Currency'       => $params['currency'] ?? 'USD',
          'RoomSelection'  => $room_selection,
        ],
      ];

      $body    = json_encode($payload);
      $request = new \GuzzleHttp\Psr7\Request(
        'POST',
        'https://smartdistribution.rategain.com/api/smartdistribution/precheckreservation',
        [
          'ApiKey'       => $config->get('rategain_api_key')    ?? '',
          'ApiSecret'    => $config->get('rategain_api_secret') ?? '',
          'Content-Type' => 'application/json',
          'Accept'       => 'application/json, text/plain, */*',
        ],
        $body
      );

      $client   = new \GuzzleHttp\Client(['timeout' => 60, 'connect_timeout' => 20]);
      $response = $client->send($request);
      $data     = json_decode($response->getBody()->getContents(), TRUE);

      if (json_last_error() !== JSON_ERROR_NONE || empty($data['status'])) {
        \Drupal::logger('sr')->error('RateGain PreCheckReservation rejected: @r', ['@r' => json_encode($data)]);
        return ['success' => FALSE, 'response' => null, 'error' => $data['description'] ?? 'Rate could not be validated.'];
      }

      // The top-level status/statusCode envelope is RateGain's actual
      // success signal (confirmed live) — the room/rate 'status' field
      // shown in the spec's sample response ("CONFIRMED"/"Available") is
      // absent from real responses on success, so its presence is only
      // trusted as an explicit REJECTION when RateGain does send one, never
      // required for approval.
      $precheck = $data['body']['preCheckResponse'] ?? null;
      if (empty($precheck['rooms'])) {
        return ['success' => FALSE, 'response' => $precheck, 'error' => 'This rate is no longer available.'];
      }

      $has_valid_rate = FALSE;
      foreach ($precheck['rooms'] as $room) {
        if (!empty($room['status']) && $room['status'] !== 'CONFIRMED') {
          continue;
        }
        foreach ($room['rates'] ?? [] as $rate) {
          if (!empty($rate['status']) && $rate['status'] !== 'Available') {
            continue;
          }
          if (is_numeric($rate['totalPrice'] ?? null)) {
            $has_valid_rate = TRUE;
          }
        }
      }

      if (!$has_valid_rate) {
        return ['success' => FALSE, 'response' => $precheck, 'error' => 'This rate is no longer available.'];
      }

      return ['success' => TRUE, 'response' => $precheck, 'error' => null];
    }
    catch (\GuzzleHttp\Exception\RequestException $e) {
      $body = $e->hasResponse() ? $e->getResponse()->getBody()->getContents() : $e->getMessage();
      \Drupal::logger('sr')->error('RateGain PreCheckReservation error: @msg', ['@msg' => $body]);
      return ['success' => FALSE, 'response' => null, 'error' => 'This rate could not be re-confirmed. Please choose another room.'];
    }
    catch (\Exception $e) {
      \Drupal::logger('sr')->error('RateGain PreCheckReservation error: @msg', ['@msg' => $e->getMessage()]);
      return ['success' => FALSE, 'response' => null, 'error' => 'This rate could not be re-confirmed. Please choose another room.'];
    }
  }

  /**
   * Total discount amount (as a positive number) from a rate's 'offers'
   * list — only entries with type 'Amount' carry a usable value; others
   * (e.g. "Non-refundable rate..." remarks) have null type/value and are
   * purely informational text, not price adjustments.
   */
  private static function sumOfferDiscount(array $offers): float {
    $total = 0.0;
    foreach ($offers as $offer) {
      if (($offer['type'] ?? '') === 'Amount' && is_numeric($offer['value'] ?? null)) {
        $total += abs((float) $offer['value']);
      }
    }
    return $total;
  }

  /**
   * Cancellation text for a single room's rate — same tier logic as
   * APIPriceForm::formatCancellationPolicy() but for exactly one room,
   * since every row here is already one individual bookable rate.
   */
  private static function formatSingleRoomCancellation(array $policies, float $total_price, string $native_currency = 'USD'): string {
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
      $free_until = static::formatLocalDate($policies[0]['from'] ?? null);
      return $free_until !== '' ? "Free cancellation until {$free_until}" : 'Free cancellation';
    }

    $current_amount = (float) ($current_tier['amount'] ?? 0);
    $is_full_penalty = $total_price > 0 && abs($current_amount - $total_price) < 0.01;

    if ($is_full_penalty && $next_tier === null) {
      return 'Non-refundable';
    }

    $conv_curr = \Drupal::service('currency_layer_integration.services')->CFConvertUserCurrency($native_currency, $current_amount);
    $curr_amount_val = (!empty($conv_curr['value']) && $conv_curr['value'] > 0) ? $conv_curr['value'] : $current_amount;
    $user_curr = (!empty($conv_curr['to'])) ? $conv_curr['to'] : $native_currency;
    $curr_prefix = ($user_curr === 'USD') ? '$' : ($user_curr . ' ');

    if ($next_tier !== null) {
      $next_amount = (float) ($next_tier['amount'] ?? 0);
      $conv_next = \Drupal::service('currency_layer_integration.services')->CFConvertUserCurrency($native_currency, $next_amount);
      $next_amount_val = (!empty($conv_next['value']) && $conv_next['value'] > 0) ? $conv_next['value'] : $next_amount;
      return 'Cancel now for a ' . $curr_prefix . number_format(ceil($curr_amount_val), 0)
        . ' fee — increases to ' . $curr_prefix . number_format(ceil($next_amount_val), 0)
        . ' after ' . static::formatLocalDate($next_tier['from']);
    }

    return 'Cancel now for a ' . $curr_prefix . number_format(ceil($curr_amount_val), 0) . ' fee';
  }

  /**
   * Formats an ISO 8601 date string ("2026-08-12T23:59:00-06:00") in the
   * date's OWN embedded offset, not the server's default timezone.
   *
   * strtotime() + date() would convert through an absolute timestamp and
   * then render it in the server's timezone (e.g. Europe/Berlin) — a
   * cancellation deadline of "23:59 in Mexico" would print as the next
   * calendar day. RateGain's cancellation deadlines are local to the
   * property, so the date guests see must stay in that local terms.
   */
  private static function formatLocalDate(?string $iso_date): string {
    if (empty($iso_date)) {
      return '';
    }
    try {
      return (new \DateTime($iso_date))->format('M j');
    }
    catch (\Exception $e) {
      return '';
    }
  }

}
