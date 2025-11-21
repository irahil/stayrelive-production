<?php
namespace Drupal\sr_mapping\Processing;

use Drupal\sr_mapping\Processing\Importer;
use Drupal\taxonomy\Entity\Term;
use Drupal\paragraphs\Entity\Paragraph;

class CsvParser {

    public static function parse($property_name, $arr_property) {
        list($private_path_to_process, $private_path_completed_process, $private_path_error_process, $private_path_delete_process)
                        = Profiler::getProfileFilePath($property_name);
        try {
            $arr_file = glob("{$private_path_to_process}*.".$arr_property['file_type']);
            if (!isset($arr_file[0]) || !file_exists($arr_file[0])) {
                \Drupal::logger('Sr_Process')->info('No Files found for provider : ' . $property_name . ' in ' . $private_path_to_process);
                return FALSE;
            }

            $handle = fopen($arr_file[0], "r");
            if ($handle) {
                $line_number = 1;
                $arr_node_load_data = array();
                $result = [];
                $counter_not_found = 0;
                $counter_diff_date = 0;

                while (($line = fgetcsv($handle)) !== FALSE) {
                    if ($line_number == 1) {
                        // Remove special char from array.
                        $line = preg_replace("/[^a-zA-Z 0-9_]+/", "", $line );
                        //$line = array_map('strtolower', $line);
                        $arr_header = array_flip($line);
//print_r($arr_header);exit;
                    } else {
                        /*
                        $latitude = (isset($line[$arr_header['latitude']]) && trim($line[$arr_header['latitude']]) != "") ? $line[$arr_header['latitude']] : "";
                        $longitude = (isset($line[$arr_header['longitude']]) && trim($line[$arr_header['longitude']]) != "") ? $line[$arr_header['longitude']] : "";
                        if ($latitude == "" || $longitude == "") {
                            continue;
                        }
                        $arr_location_info = \Drupal::service('sr.services')->getCityNameByLatitudeLongitude($latitude.",".$longitude);
                        */

                        $listingid = (isset($line[$arr_header['listingid']]) && trim($line[$arr_header['listingid']]) != "") ? $line[$arr_header['listingid']] : "";

                        $arr_api_info = \Drupal::service('sr.services')->getPlumGuideAPI($arr_property, $listingid);
//print_r($arr_api_info);echo "in\n";

//exit;
                        foreach ($arr_property['field_mapping'] as $key_node_field_name => $val_field_details) {
//print_r($line);exit;
                            // Direct
                            if (isset($val_field_details['direct'])) {
                                $str_data = (isset($val_field_details['direct'][0]) && isset($line[$arr_header[$val_field_details['direct'][0]]])) ? $line[$arr_header[$val_field_details['direct'][0]]] : '';
                                if ($str_data == '' && isset($val_field_details['default'])) {
                                    $str_data = $val_field_details['default'];
                                }
                                // Calculate date.
                                if (isset($val_field_details['rule']) && $val_field_details['rule'] == 'calculate_date') {
                                    if ($str_data == '' || !is_numeric($str_data)) {
                                        // Set blank so that record does not get inserted incase we dont have AVAILABLE Dates
                                        $str_data = '';
                                    } else {
                                        $current_date = date("Y-m-d");
                                        $avaiable_days = floor($str_data * 180);
                                        $available_from_date = date('Y-m-d', strtotime($current_date . ' +'.$avaiable_days.' day'));
                                        $str_data = $available_from_date;
                                    }
                                }
                                $arr_node_load_data[$key_node_field_name] = $str_data;
                            // Composite
                            } elseif (isset($val_field_details['composite'])) {
                                $str_composite = '';
                                foreach ($val_field_details['composite'] as $fields_composite => $text_composite) {
                                    if (isset($line[$arr_header[$fields_composite]])) {
                                        if ($fields_composite == 'bedroom_count') {
                                            // Skip bedroom if it is 0
                                            if (isset($line[$arr_header[$fields_composite]]) && $line[$arr_header[$fields_composite]] > 0) {
                                                $str_composite.= $line[$arr_header[$fields_composite]] . $text_composite . " ";
                                            }
                                        } else {
                                            $str_composite.= $line[$arr_header[$fields_composite]] . $text_composite . " ";
                                        }
                                    }

                                }
                                $str_composite = rtrim($str_composite, " ");
                                $arr_node_load_data[$key_node_field_name] = ($str_composite != '') ? $str_composite : 'Apartment - ' . time();
                            // Serialize
                            } elseif (isset($val_field_details['serialize'])) {
                                $arr_content = array();
                                foreach ($val_field_details['serialize'] as $val_content) {
                                    if (isset($line[$arr_header[$val_content]]) && $line[$arr_header[$val_content]] != '') {
                                        $arr_content[] = array('type' => 'image', 'url' => "http://".$line[$arr_header[$val_content]]);
                                    }
                                }
                                $arr_node_load_data[$key_node_field_name] = (!empty($arr_content)) ? serialize($arr_content) : '';
                            // Paragraph
                            } elseif (isset($val_field_details['paragraph'])) {
                                $arr_para[0]['heading'] = (isset($line[$arr_header['listing_name']])) ? $line[$arr_header['listing_name']] : 'Heading';
                                $arr_para[0]['text'] = (isset($line[$arr_header[$val_field_details['paragraph'][0]]])) ? $line[$arr_header[$val_field_details['paragraph'][0]]] : '';
                                $arr_node_load_data[$key_node_field_name] = $arr_para;
                            // Taxonomy
                            } elseif (isset($val_field_details['taxonomy'])) {
                                $arr_vocab = array();
                                if (!empty($val_field_details['taxonomy'])) {
                                    foreach ($val_field_details['taxonomy'] as $val_content) {
                                        if (isset($line[$arr_header[$val_content]]) && $line[$arr_header[$val_content]] != '') {
                                            $arr_vocab[] = $line[$arr_header[$val_content]];
                                        }
                                    }
                                }
                                // Setting Default
                                if (empty($arr_vocab) && isset($val_field_details['default'])) {
                                    $arr_vocab = $val_field_details['default'];
                                }

                                if (!empty($arr_vocab)) {
                                    // Checking for rules
                                    if (isset($val_field_details['rule']) && $val_field_details['rule'] == 'strip_number') {
                                        $arr_vocab = preg_replace("/[^a-zA-Z]+/", "", $arr_vocab);
                                    }

                                    $arr_terms = Importer::getTaxonomyTerms($arr_vocab, $val_field_details['machine_name']);
                                    if (is_array($arr_terms) && count($arr_terms) > 0 ) {
                                        $arr_node_load_data[$key_node_field_name] =  $arr_terms;
                                    }
                                }
                            // Direct API
                            } elseif (isset($val_field_details['level2:direct:api']) && !empty($arr_api_info)) {
                                $level2_direct_text = '';
                                foreach ($val_field_details['level2:direct:api'] as $val_field) {
                                    list($level1, $level2) = explode(":", $val_field);

                                    if (isset($arr_api_info->$level1)) {
                                        $level2_direct_text.= $arr_api_info->$level1->$level2;
                                    }
                                }
                                $arr_node_load_data[$key_node_field_name] = $level2_direct_text;

                            // taxonomy API
                            } elseif (isset($val_field_details['level2:taxonomy:api'])) {
                                $level2_taxonomy_text = '';
                                foreach ($val_field_details['level2:taxonomy:api'] as $val_field) {
                                    list($level1, $level2) = explode(":", $val_field);

                                    if (isset($arr_api_info->$level1)) {
                                        $level2_taxonomy_text.= $arr_api_info->$level1->$level2;
                                    }
                                }
                                if ($level2_taxonomy_text != '') {
                                    $arr_terms = Importer::getTaxonomyTerms(array($level2_taxonomy_text), $val_field_details['machine_name']);
                                    if (is_array($arr_terms) && count($arr_terms) > 0 ) {
                                        $arr_node_load_data[$key_node_field_name] =  $arr_terms;
                                    }
                                }
                            } elseif (isset($val_field_details['level3:taxonomy:api'])) {
                                $level1 = $val_field_details['level3:taxonomy:api'][0];
                                $arr_taxonomy = array();
                                if (isset($arr_api_info->$level1)) {
                                    foreach($arr_api_info->$level1 as $key => $val) {
                                        $arr_taxonomy[] = str_replace('-', ' ', $val->id);;;
                                    }
                                }
                                if (!empty($arr_taxonomy)) {
                                    $arr_terms = Importer::getTaxonomyTerms($arr_taxonomy, $val_field_details['machine_name']);
                                    if (is_array($arr_terms) && count($arr_terms) > 0 ) {
                                        $arr_node_load_data[$key_node_field_name] =  $arr_terms;
                                    }
                                }
                            }
                        }

                        $arr_node_load_data['field_property_source'] = $property_name;
                        //echo "\nfield_available_from : ". $arr_node_load_data['field_available_from'];
                        if($arr_node_load_data['field_reference_id'] != '' && $arr_node_load_data['field_available_from'] != '' && isset($arr_node_load_data['field_town_city']) && $arr_node_load_data['field_town_city'] != '') {
                            $your_date = strtotime($arr_node_load_data['field_available_from']);
                            $diff_days_timestamp = $your_date - time();
                            $diff_days = round($diff_days_timestamp / (60 * 60 * 24));
                            // Skip records if date is more than load_days
                            if ($diff_days <= 0 || $arr_property['load_days'] > $diff_days) {
                                $result[$arr_node_load_data['field_reference_id']] = Importer::createProperty($arr_node_load_data);
                                echo "\nReference ID : " . $arr_node_load_data['field_reference_id'] . " - Node ID : " . $result[$arr_node_load_data['field_reference_id']] . " - Diff Date : " . $diff_days;
                                Importer::writeDeleteFile($private_path_delete_process, $arr_node_load_data['field_reference_id']);
                            } else {
                                echo "\nNot importing Reference ID : " . $arr_node_load_data['field_reference_id'] . " - Diff Date : " . $diff_days;
                                $counter_diff_date++;
                            }
                        } else {
                            $counter_not_found++;
                            echo "\nNot Found Reference ID : " . $arr_node_load_data['field_reference_id'];
                        }
                    }
                    $line_number++;

//echo "\n Line No : " . $line_number . "\n"; if ($line_number == 500) {break;}
                }
            } else {
                die("Unable to open file");
            }

            if (count($result) > 0) {
                Importer::renameDeleteFile($private_path_delete_process);
            }

            echo "\nTotal records imported ". count($result);
            echo "\nTotal records Not Found ". $counter_not_found;
            echo "\nTotal records Date Diff ". $counter_diff_date;

            //print_r($result);
        }
        catch (\Exception $e) {
            echo 'Caught exception: ',  $e->getMessage(), "\n";
        }
        return $result;
    }
}