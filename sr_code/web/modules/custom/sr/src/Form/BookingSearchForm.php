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


class BookingSearchForm extends FormBase {

  var $var_content_type = "booking_search";

  public function getFormId() {
    return 'booking_search';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $flag_is_admin = false;
    $current_user = \Drupal::currentUser();
    $current_roles = $current_user->getRoles();
    $current_uid = $current_user->id();

    if (in_array('sr_admin', $current_roles) || in_array('administrator', $current_roles)) {
      $flag_is_admin = true;
    }

    // $current_user = \Drupal\user\Entity\User::load(\Drupal::currentUser()->id());

    //$arr_country_list = commonUtil::get_term_list('country', 'country');
    $arr_currency_list = commonUtil::get_term_list('currency');

    $arr_status_list = array(
      'pending_booking' => 'Pending Booking',
      'confirmed' => 'Confirmed Booking',
      'canceled_booking' => 'Canceled Booking',
    );

    $form_param = array('status' => '', 'uid' => '');

    foreach ($form_param as $key_param => $val_param) {
      $query_value = \Drupal::request()->query->get($key_param);
      if ($query_value != '') {
        $form_param[$key_param] = urldecode(trim($query_value));
      }
    }

    $form['filter'] = array(
      '#type' => 'fieldset',
      '#title' => $this
        ->t('Filter'),
      '#attributes' => array(
          'class' => array('SectionContainer'),
      )
    );

    $form['filter']['status'] = [
      '#type' => 'select',
      '#title' => $this->t('Status'),
      '#options' => array_merge( array('' => 'All'), $arr_status_list),
      '#default_value' => $form_param['status'],
    ];

    $form['filter']['actions'] = [
      '#type' => 'actions',
    ];

    $form['filter']['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Search'),
      '#weight' => 110,
    ];

    // Search
    $search_perm['query'] = [
      'status' => array_keys($arr_status_list),
    ];
    foreach ($form_param as $key_param => $val_param) {
      if ($form_param[$key_param] != '') {
        $search_perm['query'][$key_param] = $form_param[$key_param];
      }
    }

    if ($flag_is_admin == false) {
      unset($form['filter']);
      $search_perm['query']['uid'] = $current_uid;
    }

    $list_item_per_page = 50;
    $arr_result = SrController::searchBooking($search_perm, $list_item_per_page);
    $form['search_result'] = array(
      '#type' => 'fieldset',
      '#title' => $this
        ->t('Booking list'),
      '#attributes' => array(
          'class' => array('SectionContainer'),
      )
    );

//    $this->t('Source'),
/*
$form['search_result']['result'][$key_result]['property_source'] = array(
  '#title_display' => 'invisible',
  '#markup' => $property_source,
);
*/

    if (is_array($arr_result['search_result']) && count($arr_result['search_result']) > 0) {
      $form['search_result']['result'] = array(
        '#type' => 'table',
        '#header' => array(
          $this->t('Booking ID'),
          $this->t('Property'),
          $this->t('Details'),
          $this->t('Date'),
          $this->t('Price'),
          $this->t('Status'),
          $this->t('Additional Information'),
        ),
        '#attributes' => array(
          'class' => array(
            'tbl-booking',
          ),
        ),
      );

      foreach ($arr_result['search_result'] as $key_result => $val_result) {
        $property_link = "NA";
        $property_source = "NA";
        $property_reference_id = "NA";
        $no_of_rooms = "NA";
        $booking_id = $val_result['id'];
        $country_code = '';
        $city_code = '';

        $property = NULL;
        if (!empty($val_result['field_property_id'])) {
          $property = Node::load($val_result['field_property_id']);
        }
        if ($property instanceof \Drupal\node\NodeInterface) {
          if (!$property->get('field_location_country_code')->isEmpty()) {
            $country_term = $property->get('field_location_country_code')->entity;
            if ($country_term instanceof \Drupal\taxonomy\TermInterface) {
              $country_code = strtoupper(substr($country_term->label(), 0, 2));
            }
          }

          $city = strtoupper($property->get('field_location_town')->value ?? '');
          $city_code = substr(preg_replace('/[^A-Z]/', '', $city), 0, 2);
        }
        $custom_id = 'SR' . $country_code . $city_code . str_pad($booking_id, 2, '0', STR_PAD_LEFT);
        
        $form['search_result']['result'][$key_result]['id'] = array(
          '#title_display' => 'invisible',
          '#markup' => $custom_id,
        );
        if ($val_result['field_property_id'] > 0) {
          $property = Node::load($val_result['field_property_id']);
          if (empty($property)) { 
            continue;
          }
          $property_alias = \Drupal::service('path_alias.manager')->getAliasByPath('/node/'.$val_result['field_property_id']);
          $property_alias = commonUtil::my_generate_url($property_alias);
          $property_link = "<a href='".$property_alias."'>".$property->title->value."</a>";
          $property_reference_id = $property->get('field_reference_id')->getString();
          $property_source = $property->get('field_property_source')->getString();
          $no_of_rooms = $property->get('field_total_bedrooms')->getString();
        }

        $booking_status_link = $arr_status_list[$val_result['field_status']];

        $property_info = $property_link;
        if ($flag_is_admin == true) {
          $booking_url = Url::fromRoute('sr.booking.edit', [
            'booking_id' => $val_result['id'],
          ], ['absolute' => TRUE])->toString();

          $booking_status_link = commonUtil::my_generate_hyperlink($booking_status_link, $booking_url);
          $property_info = $property_link . "(".$property_source.")";
        }

        $form['search_result']['result'][$key_result]['property'] = array(
          '#title_display' => 'invisible',
          '#markup' => $property_info,
        );
        $is_admin = in_array('administrator', $current_user->getRoles());
        $details = 
        '<strong>Name: </strong>' . $val_result["field_name"] . '<br>' .
        '<strong>Ref Id: </strong>SR' . $property_reference_id . '<br>' .
        '<strong>Email: </strong>' . $val_result["field_email"] . '<br>' .
        '<strong>Phone: </strong>' . $val_result["field_phone_number"] . '<br>' .
        '<strong>No. of Adults: </strong>' . $val_result["field_adults"] . '<br>' .
        '<strong>No. of kids: </strong>' . $val_result["field_kids"] . '<br>' .
        '<strong>No. of room: </strong>' . $no_of_rooms ;
        if ($is_admin) {
          $details .= '<br><strong>Supplier: </strong>' . $property_source;
        }

        $form['search_result']['result'][$key_result]['details'] = array(
          '#title_display' => 'invisible',
          '#markup' => $details,
        );

        $val_result['field_from_date'] = new DrupalDateTime($val_result['field_from_date']);
        $val_result['field_from_date'] = date('d-m-y', strtotime($val_result['field_from_date']));

        $val_result['field_to_date'] = new DrupalDateTime($val_result['field_to_date']);
        $val_result['field_to_date'] = date('d-m-y', strtotime($val_result['field_to_date']));

        $date = '<strong>From: </strong>' . $val_result['field_from_date'] . '<br>' .
                '<strong>To: </strong>' . $val_result['field_to_date'];

        $form['search_result']['result'][$key_result]['date'] = array(
          '#title_display' => 'invisible',
          '#markup' => $date,
        );

        $selected_currency = (isset($arr_currency_list[$val_result['field_currency_code']])) ? $arr_currency_list[$val_result['field_currency_code']] : 'NA'."";

        $val_result['field_price'] = ($val_result['field_price'] != '') ? commonUtil::formatCurrency($val_result['field_price']) : '';

        $form['search_result']['result'][$key_result]['price'] = array(
          '#title_display' => 'invisible',
          '#markup' => $selected_currency . " ".$val_result['field_price']."",
        );
        $form['search_result']['result'][$key_result]['status'] = array(
          '#title_display' => 'invisible',
          '#markup' => "".$booking_status_link."",
        );

        $form['search_result']['result'][$key_result]['remark'] = array(
          '#title_display' => 'invisible',
          '#markup' => "".$val_result['field_remarks']."",
        );

        $required_fields[] = [
          'booking_id' => $custom_id,
          'property' => $property_link,
          'ref_id' => 'SR'.$property_reference_id,
          'property_source' => $property_source,
          'adults' => $val_result['field_adults'],
          'kids' => $val_result['field_kids'],
          'no_of_rooms' => $no_of_rooms,
          'name' => $val_result['field_name'],
          'email' => $val_result['field_email'],
          'phone' => $val_result['field_phone_number'],
          'from_date' => $val_result['field_from_date'],
          'to_date' => $val_result['field_to_date'],
          'price' => $selected_currency . " " . $val_result['field_price'],
          'status' => $arr_status_list[$val_result['field_status']],
          'additional_info' => $val_result['field_remarks'],
        ];
      }
      
      \Drupal::service('tempstore.private')->get('booking_data')->set('search_results', $required_fields);

      if (in_array('administrator', $current_user->getRoles())) {
        $form['search_result']['footer'] = [
          '#type' => 'container',
          '#attributes' => ['class' => ['table-footer']],
          'export_button' => [
            '#type' => 'link',
            '#title' => $this->t('Export CSV'),
            '#url' => Url::fromRoute('sr.export_csv'),
            '#attributes' => ['class' => ['button']],
          ],
        ];
      }
      if (isset($arr_result['search_pager'])) {
        $form['search_result']['pager'] = [
          '#type' => 'item',
          '#markup' => $arr_result['search_pager'],
        ];
      }
    }
    else {
      $form['search_result']['no_result'] = [
        '#type' => 'item',
        '#title' => t('Result'),
        '#markup' => t('Sorry, no booking found!'),
      ];
    }

    $form['#theme'] = 'sr_booking_search_form';

    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state) {

  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $url = Url::fromRoute('sr.booking.search', [
      'status' => urlencode($form_state->getValue('status')),
    ], ['absolute' => TRUE])->toString();

    commonUtil::my_goto($url);
  }
}
