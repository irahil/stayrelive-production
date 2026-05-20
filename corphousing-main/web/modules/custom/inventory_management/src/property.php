<?php
namespace Drupal\inventory_management;

use Drupal\views\ViewExecutable;
use Drupal\views\Views;

use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\path_alias\Entity\PathAlias;
use Drupal\propertyfile\Entity\Propertyfile;


class property {

  /**
   * Maps URL/form keys to Views exposed identifiers (entity/taxonomy target_id).
   */
  protected static function normalizePropertyViewExposedInput(array &$query) {
    if (isset($query['vendor_id']) && $query['vendor_id'] !== '' && $query['vendor_id'] !== NULL) {
      $query['field_vendor_id_target_id'] = $query['vendor_id'];
      unset($query['vendor_id']);
    }
    elseif (isset($query['field_vendor_id']) && $query['field_vendor_id'] !== '' && $query['field_vendor_id'] !== NULL) {
      $query['field_vendor_id_target_id'] = $query['field_vendor_id'];
      unset($query['field_vendor_id']);
    }

    if (isset($query['city']) && $query['city'] !== '' && $query['city'] !== NULL) {
      $query['field_city_target_id'] = $query['city'];
      unset($query['city']);
    }
    elseif (isset($query['field_city']) && $query['field_city'] !== '' && $query['field_city'] !== NULL) {
      $query['field_city_target_id'] = $query['field_city'];
      unset($query['field_city']);
    }
  }

  /**
   * Copies city/vendor values onto each matching exposed filter's real identifier.
   *
   * Views only reads keys that match "Expose filter" → Identifier in the UI.
   * If that identifier is "city" but we send "field_city_target_id", no filter runs.
   */
  protected static function syncPropertyViewExposedIdentifiers(ViewExecutable $view, array &$query): void {
    if (!$view->display_handler || !is_array($query)) {
      return;
    }
    $view->initHandlers();

    $city_tid = NULL;
    if (array_key_exists('field_city_target_id', $query) && $query['field_city_target_id'] !== '' && $query['field_city_target_id'] !== NULL) {
      $city_tid = $query['field_city_target_id'];
    }

    $vendor_id = NULL;
    if (array_key_exists('field_vendor_id_target_id', $query) && $query['field_vendor_id_target_id'] !== '' && $query['field_vendor_id_target_id'] !== NULL) {
      $vendor_id = $query['field_vendor_id_target_id'];
    }

    $handlers = $view->display_handler->getHandlers('filter');
    if (empty($handlers)) {
      return;
    }

    $city_identifiers = [];
    $vendor_identifiers = [];
    $combine_identifiers = [];

    $property_name = NULL;
    if (array_key_exists('property_name', $query) && $query['property_name'] !== '' && $query['property_name'] !== NULL) {
      $property_name = trim((string) $query['property_name']);
      if ($property_name === '') {
        $property_name = NULL;
      }
    }

    foreach ($handlers as $handler_id => $handler) {
      if (!is_object($handler) || !method_exists($handler, 'isExposed') || !$handler->isExposed()) {
        continue;
      }
      $identifier = $handler->options['expose']['identifier'] ?? '';
      if ($identifier === '') {
        continue;
      }

      $plugin_id = method_exists($handler, 'getPluginId') ? (string) $handler->getPluginId() : '';
      $is_combine = (stripos($plugin_id, 'combine') !== FALSE)
        || (stripos((string) $handler_id, 'combine') !== FALSE);

      $id_lower = strtolower((string) $identifier);
      // Never treat the combine filter as city/vendor: its handler id can contain
      // field_vendor_id / field_city when those fields are part of the combined search.
      $is_city = !$is_combine && (
        (stripos((string) $handler_id, 'field_city') !== FALSE)
        || (stripos((string) $identifier, 'field_city') !== FALSE)
        || $id_lower === 'city'
      );
      $is_vendor = !$is_combine && (
        (stripos((string) $handler_id, 'field_vendor_id') !== FALSE)
        || (stripos((string) $identifier, 'field_vendor_id') !== FALSE)
        || $id_lower === 'vendor'
        || $id_lower === 'vendor_id'
      );

      if ($property_name !== NULL && $is_combine) {
        $query[$identifier] = $property_name;
        $combine_identifiers[] = $identifier;
      }
      if ($city_tid !== NULL && $is_city) {
        $query[$identifier] = $city_tid;
        $city_identifiers[] = $identifier;
      }
      if ($vendor_id !== NULL && $is_vendor) {
        $query[$identifier] = $vendor_id;
        $vendor_identifiers[] = $identifier;
      }
    }

    if ($combine_identifiers !== []) {
      if (!in_array('property_name', $combine_identifiers, TRUE)) {
        unset($query['property_name']);
      }
    }

    if ($city_tid !== NULL && $city_identifiers === []) {
      $query['city'] = $city_tid;
    }

    if ($vendor_id !== NULL && $vendor_identifiers === []) {
      $query['vendor_id'] = $vendor_id;
    }

    if ($city_identifiers !== []) {
      unset($query['city'], $query['field_city']);
      if (!in_array('field_city_target_id', $city_identifiers, TRUE)) {
        unset($query['field_city_target_id']);
      }
    }
    if ($vendor_identifiers !== []) {
      unset($query['vendor_id'], $query['field_vendor_id']);
      if (!in_array('field_vendor_id_target_id', $vendor_identifiers, TRUE)) {
        unset($query['field_vendor_id_target_id']);
      }
    }
  }

  /**
   * Merges programmatic exposed values with Views' request/session baseline.
   */
  protected static function applyViewExposedInput(ViewExecutable $view, array $query): void {
    $view->initHandlers();
    $baseline = $view->getExposedInput();
    if (!is_array($baseline)) {
      $baseline = [];
    }
    $view->setExposedInput(array_merge($baseline, $query));
  }

  /**
   * Hard guard: keep only rows belonging to the requested vendor.
   */
  protected static function filterSearchResultsByVendor(array $search_result, $vendor_id): array {
    $vendor_id = (string) $vendor_id;
    if ($vendor_id === '' || empty($search_result)) {
      return $search_result;
    }

    $filtered = [];
    foreach ($search_result as $row) {
      $nid = isset($row['nid']) ? (int) $row['nid'] : 0;
      if ($nid <= 0) {
        continue;
      }
      $node = Node::load($nid);
      if (!$node || !$node->hasField('field_vendor_id') || $node->get('field_vendor_id')->isEmpty()) {
        continue;
      }

      $node_vendor_id = (string) $node->get('field_vendor_id')->value;
      if ($node_vendor_id === $vendor_id) {
        $filtered[] = $row;
      }
    }

    return $filtered;
  }

  public static function searchProperty($arg_data, $item_per_page = NULL) {
    $view = Views::getView('property');
    $view->setDisplay('default');
    $view->get_total_rows = TRUE;

    if (is_array($arg_data['query']) && count($arg_data['query']) > 0) {
      foreach ($arg_data['query'] as $key_query => $val_query) {
        if ($key_query == "status" && $val_query == '') {
          $arg_data['query'][$key_query] = "0";
        }
      }
    }
    $original_query = $arg_data['query'];
    static::normalizePropertyViewExposedInput($arg_data['query']);
    static::syncPropertyViewExposedIdentifiers($view, $arg_data['query']);
    static::applyViewExposedInput($view, $arg_data['query']);
    $view->preExecute();
    if ($item_per_page !== NULL) {
      $view->setItemsPerPage((int) $item_per_page);
    }
    $view->execute();
    $rows = $view->total_rows;

    $search_result = array();
    foreach ($view->result as $rid => $row) {
      foreach ($view->field as $fid => $field ) {
        $search_result[$rid][$fid] = $field->getValue($row);
      }
    }

    // Enforce vendor-level visibility irrespective of Views exposed filter identifiers.
    $vendor_filter = NULL;
    if (isset($original_query['vendor_id']) && $original_query['vendor_id'] !== '' && $original_query['vendor_id'] !== NULL) {
      $vendor_filter = $original_query['vendor_id'];
    }
    elseif (isset($original_query['field_vendor_id']) && $original_query['field_vendor_id'] !== '' && $original_query['field_vendor_id'] !== NULL) {
      $vendor_filter = $original_query['field_vendor_id'];
    }
    elseif (isset($arg_data['query']['field_vendor_id_target_id']) && $arg_data['query']['field_vendor_id_target_id'] !== '' && $arg_data['query']['field_vendor_id_target_id'] !== NULL) {
      $vendor_filter = $arg_data['query']['field_vendor_id_target_id'];
    }
    if ($vendor_filter !== NULL) {
      $search_result = static::filterSearchResultsByVendor($search_result, $vendor_filter);
      $rows = count($search_result);
    }
    $pager = '';
    if($search_result) {
      $arr_pager = $view->pager->render(array());
      $pager = \Drupal::service('renderer')->render($arr_pager);
    }

    return array('search_result' => $search_result, 'search_pager' => $pager, 'result_count' => $rows);
  }

  public static function countProperty($arg_data) {
    $view = Views::getView('property');
    $view->setDisplay('default');
    $view->get_total_rows = TRUE;

    if (is_array($arg_data['query']) && count($arg_data['query']) > 0) {
      foreach ($arg_data['query'] as $key_query => $val_query) {
        if ($key_query == "status" && $val_query == '') {
          $arg_data['query'][$key_query] = "0";
        }
      }
    }

    $original_query = $arg_data['query'];
    static::normalizePropertyViewExposedInput($arg_data['query']);
    static::syncPropertyViewExposedIdentifiers($view, $arg_data['query']);
    static::applyViewExposedInput($view, $arg_data['query']);
    $view->preExecute();
    $view->execute();

    // Apply the same vendor hard-guard as searchProperty() to ensure counts
    // are scoped to the requested vendor, not the entire platform.
    $vendor_filter = NULL;
    if (isset($original_query['vendor_id']) && $original_query['vendor_id'] !== '' && $original_query['vendor_id'] !== NULL) {
      $vendor_filter = $original_query['vendor_id'];
    }
    elseif (isset($original_query['field_vendor_id']) && $original_query['field_vendor_id'] !== '' && $original_query['field_vendor_id'] !== NULL) {
      $vendor_filter = $original_query['field_vendor_id'];
    }
    elseif (isset($arg_data['query']['field_vendor_id_target_id']) && $arg_data['query']['field_vendor_id_target_id'] !== '' && $arg_data['query']['field_vendor_id_target_id'] !== NULL) {
      $vendor_filter = $arg_data['query']['field_vendor_id_target_id'];
    }

    if ($vendor_filter !== NULL) {
      $search_result = [];
      foreach ($view->result as $rid => $row) {
        foreach ($view->field as $fid => $field) {
          $search_result[$rid][$fid] = $field->getValue($row);
        }
      }
      $search_result = static::filterSearchResultsByVendor($search_result, $vendor_filter);
      return count($search_result);
    }

    return $view->total_rows;
  }  

}
