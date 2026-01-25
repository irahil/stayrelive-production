<?php

namespace Drupal\sr_mapping\Processing;

use Drupal\taxonomy\Entity\Term;
use Drupal\file\Entity\File;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\node\Entity\Node;
use Drupal\path_alias\Entity\PathAlias;

class Profiler {

    public static function getProfileFilePath($property_name) {
        $arr_property = self::getProfile($property_name);

        $private_path_to_process = \Drupal::service('file_system')->realpath("private://")."/".$arr_property['to_process_directory'];
        $private_path_completed_process = \Drupal::service('file_system')->realpath("private://")."/".$arr_property['completed_process_directory'];
        $private_path_error_process = \Drupal::service('file_system')->realpath("private://")."/".$arr_property['error_process_directory'];
        $private_path_delete_process = \Drupal::service('file_system')->realpath("private://")."/".$arr_property['delete_process_directory'];

        // Create directory if it does not exists
        if (!file_exists($private_path_to_process)) { mkdir($private_path_to_process, 0777, true);}
        if (!file_exists($private_path_completed_process)) { mkdir($private_path_completed_process, 0777, true);}
        if (!file_exists($private_path_error_process)) { mkdir($private_path_error_process, 0777, true);}
        if (!file_exists($private_path_delete_process)) { mkdir($private_path_delete_process, 0777, true);}

        return array($private_path_to_process, $private_path_completed_process, $private_path_error_process, $private_path_delete_process);
    }

    public static function getProfile($property_name) {
        $profiles = [
            'spacest' => [
                'download' => [
                    'type' => 'https',
                    'endpoint' => 'https://roomless-file.s3.us-east-2.amazonaws.com/feed-partner/example_feed.json'
                ],
                'file_type' => 'json',
                'load_days' => '180',
                'to_process_directory' => 'property/spacest/to_process/',
                'completed_process_directory' => 'property/spacest/completed/',
                'error_process_directory' => 'property/spacest/error/',
                'delete_process_directory' => 'property/spacest/delete/',
                'field_mapping' => [
                    'title' => ['direct' => ['name']],
                    'field_reference_id' => ['direct' => ['listing_code']],
                    'field_available_from' => ['direct' => ['first_availability']],
                    'field_deposit' => ['level2' => ['surcharges:deposit']],
                    'field_total_bathrooms' => ['level2' => ['house_informations:bathrooms']],
                    'field_total_bedrooms' => ['level2' => ['house_informations:bedrooms']],
                    'field_display_address' => ['level2' => ['location:address']],
                    'field_description' => ['paragraph' => ['description']],
                    'field_media' => ['serialize' => ['photos']],
                    'field_location_postal_code' => ['level2' => ['location:addressZipCode']],
                    'field_location_town' => ['level2' => ['location:city']],
                    'field_price' => ['direct' => ['price']],
                    'field_rental_term' => ['taxonomy' => [], 'machine_name' => 'rental_term', 'default' => ['short_term']],
                    'field_status' => ['taxonomy' => [], 'machine_name' => 'availability_status', 'default' => ['under_offer']],
                    'field_furnishing_status' => ['taxonomy' => [], 'machine_name' => 'furnished_status', 'default' => ['furnished']],
                    'field_location_coords_latitude' => ['level3' => ['location:coordinates:latitude']],
                    'field_location_coords_longitude' => ['level3' => ['location:coordinates:longitude']],
                    'field_amenities' => ['taxonomy' => ['amenities'], 'machine_name' => 'amenities'],
                    'field_category' => ['taxonomy' => ['category'], 'machine_name' => 'property_category'],
                    'field_property_type' => ['taxonomy' => ['category'], 'machine_name' => 'property_type'],
                    'field_currency_code' => ['taxonomy' => ['currency'], 'machine_name' => 'currency'],
                    'field_pricing_frequency' => ['level2:taxonomy' => ['pricing:rent_frequency'], 'machine_name' => 'pricing_frequency' , 'default' => ['per_night']],
                    'field_location_country_code' => ['level2:taxonomy' => ['location:country'], 'machine_name' => 'country'],
                    'field_town_city' => ['level2:taxonomy' => ['location:city'], 'machine_name' => 'town_city'],
                ],
            ],
            'homelike' => [
                'download' => [
                    'type' => 'https',
                    'username' => 'stayrelive',
                    'password' => 'jJQ6vJwhNTvGVLC4JKjeGxkenLPEXbg2qMxYXY5bQKcqst9T',
                    'endpoint' => 'https://feeds.services.thehomelike.com/feeds/stayrelive_en.json'
                ],
                'file_type' => 'json',
                'load_days' => '180',
                'to_process_directory' => 'property/homelike/to_process/',
                'completed_process_directory' => 'property/homelike/completed/',
                'error_process_directory' => 'property/homelike/error/',
                'delete_process_directory' => 'property/homelike/delete/',
                'field_mapping' => [
                    'title' => ['composite' => ['total_bedrooms', 'furnished_state', 'property_type', 'display_address']],
                    'field_reference_id' => ['direct' => ['listing_reference']],
                    'field_available_from' => ['direct' => ['available_from_date']],
                    'field_bills_included' => ['direct' => ['bills_included']],
                    'field_deposit' => ['direct' => ['deposit']],
                    'field_total_bathrooms' => ['direct' => ['bathrooms']],
                    'field_total_bedrooms' => ['direct' => ['total_bedrooms']],
                    'field_total_living_rooms' => ['direct' => ['living_rooms']],
                    'field_display_address' => ['direct' => ['display_address']],
                    'field_description' => ['paragraph' => ['detailed_description']],
                    'field_media' => ['serialize' => ['content']],
                    'field_location_postal_code' => ['level2' => ['location:postal_code']],
                    'field_street_name' => ['level2' => ['location:street_name']],
                    'field_property_number' => ['level2' => ['location:property_number_or_name']],
                    'field_location_town' => ['level2' => ['location:town_or_city']],
                    'field_price' => ['level2' => ['pricing:price']],
                    'field_location_coords_latitude' => ['level3' => ['location:coordinates:latitude']],
                    'field_location_coords_longitude' => ['level3' => ['location:coordinates:longitude']],
                    'field_rental_term' => ['taxonomy' => ['rental_term'], 'machine_name' => 'rental_term'],
                    'field_amenities' => ['taxonomy' => ['feature_list'], 'machine_name' => 'amenities'],
                    'field_category' => ['taxonomy' => ['category'], 'machine_name' => 'property_category'],
                    'field_property_type' => ['taxonomy' => ['property_type'], 'machine_name' => 'property_type'],
                    'field_status' => ['taxonomy' => ['life_cycle_status'], 'machine_name' => 'availability_status'],
                    'field_furnishing_status' => ['taxonomy' => ['furnished_state'], 'machine_name' => 'furnished_status'],
                    'field_transaction_type' => ['level2:taxonomy' => ['pricing:transaction_type'], 'machine_name' => 'transaction_'],
                    'field_currency_code' => ['level2:taxonomy' => ['pricing:currency_code'], 'machine_name' => 'currency'],
                    'field_pricing_frequency' => ['level2:taxonomy' => ['pricing:rent_frequency'], 'machine_name' => 'pricing_frequency'],
                    'field_location_country_code' => ['level2:taxonomy' => ['location:country_code'], 'machine_name' => 'country'],
                    'field_town_city' => ['level2:taxonomy' => ['location:town_or_city'], 'machine_name' => 'town_city'],
                ],
            ],
            'plumguide' => [
                'download' => [
                    'type' => 'httpscsv',
                    'endpoint' => 'http://plumguide.com/syndication-service/hotelcatalog/csv/partnerize/gbp.csv'
                ],
                'file_type' => 'csv',
                'load_days' => '180',
                'to_process_directory' => 'property/plumguide/to_process/',
                'completed_process_directory' => 'property/plumguide/completed/',
                'error_process_directory' => 'property/plumguide/error/',
                'delete_process_directory' => 'property/plumguide/delete/',
                'endpoint_authenticate' => 'https://api.plumguide.com/authenticate',
                'clientId' => 'stayrelive.integration',
                'clientSecret' => 'eb3bd5e1-18b9-4dc9-89ad-b66a848aedf7',
                'endpoint_getlisting' => 'https://api.plumguide.com/partner/listings/',
                'field_mapping' => [
                    'title' => ['composite' => ['bedroom_count' => ' Bedroom', 'property_type' => '', 'listing_name' => '']],
                    'field_reference_id' => ['direct' => ['listingid']],
                    'field_location_coords_latitude' => ['direct' => ['latitude']],
                    'field_location_coords_longitude' => ['direct' => ['longitude']],
                    'field_available_from' => ['direct' => ['AvailableDays180Percentage'], 'rule' => 'calculate_date', 'default' => 0],
                    'field_deposit' => ['direct' => [], 'default' => 0],
                    'field_total_bathrooms' => ['direct' => ['bathroom_count'], 'default' => 1],
                    'field_total_bedrooms' => ['direct' => ['bedroom_count'], 'default' => 1],
                    'field_display_address' => ['direct' => ['listing_name']],
                    'field_location_town' => ['direct' => ['location']],
                    'field_description' => ['paragraph' => ['description']],
                    'field_media' => ['serialize' => ['image_01', 'image_02', 'image_03', 'image_04', 'image_05', 'image_06', 'image_07', 'image_08', 'image_09', 'image_10', 'image_11', 'image_12', 'image_13', 'image_14', 'image_15']],
                    'field_property_number' => ['direct' => ['listingid']],
                    'field_price' => ['direct' => ['price'], 'default' => 0],
                    'field_rental_term' => ['taxonomy' => [], 'machine_name' => 'rental_term', 'default' => ['short_term']],
                    'field_category' => ['taxonomy' => [], 'machine_name' => 'property_category', 'default' => ['residential']],
                    'field_property_type' => ['taxonomy' => ['property_type'], 'machine_name' => 'property_type'],
                    'field_status' => ['taxonomy' => [], 'machine_name' => 'availability_status', 'default' => ['under_offer']],
                    'field_furnishing_status' => ['taxonomy' => [], 'machine_name' => 'furnished_status', 'default' => ['furnished']],
                    'field_transaction_type' => ['taxonomy' => [], 'machine_name' => 'transaction_', 'default' => ['rent']],
                    'field_currency_code' => ['taxonomy' => ['price'], 'machine_name' => 'currency', 'rule' => 'strip_number', 'default' => ['GBP']],
                    'field_pricing_frequency' => ['taxonomy' => [], 'machine_name' => 'pricing_frequency', 'default' => ['per_night']],
                    'field_street_name' => ['level2:direct:api' => ['address:address1', 'address:address2']],
                    'field_location_postal_code' => ['level2:direct:api' => ['address:postCode']],
                    'field_location_country_code' => ['level2:taxonomy:api' => ['address:country'], 'machine_name' => 'country'],
                    'field_town_city' => ['level2:taxonomy:api' => ['address:neighbourhood'], 'machine_name' => 'town_city'],
                    'field_amenities' => ['level3:taxonomy:api' => ['amenities'], 'machine_name' => 'amenities'],
                ],
            ],
            'ratehawk' => [
                'download' => [
                    'type' => 'basicauth_zst',
                    'username' => '7556',
                    'password' => '331c5926-5964-423a-aba2-38288ebb74a5',
                    'endpoint' => 'https://api.worldota.net/api/b2b/v3/hotel/custom/dump/'
                ],
                'file_type' => 'json',
                'load_days' => '180',
                'to_process_directory' => 'property/ratehawk/to_process/',
                'completed_process_directory' => 'property/ratehawk/completed/',
                'error_process_directory' => 'property/ratehawk/error/',
                'delete_process_directory' => 'property/ratehawk/delete/',
                'field_mapping' => [
                    'parent_title' => ['composite' => ['level0' => ['name', 'address']], 'delimiter' => ", "],
                    'parent_field_reference_id' => ['level0' => ['hid']],
                    'parent_field_media' => ['serialize_composite_image' => ['images']],

                    'title' => ['composite' => ['level0' => ['name'], 'level2:room_groups' => ['name']], 'delimiter' => "--"],
                    'field_reference_id' => ['composite_id' => ['level0' => ['hid'], 'level2:room_groups' => ['room_group_id', 'name']]],
                    'field_available_from' => ['level0' => [], 'default' => date('Y-m-d',time())],
                    'field_total_bathrooms' => ['level2:room_groups' => ['rg_ext:bathroom']],
                    'field_total_bedrooms' => ['level2:room_groups' => ['rg_ext:bedding']],
                    'field_display_address' => ['level0' => ['address']],
                    'field_description' => ['paragraph' => ['description_struct']],
                    'field_media' => ['serialize_composite_image' => ['level2:room_groups:images']],
                    'field_extra_info' => ['serialize_composite' => ['level2:room_groups:rg_ext']],
                    'field_location_postal_code' => ['level0' => ['postal_code']],
                    'field_location_coords_latitude' => ['level0' => ['latitude']],
                    'field_location_coords_longitude' => ['level0' => ['longitude']],
                    'field_rental_term' => ['taxonomy' => [], 'machine_name' => 'rental_term', 'default' => ['short_term']],
                    'field_amenities' => ['level3:taxonomy' => ['amenity_groups:amenities:group_name'], 'machine_name' => 'amenities', 'filter' => ['General', 'Rooms', 'Services and amenities', 'Meals', 'Internet', 'Pool and beach']],
                    'field_category' => ['taxonomy' => ['kind'], 'machine_name' => 'property_category'],
                    'field_property_type' => ['taxonomy' => ['kind'], 'machine_name' => 'property_type'],
                    'field_status' => ['taxonomy' => [], 'machine_name' => 'availability_status', 'default' => ['under_offer']],
                    'field_furnishing_status' => ['taxonomy' => [], 'machine_name' => 'furnished_status', 'default' => ['furnished']],
                    'field_transaction_type' => ['taxonomy' => [], 'machine_name' => 'transaction_', 'default' => ['rent']],
                    'field_currency_code' => ['taxonomy' => [], 'machine_name' => 'currency', 'default' => ['USD']],
                    'field_pricing_frequency' => ['taxonomy' => [], 'machine_name' => 'pricing_frequency', 'default' => ['per_night']],
                    'field_location_country_code' => ['level2:taxonomy' => ['region:country_code'], 'machine_name' => 'country'],
                    'field_town_city' => ['level2:taxonomy' => ['region:name'], 'machine_name' => 'town_city'],
                ],
            ],
            'interhome' => [
                'download' => [
                    'type' => 'http_interhome',
                    'PartnerId' => 'CH1002574',
                    'Token' => 'UrW1FAWHjU',
                    'endpoint' => 'https://ws.interhome.com/test/ih/b2b/V0100/accommodation/list',
                    'endpoint_static' => 'https://ws.interhome.com/ih/b2b/V0100/accommodation/'
                ],
                'file_type' => 'json',
                'load_days' => '180',
                'to_process_directory' => 'property/interhome/to_process/',
                'completed_process_directory' => 'property/rinterhomeatehawk/completed/',
                'error_process_directory' => 'property/interhome/error/',
                'delete_process_directory' => 'property/interhome/delete/',
                'field_mapping' => [
                    'title' => ['composite' => ['level1' => ['name'], 'level3' => ['place:0:content', 'region:0:content','country:0:content'], 'level2' => ['address:postalCode']], 'delimiter' => ","],
                    'field_reference_id' => ['level1' => ['code']],
                    'field_available_from' => ['level4' => ['paxByValidity:item:0:validFrom'], 'default' => date('Y-m-d',time())],
                    'field_total_bathrooms' => ['level2' => ['bathRooms:number']],
                    'field_total_bedrooms' => ['level2' => ['bedRooms:number']],
                    'field_display_address' => ['composite' => ['level3' => ['place:0:content', 'region:0:content','country:0:content'], 'level2' => ['address:postalCode']], 'delimiter' => ","],
                    'field_location_postal_code' => ['level2' => ['address:postalCode']],
                    'field_location_coords_latitude' => ['level2' => ['address:latitude']],
                    'field_location_coords_longitude' => ['level2' => ['address:longitude']],
                    'field_rental_term' => ['level1:taxonomy' => 'default', 'machine_name' => 'rental_term', 'default' => ['short_term']],
                    'field_category' => ['level1:taxonomy' => 'default', 'machine_name' => 'property_category', 'default' => ['residential']],
                    'field_property_type' => ['level1:taxonomy' => 'default', 'machine_name' => 'property_type', 'default' => ['flat']],
                    'field_status' => ['level1:taxonomy' => 'default', 'machine_name' => 'availability_status', 'default' => ['under_offer']],
                    'field_furnishing_status' => ['level1:taxonomy' => 'default', 'machine_name' => 'furnished_status', 'default' => ['furnished']],
                    'field_transaction_type' => ['level1:taxonomy' => 'default', 'machine_name' => 'transaction_', 'default' => ['rent']],
                    'field_currency_code' => ['level1:taxonomy' => 'domesticCurrency', 'machine_name' => 'currency', 'default' => ['USD']],
                    'field_pricing_frequency' => ['level1:taxonomy' => 'default', 'machine_name' => 'pricing_frequency', 'default' => ['per_night']],
                    'field_location_country_code' => ['level3:taxonomy' => 'country:0:content', 'machine_name' => 'country'],
                    'field_town_city' => ['level3:taxonomy' => 'region:0:content', 'machine_name' => 'town_city'],
                    'field_media' => ['serialize_composite_image' => 'media:mediaItem'],
                    'field_description' => ['level2:paragraph' => 'descriptions:description'],
                    'field_amenities' => ['level2:taxonomy' => 'attributes:attribute', 'machine_name' => 'amenities'],
                ],
            ]
        ];
        return (isset($profiles[$property_name])) ? $profiles[$property_name] : array();
    }
}