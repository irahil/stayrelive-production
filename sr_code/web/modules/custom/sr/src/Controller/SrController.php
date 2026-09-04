<?php
namespace Drupal\sr\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Component\Utility\Unicode;
use Drupal\node\Entity\Node;
use Drupal\common_utilities\Utilities\commonUtil;
use Drupal\sr\Rategain;
use Drupal\views\Views;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\Response;
use Drupal\sr_mapping\Processing\Importer;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\JsonResponse;

class SrController extends ControllerBase {

  var $arr_request_param = array(
    'town_city' => '',
    'price_min' => '', 'price_max' => '',
    'bathroom' => '', 'bedroom' => '',
    'date_range' => '',
    'adult' => '', 'kid' => '', 'child_age' => '',
    'amenities' => '', 'sort' => '',
    'property_type' => '',
  );

  public function main() {
    return array(
      '#markup' => ''
    );
  }

  public function BookingConfirmation() {
    return array(
      '#markup' => 'Booking request has been sent successfully!'
    );
  }

  /**
   * AJAX endpoint backing the city autocomplete field (HomeSearchForm /
   * PropertySearchForm). Returns just the matching city names for the
   * typed prefix — up to 15 of them — instead of the previous approach
   * of loading and hydrating all ~71k town_city terms and embedding
   * them as a JS array on every page load (see getMatchingTownCityTermIds()
   * for the same lean query already used once a search is submitted).
   */
  public function townCityAutocomplete() {
    $search_value = trim((string) \Drupal::request()->query->get('q', ''));
    $matches = [];

    if (mb_strlen($search_value) >= 2) {
      $tids = static::getMatchingTownCityTermIds($search_value, 15, 'all');
      if ($tids) {
        $terms = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadMultiple($tids);
        foreach ($terms as $term) {
          $matches[] = $term->label();
        }
      }
    }

    return new JsonResponse($matches);
  }

public static function searchProperty($arg_data, $item_per_page = 5) {
  $config = \Drupal::config('sr.settings');
  // Clean up '0' values for Views exposed filters.
  // Excludes 'adult'/'kid': 0 is a valid occupancy count (e.g. "0 children")
  // and must reach getRateGainResults() as-is, not be reset to "unspecified".
  foreach ($arg_data['query'] as $key_query => $val_query) {
    if ($val_query == '0' && !in_array($key_query, ['adult', 'kid'], TRUE)) {
      $arg_data['query'][$key_query] = '';
    }
  }
  $current_page = \Drupal::request()->query->getInt('page', 0);

  // Extract price range early — used for both sources
  $price_min = (float) ($arg_data['query']['price']['min'] ?? $arg_data['query']['price[min]'] ?? 0);
  $price_max = (float) ($arg_data['query']['price']['max'] ?? $arg_data['query']['price[max]'] ?? 0);

  // --- Try cache first ---
  $cache_key = 'property_search_' . md5(serialize($arg_data['query']));
  $cache     = \Drupal::cache('data');

  $get_cache_content    = $config->get('get_cache_content')    ?? TRUE;

  if ($get_cache_content == TRUE && $cached = $cache->get($cache_key)) {
    $all_results = $cached->data;
  }
  else {
    // --- 1. Get Drupal results (all rows, no paging) ---
    // Views already handles price[min]/price[max] via exposed filter natively
    $drupal_results = static::getDrupalResults($arg_data['query']);

    // --- 2. Get RateGain API results ---
    $api_results = static::getRateGainResults($arg_data['query']);

    // --- 3. Filter API results by price range (Views handles Drupal side) ---
    if ($price_min > 0 || $price_max > 0) {
      $api_results = array_values(array_filter($api_results, function ($item) use ($price_min, $price_max) {
        $price = (float) $item['field_price'];
        $above_min = ($price_min <= 0) || ($price >= $price_min);
        $below_max = ($price_max <= 0) || ($price <= $price_max);
        return $above_min && $below_max;
      }));
    }

    // --- 3b. Property-type filter — RateGain results only (see
    // Rategain::filterableAccommodationTypes()); Drupal's own
    // field_property_type taxonomy is a separate vocabulary and isn't
    // wired to this filter.
    $selected_property_types = array_filter((array) ($arg_data['query']['property_type'] ?? []));
    if (!empty($selected_property_types)) {
      $api_results = array_values(array_filter($api_results, function ($item) use ($selected_property_types) {
        return in_array($item['field_property_type'] ?? '', $selected_property_types, TRUE);
      }));
    }
    //echo "<pre>";print_r($drupal_results);print_r($api_results);exit;
    // --- 4. Merge ---
    $all_results = array_merge($drupal_results, $api_results);

    // --- 5. Sort by price ---
    $sort_order = strtoupper($arg_data['query']['sort_order'] ?? 'ASC');
    usort($all_results, function ($a, $b) use ($sort_order) {
      return $sort_order === 'DESC'
        ? $b['field_price'] <=> $a['field_price']
        : $a['field_price'] <=> $b['field_price'];
    });

    // Cache for 5 minutes
    $cache->set($cache_key, $all_results, time() + 300);
  }

  // --- 6. Paginate ---
  $total_rows    = count($all_results);
  $offset        = $current_page * $item_per_page;
  $search_result = array_slice($all_results, $offset, $item_per_page);

  // Resolve node links only for the page being shown (path-alias lookups
  // are expensive; RateGain rows already carry their own 'link').
  foreach ($search_result as &$row) {
    if (empty($row['link']) && !empty($row['nid'])) {
      $row['link'] = Url::fromRoute('entity.node.canonical', ['node' => $row['nid']])->toString();
    }
  }
  unset($row);

  // --- 7. Build pager ---
  $pager = '';
  if (!empty($search_result)) {
    \Drupal::service('pager.manager')->createPager($total_rows, $item_per_page);
    $pager_render = ['#type' => 'pager', '#quantity' => 5];
    $pager = \Drupal::service('renderer')->render($pager_render);
  }

  return [
    'search_result' => $search_result,
    'search_pager'  => $pager,
  ];
}


/**
 * Get all Drupal results without paging, normalized to common shape.
 * Views handles price[min]/price[max] natively via exposed filter.
 */
protected static function getDrupalResults(array $query): array {
  if (empty($query['town_city'])) {
    return [];
  }

  $view = Views::getView('property_view');
  $view->setDisplay('default');
  $view->get_total_rows = TRUE;

  // Town city is handled separately below, bypassing the exposed
  // entity_autocomplete widget entirely — it's built for a human typing
  // 1-2 tags, not dozens of programmatically-matched term IDs (its
  // 128-char maxlength validation rejects longer inputs).
  $exposed_input = $query;
  unset($exposed_input['town_city']);
  $view->setExposedInput($exposed_input);  // price[min]/price[max] passed here, Views handles it
  $view->preExecute();

  if (!empty($view->filter['field_town_city_target_id'])) {
    $view->filter['field_town_city_target_id']->value = array_values($query['town_city_api'] ?? $query['town_city'] ?? []);
  }

  $view->setItemsPerPage(500);
  $view->setCurrentPage(0);
  $view->execute();

  $results = [];
  foreach ($view->result as $rid => $row) {
    $entity = $row->_entity;
    $item   = [];

    // Defer the path-alias lookup (expensive) until after pagination, so it
    // only runs for the rows actually shown, not all 500 fetched here.
    $item['nid'] = $entity->id();
    foreach ($view->field as $fid => $field) {
      if ($fid == 'field_media') {
        $arr_images = unserialize($field->getValue($row));
        $item[$fid] = $arr_images[0]['url'] ?? '';
      }
      else {
        $item[$fid] = $field->getValue($row);
      }
    }

    $item['_source'] = 'drupal';

    $results[] = $item;
  }

  return $results;
}


/**
 * Get results from RateGain API, normalized to same shape as Drupal results.
 */
protected static function getRateGainResults(array $query): array {
  $date_range = explode(' to ', $query['date_range'] ?? '');

  $query['checkin']  = $date_range[0] ?? '';
  $query['checkout'] = $date_range[1] ?? '';

  if (empty($query['town_city_api']) || empty($query['checkin']) || empty($query['checkout'])) {
    return [];
  }

  // Get dest_code/country_code from the matched town_city terms. An
  // ambiguous city name (e.g. "Dubai") can match several terms — "Bur
  // Dubai", "Dubai Marina", "Dubai" itself, etc — and blindly using
  // whichever came first used to silently skip RateGain entirely
  // whenever that one happened to have no dest_code set, with nothing
  // logged. Load them all in one query and use the first (in match
  // order) that actually carries both codes.
  $terms = \Drupal::entityTypeManager()
    ->getStorage('taxonomy_term')
    ->loadMultiple($query['town_city_api']);

  $term = NULL;
  foreach ($query['town_city_api'] as $tid) {
    if (!empty($terms[$tid]) && !empty($terms[$tid]->get('field_dest_code')->value) && !empty($terms[$tid]->get('field_country_code')->value)) {
      $term = $terms[$tid];
      break;
    }
  }

  if (!$term) {
    return [];
  }
  $dest_code    = $term->get('field_dest_code')->value;
  $country_code = $term->get('field_country_code')->value;

  // 'adult'/'kid' are the real search-form keys (see arr_request_param) —
  // 'adults'/'children' never existed, so this always sent RateGain a
  // fixed 2 adults + 2 children regardless of what was actually searched.
  $adults_count   = max(1, (int) ($query['adult'] ?? 2));
  $children_count = (int) ($query['kid'] ?? 0);
  $child_ages     = Rategain::parseChildAges($query['child_age'] ?? '', $children_count);

  $rooms = [];
  foreach (Rategain::splitOccupantsWithAges($adults_count, $children_count, $child_ages) as $occupant_split) {
    $rooms[] = [
      'NumberOfRoom' => 1,
      'Adults'       => $occupant_split['adults'],
      'Children'     => $occupant_split['children'],
      // RateGain's bestproperties API requires 'paxes' to be present even
      // when empty — omitting it entirely makes the API return zero
      // properties regardless of destination.
      'paxes'        => array_map(fn($age) => ['type' => 'Child', 'age' => $age], $occupant_split['ages']),
    ];
  }

  $payload = [
    'destinationCode' => $dest_code,
    'CountryCode'     => $country_code,    
    'checkin'         => $query['checkin']      ?? date('Y-m-d', strtotime('+1 week')),
    'checkout'        => $query['checkout']     ?? date('Y-m-d', strtotime('+1 week +2 days')),
    'Currency'        => 'USD',
    'Rooms'           => $rooms,
    'Echotoken'       => 'search_' . md5(serialize($query)),
  ];

  // Pass price range to API if it supports it — avoids fetching records we'll discard
  $price_min = (float) ($query['price']['min'] ?? $query['price[min]'] ?? 0);
  $price_max = (float) ($query['price']['max'] ?? $query['price[max]'] ?? 0);
  if ($price_min > 0) $payload['minPrice'] = $price_min;  // adjust key to match RateGain docs
  if ($price_max > 0) $payload['maxPrice'] = $price_max;  // adjust key to match RateGain docs

  // Cache the raw RateGain response by the fields that actually change it
  // (destination, dates, occupancy, price bounds) — separate from the
  // outer property_search_ cache, which is keyed on the *whole* query
  // including sort_order/amenities/etc. Those don't affect what RateGain
  // returns, so without this a sort-order toggle or a second user
  // searching the same city/dates still re-triggers a full blocking
  // RateGain HTTP call. Echotoken is excluded here — it's itself a hash
  // of the full incoming query, so leaving it in would vary the cache
  // key on every unrelated filter change and defeat this entirely.
  $rategain_cache_key = 'rategain_search_' . md5(serialize(array_diff_key($payload, ['Echotoken' => TRUE])));
  $rategain_cache      = \Drupal::cache('data');
  $rategain_cached     = $rategain_cache->get($rategain_cache_key);

  if ($rategain_cached) {
    $data = $rategain_cached->data;
  }
  else {
    $data = Rategain::searchProperties($payload);
    // Only cache a real response — don't let a transient RateGain error or
    // timeout (searchProperties() returns exactly [] on exception) get
    // cached as "no results" for other users searching the same thing. A
    // genuine zero-availability response still comes back as a non-empty
    // array (status/statusCode/description keys alongside an empty
    // 'body'), so check the whole payload, not just ['body'].
    if (!empty($data) && is_array($data)) {
      // Short TTL: this is live availability/pricing, not static content.
      $rategain_cache->set($rategain_cache_key, $data, time() + 120);
    }
  }

  if (empty($data['body']) || !is_array($data['body'])) {
    return [];
  }

  $response_data = [];

  // RateGain's 'price' is the TOTAL for the whole searched stay, not a
  // nightly rate (confirmed live: 1 night $129.47, 2 nights $258.93, 4
  // nights $517.85 — scales linearly with nights). Left unlabeled below
  // rather than "Per Night" because of that same uncertainty — but sorting/
  // filtering this page merges these totals directly against every other
  // source's genuine per-night field_price, so a 4-night rategain total can
  // wrongly outrank a cheaper-per-night Drupal listing. Normalizing to a
  // true per-night rate here fixes both the sort fairness and the label.
  $checkin_ts = strtotime($query['checkin']);
  $checkout_ts = strtotime($query['checkout']);
  $nights = ($checkin_ts !== false && $checkout_ts !== false)
    ? max(1, (int) round(($checkout_ts - $checkin_ts) / 86400))
    : 1;

  // Carry the original search's occupancy/dates through to the property-
  // details link — without this, adult/kid/child_age never reach
  // propertyDetails() or APIPriceForm, which silently default to 1 adult
  // / 0 children regardless of what was actually searched.
  $link_query = [];
  foreach (['town_city', 'price_min', 'price_max', 'bathroom', 'bedroom', 'date_range', 'adult', 'kid', 'child_age', 'amenities', 'sort', 'property_type'] as $link_param) {
    if (isset($query[$link_param]) && $query[$link_param] !== '') {
      // town_city/bedroom can be arrays here (matched term IDs, bedroom-count
      // range) — flatten to a scalar so the generated link's query string
      // stays valid (an array value crashes Symfony's InputBag::get() on
      // propertyDetails()).
      $link_query[$link_param] = is_array($query[$link_param]) ? implode(',', $query[$link_param]) : $query[$link_param];
    }
  }

  foreach ($data['body'] as $i => $item) {
    $price = (float) ($item['price'] ?? 0) / $nights;

    $response_data[] = [
      'link'                            => Url::fromRoute('sr.property_details', [
                                            'property_source' => 'rg',
                                            'property_id'     => urldecode($item['propertyId'] ?? ''),
                                            'property_code'     => urldecode($item['propertyCode'] ?? ''),
                                            'brand_code'     => urldecode($item['brandCode'] ?? ''),
                                            'country_code'   => urldecode($item['countryCode'] ?? ''),
                                          ], ['query' => $link_query])->toString(),
      'nid'                             => time() * 1000 + $i,
      'title'                           => $item['propertyName'] ?? '',
      'field_location_coords_latitude'  => $item['latitude'] ?? '',
      'field_location_coords_longitude' => $item['longitude'] ?? '',
      'field_media'                     => $item['images'][0] ?? '',
      'field_price'                     => $price,
      'field_currency_code'             => commonUtil::getTermIdByName('USD', 'currency'),
      'field_property_source'           => 'rategain',
      'field_property_type'             => $item['accTypeDesc'] ?? '',
      'property_id'                     => $item['propertyId'] ?? '',
      '_source'                         => 'rategain',
    ];
  }

  $config = \Drupal::config('sr.settings');

  $rategain_api_debug    = $config->get('rategain_api_debug')    ?? TRUE;

  if ($rategain_api_debug) {
      \Drupal::logger('sr')->notice('RateGain : COUNT : @count - PAYLOAD : @payload - QUERY : @search', [
        '@count' => count($response_data),
        '@payload' => json_encode($payload),
        '@search' => json_encode($query),
      ]);
  }
  return $response_data;
}

  /**
   * Summary of propertyDetails
   * @param mixed $property_source
   * @param mixed $property_id
   * @return array{#markup: string}
   */
  public function propertyDetails($property_source, $property_id, $property_code, $brand_code, $country_code) {
    if ($property_source === 'rg' and !empty($property_id) and !empty($property_code)) {

      $query = array();

      $query['property_id'] = $property_id;
      $query['property_code'] = $property_code;
      $query['brand_code'] = $brand_code;
      $query['country_code'] = $country_code;

      $arr_query_string = [];

      foreach ($this->arr_request_param as $key_param => $val_param) {
        // Use all() rather than get() — get() throws BadRequestException on
        // a non-scalar (e.g. an old shared link with town_city[]=.. still
        // in the URL), which Drupal surfaces as "A client error happened".
        $query_value = \Drupal::request()->query->all()[$key_param] ?? '';
        if (is_array($query_value)) {
          $query_value = implode(',', $query_value);
        }
        if ($query_value != '') {
          $arr_query_string[$key_param] = urldecode(trim($query_value));
        } else {
          $arr_query_string[$key_param] = '';
        }
      }

      // Check if property already exists in Drupal by reference ID (property_id + property_code)
      $existing_nodes = \Drupal::entityTypeManager()
        ->getStorage('node')
        ->loadByProperties([
          'type' => 'property',
          'field_property_source' => 'rategain',
          'field_parent_id' => '',
          'field_reference_id' => $property_id . "_" . $property_code,
        ]);
      if (!empty($existing_nodes)) {
        $node = reset($existing_nodes);

      } else {
        $query = array_merge($query, $arr_query_string);
        $date_range = explode(' to ', $query['date_range'] ?? '');

        $query['checkin']  = $date_range[0] ?? '';
        $query['checkout'] = $date_range[1] ?? '';
        unset($query['date_range']);

        // Fetch details from RateGain API using $property_id
        $property_details = Rategain::fetchPropertyDetails($query);
        if ($property_details) {
          //echo "<pre>";print_r($property_details['raw']);exit;
          if ($property_details['raw']['statusCode'] == '200') {
            $property_details = $property_details['raw']['body'] ?? [];
            //echo "<pre>";print_r($property_details);exit;
            $arr_images = [];
            if (!empty($property_details['images']) && is_array($property_details['images'])) {
              foreach ($property_details['images'] as $image) {
                $arr_images[] = [
                  'url' => $image,
                  'type' => 'image',
                ];
              }
            }
            $arr_description = [
              [
                'heading' => $property_details['propertyName'] ?? '',
                'text' => $property_details['description'] ?? '',
              ]
            ];

            // Use the cheapest product rate as the property's starting price,
            // instead of hardcoding 0 — mirrors the extraction already used in
            // getRateGainResults() and APIPriceForm.
            $starting_price = 0;
            if (!empty($property_details['products']) && is_array($property_details['products'])) {
              foreach ($property_details['products'] as $product) {
                $product_price = (float) ($product['rate'][0]['totalPrice'] ?? 0);
                if ($product_price > 0 && ($starting_price == 0 || $product_price < $starting_price)) {
                  $starting_price = $product_price;
                }
              }
            }

            $arr_node = [
              'type' => 'property',
              'title' => $property_details['propertyName'] ?? '',
              'field_reference_id' => $property_id."_".$property_code,
              'field_price' => $starting_price,
              'field_currency_code' => commonUtil::getTermIdByName('USD', 'currency'),
              'field_parent_id' => '',
              'field_property_source' => 'rategain',
              'field_media' => serialize($arr_images),
              'field_town_city' => commonUtil::getTermIdByName($property_details['destinationName'] ?? '', 'town_city'),
              'field_location_postal_code' => $property_details['postalCode'] ?? '',
              'field_location_country_code' => commonUtil::getTermIdByName($country_code, 'country'),
              'field_location_coords_latitude' => $property_details['latitude'] ?? '',
              'field_location_coords_longitude' => $property_details['longitude'] ?? '',
              'field_display_address' => $property_details['address'] ?? '',
              'field_description' => $arr_description,
              'field_extra_info' => serialize([
                'property_id' => $property_id,
                'property_code' => $property_code,
                'brand_code' => $brand_code,
                'country_code' => $country_code,
              ]),
            ];
            //  'field_category' => $property_details['category'] ?? '',
            //  'field_property_type' => $property_details['propertyType'] ?? '',
//echo "<pre>";print_r($arr_node);exit;

            $node_id = Importer::createProperty($arr_node);
            // Load the newly created node to get its URL
            $node = Node::load($node_id);
          }
        }
      }

      if (isset($node) && $node) {
        // Get the URL object from the node and attach query options
        $url = $node->toUrl();
        $url->setOption('query', $arr_query_string);
        return new RedirectResponse($url->toString());
      } else {
        return array(
          '#markup' => 'Sorry, could not process your request.'
        );
      }
    } else {
      return array(
        '#markup' => 'Sorry, could not process your request.'
      );
    }
  }


  public static function getMatchingTownCityTermIds($search_value, $limit = 10, $filter_codes = 'all')
  {
    // $filter_codes options:
    // 'all'        — return all terms regardless of dest_code/country_code
    // 'with'       — return only terms that HAVE dest_code and country_code
    // 'without'    — return only terms that are MISSING dest_code or country_code

    if (empty($search_value)) {
      return [];
    }

    $search_value = strtolower(trim($search_value));

    $query = \Drupal::entityQuery('taxonomy_term')
      ->accessCheck(FALSE)
      ->condition('vid', 'town_city')
      ->condition('name', '%' . $search_value . '%', 'LIKE')
      ->range(0, $limit);

    if ($filter_codes === 'with') {
      // Only terms that HAVE both dest_code and country_code
      $query->condition('field_dest_code', '', '!=');
      $query->condition('field_country_code', '', '!=');
    }
    elseif ($filter_codes === 'without') {
      // Only terms MISSING dest_code or country_code
      $dest_code_condition = $query->orConditionGroup()
        ->condition('field_dest_code', NULL, 'IS NULL')
        ->condition('field_dest_code', '', '=');

      $country_code_condition = $query->orConditionGroup()
        ->condition('field_country_code', NULL, 'IS NULL')
        ->condition('field_country_code', '', '=');

      $query->condition($dest_code_condition);
      $query->condition($country_code_condition);
    }
    // 'all' — no extra conditions, returns everything

    return $query->execute();
  }

  public static function searchBooking($arg_data, $item_per_page = 5) {
    $view = Views::getView('booking_list');
    $view->setDisplay('default');
    $view->get_total_rows = TRUE;

    if (is_array($arg_data['query']) && count($arg_data['query']) > 0) {
      foreach ($arg_data['query'] as $key_query => $val_query) {
        if ($val_query == '0') {
          $arg_data['query'][$key_query] = '';
        }
      }
    }
    $view->setExposedInput($arg_data['query']);
    $view->preExecute();
    //$view->setOffset(1);
    $view->setItemsPerPage($item_per_page);
    $view->execute();
    $rows = $view->total_rows;

    $search_result = array();
    foreach ($view->result as $rid => $row) {
      foreach ($view->field as $fid => $field ) {
        $search_result[$rid][$fid] = $field->getValue($row);
      }
    }
    $pager = '';
    if($search_result) {
      $arr_pager = $view->pager->render(array());
      $pager = \Drupal::service('renderer')->render($arr_pager);
    }

    return array('search_result' => $search_result, 'search_pager' => $pager);
  }

  /**
   * Exports data as a CSV file.
   */
  public function exportCsv() {
    // Fetch data from the session.
    $tempstore = \Drupal::service('tempstore.private')->get('booking_data');
    $data = $tempstore->get('search_results');
    if (empty($data)) {
      return new Response('No data available in the session.', 400);
    }

    // Static header for the CSV.
    $header = [
      'Booking ID',
      'Property',
      'Ref. ID',
      'Property Source',
      'No. of Adults', 
      'No. of kids',
      'No. of Rooms',
      'Name',
      'Email',
      'Phone',
      'From Date',
      'To Date',
      'Price',
      'Status',
      'Additional Information',
    ];

    // Fetch rows from the session data.
    $rows = [];
    foreach ($data as $key => $row_data) {
      if (is_numeric($key) && is_array($row_data)) {
        // Create a row for the CSV.
        $row = [
          $row_data['booking_id'] ?? '',
          strip_tags($row_data['property'] ?? ''),
          $row_data['ref_id'] ?? '',
          $row_data['property_source'] ?? '',
          $row_data['adults'] ?? '',
          $row_data['kids'] ?? '',
          $row_data['no_of_rooms'] ?? '',
          $row_data['name'] ?? '',
          $row_data['email'] ?? '',
          $row_data['phone'] ?? '',
          $row_data['from_date'] ?? '',
          $row_data['to_date'] ?? '', 
          $row_data['price'] ?? '',
          $row_data['status'] ?? '',
          $row_data['additional_info'] ?? '',
        ];
        $rows[] = $row;
      }
    }

    // Check if rows are available.
    if (empty($rows)) {
      return new Response('No data available to export.', 400);
    }

    // Generate CSV content.
    $csv_content = fopen('php://temp', 'r+');
    fputcsv($csv_content, $header);
    foreach ($rows as $row) {
      fputcsv($csv_content, $row);
    }

    // Prepare response.
    rewind($csv_content);
    $csv_data = stream_get_contents($csv_content);
    fclose($csv_content);

    // Generate filename with timestamp.
    $timestamp = date('Y-m-d_H-i-s');
    $filename = 'Booking_records_' . $timestamp . '.csv';

    $response = new Response($csv_data);
    $response->headers->set('Content-Type', 'text/csv');
    $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');
    return $response;
  }



}
