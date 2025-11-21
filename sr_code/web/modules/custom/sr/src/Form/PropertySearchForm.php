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
use Drupal\Core\Datetime\DrupalDateTime;


class PropertySearchForm extends FormBase {

  var $arr_request_param = array(
    'town_city' => '',
    'price_min' => '', 'price_max' => '',
    'bathroom' => '', 'bedroom' => '',
    'date_range' => '',
    'adult' => '', 'kid' => '',
    'amenities' => '', 'sort' => '',
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

    $arr_bathroom[''] = 'Bathroom';
    $arr_bedroom[''] = 'Bedroom';

    for($i=1;$i<=5;$i++) {
      $arr_bathroom[$i] = $i;
      $arr_bedroom[$i] = $i;
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

    // Validate the param and correct if needed.
    list($param_status, $arr_param) = \Drupal::service('sr.services')->validateSearchParam($form_param);
    if ($param_status == FALSE) {
      $url = Url::fromRoute('sr.search', $arr_param, ['absolute' => TRUE])->toString();
      commonUtil::my_goto($url);
      return;
    }

    list($from_date, $to_date) = explode(" to ", $form_param['date_range']);
    $form_param['amenities'] = ($form_param['amenities']!='') ? explode(',', $form_param['amenities']) : array();

    // Adding js and css
    $form['#attached']['library'][] = 'sr/sr_lib';
    $form['#attached']['library'][] = 'sr/sr_gmap_lib';
    $form['#attached']['library'][] = 'sr/sr_autocomplete_lib';
    $form['#attached']['library'][] = 'sr/sr_occupants';

    $arr_town_city = commonUtil::get_term_list('town_city');
    $str_country_list = 'var countries = ["'. implode('","', $arr_town_city) . '"];';

    $form['js_town_city'] = [
      '#markup' => $str_country_list,
    ];

    $form['sort'] = [
      '#type' => 'select',
      '#title' => $this->t('Sort by'),
      '#options' => array('lh_price' => "Price: Low to High", 'hl_price' => "Price: High to Low"),
      '#default_value' => $form_param['sort'],
      '#attributes' => array('onchange' => 'this.form.submit();'),
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
      '#default_value' => $form_param['price_min'],
      '#attributes' => array(
        'placeholder' => array('Minimum Price'),
        'id' => array('edit-price-min'),
      )
    );

    $form['price']['price_max'] = array(
      '#type' => 'number',
      '#title' => $this->t('Price range Max'),
      '#default_value' => $form_param['price_max'],
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

      // Call a custom controller method to get matching city term IDs
      // based on a partial search string.
      $matched_tids = \Drupal\sr\Controller\SrController::getMatchingTownCityTermIds($form_param['town_city']);

      if (!empty($matched_tids)) {
        $search_perm['query']['town_city'] = array_column($matched_tids, 'tid');
      }

      // echo "<pre>";
      // print_r($matched_tids);
      // echo "</pre>";
      // exit;
    }
    
    // if ($form_param['town_city'] != "") {
    //   $properties = [
    //     'name' => $form_param['town_city'],
    //     'vid' => 'town_city',
    //   ];
    //   $terms = \Drupal::service('entity_type.manager')->getStorage('taxonomy_term')->loadByProperties($properties);
    //   $term = reset($terms);
    //   $id = !empty($term) ? $term->id() : 0;
    //   if ($id > 0 ) {
    //     $search_perm['query']['town_city'] = array($id);
    //   }
    // }

    //list($from_date, $to_date, $booking_days) = \Drupal::service('sr.services')->convertDate($from_date, $to_date);

    $from_date = new DrupalDateTime($from_date);
    $to_date = new DrupalDateTime($to_date);
    $booking_days = $to_date->diff($from_date)->format("%a");

    $search_perm['query']['property_source'] = array('plumguide', 'ratehawk', 'interhome');

    if ($booking_days >= 30) {
      array_push($search_perm['query']['property_source'], 'homelike');
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

    for($i = $arr_param['bedroom']; $i <= 5; $i++) {
      $arr_bedroom[] = $i;
    }
    $search_perm['query']['bedroom'] = $arr_bedroom;

//echo "<pre>";print_r($search_perm);exit;

    $arr_result = array();
    //$list_item_per_page = ($config->get('list_item_per_page') > 0) ? $config->get('list_item_per_page') : 5;
    if (isset($search_perm['query']['town_city']) && $search_perm['query']['town_city'] > 0) {
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
        if ($val_result['field_media'] != '') {
          $arr_image = unserialize($val_result['field_media']);
          if(isset($arr_image[0]['url'])) {
            $image = "<img src='".$arr_image[0]['url']."' width='200' height='200' loading='lazy'/>";
          }
        }

        $base_path = Url::fromRoute('<front>', [], ['absolute' => TRUE])->toString();

        $selected_amenities = $this->convertAmenities($form_param['amenities']);

        $url = $val_result['link'] . "?";
        foreach ($this->arr_request_param as $key_param => $val_param) {
          if ($key_param != 'amenities') {
            $url.= $key_param."=".urlencode($form_param[$key_param])."&";
          }
        }
        $url.= "amenities=".urlencode($selected_amenities);

        //$image_link = "<a href='".$url."'>".$image."</a>";
        //$title_link = "<a href='".$url."'>".$val_result['title']."</a>";
        $image_link = $image;
        //$title_link = $val_result['title'] . " - " . $val_result['nid'];
        $title_link = $val_result['title'];

        $calculated_price = 'NA';
        $calculated_currency_code = 'NA';

        if ($val_result['field_currency_code'] > 0 && $val_result['field_price'] > 0) {
          $term = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->load($val_result['field_currency_code']);
          $field_currency_code = $term->label();

          // Convert currency price
          $result = \Drupal::service('currency_layer_integration.services')->CFConvertUserCurrency($field_currency_code, $val_result['field_price']);

          $calculated_price = $result['value'];
          $calculated_currency_code = $result['to'];

          // Calculate commission
          $calculated_price = \Drupal::service('sr.services')->calculateCommission($val_result['field_property_source'], $calculated_price);
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
        if ($val_result['field_property_source'] != 'ratehawk') {
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

        $price_type = ($val_result['field_property_source'] == 'homelike') ? "Per Month" : "Per Night";

        if ($calculated_price != 'NA') {
          $price_value = 'Price ' . $calculated_currency_code . " " . $calculated_price . " - " . $price_type;
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
        $arr_location[$key_result]['title'] = $val_result['title'];
        $arr_location[$key_result]['lat'] = $val_result['field_location_coords_latitude'];
        $arr_location[$key_result]['lng'] = $val_result['field_location_coords_longitude'];
        $arr_location[$key_result]['id'] = $val_result['nid'];
        $arr_location[$key_result]['link'] = $url;
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
    $selected_amenities = $this->convertAmenities($form_state->getValue('amenities'));

    $arr_param = array();
    foreach ($this->arr_request_param as $key_param => $val_param) {
      if ($key_param != 'amenities') {
        $arr_param[$key_param] = urlencode($form_state->getValue($key_param));
      }
    }
    $arr_param['amenities'] = $selected_amenities;
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

  private function convertAmenities($param_amenities = array()) {
    $arr_amenities = array();
    if (is_array($param_amenities) && count($param_amenities)>0) {
      foreach($param_amenities as $val_amenities) {
        if ($val_amenities > 0) {
          array_push($arr_amenities, $val_amenities);
        }
      }
    }
    return implode(",", $arr_amenities);
  }


}
