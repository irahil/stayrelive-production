<?php

namespace Drupal\sr_mapping\Processing;

use Drupal\taxonomy\Entity\Term;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\node\Entity\Node;
use Drupal\path_alias\Entity\PathAlias;

class Importer {
    public static function createProperty($propertyData) {
        $nodes = \Drupal::entityTypeManager()->getStorage('node')->loadByProperties([
            'field_reference_id' => $propertyData['field_reference_id'],
        ]);
        $node = reset($nodes);
        if(!$node) {
            $node = Node::create(['type' => 'property']);
        }
        $has_price = 0;
        foreach($propertyData as $key => $value) {
            if ($key == "field_description") {
                $result_para = array();
                $current_para = $node->get("field_description")->getValue();
                $result_para = self::createParagraph($value, $current_para);
                $node->set($key, $result_para);
            } else {
                $node->set($key, $value);
                if ($key == "field_price" && $value > 0) {
                    $has_price = 1;
                }
            }
        }
        $node->set('field_has_price', $has_price);
        $node->setOwnerId(1);
        $node->save();
        $clean_reference_id = \Drupal::service('pathauto.alias_cleaner')->cleanString($propertyData['field_reference_id']);
        $clean_uri = \Drupal::service('pathauto.alias_cleaner')->cleanString($propertyData['title']);
        $source = substr($propertyData['field_property_source'], 0, 2);
        $node_url = "/" . $source . "/" .$clean_reference_id."/".$clean_uri;
        $path_alias = PathAlias::create([
            'path' => '/node/' . $node->id(),
            'alias' => $node_url,
        ]);
        $path_alias->save();

        return $node->id();
    }

    public static function createParagraph($value, $current_para) {
        $paragraphs = [];
        if (!empty($value) && is_array($value)) {
            foreach($value as $key => $t) {
                if (isset($t['heading']) && isset($t['text']) && $t['heading'] != '' && $t['text'] != "") {
                    $t['heading'] = (strlen($t['heading']) > 150) ? substr($t['heading'],0,150).'...' : $t['heading'];
                    $t['heading'] = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $t['heading']);

                    if (!empty($current_para) && is_array($current_para) && isset($current_para[$key]['target_id'])) {
                        $paragraph = Paragraph::load($current_para[$key]['target_id']);
                        // Update the field.
                        $paragraph->set('field_heading', $t['heading']);
                        $paragraph->set('field_text', $t['text']);
                    } else {
                        $paragraph = Paragraph::create([
                            'type' => 'property_description',
                            'field_heading' => array(
                                "value" => $t['heading'],
                                "format" => ""
                            ),
                            'field_text' => array(
                                "value" => $t['text']
                            )
                        ]);
                    }

                    // Save the Paragraph.
                    $paragraph->save();

                    $paragraphs[] = array(
                        'target_id' => $paragraph->id(),
                        'target_revision_id' => $paragraph->getRevisionId()
                    );
                }
            }
        }
        return $paragraphs;
    }

    public static function getTaxonomyTerms($arr_term_name, $taxonomy_machine_name) {
        $tid = [];
        $tids = [];
        if(!is_array($arr_term_name) && $arr_term_name != "") {
            $str_term_name = $arr_term_name;
            unset($arr_term_name);
            $arr_term_name[] = $str_term_name;
        }
        if (is_array($arr_term_name) && !empty($arr_term_name) ) {
            foreach($arr_term_name as $t) {
                $tid[] = self::getTid($t, $taxonomy_machine_name);
            }
            $tids = array_unique($tid);
        }
        return $tids;
    }

    public static function getTid($term_name, $vid) {
        $terms = \Drupal::entityTypeManager()->getStorage('taxonomy_term')
        ->loadByProperties([
            'name' => $term_name,
            'vid'=> $vid
        ]);

        $term = reset($terms);
        if(!empty($term)) {
            $term_id = $term->id();
        }
        else {
            if ($vid == "country") {
                $arr_term_data = [
                    'name' => $term_name,
                    'vid' => $vid,
                    'field_country_name' => self::getCountryName($term_name),
                ];
            } else {
                $arr_term_data = [
                    'name' => $term_name,
                    'vid' => $vid
                ];
            }
            $term = Term::create($arr_term_data);
            $term->save();
            if(isset($term)) {
                $term_id = $term->id();
                \Drupal::logger('sr_mapping')->notice('New Term created: '. $term_name .' for '.$vid);
            }
        }

        return $term_id ? $term_id : NULL;
    }

    public static function getCountryName($country_code) {
        $arr_country_list = [
            "AF" => "Afghanistan",
            "AX" => "Aland Islands",
            "AL" => "Albania",
            "DZ" => "Algeria",
            "AS" => "American Samoa",
            "AD" => "Andorra",
            "AO" => "Angola",
            "AI" => "Anguilla",
            "AQ" => "Antarctica",
            "AG" => "Antigua and Barbuda",
            "AR" => "Argentina",
            "AM" => "Armenia",
            "AW" => "Aruba",
            "AU" => "Australia",
            "AT" => "Austria",
            "AZ" => "Azerbaijan",
            "BS" => "Bahamas",
            "BH" => "Bahrain",
            "BD" => "Bangladesh",
            "BB" => "Barbados",
            "BY" => "Belarus",
            "BE" => "Belgium",
            "BZ" => "Belize",
            "BJ" => "Benin",
            "BM" => "Bermuda",
            "BT" => "Bhutan",
            "BO" => "Bolivia",
            "BQ" => "Bonaire, Sint Eustatius and Saba",
            "BA" => "Bosnia and Herzegovina",
            "BW" => "Botswana",
            "BV" => "Bouvet Island",
            "BR" => "Brazil",
            "IO" => "British Indian Ocean Territory",
            "BN" => "Brunei Darussalam",
            "BG" => "Bulgaria",
            "BF" => "Burkina Faso",
            "BI" => "Burundi",
            "KH" => "Cambodia",
            "CM" => "Cameroon",
            "CA" => "Canada",
            "CV" => "Cape Verde",
            "KY" => "Cayman Islands",
            "CF" => "Central African Republic",
            "TD" => "Chad",
            "CL" => "Chile",
            "CN" => "China",
            "CX" => "Christmas Island",
            "CC" => "Cocos (Keeling) Islands",
            "CO" => "Colombia",
            "KM" => "Comoros",
            "CG" => "Congo",
            "CD" => "Congo, The Democratic Republic of ",
            "CK" => "Cook Islands",
            "CR" => "Costa Rica",
            "CI" => "Cote d'Ivoire",
            "HR" => "Croatia",
            "CU" => "Cuba",
            "CW" => "Curaçao",
            "CY" => "Cyprus",
            "CZ" => "Czechia",
            "DK" => "Denmark",
            "DJ" => "Djibouti",
            "DM" => "Dominica",
            "DO" => "Dominican Republic",
            "EC" => "Ecuador",
            "EG" => "Egypt",
            "SV" => "El Salvador",
            "GQ" => "Equatorial Guinea",
            "ER" => "Eritrea",
            "EE" => "Estonia",
            "ET" => "Ethiopia",
            "FK" => "Falkland Islands (Malvinas)",
            "FO" => "Faroe Islands",
            "FJ" => "Fiji",
            "FI" => "Finland",
            "FR" => "France",
            "GF" => "French Guiana",
            "PF" => "French Polynesia",
            "TF" => "French Southern Territories",
            "GA" => "Gabon",
            "GM" => "Gambia",
            "GE" => "Georgia",
            "DE" => "Germany",
            "GH" => "Ghana",
            "GI" => "Gibraltar",
            "GR" => "Greece",
            "GL" => "Greenland",
            "GD" => "Grenada",
            "GP" => "Guadeloupe",
            "GU" => "Guam",
            "GT" => "Guatemala",
            "GG" => "Guernsey",
            "GN" => "Guinea",
            "GW" => "Guinea-Bissau",
            "GY" => "Guyana",
            "HT" => "Haiti",
            "HM" => "Heard and Mc Donald Islands",
            "VA" => "Holy See (Vatican City State)",
            "HN" => "Honduras",
            "HK" => "Hong Kong",
            "HU" => "Hungary",
            "IS" => "Iceland",
            "IN" => "India",
            "ID" => "Indonesia",
            "IR" => "Iran, Islamic Republic of",
            "IQ" => "Iraq",
            "IE" => "Ireland",
            "IM" => "Isle of Man",
            "IL" => "Israel",
            "IT" => "Italy",
            "JM" => "Jamaica",
            "JP" => "Japan",
            "JE" => "Jersey",
            "JO" => "Jordan",
            "KZ" => "Kazakstan",
            "KE" => "Kenya",
            "KI" => "Kiribati",
            "KP" => "Korea, Democratic People\'s Republic of",
            "KR" => "Korea, Republic of",
            "XK" => "Kosovo (temporary code)",
            "KW" => "Kuwait",
            "KG" => "Kyrgyzstan",
            "LA" => "Lao, People\'s Democratic Republic",
            "LV" => "Latvia",
            "LB" => "Lebanon",
            "LS" => "Lesotho",
            "LR" => "Liberia",
            "LY" => "Libyan Arab Jamahiriya",
            "LI" => "Liechtenstein",
            "LT" => "Lithuania",
            "LU" => "Luxembourg",
            "MO" => "Macao",
            "MK" => "Macedonia, The Former Yugoslav Republic Of",
            "MG" => "Madagascar",
            "MW" => "Malawi",
            "MY" => "Malaysia",
            "MV" => "Maldives",
            "ML" => "Mali",
            "MT" => "Malta",
            "MH" => "Marshall Islands",
            "MQ" => "Martinique",
            "MR" => "Mauritania",
            "MU" => "Mauritius",
            "YT" => "Mayotte",
            "MX" => "Mexico",
            "FM" => "Micronesia, Federated States of",
            "MD" => "Moldova, Republic of",
            "MC" => "Monaco",
            "MN" => "Mongolia",
            "ME" => "Montenegro",
            "MS" => "Montserrat",
            "MA" => "Morocco",
            "MZ" => "Mozambique",
            "MM" => "Myanmar",
            "NA" => "Namibia",
            "NR" => "Nauru",
            "NP" => "Nepal",
            "NL" => "Netherlands",
            "AN" => "Netherlands Antilles",
            "NC" => "New Caledonia",
            "NZ" => "New Zealand",
            "NI" => "Nicaragua",
            "NE" => "Niger",
            "NG" => "Nigeria",
            "NU" => "Niue",
            "NF" => "Norfolk Island",
            "MP" => "Northern Mariana Islands",
            "NO" => "Norway",
            "OM" => "Oman",
            "PK" => "Pakistan",
            "PW" => "Palau",
            "PS" => "Palestinian Territory, Occupied",
            "PA" => "Panama",
            "PG" => "Papua New Guinea",
            "PY" => "Paraguay",
            "PE" => "Peru",
            "PH" => "Philippines",
            "PN" => "Pitcairn",
            "PL" => "Poland",
            "PT" => "Portugal",
            "PR" => "Puerto Rico",
            "QA" => "Qatar",
            "RS" => "Republic of Serbia",
            "RE" => "Reunion",
            "RO" => "Romania",
            "RU" => "Russia Federation",
            "RW" => "Rwanda",
            "BL" => "Saint Barthélemy",
            "SH" => "Saint Helena",
            "KN" => "Saint Kitts & Nevis",
            "LC" => "Saint Lucia",
            "MF" => "Saint Martin",
            "PM" => "Saint Pierre and Miquelon",
            "VC" => "Saint Vincent and the Grenadines",
            "WS" => "Samoa",
            "SM" => "San Marino",
            "ST" => "Sao Tome and Principe",
            "SA" => "Saudi Arabia",
            "SN" => "Senegal",
            "CS" => "Serbia and Montenegro",
            "SC" => "Seychelles",
            "SL" => "Sierra Leone",
            "SG" => "Singapore",
            "SX" => "Sint Maarten",
            "SK" => "Slovakia",
            "SI" => "Slovenia",
            "SB" => "Solomon Islands",
            "SO" => "Somalia",
            "ZA" => "South Africa",
            "GS" => "South Georgia & The South Sandwich Islands",
            "SS" => "South Sudan",
            "ES" => "Spain",
            "LK" => "Sri Lanka",
            "SD" => "Sudan",
            "SR" => "Suriname",
            "SJ" => "Svalbard and Jan Mayen",
            "SZ" => "Swaziland",
            "SE" => "Sweden",
            "CH" => "Switzerland",
            "SY" => "Syrian Arab Republic",
            "TW" => "Taiwan, Province of China",
            "TJ" => "Tajikistan",
            "TZ" => "Tanzania, United Republic of",
            "TH" => "Thailand",
            "TL" => "Timor-Leste",
            "TG" => "Togo",
            "TK" => "Tokelau",
            "TO" => "Tonga",
            "TT" => "Trinidad and Tobago",
            "TN" => "Tunisia",
            "TR" => "Turkey",
            "XT" => "Turkish Rep N Cyprus (temporary code)",
            "TM" => "Turkmenistan",
            "TC" => "Turks and Caicos Islands",
            "TV" => "Tuvalu",
            "UG" => "Uganda",
            "UA" => "Ukraine",
            "AE" => "United Arab Emirates",
            "GB" => "United Kingdom",
            "US" => "United States",
            "UM" => "United States Minor Outlying Islands",
            "UY" => "Uruguay",
            "UZ" => "Uzbekistan",
            "VU" => "Vanuatu",
            "VE" => "Venezuela",
            "VN" => "Vietnam",
            "VG" => "Virgin Islands, British",
            "VI" => "Virgin Islands, U.S.",
            "WF" => "Wallis and Futuna",
            "EH" => "Western Sahara",
            "YE" => "Yemen",
            "ZM" => "Zambia",
            "ZW" => "Zimbabwe",
        ];
        return (isset($arr_country_list[$country_code])) ? $arr_country_list[$country_code] : 'NA';
    }

    public static function writeDeleteFile($private_path_delete_process, $reference_id) {
        if ($reference_id) {
            file_put_contents($private_path_delete_process."tmp_current.txt", $reference_id.PHP_EOL , FILE_APPEND | LOCK_EX);
        }
    }

    public static function renameDeleteFile($private_path_delete_process) {
        if (file_exists($private_path_delete_process."old.txt")) {
            unlink($private_path_delete_process."old.txt");
        }
        if (file_exists($private_path_delete_process."previous.txt")) {
            rename($private_path_delete_process."previous.txt", $private_path_delete_process."old.txt");
        }

        if (file_exists($private_path_delete_process."current.txt")) {
            rename($private_path_delete_process."current.txt", $private_path_delete_process."previous.txt");
        }

        if (file_exists($private_path_delete_process."tmp_current.txt")) {
            rename($private_path_delete_process."tmp_current.txt", $private_path_delete_process."current.txt");
        }
    }

    public static function removeProperty($reference_id) {
        $reference_id = trim($reference_id);
        if ($reference_id == "" ) { return false;}

        $nodes = \Drupal::entityTypeManager()->getStorage('node')->loadByProperties([
            'field_reference_id' => $reference_id,
            'type' => 'property',
            'status'=> 1,
        ]);
        $node = reset($nodes);
        if(!$node) {
            return false;
        }
        $nid = $node->id();

        // Load booking by property id
        $booking = \Drupal::entityTypeManager()->getStorage('booking')->loadByProperties([
            'field_property_id' => $nid,
        ]);

        if(empty($booking)) {
            $node->delete();
            return $nid;
        }
        return false;
        /*
        $node->setUnpublished();
        $node->save();
        return $node->id();
        */
    }
}
