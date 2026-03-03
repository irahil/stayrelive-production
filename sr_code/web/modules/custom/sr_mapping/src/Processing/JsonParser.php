<?php
namespace Drupal\sr_mapping\Processing;

use Drupal\sr_mapping\Processing\Importer;
use Drupal\taxonomy\Entity\Term;
use Drupal\paragraphs\Entity\Paragraph;

class JsonParser {

    public static function parse($property_name, $arr_property) {

        list($private_path_to_process, $private_path_completed_process, $private_path_error_process, $private_path_delete_process)
                        = Profiler::getProfileFilePath($property_name);
        try {
            $arr_file = glob("{$private_path_to_process}*.".$arr_property['file_type']);

            if (!isset($arr_file[0])) {
                \Drupal::logger('Sr_Process')->info('No Files found for provider : ' . $property_name . ' in ' . $private_path_to_process);
                return FALSE;
            }

            $str_json = file_get_contents($arr_file[0]);
            $decodedJson = json_decode($str_json, true);
            $result = [];

            $arr_node_load_data = array();
            $counter_diff_date = 0;
            foreach($decodedJson as $record) {
                if (!isset($record['data'])) {
                    $record['data'] = $record;
                }

                foreach ($arr_property['field_mapping'] as $key_node_field_name => $val_json_field_details) {
                    if (isset($val_json_field_details['direct'])) {
                        $arr_node_load_data[$key_node_field_name] = (isset($record['data'][$val_json_field_details['direct'][0]])) ? $record['data'][$val_json_field_details['direct'][0]] : '';
                    } else if (isset($val_json_field_details['level3'])) {
                        list($level1, $level2, $level3) = explode(":", $val_json_field_details['level3'][0]);
                        $arr_node_load_data[$key_node_field_name] = (isset($record['data'][$level1][$level2][$level3])) ? $record['data'][$level1][$level2][$level3] : '';
                    } else if (isset($val_json_field_details['level2'])) {
                        list($level1, $level2) = explode(":", $val_json_field_details['level2'][0]);
                        $arr_node_load_data[$key_node_field_name] =
                            $record['data'][$level1][$level2]
                            ?? $record['data'][$level1][0][$level2]
                            ?? '';
                    } else if (isset($val_json_field_details['serialize'])) {
                        $media_list = (isset($record['data'][$val_json_field_details['serialize'][0]])) ? ($record['data'][$val_json_field_details['serialize'][0]]) : '';
                        $arr_media_list = array();
                        foreach($media_list as $key_media_list => $value_media_list) {
                            $arr_media_list[$key_media_list]['type'] = "image";
                            $arr_media_list[$key_media_list]['url'] = $value_media_list['url'];

                        }
                        $arr_node_load_data[$key_node_field_name] = serialize($arr_media_list);
                    } else if (isset($val_json_field_details['taxonomy'])) {
                        $arr_terms = array();
                        $arr_node_load_data[$key_node_field_name] = array();
                        if (isset($val_json_field_details['taxonomy'][0]) && isset($record['data'][$val_json_field_details['taxonomy'][0]])) {
                            $taxonomy_data = $record['data'][$val_json_field_details['taxonomy'][0]];
                        } else if (isset($val_json_field_details['default'])) {
                            $taxonomy_data = $val_json_field_details['default'];
                        }
                        $arr_terms = Importer::getTaxonomyTerms($taxonomy_data, $val_json_field_details['machine_name']);

                        if (is_array($arr_terms) && count($arr_terms) > 0 ) {
                            $arr_node_load_data[$key_node_field_name] =  $arr_terms;
                        }

                    } else if (isset($val_json_field_details['level2:taxonomy'])) {
                        $arr_terms = array();
                        list($level1, $level2) = explode(":", $val_json_field_details['level2:taxonomy'][0]);

                        if (isset($record['data'][$level1][$level2])) {
                            $arr_terms = Importer::getTaxonomyTerms($record['data'][$level1][$level2], $val_json_field_details['machine_name']);
                        }
                        if (is_array($arr_terms) && count($arr_terms) > 0 ) {
                            $arr_node_load_data[$key_node_field_name] =  $arr_terms;
                        }
                    } else if (isset($val_json_field_details['paragraph'])) {
                        $arr_node_load_data[$key_node_field_name] = (isset($record['data'][$val_json_field_details['paragraph'][0]])) ? $record['data'][$val_json_field_details['paragraph'][0]] : '';
                    }else if (isset($val_json_field_details['composite'])) {
                        $arr_node_load_data[$key_node_field_name] = time();
                        $str_composite = '';
                        if ($key_node_field_name == 'title') {
                            $str_bedroom = '';
                            if ($record['data'][$val_json_field_details['composite'][0]] > 0) {
                                $str_bedroom = ($record['data'][$val_json_field_details['composite'][0]] > 1) ?
                                $record['data'][$val_json_field_details['composite'][0]] . ' bedrooms': $record['data'][$val_json_field_details['composite'][0]] . ' bedroom';
                            }

                            $str_funished_state = (isset($record['data'][$val_json_field_details['composite'][1]])) ? $record['data'][$val_json_field_details['composite'][1]] : '';
                            $str_property_type = (isset($record['data'][$val_json_field_details['composite'][2]])) ? $record['data'][$val_json_field_details['composite'][2]] : '';
                            $str_display_address = (isset($record['data'][$val_json_field_details['composite'][3]])) ? $record['data'][$val_json_field_details['composite'][3]] : '';
                            $str_composite =  $str_bedroom . " " . $str_funished_state . " " . $str_property_type . " " . $str_display_address;
                            $arr_node_load_data[$key_node_field_name] = $str_composite;
                        }
                    }
                }
                $arr_node_load_data['field_property_source'] = $property_name;

                //print_r($arr_node_load_data);exit;
                
                if($arr_node_load_data['field_reference_id'] != '' && $arr_node_load_data['field_available_from'] != '' && isset($arr_node_load_data['field_town_city']) && $arr_node_load_data['field_town_city'] != '') {
                    
                    $arr_node_load_data['field_available_from'] = \DateTime::createFromFormat('Y/m/d', $arr_node_load_data['field_available_from'])->format('Y-m-d');
                    $your_date = strtotime($arr_node_load_data['field_available_from']);
                    $diff_days_timestamp = $your_date - time();
                    $diff_days = round($diff_days_timestamp / (60 * 60 * 24));
                    // Skip records if date is more than load_days
                    if (1==1 || $diff_days <= 0 || $arr_property['load_days'] > $diff_days) {
                        $result[$arr_node_load_data['field_reference_id']] = Importer::createProperty($arr_node_load_data);
                        echo "\nReference ID : " . $arr_node_load_data['field_reference_id'] . " - Node ID : " . $result[$arr_node_load_data['field_reference_id']] . " - Diff Date : " . $diff_days;
                        Importer::writeDeleteFile($private_path_delete_process, $arr_node_load_data['field_reference_id']);
                    } else {
                        $counter_diff_date++;
                        echo "\nNot importing Reference ID : " . $arr_node_load_data['field_reference_id'] . " - Diff Date : " . $diff_days;
                    }
                }

            }
            if (count($result) > 0) {
                Importer::renameDeleteFile($private_path_delete_process);
            }
            echo "\nTotal records imported ". count($result);
            echo "\nTotal records Date Diff ". $counter_diff_date;

            //print_r($result);
        }
        catch (\Exception $e) {
            echo 'Caught exception: ',  $e->getMessage(), "\n";
        }
        return $result;
    }
}
