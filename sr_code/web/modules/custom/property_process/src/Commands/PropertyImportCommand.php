<?php

namespace Drupal\property_process\Commands;
use Drupal\common_utilities\Utilities\commonUtil;
use Drupal\property_process\propertyImport;
use Drupal\node\Entity\Node;
use Drush\Commands\DrushCommands;
use Drupal\sr_mapping\Processing\Importer;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityStorageInterface;

class PropertyImportCommand extends DrushCommands {

  protected $arr_fields = [
    'field_property_type' => 'Property type',
    'title' => 'Property name',
    'field_town_city' => 'Town City',
    'field_description' => 'Property Description',
    'field_display_address' => 'Address',
    'field_location_country_code' => 'Country',
    'field_amenities' => 'Amenities',
    'field_price' => 'Price',
    'field_location_coords_latitude' => 'Latitude',
    'field_location_coords_longitude' => 'Longitude',
    'field_street_name' => 'Street Name',
    'field_currency_code' => 'Currency Code',
    'field_total_bedrooms' => 'Total Bedrooms',
    'ROOM_DESC' => 'Room Description',
  ];
  /**
   * Command description here.
   *
   * @param $message
   *   Argument description.
   * @param array $options
   *   An associative array of options whose values come from cli, aliases, config, etc.
   * @option status
   *   status : Site status
   * @usage expteche_drush_command:message Welcome to exptechestatus
   *   Usage description
   *
   * @command expteche_drush_command:message
   * @aliases exp_message
   */
  public function print($message = 'Default message', $options = ['custom' => false]) {
    if ($options['custom']) {
      $this->logger()->log('notice',dt('Implementing Custom code'));
      # code
      return true;
    }
    return $this->logger()->log('success',dt('Message: @msg',['@msg'=>$message]));
  }

  /**
   * Command description here.
   *
   * @usage property_drush_command:import Import property
   *   Usage description
   *
   * @command property:import
   * @aliases property:imp
   */

  public function importProperty() {
    echo "Importing Property\n";

    $storage = \Drupal::entityTypeManager()->getStorage('propertyfile');
    $entities = $storage->loadByProperties(['status' => TRUE, 'file_status' => 'uploaded']);

    if (empty($entities)) {
      \Drupal::logger('property_process')->notice('No property file to process.');
      return;
    }

    // Make sure $this->arr_fields exists.
    if (empty($this->arr_fields) || !is_array($this->arr_fields)) {
      \Drupal::logger('property_process')->error('arr_fields property is not defined.');
      return;
    }

    foreach ($entities as $entity) {
      $file_status = "completed";
      $error_message = "";

      $id = $entity->id();
      $label = $entity->label();

      // Get the file entity.
      $file = $entity->get('file')->entity;
      $vendor_id = $entity->get('vendor_id')->value;

      if (empty($vendor_id) || $vendor_id <= 0) {
        $this->logError($entity, message: "Vendor ID is not set for entity ID: " . $id);
        continue; // skip this entity, do not abort the whole import
      }

      if (!$file) {
        $this->logError($entity, message: "No file attached for entity ID: " . $id);
        continue;
      }

      $file_path = \Drupal::service('file_system')->realpath($file->getFileUri());
      if (!file_exists($file_path)) {
        $this->logError($entity, message: "File not found: " . $file_path);
        continue;
      }

      echo "ID: $id | Label: $label File $file_path\n";

      if (($handle = fopen($file_path, 'r')) !== FALSE) {
        // Read the header row.
        $header = fgetcsv($handle);
        if (!$header) {
          fclose($handle);
          $this->logError($entity, message: "CSV file is empty or has no header: " . $file_path);
          continue;
        }

        $row_index = 0;
        $node_content = [];

        while (($row = fgetcsv($handle)) !== FALSE) {
          $row_index++;

          // Skip empty rows.
          if (empty(array_filter($row))) {
            continue;
          }

          // Ensure row and header have same number of columns.
          if (count($header) !== count($row)) {
            $this->logError(
              $entity,
              message: "Row " . $row_index . " has " . count($row) . 
                      " columns but header has " . count($header)
            );
            continue;
          }

          // Map row values to column names.
          $row_data = array_combine($header, $row);

          $arr_property_data = [];
          $arr_room_data = [];

          if (empty($row_data['Property name']) || empty($row_data['Latitude']) || empty($row_data['Longitude'])) {
            $error_message.= "Property name or Latitude or Longitude is missing. Skipping row number : $row_index\n";
            $this->logger()->log('error', $error_message);
            $file_status = "completed with error";
            continue;
          }

          $vendor_name = commonUtil::getVendorNameById($vendor_id);

          $arr_property_data['row_index'] = $row_index;
          $arr_property_data['field_property_source'] = $vendor_name;

          $arr_property_data['field_property_type'] = commonUtil::getTermIdByName($row_data['Property type'] ?? '', 'property_type');
          $arr_property_data['title'] = $row_data['Property name'] ?? '';
          $arr_property_data['field_town_city'] = Importer::getTaxonomyTerms($row_data['Town City'] ?? '', 'town_city');
          $arr_property_data['field_description'] = $row_data['Property Description'] ?? '';
          $arr_property_data['field_display_address'] = $row_data['Address'] ?? '';
          
          $arr_property_data['field_location_country_code'] = commonUtil::getTermIdByName(commonUtil::getCountryIso2Code($row_data['Country'] ?? ''), 'country');

          $arr_property_data['field_location_coords_latitude'] = $row_data['Latitude'] ?? '';
          $arr_property_data['field_location_coords_longitude'] = $row_data['Longitude'] ?? '';
          $arr_property_data['field_street_name'] = $row_data['Street Name'] ?? '';
          $property_reference_id = commonUtil::generate_property_id($row_data['Property name'], $row_data['Latitude'] ?? '', $row_data['Longitude'] ?? '');
          $arr_property_data['field_reference_id'] = $property_reference_id;
          $arr_property_data['field_available_from'] = date('Y-m-d',  time());

          // Room data
          $arr_room_data['room_row_index'] = $row_index;
          $arr_room_data['title'] = $row_data['Total Bedrooms'] . " bedroom, " . $row_data['Property name'];
          $arr_room_data['field_price'] = $row_data['Price'] ?? '';
          $arr_room_data['field_has_price'] = !empty($row_data['Price']) && $row_data['Price'] > 0 ? 1 : 0;
          $arr_room_data['field_total_bedrooms'] = $row_data['Total Bedrooms'] ?? '';
          $arr_room_data['field_currency_code'] = commonUtil::getTermIdByName($row_data['Currency Code'] ?? '', 'currency');
          $arr_room_data['field_available_from'] = date('Y-m-d',  time());

          $arr_room_data['field_room_description'] = $row_data['Room Description'] ?? '';
          $arr_amenities_terms = array_map('trim', explode(',', $row_data['Amenities'] ?? ''));

          $arr_amenities_term_ids = [];
          foreach ($arr_amenities_terms as $amenities_term_name) {
            if (!empty($amenities_term_name)) {
              $term_id = commonUtil::getTermIdByName($amenities_term_name, 'amenities');
              if ($term_id) {
                $arr_amenities_term_ids[] = ['target_id' => $term_id];
              }
            }
          }
          
          $arr_room_data['field_amenities'] = $arr_amenities_term_ids ?? '';

          $node_content[$property_reference_id]['property_data'] = $arr_property_data;
          $node_content[$property_reference_id]['room_data'][] = $arr_room_data;
        }
        fclose($handle);
//print_r($node_content);echo "END";exit;
        foreach ($node_content as $property_key => $content) {
          $row_index = $content['property_data']['row_index'] ?? 0;
          unset($content['property_data']['row_index']);

          if (array_filter($content['property_data'], fn($v) => $v === '' || $v === null)) {
            // At least one element is exactly '' or null.
            // Build a readable string from property_data array
            $property_details = "";
            foreach ($content['property_data'] as $field => $value) {
              if (empty($value)) {
                $clean_field = str_replace("field_", "", $field);
                $property_details .= "  - $clean_field: " . (is_array($value) ? json_encode($value) : $value) . " <br> \n";
              }
            }

            $error_message .= "Some property data fields are empty or invalid. "
                            . "Skipping row number: $row_index\n"
                            . "Property details:\n"
                            . $property_details . "\n";
            $this->logger()->log('error', $error_message);
            $file_status = "completed with error";
            continue;
          }
//print_r($content);echo "END";exit;
          $obj_property_import = new propertyImport();
          $result = $obj_property_import->createProperty($content['property_data']);

          if ($result[1]) {
            $this->logger()->log('notice', "Inserted Property Node ID: " . $result[0] . " Property ID: " . $result[2]);
          } else {
            $this->logger()->log('notice', "Updated Property Node ID: " . $result[0] . " Property ID: " . $result[2]);
          }
          $parent_id = $result[0];

          foreach ($content['room_data'] as $room) {
            $room_row_index = $room['room_row_index'] ?? 0;
            unset($room['room_row_index']);
            if (array_filter($room, fn($v) => $v === '' || $v === null)) {
              // At least one element is exactly '' or null.
              // Build a readable string from $room array
              $room_details = "";
              foreach ($room as $field => $value) {
                if (empty($value)) {
                  $clean_field = str_replace("field_", "", $field);
                  $room_details .= "  - $clean_field: " . (is_array($value) ? json_encode($value) : $value) . " <br> \n";
                }
              }

              $error_message .= "Some room data fields are empty or invalid. "
                              . "Skipping room row number: $room_row_index\n"
                              . "Room details:\n"
                              . $room_details . "\n";
              $this->logger()->log('error', $error_message);
              $file_status = "completed with error";
              continue;
            }
            $room['field_parent_id'] = $parent_id;
            $room['field_property_source'] = $vendor_name;

            $result = $obj_property_import->createRoom($room);

            if ($result[1]) {
              $this->logger()->log('notice', "Inserted Room Node ID: " . $result[0] . " Property ID: " . $result[2]);
            } else {
              $this->logger()->log('notice', "Updated Room Node ID: " . $result[0] . " Property ID: " . $result[2]);
            }
          }
        }
        $entity->set('error', trim($error_message));
        $entity->set('file_status', $file_status);
        $this->logger()->log('notice', "Entity ID: " . $id . " File status updated to '" . $file_status."'");
        $entity->save();
      } else {
        \Drupal::logger('property_process')->error('Cannot open file: @path', ['@path' => $file_path]);
        $this->logError($entity, message: "Cannot open file: " . $file_path);
        continue;
      }
    }
  }



  public function logError($entity , $message) {
    $entity->set('file_status', 'error');
    $entity->set('error', $message);
    $this->logger()->log('error', $message);
    $entity->save();
  }
}
