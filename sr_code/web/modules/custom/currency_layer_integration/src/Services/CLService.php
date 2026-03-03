<?php

namespace Drupal\currency_layer_integration\Services;

use Drupal\Core\Session\AccountInterface;
use GuzzleHttp\Client;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Class CustomService
 * @package Drupal\currency_layer_integration\Services
 */
class CLService {

  protected $currentUser;
  protected $cache_id_live;
  protected $cache_id_list;

  var $api_param = array();

  /**
   * CustomService constructor.
   * @param AccountInterface $currentUser
   */
  public function __construct(AccountInterface $currentUser) {
    $this->currentUser = $currentUser;
    $this->cache_id_live = 'currency_layer_live';
    $this->cache_id_list = 'currency_layer_list';
  }

  public function getApiLiveData($cache = TRUE) {
    $result = FALSE;
    if ($cache == TRUE && $cache = \Drupal::cache()->get($this->cache_id_live)) {
      if (!empty($cache->data) && $cache->expire > time()) {
        $result = $cache->data;
      }
    }
    else {
      $result_currency = $this->getApiData('live');
      if (isset($result_currency['success']) && $result_currency['success'] == 1 && !empty($result_currency['quotes'])) {
        $expire_date = time() + (24 * 60 * 60);
        \Drupal::cache()->set($this->cache_id_live, $result_currency['quotes'], $expire_date);
        $result = $result_currency['quotes'];
      } else {
        \Drupal::logger('currency_layer_integration')->error('Live : Unable to fetch data '. print_r($result_currency, TRUE));
      }
    }
    return $result;
  }

  public function getApiListData($cache = FALSE) {
    $result = FALSE;
    if ($cache == TRUE && $cache = \Drupal::cache()->get($this->cache_id_list)) {
      if (!empty($cache->data) && $cache->expire > time()) {
        $result = $cache->data;
      }
    }
    else {
      $result_currency = $this->getApiData('list');
      if (isset($result_currency['success']) && $result_currency['success'] == 1 && !empty($result_currency['currencies'])) {
        $expire_date = time() + (24 * 60 * 60);
        \Drupal::cache()->set($this->cache_id_list, $result_currency['currencies'], $expire_date);
        $result = $result_currency['currencies'];
      } else {
        \Drupal::logger('currency_layer_integration')->error('List : Unable to fetch data '. print_r($result_currency, TRUE));
      }
    }
    return $result;
  }

  public function getApiData($api_type = 'live') {
    $result = array();
    try {
      $api_endpoint = \Drupal::config('currency_layer_integration.settings')->get('currency_layer_integration.api_endpoint');
      $api_key = \Drupal::config('currency_layer_integration.settings')->get('currency_layer_integration.api_key');

      $client = new Client();
      $url = $api_endpoint.$api_type."?access_key=".$api_key;
      $response = $client->get($url);
      $result = \GuzzleHttp\json_decode($response->getBody(), TRUE);

      if (!empty($result)) {
        return $result;
      }
    }
    catch (\Exception $error) {
      \Drupal::logger('currency_layer_integration')->error('Error to fetch data : ' . $error->getMessage());
      return $result;
    }
  }

  public function CFConvertUserCurrency($from_ctype = 'USD', $value = 1) {
    $to_ctype = $this->CFgetUserSessionCurrency();
    $currency_value = $this->CFConvert($from_ctype, $to_ctype, $value);

    if ($currency_value != FALSE) {
      //$currency_value = number_format((float)$currency_value, 2, '.', '');
      //$currency_value = round($currency_value);
      $currency_value = $currency_value;
    } else {
      $currency_value = 0;
    }
    return array('value' => $currency_value, 'from' => $from_ctype, 'to' => $to_ctype);
  }

  public function CFConvert($from_ctype = 'USD', $to_ctype = 'USD', $value = 1) {
    $currency_value = array();

    if ($from_ctype != '' && $to_ctype != '' && $value > 1) {
      $arr_api_live = $this->getApiLiveData();

      if ($from_ctype == 'USD') {
        $currency_usd_unit = 1;
      } else {
        $currency_usd_key = 'USD'.$from_ctype;
        $currency_usd_unit = $arr_api_live[$currency_usd_key];
      }
      if ($currency_usd_unit > 0) {
        $currency_usd_value = $value / $currency_usd_unit;
      } else {
        \Drupal::logger('currency_layer_integration')->error('CFConvert : Unable to get unit of USD to '.$from_ctype);
        return FALSE;
      }

      if ($to_ctype == 'USD') {
        $currency_unit = 1;
      } else {
        $currency_key = 'USD'.$to_ctype;
        $currency_unit = $arr_api_live[$currency_key];
      }
      $currency_value = $currency_usd_value * $currency_unit;
    }
    return $currency_value;
  }

  public function CFgetUserSessionCurrency() {
    $to_ctype = 'USD';
    $request = \Drupal::request();
    if (is_object($request) && $request->hasSession()) {
      $session = $request->getSession();
      $session_curr_selected = $session->get('currency_layer_integration_selected');
      $to_ctype = ($session_curr_selected != '') ? $session_curr_selected : $to_ctype;
    }
    return $to_ctype;
  }


}