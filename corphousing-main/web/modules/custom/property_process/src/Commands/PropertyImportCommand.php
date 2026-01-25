<?php

namespace Drupal\property_process\Commands;
use Drupal\common_utilities\Utilities\commonUtil;
use Drupal\property_process\propertyImport;
use Drupal\node\Entity\Node;
use Drush\Commands\DrushCommands;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityStorageInterface;

class PropertyImportCommand extends DrushCommands {

  protected $arr_fields = [
    'field_property_type' => 'property type',
    'field_property_name' => 'property name',
    'field_area_name' => 'area name',
    'field_description' => 'property description',
    'field_display_address' => 'address',
    'field_country' => 'country',
    'field_state' => 'state',
    'field_city' => 'city',
    'field_pincode' => 'pincode',
    'field_amenities' => 'property amenities',
    'field_hosting_type' => 'hosting type',
    'field_contact_person' => 'property contact person',
    'field_contact_email_id' => 'property contact email',
    'field_contact_number' => 'property contact number',
    'field_room_type' => 'room type',
    'field_room_amenities' => 'room amenities',
    'field_breakfast_provides' => 'breakfast provided',
    'field_room_price' => 'average price',
    'field_tax_details' => 'tax details (gst/vat)',
    'field_latitude' => 'latitude',
    'field_longitude' => 'longitude',
    'field_room_description' => 'room description',
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
        $header_array = fgetcsv($handle);
        if (!$header_array) {
          fclose($handle);
          $this->logError($entity, message: "CSV file is empty or has no header: " . $file_path);
          continue;
        }
        $header = array_map('strtolower', $header_array);

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
          $str_room_type_id = [];

          $property_name = $row_data['property name'] ?? '';
          $property_area_name = $row_data['area name'] ?? '';

          if (empty($property_name) || empty($property_area_name)) {
            $error_message.= "Property name or Area Name is missing. Skipping row number : $row_index\n";
            $this->logger()->log('error', $error_message);
            $file_status = "completed with error";
            continue;
          }
          $property_key = commonUtil::generate_property_id($property_name, $property_area_name);

          $arr_property_data['row_index'] = $row_index;
          $arr_property_data['field_vendor_id'] = $vendor_id;

          foreach ($this->arr_fields as $field_name => $field_label) {
            if (isset($row_data[$field_label])) {
              $value = trim($row_data[$field_label]);
              if (!empty($value)) {
                // Convert name to term ID.
                if ($field_name == 'field_city') {
                  $value = commonUtil::getTermIdByName($value, 'city');
                }
                if ($field_name == 'field_country') {
                  $value = commonUtil::getTermIdByName($value, 'country');
                }
                if ($field_name == 'field_state') {
                  $value = commonUtil::getTermIdByName($value, 'state');
                }
                if ($field_name == 'field_property_type') {
                  $value = commonUtil::getTermIdByName($value, 'property_type');
                }
                if ($field_name == 'field_room_type') {
                  $value = commonUtil::getTermIdByName($value, 'room_type');
                  $str_room_type_id = $value;
                }
                if (in_array($field_name, ['field_amenities', 'field_room_amenities'])) {

                  $arr_terms = array_map('trim', explode(',', $value));

                  $arr_term_ids = [];
                  foreach ($arr_terms as $term_name) {
                    if (!empty($term_name)) {
                      $vocabulary_name = trim(str_replace('field_', '', $field_name));
                      if ($vocabulary_name == 'amenities') {
                        $vocabulary_name = 'property_amenities';
                      }
                      $term_id = commonUtil::getTermIdByName($term_name, $vocabulary_name);
                      if ($term_id) {
                        $arr_term_ids[] = ['target_id' => $term_id];
                      }
                    }
                  }
                  $value = $arr_term_ids;
                }
              }

              $arr_property_data[$field_name] = $value;

            } else {
              $arr_property_data[$field_name] = $arr_property_data[$field_name] ?? '';
            }

            if (in_array($field_name, ['field_room_amenities', 'field_room_type', 'field_number_of_units', 'field_room_description', 'field_room_price' , 'field_tax_details'])) {
              $arr_room_data[$field_name] = $arr_property_data[$field_name];
              $arr_room_data['room_row_index'] = $row_index;
              unset($arr_property_data[$field_name]);
            } 
          }

          $node_content[$property_key]['property_data'] = $arr_property_data;
          $node_content[$property_key]['room_data'][] = $arr_room_data;
          $node_content[$property_key]['room_types'][] = $str_room_type_id;
        }
        fclose($handle);
//print_r($node_content);echo "END";exit;

        foreach ($node_content as $property_key => $content) {
          $arr_room_type = $content['room_types'];
          $arr_room_type = array_map(fn($tid) => ['target_id' => $tid], $arr_room_type);

          $row_index = $content['property_data']['row_index'] ?? 0;
          unset($content['property_data']['row_index']);

          $content['property_data']['field_room_types'] = $arr_room_type;
          unset($content['room_types']);
          $content['property_data']['field_property_id'] = $property_key;
          $content['property_data']['title'] = $content['property_data']['field_property_name'];

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
            $room['title'] = $content['property_data']['field_property_name'] . " - " . commonUtil::getTermById($room['field_room_type']);
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
            $room['vendor_id'] = $vendor_id;

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
