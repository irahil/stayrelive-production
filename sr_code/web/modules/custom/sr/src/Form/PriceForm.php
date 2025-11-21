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


class PriceForm extends FormBase {
  var $arr_request_param = array(
    'country_code' => '', 'pid' => '',
    'price_min' => '', 'price_max' => '',
    'bathroom' => '', 'bedroom' => '',
    'date_range' => '', 'town_city' => '',
    'adult' => '', 'kid' => '',
    'amenities' => '', 'sort' => '',
  );

  public function getFormId() {
    return 'price';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $flag_product_detail = true;
    $flag_show_price = true;
    $form['error'] = array(
      '#title_display' => 'invisible',
      '#markup' => "Sorry, something went wrong. Please try again later.",
      '#prefix' => '<div class="form-error">',
      '#suffix' => '</div>',
    );

    $pid = \Drupal::request()->query->get('pid');

    if ($pid == '' || $pid <= 0) {
      $pid = 0;
      $node = \Drupal::routeMatch()->getParameter('node');
      if ($node instanceof \Drupal\node\NodeInterface) {
        $pid = $node->id();
      } else {
        // redirect user to error page.
      }
    }

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

    $form_param = array();

    foreach ($this->arr_request_param as $key_param => $val_param) {
      $query_value = \Drupal::request()->query->get($key_param);
      if ($query_value != '') {
        $form_param[$key_param] = urldecode(trim($query_value));
      } else {
        $form_param[$key_param] = '';
      }
    }

    if ($form_param['date_range'] == '') {
      // Set Dates
      list($from_date, $to_date) = \Drupal::service('sr.services')->convertDateRange($form_param['date_range']);
      // Setting it again to make sure current date is set incase of empty date.
      $form_param['date_range'] = $from_date . " to " . $to_date;
    }

    $form_param['pid'] = $pid;

    $arr_block_param = \Drupal::service('sr.services')->generatePriceInfo($pid, $form_param);

    // \Drupal::logger('Sr')->info('arr_block_param: ' . print_r($arr_block_param, true));

    $arr_block_param['price'] = ($arr_block_param['price'] != '') ? commonUtil::formatCurrency($arr_block_param['price']) : '';
    $arr_block_param['deposit'] = ($arr_block_param['deposit'] != '') ? commonUtil::formatCurrency($arr_block_param['deposit']) : '';
    $arr_block_param['price_for_days'] = ($arr_block_param['price_for_days'] != '') ? commonUtil::formatCurrency($arr_block_param['price_for_days']) : '';
    $arr_block_param['final_price'] = ($arr_block_param['final_price'] != '') ? commonUtil::formatCurrency($arr_block_param['final_price']) : '';

    if ($arr_block_param['price'] <= 0) {
      $flag_show_price = false;
      $arr_block_param['currency_code'] = '';
      $arr_block_param['price_for_days'] = 'Price on Request';
      $arr_block_param['final_price'] = 'Price on Request';
    }

    $arr_block_param['flag_product_detail'] = $flag_product_detail;
    $arr_block_param['flag_show_price'] = $flag_show_price;

    foreach ($arr_block_param as $key_block => $val_block) {
      $form['price_summary'][$key_block] = ['#markup' => $val_block];
    }

    //\Drupal::logger('Sr')->info('arr_block_param : ' . print_r($arr_block_param, true));

    $form['#attached']['library'][] = 'sr/sr_lib';

    $form['property_id'] = [
      '#type' => 'hidden',
      '#attributes' => array(
        'readonly' => 'readonly',
      ),
      '#default_value' => $pid,
      '#required' => TRUE,
    ];

    $form['date_range'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Date Range'),
      '#date_date_format' => 'Y-m-d',
      '#default_value' => $form_param['date_range'],
      '#attributes' => array(
        'id' => array('flatpickr_date_range'),
        'placeholder' => array('Dates'),
      )
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Update Price'),
      '#weight' => 110,
    ];

    $form['#theme'] = 'sr_property_price_form';
    //$form['#price_summary'] = $arr_block_param;

    return $form;
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
