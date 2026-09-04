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
use Drupal\Core\Datetime\DrupalDateTime;


class PropertySearchForm extends FormBase {

  var $arr_request_param = array(
    'town_city' => '',
    'price_min' => '', 'price_max' => '',
    'bathroom' => '', 'bedroom' => '',
    'date_range' => '', 'rooms' => '',
    'adult' => '', 'kid' => '', 'child_age' => '',
    'amenities' => '', 'sort' => '',
    'property_type' => '',
  );

  public function getFormId() {
    return 'property';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $current_user = \Drupal::currentUser();
    $current_roles = $current_user->getRoles();
    $arr_amenities_complete = commonUtil::get_term_list('amenities');
    $arr_allowed_amenities = array("Flatscreen TV", "Toiletries", "Dishwasher", "Oven", "Toaster", "Microwave", "Swimming Pool", "Air conditioning", "CRT TV",
    "Hair dryer","Iron", "Capsule coffee machine", "Filter coffee machine", "Washing machine", "Cleaning", "Fitness", "Gym Access", "Guaranteed parking");

    $amenity_icons = array(
      "Flatscreen TV" => "fa-tv",
      "Toiletries" => "fa-soap",
      "Dishwasher" => "fa-sink",
      "Oven" => "fa-fire",
      "Toaster" => "fa-bread-slice",
      "Microwave" => "fa-box",
      "Swimming Pool" => "fa-water",
      "Air conditioning" => "fa-snowflake",
      "CRT TV" => "fa-tv",
      "Hair dryer" => "fa-wind",
      "Iron" => "fa-tshirt",
      "Capsule coffee machine" => "fa-mug-hot",
      "Filter coffee machine" => "fa-coffee",
      "Washing machine" => "fa-sync-alt",
      "Cleaning" => "fa-broom",
      "Fitness" => "fa-dumbbell",
      "Gym Access" => "fa-user-alt",
      "Guaranteed parking" => "fa-parking"
    );
    
    foreach ($arr_amenities_complete as $key_amenities => $val_amenities) {
      if (in_array($val_amenities, $arr_allowed_amenities)) {
        $arr_amenities_list[$key_amenities] = '<i class="fa-solid '. $amenity_icons[$val_amenities] . '"></i>' . "  " . $val_amenities;
      }
    }

    $arr_property_type_list = array_combine(
      Rategain::filterableAccommodationTypes(),
      Rategain::filterableAccommodationTypes()
    );

    $arr_bathroom[''] = 'Bathroom';
    $arr_bedroom[''] = 'Bedroom';

    for($i=1;$i<=5;$i++) {
      $arr_bathroom[$i] = $i;
      $arr_bedroom[$i] = $i;
    }

    $form_param = array();

    foreach ($this->arr_request_param as $key_param => $val_param) {
      // Use all() rather than get() — get() throws BadRequestException on
      // a non-scalar (e.g. an old shared link with town_city[]=.. still
      // in the URL), which Drupal surfaces as "A client error happened".
      $query_value = \Drupal::request()->query->all()[$key_param] ?? '';
      if (is_array($query_value)) {
        $query_value = implode(',', $query_value);
      }
      if ($query_value != '') {
        $form_param[$key_param] = urldecode(trim($query_value));
      } else {
        $form_param[$key_param] = '';
      }
    }

    // Validate the param and correct if needed.
    list($param_status, $arr_param) = \Drupal::service('sr.services')->validateSearchParam($form_param);
    if ($param_status == FALSE) {
      $url = Url::fromRoute('sr.search', $arr_param, ['absolute' => TRUE])->toString();
      commonUtil::my_goto($url);
      return;
    }

    list($from_date, $to_date) = explode(" to ", $form_param['date_range']);
    $form_param['amenities'] = ($form_param['amenities']!='') ? explode(',', $form_param['amenities']) : array();
    $form_param['property_type'] = ($form_param['property_type']!='') ? explode(',', $form_param['property_type']) : array();

    // Adding js and css
    $form['#attached']['library'][] = 'sr/sr_lib';
    $form['#attached']['library'][] = 'sr/sr_gmap_lib';
    $form['#attached']['library'][] = 'sr/sr_autocomplete_lib';
    $form['#attached']['library'][] = 'sr/sr_occupants';
    $form['#attached']['library'][] = 'sr/sr_search_loader';

    // Marks this form for sr_search_loader.js — every filter change here
    // (sort auto-submit, the inline price/room/amenities/property-type
    // "Search" buttons, the main Search button) reloads the page, so a
    // loading overlay covers that transition.
    $form['#attributes']['class'][] = 'sr-search-form';

    // City suggestions are fetched from sr.town_city_autocomplete as the
    // user types (see sr_autocomplete.js) rather than loaded here — the
    // town_city vocabulary has ~71k terms, and loading/hydrating all of
    // them to embed as a JS array on every page load was exhausting PHP's
    // memory limit on every request.
    //
    // Separately: RateGain's API here points at their production endpoint
    // (see Rategain.php), but the town_city taxonomy's field_dest_code
    // values were last synced from RateGain's sandbox destination list —
    // only a fixed set of demand-partner test destinations has been
    // re-synced against production so far (see
    // SrCommands::syncRG_Destinations()); every other town_city term
    // still carries a stale sandbox destCode and returns no RateGain
    // results (Drupal-side results are unaffected). That's a data-sync
    // gap, not a reason to restrict which cities are suggested here.

    $existing_child_ages = Rategain::parseChildAges($form_param['child_age'], (int) $form_param['kid']);
    $form['js_child_ages'] = [
      '#markup' => 'var srChildAges = [' . implode(',', array_map('intval', $existing_child_ages)) . '];',
    ];

    $form['sort'] = [
      '#type' => 'select',
      '#title' => $this->t('Sort by'),
      '#options' => array('lh_price' => "Price: Low to High", 'hl_price' => "Price: High to Low"),
      '#default_value' => $form_param['sort'],
      // requestSubmit() (unlike submit()) fires the form's 'submit' event,
      // which sr_search_loader.js listens for to show the loading overlay.
      '#attributes' => array('onchange' => 'this.form.requestSubmit ? this.form.requestSubmit() : this.form.submit();'),
    ];

    $form['city'] = array(
      '#type' => 'fieldset',
      '#title' => $this
        ->t('City'),
      '#attributes' => array(
          'class' => array('SectionContainer'),
      )
    );

    $form['city']['town_city'] = [
      '#type' => 'textfield',
      '#title' => $this->t('City'),
      '#required' => TRUE,
      '#default_value' => $form_param['town_city'],
      '#attributes' => array(
        'id' => array('autocomplete_town_city'),
        'autocomplete' => array('off'),
        'placeholder' => array('Select a city'),
      )
    ];

    $form['date'] = array(
      '#type' => 'fieldset',
      '#title' => $this
        ->t('Date'),
      '#attributes' => array(
          'class' => array('SectionContainer'),
      )
    );

    $form['date']['date_range'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Date Range'),
      '#date_date_format' => 'Y-m-d',
      '#default_value' => $form_param['date_range'],
      '#attributes' => array(
        'id' => array('flatpickr_date_range'),
        'placeholder' => array('Dates'),
      )
    ];

    $form['price'] = array(
      '#type' => 'fieldset',
      '#title' => $this
        ->t('Price'),
      '#attributes' => array(
          'class' => array('SectionContainer'),
      )
    );

    $form['price']['price_min'] = array(
      '#type' => 'number',
      '#title' => $this->t('Price range Min'),
      '#value' => $form_param['price_min'],
      '#attributes' => array(
        'placeholder' => array('Minimum Price'),
        'id' => array('edit-price-min'),
      )
    );

    $form['price']['price_max'] = array(
      '#type' => 'number',
      '#title' => $this->t('Price range Max'),
      '#value' => $form_param['price_max'],
      '#attributes' => array(
        'placeholder' => array('Maximum Price'),
        'id' => array('edit-price-max'),
      )
    );

    $form['price']['submit_price'] = [
      '#type' => 'submit',
      '#value' => $this->t('Search'),
    ];

    $form['room'] = array(
      '#type' => 'fieldset',
      '#title' => $this
        ->t('Rooms'),
      '#attributes' => array(
          'class' => array('SectionContainer'),
      )
    );

    $form['room']['bedroom'] = [
      '#type' => 'select',
      '#options' => $arr_bedroom,
      '#default_value' => $form_param['bedroom'],
    ];

    $form['room']['bathroom'] = [
      '#type' => 'select',
      '#options' => $arr_bathroom,
      '#default_value' => $form_param['bathroom'],
    ];

    $form['room']['submit_room'] = [
      '#type' => 'submit',
      '#value' => $this->t('Search'),
    ];

    $form['rooms'] = [
      '#type' => 'select',
      '#title' => $this->t('Rooms'),
      '#options' => [
        '1' => $this->t('1 Room'),
        '2' => $this->t('2 Room'),
      ],
      '#default_value' => ($form_param['rooms'] != '') ? $form_param['rooms'] : '1',
      '#attributes' => array(
        'id' => array('edit-rooms'),
      ),
    ];

    $form['occupants'] = array(
      '#type' => 'fieldset',
      '#title' => $this
        ->t('Occupants'),
      '#attributes' => array(
          'class' => array('SectionContainer'),
      )
    );

    $form['occupants']['adult'] = array(
      '#type' => 'number',
      '#title' => $this->t('Adult Count'),
      '#default_value' => $form_param['adult'],
      '#attributes' => array(
        'placeholder' => array('Adults'),
      )
    );

    $form['occupants']['kid'] = array(
      '#type' => 'number',
      '#title' => $this->t('Kids Count'),
      '#default_value' => $form_param['kid'],
      '#attributes' => array(
        'placeholder' => array('Kids'),
      )
    );

    $form['occupants']['submit_occupants'] = [
      '#type' => 'submit',
      '#value' => $this->t('Search'),
    ];

    $form['amenities_list'] = array(
      '#type' => 'fieldset',
      '#title' => $this
        ->t('Amenities'),
      '#attributes' => array(
          'class' => array('SectionContainer'),
      )
    );

    $form['amenities_list']['amenities'] = [
      '#type' => 'checkboxes',
      '#options' => $arr_amenities_list,
      '#title' => $this->t('Amenities'),
      '#default_value' => $form_param['amenities'],
    ];

    $form['amenities_list']['submit_amenities_list'] = [
      '#type' => 'submit',
      '#value' => $this->t('Search'),
    ];

    $form['property_type_list'] = array(
      '#type' => 'fieldset',
      '#title' => $this
        ->t('Property Type'),
      '#attributes' => array(
          'class' => array('SectionContainer'),
      )
    );

    $form['property_type_list']['property_type'] = [
      '#type' => 'checkboxes',
      '#options' => $arr_property_type_list,
      '#title' => $this->t('Property Type'),
      '#default_value' => $form_param['property_type'],
    ];

    $form['property_type_list']['submit_property_type_list'] = [
      '#type' => 'submit',
      '#value' => $this->t('Search'),
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['reset'] = array(
      '#type' => 'button',
      '#button_type' => 'reset',
      '#value' => t('Reset'),
      '#attributes' => array(
        'onclick' => 'customReset(); return false;',
      ),
    );

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Search'),
      '#weight' => 110,
    ];

    // Search
    $search_perm['query'] = [
      'from_date' => $from_date,
    ];
    foreach ($form_param as $key_param => $val_param) {
      if ($val_param != '') {
        if ($key_param == 'price_min') {
          $search_perm['query']['price[min]'] = $val_param;
        } else if ($key_param == 'price_max') {
          $search_perm['query']['price[max]'] = $val_param;
        } else if ($key_param == 'sort') {
          if ($val_param == 'hl_price') {
            $search_perm['query']['sort_by'] = 'price';
            $search_perm['query']['sort_order'] = 'DESC';
          } else {
            $search_perm['query']['sort_by'] = 'price';
            $search_perm['query']['sort_order'] = 'ASC';
          }
        } else {
          $search_perm['query'][$key_param] = $val_param;
        }
      }
    }

    unset($search_perm['query']['town_city']);

    if (!empty($form_param['town_city'])) {

      // Call a custom controller method to get matching city term IDs.
      // SrController::getDrupalResults() sets these directly on the Views
      // filter (bypassing the exposed entity_autocomplete widget, which
      // can't handle a large set of programmatically-matched term IDs).
      $all_terms = SrController::getMatchingTownCityTermIds($form_param['town_city'], 500, 'all');
      $search_perm['query']['town_city'] = $all_terms;
      $search_perm['query']['town_city_api'] = $all_terms;
    }
    
    $from_date = new DrupalDateTime($from_date);
    $to_date = new DrupalDateTime($to_date);
    $booking_days = $to_date->diff($from_date)->format("%a");

    $search_perm['query']['property_source'] = array('plumguide', 'ratehawk', 'interhome', 'spacest', 'rategain');

    if ($booking_days >= 31) {
      array_push($search_perm['query']['property_source'], 'spacest');
    }

    $arr_bedroom = array();

    if ($form_param['adult'] != '') {
      $arr_param = $this->calculateBedroom($search_perm['query']);
      if ($arr_param['bathroom'] != '') {
        $search_perm['query']['bathroom'] = $arr_param['bathroom'];
      }
    } else {
      $arr_param['bedroom'] = 1;
    }

    // An explicit Rooms selection overrides the adult/kid-derived minimum.
    if ($form_param['rooms'] != '') {
      $arr_param['bedroom'] = (int) $form_param['rooms'];
    }

    for($i = $arr_param['bedroom']; $i <= 5; $i++) {
      $arr_bedroom[] = $i;
    }
    $search_perm['query']['bedroom'] = $arr_bedroom;

    $arr_result = array();
    //$list_item_per_page = ($config->get('list_item_per_page') > 0) ? $config->get('list_item_per_page') : 5;
    if (!empty($search_perm['query']['town_city']) || !empty($search_perm['query']['town_city_api'])) {
      $list_item_per_page = 20;
      $arr_result = SrController::searchProperty($search_perm, $list_item_per_page);
    }
    //print_r($search_perm);exit;

    $form['search_result'] = array(
      '#type' => 'fieldset',
      '#title' => $this
        ->t('Search Result'),
      '#attributes' => array(
          'class' => array('SectionContainer'),
      )
    );

    $arr_location = array();
    if (isset($arr_result['search_result']) && is_array($arr_result['search_result']) && count($arr_result['search_result']) > 0) {
      $form['search_result']['result'] = array(
        '#type' => 'table',
        '#header' => array(
          $this->t(''),
          $this->t('Title'),
          $this->t('BedRoom'),
          $this->t('BathRoom'),
          $this->t('Price'),
          $this->t(''),
        ),
        '#attributes' => array(
          'class' => array(
            'tbl-property',
          ),
        ),
      );

      foreach ($arr_result['search_result'] as $key_result => $val_result) {
        $image = "<img src='https://images.unsplash.com/photo-1517840901100-8179e982acb7?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxzZWFyY2h8Mnx8aG90ZWx8ZW58MHx8MHx8fDA%3D&w=1000&q=80' width='200' height='200' loading='lazy'/>";
        if ($val_result['field_media'] != '') {
          $image = "<img src='".$val_result['field_media']."' width='200' height='200' loading='lazy'/>";
        }

        $base_path = Url::fromRoute('<front>', [], ['absolute' => TRUE])->toString();

        $selected_amenities = commonUtil::convertAmenitiesUtil($form_param['amenities']);

        $url = $val_result['link'] . "?";
        foreach ($this->arr_request_param as $key_param => $val_param) {
          if ($key_param != 'amenities' && $key_param != 'property_type') {
            $url.= $key_param."=".urlencode($form_param[$key_param])."&";
          }
        }
        $url.= "amenities=".urlencode($selected_amenities)."&";
        $url.= "property_type=".urlencode(implode(',', $form_param['property_type']));

        $image_link = $image;
        //$title_link = $val_result['title'] . " - " . $val_result['nid'];
        $title_link = $val_result['title'];

        $calculated_price = 'NA';
        $calculated_currency_code = 'NA';

        if ($val_result['field_currency_code'] > 0 && $val_result['field_price'] > 0) {

          $term = \Drupal::entityTypeManager()
            ->getStorage('taxonomy_term')
            ->load($val_result['field_currency_code']);

          $field_currency_code = $term->label();

          $actual_price = $val_result['field_price'];

          // CONDITION ONLY FOR SPACEST
          if ($val_result['field_property_source'] == 'spacest' && $booking_days > 0) {

            // spacest stores MONTHLY price so convert to PER NIGHT
            $actual_price = $actual_price / 31;
          }

          // Convert currency
          $result = \Drupal::service('currency_layer_integration.services')
            ->CFConvertUserCurrency($field_currency_code, $actual_price);

          $calculated_price = $result['value'];
          $calculated_currency_code = $result['to'];

          // Commission calculation
          $calculated_price = \Drupal::service('sr.services')
            ->calculateCommission(
              $val_result['field_property_source'],
              $calculated_price
            );

          $calculated_price = commonUtil::formatCurrency($calculated_price);
        }

        $form['search_result']['result'][$key_result]['url'] = array(
          '#title_display' => 'invisible',
          '#markup' => $url,
        );

        $form['search_result']['result'][$key_result]['image'] = array(
          '#title_display' => 'invisible',
          '#markup' => $image_link,
          '#prefix' => '<div class="result-image">',
          '#suffix' => '<span class="more-icon"><i class="fa-solid fa-circle-info"></i></span></div>',
        );

        $form['search_result']['result'][$key_result]['title'] = array(
          '#title_display' => 'invisible',
          '#markup' => "<h3>".$title_link."</h3>",
          '#prefix' => '<div class="result-title">',
          '#suffix' => '</div>',
        );

        // For ratehawk, dont show bedroom and bathroom count.
        if ($val_result['field_property_source'] != 'ratehawk' && $val_result['field_property_source'] != 'rategain') {
          $form['search_result']['result'][$key_result]['bedroom'] = [
            '#title_display' => 'invisible',
            '#markup' => $val_result['field_total_bedrooms'] . ' bedroom & ',
            '#prefix' => '<div class="result-room"><span> <i class="fa-solid fa-bed"></i> ',
            '#suffix' => '</span>',
          ];
        
          $form['search_result']['result'][$key_result]['bathroom'] = [
            '#title_display' => 'invisible',
            '#markup' => $val_result['field_total_bathrooms'] . ' bathroom',
            '#prefix' => '<span><i class="fa-solid fa-shower"></i> ',
            '#suffix' => '</span></div>',
          ];
        }
        else {
          // Keep the structure but output nothing—no markup, no wrapper, no icons.
          $form['search_result']['result'][$key_result]['bedroom'] = [
            '#markup' => '',
          ];
          $form['search_result']['result'][$key_result]['bathroom'] = [
            '#markup' => '',
          ];
        }

        $price_value = 'Price on Request';
        $price_prefix = '<div class="result-price"><span><i class="fa-solid fa-credit-card"></i>';

        $price_suffix = '</div>';

        if ($val_result['field_property_source'] == 'homelike') {
          $price_type = 'Per Month';
        } else {
          // RateGain's field_price is normalized to a true per-night rate
          // in SrController::getRateGainResults() (its API returns a
          // stay total, confirmed by testing), so it's safe to label the
          // same as every other source here.
          $price_type = 'Per Night';
        }

        if ($calculated_price != 'NA') {
          $price_value = 'Price ' . $calculated_currency_code . " " . $calculated_price;
          if ($price_type != '') {
            $price_value .= " - " . $price_type;
          }
        }

        $form['search_result']['result'][$key_result]['price'] = array(
          '#title_display' => 'invisible',
          '#markup' => $price_value,
          '#prefix' => $price_prefix,
          '#suffix' => $price_suffix,
        );

        $form['search_result']['result'][$key_result]['nid'] = array(
          '#title_display' => 'invisible',
          '#markup' => $val_result['nid'],
        );

        // Creating Maps param
        $map_image_url = '';
        
        if (!empty($val_result['field_media']) && is_string($val_result['field_media'])) {
          $raw = trim($val_result['field_media']);
        
          $map_media = false;
        
          if (preg_match('/^(a|s|i|b|d|O|C|N):/', $raw)) {
            $map_media = @unserialize($raw, ['allowed_classes' => false]);
          }
        
          if (is_array($map_media) && !empty($map_media[0]['url'])) {
            $map_image_url = $map_media[0]['url'];
          }
        }
        $arr_location[$key_result]['title'] = $val_result['title'];
        $arr_location[$key_result]['lat'] = $val_result['field_location_coords_latitude'];
        $arr_location[$key_result]['lng'] = $val_result['field_location_coords_longitude'];
        $arr_location[$key_result]['id'] = $val_result['nid'];
        $arr_location[$key_result]['link'] = $url;
        $arr_location[$key_result]['image'] = $map_image_url;
        $arr_location[$key_result]['price'] = ($calculated_price != 'NA') ? $calculated_currency_code . ' ' . $calculated_price : 'On Request';
        $arr_location[$key_result]['bedrooms'] = (isset($val_result['field_total_bedrooms']) && $val_result['field_property_source'] != 'ratehawk') ? (string) $val_result['field_total_bedrooms'] : '';
        $arr_location[$key_result]['bathrooms'] = (isset($val_result['field_total_bathrooms']) && $val_result['field_property_source'] != 'ratehawk') ? (string) $val_result['field_total_bathrooms'] : '';
      }
      if (isset($arr_result['search_pager'])) {
        $form['search_result']['pager'] = [
          '#type' => 'item',
          '#markup' => $arr_result['search_pager'],
        ];
      }
    }
    else {
      global $base_url;
      $url_contact_us = $base_url . "/contact/contact_us";
      $form['search_result']['no_result'] = [
        '#type' => 'item',
        '#title' => '',
        '#markup' => '<p><i class="fa-solid fa-triangle-exclamation"></i></p>
        <h3>We did not find anything that you were searching for.</h3>

        <p>Try a different search or <a href="'.$url_contact_us.'">send us your query </a> to what your are looking for. </p>
        ',
      ];
    }

    $arr_google_maps = \Drupal::service('sr.services')->generateGoogleMapsParam($arr_location);
    $form['search_result']['gm_param_value'] = [
      '#markup' => $arr_google_maps['gm_param_value'],
    ];

    $form['#theme'] = 'sr_property_search_form';

    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state) {

  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $selected_amenities = commonUtil::convertAmenitiesUtil($form_state->getValue('amenities'));
    $selected_property_types = implode(',', array_filter((array) $form_state->getValue('property_type')));

    $arr_param = array();
    foreach ($this->arr_request_param as $key_param => $val_param) {
      if ($key_param != 'amenities' && $key_param != 'property_type') {
        $arr_param[$key_param] = urlencode($form_state->getValue($key_param));
      }
    }
    $arr_param['amenities'] = $selected_amenities;
    $arr_param['property_type'] = $selected_property_types;

    // child_age[] selects are generated client-side (sr_occupants.js) to
    // match the current Kids Count, one per child — they aren't declared
    // FAPI elements, so they're read from raw user input rather than
    // $form_state->getValue().
    $child_ages = array_filter((array) ($form_state->getUserInput()['child_age'] ?? []), function ($v) {
      return $v !== '';
    });
    $arr_param['child_age'] = urlencode(implode(',', $child_ages));
//$values = $form_state->getValues();
//echo "<pre>";
//print_r($values);exit;
    $arr_param = $this->calculateBedroom($arr_param);

    $url = Url::fromRoute('sr.search', $arr_param, ['absolute' => TRUE])->toString();

    commonUtil::my_goto($url);
  }

  private function calculateBedroom($arr_param = array()) {
    $arr_param['bedroom'] = (isset($arr_param['bedroom']) && $arr_param['bedroom'] != '') ? $arr_param['bedroom'] : 1;
    $arr_param['bathroom'] = '';

    if ($arr_param['adult'] <= 2 && $arr_param['kid'] <= 2) {
      $arr_param['bedroom'] = 1;
    } elseif ($arr_param['adult'] <= 4 && $arr_param['kid'] <= 4) {
      $arr_param['bedroom'] = 2;
    } elseif ($arr_param['adult'] <= 5 && $arr_param['kid'] <= 4) {
      $arr_param['bedroom'] = 3;
    }
    return $arr_param;
  }
}
