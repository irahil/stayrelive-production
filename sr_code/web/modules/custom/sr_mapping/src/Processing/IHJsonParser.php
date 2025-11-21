<?php
namespace Drupal\sr_mapping\Processing;

use Drupal\sr_mapping\Processing\Importer;
use Drupal\taxonomy\Entity\Term;
use Drupal\paragraphs\Entity\Paragraph;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;

class IHJsonParser {

    public static function parse($property_name, $arr_property) {
        list($private_path_to_process, $private_path_completed_process, $private_path_error_process, $private_path_delete_process)
                        = Profiler::getProfileFilePath($property_name);
        try {
            $arr_file = glob("{$private_path_to_process}*.".$arr_property['file_type']);

            if (!isset($arr_file[0])) {
                \Drupal::logger('Sr_Process')->info('No Files found for provider : ' . $property_name . ' in ' . $private_path_to_process);
                return FALSE;
            }

            // Load the JSON data from the file
            $jsonData = file_get_contents($arr_file[0]);

            // Decode the JSON data into a PHP array
            $dataArray = json_decode($jsonData, true);

            // Check if the decoding was successful
            if ($dataArray !== null) {
                $insert_counter = 0;
                $total_counter = 0;
                $counter_diff_date = 0;
                $counter_not_found = 0;

                // Loop through each record in the decoded array
                foreach ($dataArray['accommodationItem'] as $record) {
                    $static_content = self::getStaticContent($arr_property, $record['code']);
                    $static_content = $static_content['accommodation'];

                    foreach ($arr_property['field_mapping'] as $key_node_field_name => $val_json_field_details) {
                        if (isset($val_json_field_details['composite'])) {
                            $str_composite = '';
                            foreach ($val_json_field_details['composite'] as $key_name => $arr_field_name) {
                                if ($key_name == 'level1') {
                                    foreach($arr_field_name as $val_field_name) {
                                        $str_composite.= (isset($static_content[$val_field_name])) ? $static_content[$val_field_name] : '';
                                        $str_composite.= $val_json_field_details['delimiter'];
                                    }
                                } elseif ($key_name == 'level2') {
                                    foreach($arr_field_name as $val_field_name) {
                                        list($level0, $level1) = explode(":", $val_field_name);
                                        $str_composite.= (isset($static_content[$level0][$level1])) ? $static_content[$level0][$level1] : '';
                                        $str_composite.= $val_json_field_details['delimiter'];
                                    }
                                } elseif ($key_name == 'level3') {
                                    foreach($arr_field_name as $val_field_name) {
                                        list($level0, $level1, $level2) = explode(":", $val_field_name);
                                        $str_composite.= (isset($static_content[$level0][$level1][$level2])) ? $static_content[$level0][$level1][$level2] : '';
                                        $str_composite.= $val_json_field_details['delimiter'];
                                    }
                                }
                            }
                            $arr_node_load_data[$key_node_field_name] = rtrim(trim($str_composite),trim($val_json_field_details['delimiter']));
                        } elseif (isset($val_json_field_details['level1'])) {
                            foreach ($val_json_field_details['level1'] as $field_name) {
                                $str_level1 = (isset($static_content[$field_name])) ? $static_content[$field_name] : '';
                            }
                            // Set default
                            if ($str_level1 == '' && isset($val_json_field_details['default'])) {
                                $str_level1 = $val_json_field_details['default'];
                            }
                            $arr_node_load_data[$key_node_field_name] = $str_level1;
                        } elseif (isset($val_json_field_details['level2'])) {
                            foreach ($val_json_field_details['level2'] as $field_name) {
                                list($level0, $level1) = explode(":", $field_name);
                                $str_level2 = (isset($static_content[$level0][$level1])) ? $static_content[$level0][$level1] : '';
                            }
                            // Set default
                            if ($str_level2 == '' && isset($val_json_field_details['default'])) {
                                $str_level2 = $val_json_field_details['default'];
                            }
                            $arr_node_load_data[$key_node_field_name] = $str_level2;
                        } elseif (isset($val_json_field_details['level3'])) {
                            foreach ($val_json_field_details['level3'] as $field_name) {
                                list($level0, $level1, $level2) = explode(":", $field_name);
                                $str_level3 = (isset($static_content[$level0][$level1][$level2])) ? $static_content[$level0][$level1][$level2] : '';
                            }
                            // Set default
                            if ($str_level3 == '' && isset($val_json_field_details['default'])) {
                                $str_level3 = $val_json_field_details['default'];
                            }
                            $arr_node_load_data[$key_node_field_name] = $str_level3;
                        } elseif (isset($val_json_field_details['level4'])) {
                            foreach ($val_json_field_details['level4'] as $field_name) {
                                list($level0, $level1, $level2, $level3) = explode(":", $field_name);
                                $str_level4 = (isset($static_content[$level0][$level1][$level2][$level3])) ? $static_content[$level0][$level1][$level2][$level3] : '';
                            }
                            // Set default
                            if ($str_level4 == '' && isset($val_json_field_details['default'])) {
                                $str_level4 = $val_json_field_details['default'];
                            }
                            $arr_node_load_data[$key_node_field_name] = $str_level4;
                        } elseif (isset($val_json_field_details['level1:taxonomy']) || isset($val_json_field_details['level2:taxonomy']) || isset($val_json_field_details['level3:taxonomy'])) {
                            $arr_vocab = array();
                            $type = array_key_first($val_json_field_details);

                            switch ($type) {
                                case 'level1:taxonomy':
                                     if (isset($val_json_field_details[$type]) && isset($static_content[$val_json_field_details[$type]])) {
                                        $arr_vocab[] = $static_content[$val_json_field_details[$type]];
                                     }
                                break;
                                case 'level2:taxonomy':
                                    @list($level1, $level2) = explode(":", $val_json_field_details[$type]);
                                    if (isset($static_content[$level1][$level2])) {
                                        foreach($static_content[$level1][$level2] as $val_taxanomy) {
                                            $arr_vocab[] = trim(ucwords(str_replace('_', ' ', $val_taxanomy['name'])));
                                        }
                                    }
                                break;
                                case 'level3:taxonomy':
                                    @list($level1, $level2, $level3) = explode(":", $val_json_field_details[$type]);
                                    if (isset($static_content[$level1][$level2][$level3])) {
                                        $arr_vocab[] = $static_content[$level1][$level2][$level3];
                                    }
                                break;
                            }
                            // Setting Default
                            if (empty($arr_vocab) && isset($val_json_field_details['default'])) {
                                $arr_vocab = $val_json_field_details['default'];
                            }

                            if (!empty($arr_vocab)) {
                                // Checking for rules
                                if (isset($val_json_field_details['rule']) && $val_json_field_details['rule'] == 'strip_number') {
                                    $arr_vocab = preg_replace("/[^a-zA-Z]+/", "", $arr_vocab);
                                }

                                $arr_terms = Importer::getTaxonomyTerms($arr_vocab, $val_json_field_details['machine_name']);
                                if (is_array($arr_terms) && count($arr_terms) > 0 ) {
                                    $arr_node_load_data[$key_node_field_name] = $arr_terms;
                                }
                            }
                        } elseif (isset($val_json_field_details['serialize_composite_image'])) {
                            @list($level1, $level2) = explode(":", $val_json_field_details['serialize_composite_image']);
                            $arr_image = array();
                            if (isset($static_content[$level1][$level2])) {
                                foreach ($static_content[$level1][$level2] as $val_img_dlts) {
                                    $arr_image[] = array(
                                        'type' => 'image',
                                        'url' => $val_img_dlts['uri']
                                    );
                                }
                             }
                             $arr_node_load_data[$key_node_field_name] = serialize($arr_image);
                        } elseif (isset($val_json_field_details['level2:paragraph'])) {
                            @list($level1, $level2) = explode(":", $val_json_field_details['level2:paragraph']);
                            $arr_desc = array();
                            if (isset($static_content[$level1][$level2])) {
                                foreach ($static_content[$level1][$level2] as $key_para => $val_para) {
                                    $arr_desc[$key_para]['heading'] = ucwords($val_para['type']);
                                    $arr_desc[$key_para]['text'] = $val_para['value'];
                                }
                            }
                            $arr_node_load_data[$key_node_field_name] = $arr_desc;
                        }
                    }

                    $arr_node_load_data['field_property_source'] = $property_name;

                    if($arr_node_load_data['field_reference_id'] != '' && $arr_node_load_data['field_available_from'] != '' && isset($arr_node_load_data['field_town_city']) && $arr_node_load_data['field_town_city'] != '') {
                        $your_date = strtotime($arr_node_load_data['field_available_from']);
                        $diff_days_timestamp = $your_date - time();
                        $diff_days = round($diff_days_timestamp / (60 * 60 * 24));
                        // Skip records if date is more than load_days
                        if ($diff_days <= 0 || $arr_property['load_days'] > $diff_days) {
                            $result[$arr_node_load_data['field_reference_id']] = Importer::createProperty($arr_node_load_data);
                            echo "\nReference ID : " . $arr_node_load_data['field_reference_id'] . " - Node ID : " . $result[$arr_node_load_data['field_reference_id']] . " - Diff Date : " . $diff_days;
                            Importer::writeDeleteFile($private_path_delete_process, $arr_node_load_data['field_reference_id']);
                            $insert_counter++;
                        } else {
                            echo "\nNot importing Reference ID : " . $arr_node_load_data['field_reference_id'] . " - Diff Date : " . $diff_days;
                            $counter_diff_date++;
                        }
                    } else {
                        $counter_not_found++;
                        echo "\nNot Found Reference ID : " . $arr_node_load_data['field_reference_id'];
                    }
                    $total_counter++;

                    if ($total_counter==2) {
                        //echo "\n";exit;
                    }
                }
                if ($insert_counter > 0) {
                    Importer::renameDeleteFile($private_path_delete_process);
                }
                echo "\nTotal records imported ". $insert_counter . " out of " . $total_counter;
            } else {
                echo "Error decoding JSON.";
            }
        }
        catch (\Exception $e) {
            echo 'Caught exception: ',  $e->getMessage(), "\n";
        }
        return $result;
    }

    public static function getStaticContent($arr_property, $code) {
        $client = new Client();
        try {
            $headers = [
                'PartnerId' => $arr_property['download']['PartnerId'],
                'Token' => $arr_property['download']['Token'],
              ];
            $request = new Request('GET', $arr_property['download']['endpoint_static'] .$code, $headers);
            $res = $client->sendAsync($request)->wait();

            $result = json_decode($res->getBody(), TRUE);

            if (is_array($result) && !empty($result)) {
                return $result;
            }
            else{
                return array();
            }
        }
        catch (RequestException $e) {
            // log exception
            \Drupal::logger('Sr_process')->error('Unable to fetch content for : ' . $code . ' --- Error Message : ' . $e->getMessage());
            return array();
        }
    }
}
