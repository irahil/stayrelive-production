<?php
namespace Drupal\sr_mapping\Processing;

use Drupal\sr_mapping\Processing\Importer;
use Drupal\taxonomy\Entity\Term;
use Drupal\paragraphs\Entity\Paragraph;

class RHJsonParser {

    public static function parse($property_name, $arr_property) {
        list($private_path_to_process, $private_path_completed_process, $private_path_error_process, $private_path_delete_process)
                        = Profiler::getProfileFilePath($property_name);
        try {
            $arr_file = glob("{$private_path_to_process}*.".$arr_property['file_type']);

            if (!isset($arr_file[0])) {
                \Drupal::logger('Sr_Process')->info('No Files found for provider : ' . $property_name . ' in ' . $private_path_to_process);
                return FALSE;
            }

            $handle = fopen($arr_file[0], 'r');
            $buffer = '';

            $tmp_jsonData = [];
            $result = [];

            $insert_counter = 0;
            $total_counter = 0;

            while (($chunk = fgets($handle)) !== false) {
                $buffer .= $chunk;
                $pos = strrpos($buffer, '}');

                if ($pos !== false) {
                    $tmp_jsonData[] = json_decode(substr($buffer, 0, $pos + 1));
                    $buffer = substr($buffer, $pos + 1);
                }

                $record = json_decode(json_encode($tmp_jsonData[0]), true);
                unset($tmp_jsonData);
//print_r($record);echo "\n";exit;
                if (isset($record['kind']) && ($record['kind'] == "Apartment" || $record['kind'] == "Apart-hotel"
                || $record['kind'] == "Cottages_and_Houses" || $record['kind'] == "Villas_and_Bungalows")) {
                    $arr_node_load_data = array();
                    $arr_image = array();
                    $parent_node_id = "";

                    foreach ($record['room_groups'] as $val_room_groups) {
                        foreach ($arr_property['field_mapping'] as $key_node_field_name => $val_json_field_details) {
                            if (isset($val_json_field_details['level0'])) {
                                if (isset($val_json_field_details['level0'][0])) {
                                    $arr_node_load_data[$key_node_field_name] = (isset($record[$val_json_field_details['level0'][0]])) ? $record[$val_json_field_details['level0'][0]] : '';
                                } else {
                                    $arr_node_load_data[$key_node_field_name] = $val_json_field_details['default'];
                                }
                            } elseif (isset($val_json_field_details['level2:room_groups'])) {
                                list($level1, $level2) = explode(":", $val_json_field_details['level2:room_groups'][0]);
                                $arr_node_load_data[$key_node_field_name] = (isset($val_room_groups[$level1][$level2])) ? $val_room_groups[$level1][$level2] : '';
                            } elseif (isset($val_json_field_details['composite'])) {
                                $str_composite = '';
                                foreach ($val_json_field_details['composite'] as $key_name => $arr_field_name) {
                                    if ($key_name == 'level0') {
                                        foreach($arr_field_name as $val_field_name) {
                                            $str_composite.= (isset($record[$val_field_name])) ? $record[$val_field_name] : '';
                                            $str_composite.= $val_json_field_details['delimiter'];
                                        }
                                    } elseif ($key_name == 'level2:room_groups') {
                                        foreach($arr_field_name as $val_field_name) {
                                            $str_composite.= (isset($val_room_groups[$val_field_name])) ? $val_room_groups[$val_field_name] : '';
                                            $str_composite.= $val_json_field_details['delimiter'];
                                        }
                                    }
                                }
                                $arr_node_load_data[$key_node_field_name] = rtrim(trim($str_composite),trim($val_json_field_details['delimiter']));
                            } elseif (isset($val_json_field_details['composite_id'])) {
                                $str_composite = '';
                                foreach ($val_json_field_details['composite_id'] as $key_name => $arr_field_name) {
                                    if ($key_name == 'level0') {
                                        foreach($arr_field_name as $val_field_name) {
                                            $str_composite.= (isset($record[$val_field_name])) ? $record[$val_field_name] : '';
                                        }
                                    } elseif ($key_name == 'level2:room_groups') {
                                        foreach($arr_field_name as $val_field_name) {
                                            $str_composite.= "-";
                                            if ($val_field_name == "name") {
                                                $str_composite.= (isset($val_room_groups[$val_field_name])) ? md5($val_room_groups[$val_field_name]) : exit('ERROR : ROOM GROUP NAME NOT FOUND.');
                                            } else {
                                                $str_composite.= (isset($val_room_groups[$val_field_name])) ? $val_room_groups[$val_field_name] : '';
                                            }
                                        }
                                    }
                                }
                                $str_composite = trim($str_composite);
                                //base64_encode
                                $arr_node_load_data[$key_node_field_name] = ($str_composite);
                            } elseif (isset($val_json_field_details['paragraph'])) {
                                $arr_desc = array();
                                foreach($record[$val_json_field_details['paragraph'][0]] as $key_para => $val_para) {
                                    $arr_desc[$key_para]['heading'] = $val_para['title'];
                                    $str_desc = '';
                                    foreach($val_para['paragraphs'] as $sub_para) {
                                        $str_desc.= $sub_para;
                                    }
                                    $arr_desc[$key_para]['text'] = $str_desc;
                                }
                                $arr_node_load_data[$key_node_field_name] = $arr_desc;
                            } elseif (isset($val_json_field_details['serialize_composite'])) {
                                $str_data = '';

                                if (isset($val_json_field_details['serialize_composite'][0]) && $val_json_field_details['serialize_composite'][0] == "level2:room_groups:rg_ext") {
                                    list($level1, $level2, $level3) = explode(":", $val_json_field_details['serialize_composite'][0]);
                                    if (isset($val_room_groups[$level3])) {
                                        $str_data = serialize($val_room_groups[$level3]);
                                    }
                                }
                                $arr_node_load_data[$key_node_field_name] = $str_data;
                            } elseif (isset($val_json_field_details['serialize_composite_image'])) {
                                $image_size = "1024x768";
                                if (isset($record[$val_json_field_details['serialize_composite_image'][0]])) {
                                    foreach ($record[$val_json_field_details['serialize_composite_image'][0]] as $val_image) {
                                        $arr_image[] = array(
                                            'type' => 'image',
                                            'url' => str_replace('{size}', $image_size, $val_image)
                                        );
                                    }
                                }

                                if (isset($val_json_field_details['serialize_composite_image'][1]) && $val_json_field_details['serialize_composite_image'][1] == "level2:room_groups:images") {
                                    list($level1, $level2, $level3) = explode(":", $val_json_field_details['serialize_composite_image'][1]);
                                    if (isset($val_room_groups[$level3])) {
                                        foreach ($val_room_groups[$level3] as $val_sub_image) {
                                            $arr_image[] = array(
                                                'type' => 'image',
                                                'url' => str_replace('{size}', $image_size, $val_sub_image)
                                            );
                                        }
                                    }
                                }
                                $arr_node_load_data[$key_node_field_name] = serialize($arr_image);
                            } elseif (isset($val_json_field_details['taxonomy']) || isset($val_json_field_details['level2:taxonomy']) || isset($val_json_field_details['level3:taxonomy'])) {
                                $arr_vocab = array();
                                $type = array_key_first($val_json_field_details);

                                switch ($type) {
                                    case 'taxonomy':
                                         if (isset($val_json_field_details['taxonomy'][0]) && isset($record[$val_json_field_details['taxonomy'][0]])) {
                                            $arr_vocab[] = $record[$val_json_field_details['taxonomy'][0]];
                                         }
                                    break;
                                    case 'level2:taxonomy':
                                        @list($level1, $level2) = explode(":", $val_json_field_details[$type][0]);
                                        if (isset($record[$level1][$level2])) {
                                            $arr_vocab[] = $record[$level1][$level2];
                                        }
                                    break;
                                    case 'level3:taxonomy':
                                        @list($level1, $level2, $level3) = explode(":", $val_json_field_details[$type][0]);
                                        foreach ($record[$level1] as $arr_term_list) {
                                            if (in_array($arr_term_list[$level3], $val_json_field_details['filter'])) {
                                                foreach ($arr_term_list[$level2] as $val_term_list) {
                                                    $arr_vocab[] = $val_term_list;
                                                }
                                            }
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
                            }
                        }

                        $arr_node_load_data['field_property_source'] = $property_name;

                        if($arr_node_load_data['field_reference_id'] != '' && isset($arr_node_load_data['field_town_city']) && $arr_node_load_data['field_town_city'] != '') {

                            if ($parent_node_id == "") {
                                // Backup original data into a temporary array
                                $arr_tmp = [
                                    'field_reference_id' => $arr_node_load_data['field_reference_id'],
                                    'title' => $arr_node_load_data['title'],
                                    'field_media' => $arr_node_load_data['field_media'],
                                ];

                                // Replace fields with parent-related data
                                $arr_node_load_data = array_merge($arr_node_load_data, [
                                    'field_reference_id' => $arr_node_load_data['parent_field_reference_id'],
                                    'title' => $arr_node_load_data['parent_title'],
                                    'field_media' => $arr_node_load_data['parent_field_media'],
                                ]);

                                // Remove parent-specific fields to clean up
                                unset(
                                    $arr_node_load_data['parent_field_reference_id'],
                                    $arr_node_load_data['parent_title'],
                                    $arr_node_load_data['parent_field_media']
                                );
//print_r($arr_node_load_data);exit;
                                // Create parent reference property
                                $arr_node_load_data['field_parent_id'] = NULL;
                                $arr_node_load_data['field_total_bedrooms'] = 5;

                                $result[$arr_node_load_data['field_reference_id']] = Importer::createProperty($arr_node_load_data);
                                unset($arr_node_load_data['field_parent_id']);
                                $parent_node_id = $result[$arr_node_load_data['field_reference_id']];
                                echo "\nParent - Reference ID : " . $arr_node_load_data['field_reference_id'] . " - Node ID : " . $parent_node_id;
                                Importer::writeDeleteFile($private_path_delete_process, $arr_node_load_data['field_reference_id']);
                                $insert_counter++;

                                // Restore original data from temporary array
                                $arr_node_load_data = array_merge($arr_node_load_data, $arr_tmp);
                            }
                            // Remove parent-specific fields to clean up
                            unset(
                                $arr_node_load_data['parent_field_reference_id'],
                                $arr_node_load_data['parent_title'],
                                $arr_node_load_data['parent_field_media']
                            );
                            $arr_node_load_data['field_parent_id'] = $parent_node_id;

//print_r($arr_node_load_data);exit;
                            $result[$arr_node_load_data['field_reference_id']] = Importer::createProperty($arr_node_load_data);
                            echo "\n Child of Node ". $parent_node_id ."- Reference ID : " . $arr_node_load_data['field_reference_id'] . " - Node ID : " . $result[$arr_node_load_data['field_reference_id']];
                            Importer::writeDeleteFile($private_path_delete_process, $arr_node_load_data['field_reference_id']);
                            $insert_counter++;
                        }

                        unset($arr_node_load_data);
                        unset($arr_image);
                        $arr_node_load_data = array();
                        $arr_image = array();
                    }
                    $total_counter++;
                    if ($total_counter==2) {
                        //print_r($result);echo "\n";exit;
                    }
                }
            }

            fclose($handle);

            if ($insert_counter > 0) {
                Importer::renameDeleteFile($private_path_delete_process);
            }
            echo "\nTotal records imported ". $counter;
        }
        catch (\Exception $e) {
            echo 'Caught exception: ',  $e->getMessage(), "\n";
        }
        return $result;
    }
}
