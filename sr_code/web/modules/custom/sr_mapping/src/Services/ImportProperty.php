<?php

namespace Drupal\sr_mapping\Services;

use Drupal\file\Entity\File;
use Drupal\Component\Serialization\Json;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\sr_mapping\Processing\Common;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Drupal\sr_mapping\Processing\Profiler;
use Drupal\sr_mapping\Processing\RHJsonParser;
use Drupal\sr_mapping\Processing\IHJsonParser;
use Drupal\sr_mapping\Processing\JsonParser;
use Drupal\sr_mapping\Processing\CsvParser;
use Drupal\sr_mapping\Processing\Importer;

class ImportProperty implements ContainerInjectionInterface {
    /**
     * The entity type manager service.
     *
     * @var \Drupal\Core\Entity\EntityTypeManagerInterface
     */
    protected $entityTypeManager;

    /**
     * Constructor.
     *
     * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
     *   The entity type manager service.
     *
     */
    public function __construct(EntityTypeManagerInterface $entity_type_manager) {
        $this->entityTypeManager = $entity_type_manager;
    }

    /**
     * Container injection factory.
     *
     * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
     *   The service discovery container.
     *
     * @return self
     *   The form object.
     */
    public static function create(ContainerInterface $container) {
        return new static(
            $container->get('entity_type.manager'),
        );
    }

    public function processProperty($property_name) {
        $arr_property = Profiler::getProfile($property_name);
        if (empty($arr_property)) {
            \Drupal::logger('Sr_Download')->error('Unable to get the property details for provider : ' . $property_name);
            return FALSE;
        }
        switch ($property_name) {
            case 'interhome':
                IHJsonParser::parse($property_name, $arr_property);
            break;
            case 'ratehawk':
                RHJsonParser::parse($property_name, $arr_property);
            break;
            case 'homelike':
                JsonParser::parse($property_name, $arr_property);
            break;
            case 'plumguide':
                CsvParser::parse($property_name, $arr_property);
            break;
            case 'spacest':
                JsonParser::parse($property_name, $arr_property);
            break;
            default:
        }
    }

    public function downloadPropertyFile($property_name) {
        echo "\nDownloading : file download from provider : " . $property_name;
        $arr_property = Profiler::getProfile($property_name);
        if (empty($arr_property)) {
            echo "\nUnable to get the property details for provider : " . $property_name;
            \Drupal::logger('Sr_Download')->error('Unable to get the property details for provider : ' . $property_name);
            return FALSE;
        }

        switch ($arr_property['download']['type']) {
            case 'https':
                $client = new Client();
                try {
                    list($private_path_to_process, $private_path_completed_process, $private_path_error_process)
                        = Profiler::getProfileFilePath($property_name);

                    // Empty to complete directory
                    array_map('unlink', glob("{$private_path_to_process}*.".$arr_property['file_type']));

                    $file_name = date("Y-m-d-H-i-s")."_".$property_name.".".$arr_property['file_type'];

                    if (!isset($arr_property['download']['username']) || $arr_property['download']['username'] == ''
                        || !isset($arr_property['download']['password']) || $arr_property['download']['password'] == '') {
                        $response = $client->request('GET', $arr_property['download']['endpoint'], [
                            'sink' => $private_path_to_process.$file_name
                        ]);
                    } else {
                        $response = $client->request('GET', $arr_property['download']['endpoint'], [
                            'sink' => $private_path_to_process.$file_name,
                            'auth' => [
                                $arr_property['download']['username'],
                                $arr_property['download']['password']
                            ]
                        ]);
                    }

                    $result = json_decode($response->getBody(), TRUE);

                    if (isset($result['message']) && $result['message'] != '') {
                        // Move file to error directory.
                        rename($private_path_to_process.'/'.$file_name, $private_path_error_process.'/'.$file_name);
                        \Drupal::logger('Sr_Download')->error('Error : File Download for provider : ' . $property_name . ' --- File Name : '.$file_name.' ---- Error Message : ' . $result['message']);
                        echo "\nError : File Download for provider : " . $property_name . " --- File Name : ".$file_name." ---- Error Message : " . $result['message'];
                    } else {
                        //\Drupal::logger('Sr_Download')->error('Successful : File Download for provider : ' . $property_name . ' --- File Name : '.$file_name);
                        echo "\nSuccessful : File Download for provider : " . $property_name . " --- File Name : ".$file_name;
                    }
                }
                catch (RequestException $e) {
                    // log exception
                    echo "\nUnable to download the file for provider : " . $property_name . " --- Error Message : " . $e->getMessage();
                    \Drupal::logger('Sr_Download')->error('Unable to download the file for provider : ' . $property_name . ' --- Error Message : ' . $e->getMessage());
                }
            break;
            case 'httpscsv':
                try {
                    list($private_path_to_process, $private_path_completed_process, $private_path_error_process)
                        = Profiler::getProfileFilePath($property_name);

                    // Empty to complete directory
                    array_map('unlink', glob("{$private_path_to_process}*.".$arr_property['file_type']));

                    $file_name = date("Y-m-d-H-i-s")."_".$property_name.".".$arr_property['file_type'];

                    $url = $arr_property['download']['endpoint'];
                    $source = file_get_contents($url);
                    $return_value = file_put_contents($private_path_to_process.$file_name, $source);

                    if (!$return_value) {
                        // Move file to error directory.
                        rename($private_path_to_process.'/'.$file_name, $private_path_error_process.'/'.$file_name);
                        \Drupal::logger('Sr_Download')->error('Error : file download from provider : ' . $property_name . ' --- File Name : '.$file_name);
                        echo "\nError : file download from provider : " . $property_name . " --- File Name : ".$file_name;
                    } else {
                        //\Drupal::logger('Sr_Download')->error('Successful : file download from provider : ' . $property_name . ' --- File Name : '.$file_name);
                        echo "\nSuccessful : file download from provider : " . $property_name . " --- File Name : ".$file_name;
                    }
                }
                catch (RequestException $e) {
                    // log exception
                    \Drupal::logger('Sr_Download')->error('Unable to download the file for provider : ' . $property_name . ' --- Error Message : ' . $e->getMessage());
                    echo "\nUnable to download the file for provider : " . $property_name . " --- Error Message : " . $e->getMessage();
                }
            break;
            case 'basicauth_zst':
                $client = new Client();

                try {
                    list($private_path_to_process, $private_path_completed_process, $private_path_error_process)
                        = Profiler::getProfileFilePath($property_name);

                    // Empty to complete directory
                    array_map('unlink', glob("{$private_path_to_process}*.".$arr_property['file_type']));
                    array_map('unlink', glob("{$private_path_to_process}*.zst"));

                    $file_name = date("Y-m-d-H-i-s")."_".$property_name.".".$arr_property['file_type'];
                    $file_name_zst = date("Y-m-d-H-i-s")."_".$property_name.".".$arr_property['file_type'].".zst";

                    $url = $arr_property['download']['endpoint'];
                    $auth = base64_encode($arr_property['download']['username'].":".$arr_property['download']['password']);
                    $headers = [
                        'Content-Type' => 'application/json',
                        'Authorization' => 'Basic '.$auth
                    ];
                    $body = '{
                        "type": "apartments",
                        "lang": "en"
                    }';
                    $request = new Request('POST', 'https://api.worldota.net/api/b2b/v3/hotel/custom/dump/', $headers, $body);
                    $res = $client->sendAsync($request)->wait();
                    if ($res->getBody() != "") {
                        $response = json_decode($res->getBody(), true);
                        if ($response['status'] == "ok" && $response['data']['url'] != '') {
                            echo "\Download URL : " . $response['data']['url'];

                            $source = file_get_contents($response['data']['url']);
                            $return_value = file_put_contents($private_path_to_process.$file_name_zst, $source);

                            if (!$return_value) {
                                // Move file to error directory.
                                rename($private_path_to_process.'/'.$file_name_zst, $private_path_error_process.'/'.$file_name_zst);
                                \Drupal::logger('Sr_Download')->error('Error : file download from provider : ' . $property_name . ' --- File Name : '.$file_name);
                                echo "\nError : file download from provider : " . $property_name . " --- File Name : ".$file_name_zst;
                            } else {
                                //\Drupal::logger('Sr_Download')->error('Successful : file download from provider : ' . $property_name . ' --- File Name : '.$file_name);
                                echo "\nSuccessful : file download from provider : " . $property_name . " --- File Name : ".$file_name_zst;
                                # unzstd $file_name
                                $command = "unzstd ".$private_path_to_process.$file_name_zst;
                                $output = shell_exec($command);
                                echo "\nSuccessful : file extracted from provider : " . $property_name . " --- File Name : ".$file_name;
                                echo "\nShell output : ".$output;
                            }
                        }
                    }
                }
                catch (RequestException $e) {
                    // log exception
                    \Drupal::logger('Sr_Download')->error('Unable to download the file for provider : ' . $property_name . ' --- Error Message : ' . $e->getMessage());
                    echo "\nUnable to download the file for provider : " . $property_name . " --- Error Message : " . $e->getMessage();
                }
            break;
            case 'http_interhome':
                $client = new Client();
                try {
                    list($private_path_to_process, $private_path_completed_process, $private_path_error_process)
                        = Profiler::getProfileFilePath($property_name);

                    // Empty to complete directory
                    array_map('unlink', glob("{$private_path_to_process}*.".$arr_property['file_type']));

                    $file_name = date("Y-m-d-H-i-s")."_".$property_name.".".$arr_property['file_type'];
                    $response = $client->request('GET', $arr_property['download']['endpoint'], [
                        'sink' => $private_path_to_process.$file_name,
                        'headers' => [
                            'PartnerId' => $arr_property['download']['PartnerId'],
                            'Token' => $arr_property['download']['Token']
                        ]
                        ]
                    );

                    $result = json_decode($response->getBody(), TRUE);

                    if (is_array($result) && !empty($result)) {
                        echo "\nSuccessful : File Download for provider : " . $property_name . " --- File Name : ".$file_name;
                    }
                    else{
                        // Move file to error directory.
                        rename($private_path_to_process.'/'.$file_name, $private_path_error_process.'/'.$file_name);
                        \Drupal::logger('Sr_Download')->error('Error : File Download for provider : ' . $property_name . ' --- File Name : '.$file_name.' ---- Error Message : ' . $result['message']);
                        echo "\nError : File Download for provider : " . $property_name . " --- File Name : ".$file_name." ---- Error Message : " . $result;
                    }
                }
                catch (RequestException $e) {
                    // log exception
                    echo "\nUnable to download the file for provider : " . $property_name . " --- Error Message : " . $e->getMessage();
                    \Drupal::logger('Sr_Download')->error('Unable to download the file for provider : ' . $property_name . ' --- Error Message : ' . $e->getMessage());
                }
            break;
            default:
        }
    }


    public function deleteProperty($property_name) {
        $arr_property = Profiler::getProfile($property_name);
        if (empty($arr_property)) {
            echo "Unable to get the property details for provider : " . $property_name;
            return FALSE;
        }

        list($private_path_to_process, $private_path_completed_process, $private_path_error_process, $private_path_delete_process)
            = Profiler::getProfileFilePath($property_name);
        $command = "grep -Fxvf ".$private_path_delete_process."current.txt ".$private_path_delete_process."previous.txt > ".$private_path_delete_process."diff.txt";

        //grep -Fxvf file1 file2 > file3
        exec($command);
        if (file_exists($private_path_delete_process."diff.txt")) {
            $file = fopen($private_path_delete_process."diff.txt", "r");
            while (!feof($file)) {
                $reference_id = fgets($file);
                $reference_id = trim($reference_id);
                if ($reference_id != '') {
                    $node_id = Importer::removeProperty($reference_id);
                    if ($node_id) {
                        echo "\nDelete : Property ID : " . $node_id . " ref id : " . $reference_id;
                    } else {
                        echo "\nUnable to Delete : Property ID : " . $node_id . " ref id : " . $reference_id;
                    }
                }
            }
            fclose($file);
            unlink($private_path_delete_process."diff.txt");
        }
    }


    public function deleteCity() {
        $vid = 'town_city';
        $terms =\Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadTree($vid);
        foreach ($terms as $term) {
            echo "\n Searching for Town City : " . $term->name . " TID : " . $term->tid;

            $node = \Drupal::entityTypeManager()
                ->getStorage('node')
                ->loadByProperties([
                'status' => '1',
                'field_town_city' => $term->tid
            ]);

            if (count($node) <=0 && $term->tid > 0 && $delete_term = \Drupal\taxonomy\Entity\Term::load($term->tid)) {
                echo "\n Deleting Town City : " . $term->name . " TID : " . $term->tid;
                print_r($delete_term->delete());
            } else {
                echo "\n Total ". count($node) ." found for Town City : " . $term->name . " TID : " . $term->tid;
            }
        }
    }
}
