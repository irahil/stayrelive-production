<?php
namespace Drupal\vendor_management;

use Drupal\views\Views;

use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\path_alias\Entity\PathAlias;
use Drupal\propertyvendor\Entity\Propertyvendor;

/**
 * Class to create a Property node type.
 */
class vendor {

  public static function searchVendor($arg_data, $item_per_page = 5) {
    $view = Views::getView('vendor_list');
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


  public static function addVendor($arg_data) {
    Propertyvendor::create([
      'name' => $arg_data['name'],
      'info' => $arg_data['info'],
    ])->save();
  }



}
