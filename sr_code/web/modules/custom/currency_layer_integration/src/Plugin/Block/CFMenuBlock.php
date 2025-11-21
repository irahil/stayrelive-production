<?php
namespace Drupal\currency_layer_integration\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Link;
use Drupal\Core\Url;

/**
* Provides a block with Currency menu.
*
* @Block(
*   id = "cf_menu_block",
*   admin_label = @Translation("Currency Layer Menu block: basic"),
*   category = "Custom"
* )
*/
class CFMenuBlock extends BlockBase {

 /**
  * {@inheritdoc}
  */
 public function build() {
  $default_currency = 'USD';
  $request = \Drupal::request();
  if (is_object($request)) {
    $session = $request->getSession();
    if ($session->get('currency_layer_integration_selected') == '') {
      $session->set('currency_layer_integration_selected', $default_currency);
    }
  }
  $arr_currency = \Drupal::config('currency_layer_integration.settings')->get('currency_layer_integration.currency_list');
  $destination = \Drupal::request()->getRequestUri();

  $arr_currency_data = array();
  foreach($arr_currency as $key_curr => $val_curr) {
    if ($val_curr != 0) {
      $arr_currency_data[$key_curr] = Url::fromRoute('currency_layer_integration.change',
        array('currency' => urldecode($key_curr)), ['query' => ['destination' => $destination], 'absolute' => TRUE])->toString();
    }
  }

  $session = $request->getSession();

  return [
    '#theme' => 'cf_menu_block',
    '#arr_currency_data' => $arr_currency_data,
    '#selected_currency' => $session->get('currency_layer_integration_selected'),
  ];
 }

  public function getCacheMaxAge() {
    return 0;
  }

}