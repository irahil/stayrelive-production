<?php
namespace Drupal\sr\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Component\Utility\Unicode;
use Drupal\node\Entity\Node;
use Drupal\common_utilities\Utilities\commonUtil;
use Drupal\views\Views;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\Response;

class SrController extends ControllerBase {
  public function main() {
    return array(
      '#markup' => 'Welcome to our Website.'
    );
  }

  public function BookingConfirmation() {
    return array(
      '#markup' => 'Booking request has been sent successfully!'
    );
  }

  public static function searchProperty($arg_data, $item_per_page = 5) {
    $view = Views::getView('property_view');
    $view->setDisplay('default');
    $view->get_total_rows = TRUE;

    foreach ($arg_data['query'] as $key_query => $val_query) {
      if ($val_query == '0') {
        $arg_data['query'][$key_query] = '';
      }
    }
    // Set exposed filters
    $view->setExposedInput($arg_data['query']);
    $view->preExecute();

    //$view->setOffset(1);
    $view->setItemsPerPage($item_per_page);
    $view->execute();

//$query = $view->query->query();
//$sql = (string) $query;
//echo $sql;exit;
    $rows = $view->total_rows;

    $search_result = array();
    foreach ($view->result as $rid => $row) {
      $search_result[$rid]['link'] = $view->result[$rid]->_entity->toUrl()->toString();

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

  public static function getMatchingTownCityTermIds($search_value, $limit = 10)
  {
    $results = [];

    if (!empty($search_value)) {
      $search_value = strtolower(trim($search_value));

      $term_storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
      $term_ids = \Drupal::entityQuery('taxonomy_term')
        ->accessCheck(TRUE)
        ->condition('vid', 'town_city')
        ->condition('name', '%' . $search_value . '%', 'LIKE')
        // ->range(0, $limit) // Limit the number of cities matched
        ->execute();

      if (!empty($term_ids)) {
        $terms = $term_storage->loadMultiple($term_ids);

        foreach ($terms as $term) {
          // Optional: count properties without loading them
          $property_count = \Drupal::entityQuery('node')
            ->accessCheck(TRUE)
            ->condition('type', 'property')
            ->condition('status', 1)
            ->condition('field_town_city.target_id', $term->id())
            ->count()
            ->execute();

          $results[] = [
            'city_name' => $term->label(),
            'tid' => $term->id(),
            'property_count' => $property_count,
            // 'hotels' => [] // optionally add hotels later
          ];
        }
      }
    }

    return $results;
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
