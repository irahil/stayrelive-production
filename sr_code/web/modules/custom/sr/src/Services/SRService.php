<?php
namespace Drupal\sr\Services;

use Drupal\Core\Session\AccountInterface;
use GuzzleHttp\Client;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\node\Entity\Node;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\user\Entity\User;
use Drupal\common_utilities\Utilities\commonUtil;

/**
 * Class CustomService
 * @package Drupal\sr\Services
 */
class SRService {

  protected $currentUser;

  var $api_param = array();

  /**
   * CustomService constructor.
   * @param AccountInterface $currentUser
   */
  public function __construct(AccountInterface $currentUser) {
    $this->currentUser = $currentUser;
  }

  public function generatePriceInfo($nid = 0, $param = array()) {
    $price_info = array();
    if ($nid > 0) {
      $node = \Drupal::entityTypeManager()->getStorage('node')->load($nid);
      $field_price = $node->get('field_price')->getString();
      $field_deposit = $node->get('field_deposit')->getString();
      $currency_code_tid = $node->get('field_currency_code')->getString();
      $term = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->load($currency_code_tid);
      $field_currency_code = $term->label();
      $field_property_source = $node->get('field_property_source')->getString();
//\Drupal::logger('my_module')->info('Price Field : ' . $field_price);

      // Since homelike price is of 30 days. so getting per night price.
      if ($field_property_source == 'spacest' && $field_price > 0) {
        $field_price = ($field_price/31);
      }
// \Drupal::logger('my_module')->info('1 day Price : ' . $field_price);

      // Convert currency price
      $result = \Drupal::service('currency_layer_integration.services')->CFConvertUserCurrency($field_currency_code, $field_price);
      // Convert currency deposit
      $result_deposit = \Drupal::service('currency_layer_integration.services')->CFConvertUserCurrency($field_currency_code, $field_deposit);
// \Drupal::logger('my_module')->info(' Cunvert Price : ' . print_r($result, true));

      // Set Dates
      list($from_date, $to_date) = $this->convertDateRange($param['date_range']);

      $from_date = new DrupalDateTime($from_date);
      $to_date = new DrupalDateTime($to_date);

      $booking_days = $to_date->diff($from_date)->format("%a");

      // Calculate commission
      $calculated_price = \Drupal::service('sr.services')->calculateCommission($field_property_source, $result['value']);

      $price_info['price'] = $calculated_price;
      $price_info['currency_code'] = $result['to'];
      $price_info['deposit'] = $result_deposit['value'];
      $price_info['days'] = $booking_days;
      $price_info['checkin_date'] = $from_date;
      $price_info['checkout_date'] = $to_date;
      $price_info['tax'] = 0;
      $price_info['price_for_days'] = $price_info['price'] * $price_info['days'];
      $price_info['final_price'] = $price_info['price'] * $price_info['days'] + $price_info['deposit'];

      $price_info['booking_url'] =  Url::fromRoute('sr.booking', array_map('urlencode', $param)
       , ['absolute' => TRUE])->toString();
    }
    return $price_info;
  }

  public function generateGoogleMapsParam($arr_location) {

    $str_location = 'var markers = [';
    foreach ($arr_location as $location_info) {
      $str_location.= "
      {
        'title': '". str_replace("'", " ", trim($location_info['title'])) ."',
        'lat': '".$location_info['lat']."',
        'lng': '".$location_info['lng']."',
        'link': '".$location_info['link']."',
        'id': '".$location_info['id'] ."'
      },";
    }
    $str_location = rtrim($str_location, ",");
    $str_location.= '];';

    $str_script = $str_location;
    $str_key = "AIzaSyBd7hoeq3JClkyQ2MQ33_jqPbzRhuB1WiA";

    return array('gm_param_value' => $str_script, 'gm_key' => $str_key);

  }

  public function validateSearchParam($form_param) {
    $flag = true;
    // Set occupants
    if ($form_param['adult'] < 1 || $form_param['adult'] > 20) {
      $form_param['adult'] = 1;
      $flag = false;
    }
    if ($form_param['kid'] < 0 || $form_param['kid'] >= 10) {
      $form_param['kid'] = 0;
      $flag = false;
    }
    // Date
    $days = "30";
    if ($form_param['date_range'] == '') {
      $from_date = date("Y-m-d");
      $to_date = date('Y-m-d', strtotime($from_date . ' +'.$days.' day'));
      $form_param['date_range'] = $from_date . " to " . $to_date;
      $flag = false;
    } else {
      list($from_date, $to_date) = explode(" to ", $form_param['date_range']);
      // If date in is a invalid date then set empty
      if (!$this->_dateValidate($from_date) || !$this->_dateValidate($to_date)) {
        $from_date = date("Y-m-d");
        $to_date = date('Y-m-d', strtotime($from_date . ' +'.$days.' day'));
        $form_param['date_range'] = $from_date . " to " . $to_date;
        $flag = false;
      }
    }

    // Set default City
    if ($form_param['town_city'] == '') {
      $form_param['town_city'] = "London";
    }

    // Sort
    if ($form_param['sort'] != 'lh_price' || $form_param['sort'] != 'hl_price') {
      $form_param['sort'] = "lh_price";
    }

    return array($flag, $form_param);
  }

  private function _dateValidate($pDate) {
    if (empty($pDate)) {
      return false;
    }
    $chDate = date('Y-m-d', strtotime($pDate));

    if ($chDate != '1970-01-01') {
      return true;
    } else {
      return false;
    }
  }

  public function convertDateRange($date_range) {
    $days = "30";
    $from_date = '';
    $to_date = '';

    if (!empty($date_range)) {
      list($from_date, $to_date) = explode(" to ", $date_range);
      // If date in is a invalid date then set empty
      if (!$this->_dateValidate($from_date) || !$this->_dateValidate($to_date)) {
        $from_date = '';
        $to_date = '';
      }
    }
    $from_date = ($from_date == '') ? date("Y-m-d") : $from_date;
    $to_date = ($to_date == '') ? date('Y-m-d', strtotime($from_date . ' +'.$days.' day')) : $to_date;
    return array($from_date, $to_date);
  }

  function getCityNameByLatitudeLongitude($latlong) {
    $APIKEY = "AIzaSyBd7hoeq3JClkyQ2MQ33_jqPbzRhuB1WiA";
    $arr_return_address = array();
    $googleMapsUrl = "https://maps.googleapis.com/maps/api/geocode/json?latlng=" . $latlong . "&language=ar&key=" . $APIKEY;

    $state_lat_long = \Drupal::state()->get($latlong);
    if ($state_lat_long != '') {
      $arr_return_address = unserialize($state_lat_long);
      //\Drupal::logger('sr_mapping')->notice('Reading from state api for '.$latlong);
    } else {
      \Drupal::logger('sr_mapping')->notice('WONT CALL GOOGLE for '.$latlong);
      return array();
      $response = file_get_contents($googleMapsUrl);

      $response = json_decode($response, true);
      $results = $response["results"];
      $addressComponents = $results[0]["address_components"];

      foreach ($addressComponents as $component) {
        $types = $component["types"];
        if (in_array("locality", $types) && in_array("political", $types)) {
            $arr_return_address['google_city'] = $component["long_name"];
        } else if (in_array("country", $types) && in_array("political", $types)) {
            $arr_return_address['google_country_code'] = $component["short_name"];
        } else if (in_array("postal_code", $types)) {
          $arr_return_address['google_postcode'] = $component["long_name"];
        } else if (in_array("street_number", $types)) {
          $arr_return_address['google_street_number'] = $component["long_name"];
        } else if (in_array("route", $types)) {
          $arr_return_address['google_street_name'] = $component["long_name"];
        }
      }
      \Drupal::state()->set($latlong, serialize($arr_return_address));
      //\Drupal::logger('sr_mapping')->notice('Write to state api for '.$latlong);
    }
    return $arr_return_address;
  }

  public function emailBooking($booking_id, $email_type = null) {

    if ($booking_id > 0) {
      # Load from node id
      $node_booking = \Drupal::entityTypeManager()->getStorage('booking')->load($booking_id);
      if ($node_booking == NULL) {
        return false;
      }
      // echo '<pre>';
      // print_r($node_booking);
      // echo '</pre>';
      // exit;

      $property_id = $node_booking->get('field_property_id')->getString();
      # Load from node id
      $node_property = Node::load($property_id);
      if ($node_property == NULL || !$node_property->isPublished()) {
        return false;
      }

      $booking_status = $node_booking->get('field_status')->getString();
      $booking_id = $node_booking->id();
      $property_title = $node_property->getTitle();

      // Calculate Dates
      $from_date = new DrupalDateTime($node_booking->get('field_from_date')->getString());
      $from_date = date("jS F y", strtotime($from_date));

      $to_date = new DrupalDateTime($node_booking->get('field_to_date')->getString());
      $to_date = date("jS F y", strtotime($to_date));

      // Fetch and calculate price.
      $final_price = $node_booking->get('field_price')->getString();
      $final_price = ($final_price != '') ? commonUtil::formatCurrency($final_price) : '';

      // Fetch currency
      $arr_currency_list = commonUtil::get_term_list('currency');
      $currency_code = $node_booking->get('field_currency_code')->getString();
      $selected_currency = (isset($arr_currency_list[$currency_code])) ? $arr_currency_list[$currency_code] : 'NA';

      $arr_content = [
        'username' => $node_booking->get('field_name')->getString(),
        'property_url' => $node_property->toUrl('canonical', ['absolute' => TRUE])->toString(),
        'booking_url' => Url::fromRoute('sr.booking', [], ['absolute' => TRUE])->toString(),
        'from_date' => date("jS F y", strtotime($from_date)),
        'to_date' => date("jS F y", strtotime($to_date)),
        'email' => $node_booking->get('field_email')->getString(),
        'currency_code' => $selected_currency,
        'price' => $final_price,
        'status' => ucwords(str_replace("_", " ", $booking_status)),
        'property_title' => $property_title,
        'booking_id' => $booking_id,
        'town_city' => $property_id,
        'adults' => $node_booking->get('field_adults')->getString(),
        'kids' => $node_booking->get('field_kids')->getString(),
        'additional_information' => $node_booking->get('field_remarks')->getString(),
        'phone_number' => $node_booking->get('field_phone_number')->getString(),
      ];

      switch($booking_status) {
        case 'pending_booking':
          // Send to user
          $user_params = [
            'module' => 'sr',
            'key' => 'booking',
            'to' => $arr_content['email'],
            'subject' => "Your Booking Request for {$property_title} [#{$booking_id}]",
            'template' => 'emails/sr-email-booking-request-user',
            'message' => $this->bookingEmailBody($arr_content, 'emails/sr-email-booking-request-user'),
          ];
          $this->sendEmail($user_params);

          //Send to internal
          $internal_params = [
            'module' => 'sr',
            'key' => 'booking',
            'to' => 'book@stayrelive.com',
            'subject' => "New Booking Request Received [#{$booking_id}] - Action Required",
            'template' => 'emails/sr-email-booking-request-internal',
            'message' => $this->bookingEmailBody($arr_content, 'emails/sr-email-booking-request-internal'),
          ];
          $this->sendEmail($internal_params);
        break;
        case 'confirmed':
          $arr_param['subject'] = "Booking Confirmed : " . $node_property->getTitle();
          $arr_content['message'] = 'confirmed';
        break;
        case 'canceled_booking':
          $arr_param['subject'] = "Booking Canceled : " . $node_property->getTitle();
          $arr_content['message'] = 'canceled';
        break;
        default:
        break;
      }
    //print_r($arr_content);exit;

      // $arr_param['message'] = $this->bookingEmailBody($arr_content);

      // $email_to = $node_booking->get('field_email')->getString();
      // $email_to.= ",book@stayrelive.com";

      // $arr_param['to'] = $email_to;
      // $arr_param['Cc'] = "book@stayrelive.com";
      // $arr_param['module'] = 'sr';
      // $arr_param['key'] = 'booking';
      // $this->sendEmail($arr_param);
    }
  }

    /**
   * Send Reminder
   *
   * @return boolean
   */
  public function sendEmail($arr_param) {
    $params['subject'] = $arr_param['subject'];
    $params['message'] = $arr_param['message'];
    if (!isset($params['Cc'])) {
      $params['Cc'] = 'book@stayrelive.com';
    }
    $langcode = \Drupal::currentUser()->getPreferredLangcode();
    $mailManager = \Drupal::service('plugin.manager.mail');
    $result = $mailManager->mail($arr_param['module'], $arr_param['key'], $arr_param['to'], $langcode,
        $params, NULL, true);

    if ($result['result'] !== true) {
        return false;;
    } else {
        return true;
    }
  }


  private function bookingEmailBody(array $arr_content, string $template) {
    try {
      // Use Drupal's Twig service (with theme caching & namespacing)
      $twig = \Drupal::service('twig');

      // Assuming template is in: templates/emails/sr-email-booking-request-user.html.twig
      // and your hook_theme() defines it as 'sr_email_booking_request_user'
      return $twig->render("@sr/{$template}.html.twig", $arr_content);
    } catch (\Exception $e) {
      \Drupal::logger('sr')->error('Twig rendering failed: @msg', ['@msg' => $e->getMessage()]);
      return '';
    }
  }





  public function convertDate($from_date, $to_date) {
    echo $from_date . " - " . $to_date;
    $format = "";
    $from_date = date_parse_from_format ($format, $from_date);

    echo "<br>";
    echo $from_date . " - " . $to_date;

    $from_date = new DrupalDateTime($from_date);
    $to_date = new DrupalDateTime($to_date);

    exit;

    $from_date = date("Y-m-d");
    $to_date = date('Y-m-d', strtotime($from_date . ' +30 day'));
    if (empty($date_range)) {
      $date_range = $from_date . " to " . $to_date;
    } else {
      list($from_date, $to_date) = explode(" to ", $date_range);
    }
    $from_date = ($from_date == '') ? date("Y-m-d") : $from_date;
    $to_date = ($to_date == '') ? date('Y-m-d', strtotime($from_date . ' +1 day')) : $to_date;
    return array($from_date, $to_date);
  }


  /**
   * Commession
   *
   * @return boolean
   */
  public function calculateCommission($source, $price) {
    if ($source != '' && $price != '' && $price >= 0) {

        // Check if the logged-in user is an agent
        $current_user = \Drupal::currentUser();
        $is_agent = 0;

        if ($current_user->isAuthenticated()) {
            $user = \Drupal\user\Entity\User::load($current_user->id());
            if ($user && $user->hasField('field_is_agent')) {
                $is_agent = $user->get('field_is_agent')->value;
            }
        }
        //\Drupal::logger('is_agent')->notice($is_agent);

        // Determine the commission key based on the user's agent status
        if ($is_agent == 1) {
            // Apply agent-specific commission
            $key_name = $source . '_agent_percentage';
        } else {
            // Apply rest of the world commission
            $key_name = $source . '_percentage';
        }

        // Fetch the commission percentage from the configuration
        $value_percentage = \Drupal::config('commission.settings')->get($key_name);
        $value_percentage = $value_percentage ?: 0;

        // Apply the commission to the price
        $price = ($price * (1 + ($value_percentage / 100)));
    }
    return $price;
  }

  public function getPlumGuideAPI($arr_property, $property_id) {
    $data = array();
    $token = $this->authPlumGuideAPI($arr_property);

    $headers = [
      'Accept' => 'application/json',
      'Authorization' => $token
    ];
    //$property_id = '1143578';
    $endpoint = $arr_property['endpoint_getlisting']. $property_id;

    try {
      $result = \Drupal::httpClient()->get($endpoint, [
        'headers' => $headers,
      ]);

      if ($result->getStatusCode() == 200) {
        $data = json_decode($result->getBody());
        //\Drupal::logger('sr')->notice('getPlumGuideAPI : Data for ' . $property_id . ' : ' . print_r($data));
      }
      else {
        \Drupal::logger('sr')->error('authPlumGuideAPI : Token did not get 200 response');
      }
    } catch (\GuzzleHttp\Exception\ConnectException $e) {
      // This is will catch all connection timeouts
      // Handle accordinly
    } catch (\GuzzleHttp\Exception\ClientException $e) {
      // This will catch all 400 level errors.
      \Drupal::logger('sr')->error('authPlumGuideAPI : Caught exception: '. $e->getMessage());
    }

    return $data;


  }

  public function authPlumGuideAPI($arr_property) {
    // Get the key value collection
    $kv_store = \Drupal::service('keyvalue.expirable')->get('sr_plumguide');

    // Get the value or null
    $token = $kv_store->get('token', null);

    if (isset($token) && $token != null) {
      //\Drupal::logger('sr_mapping')->notice('authPlumGuideAPI : Reading from cache token : ' . $token);
      return $token;
    }

    $headers = [
      'accept' => 'application/json',
      'Content-Type' => 'application/json',
    ];
    $body = '{
      "clientId": "'.$arr_property['clientId'].'",
      "clientSecret": "'.$arr_property['clientSecret'].'"
    }';
    $endpoint = $arr_property['endpoint_authenticate'];

    try {
      $result = \Drupal::httpClient()->post($endpoint, [
        'body' => $body,
        'headers' => $headers,
      ]);

      if ($result->getStatusCode() == 200) {
        $data = json_decode($result->getBody());
        $token = $data->token;
        $expiresIn = $data->expiresIn;
        // If no value, set the value
        if ($token != '') {
          $kv_store->setWithExpire('token', $token, $expiresIn);
          //\Drupal::logger('sr_mapping')->notice('authPlumGuideAPI : Adding to cache token : ' . $token);
        } else {
          \Drupal::logger('sr_mapping')->error('authPlumGuideAPI : Token empty');
        }
      }
      else {
        \Drupal::logger('sr_mapping')->error('authPlumGuideAPI : Token to get 200 response');
      }
    } catch (Exception $e) {
      \Drupal::logger('sr_mapping')->error('authPlumGuideAPI : Caught exception: ',  $e->getMessage());
    }
    return $token;
  }


  public function getRHPrice($arr_param) {
    $url = 'https://api.worldota.net/api/b2b/v3/search/serp/hotels/';
    $username = "7556";
    $password = "331c5926-5964-423a-aba2-38288ebb74a5";
    $headers = [
      'Content-Type' => 'application/json',
      'Authorization' => 'Basic ' . base64_encode($username.":".$password),
    ];
    $arr_hid = [$arr_param['hid']];
    $arr_hid = array_map('intval', $arr_hid);

    $body = json_encode([
      'checkin' => $arr_param['checkin'],
      'checkout' => $arr_param['checkout'],
      'language' => 'en',
      'guests' => [
        ['adults' => 1, 'children' => []],
      ],
      'hids' => $arr_hid,
      'currency' => 'EUR',
    ]);
//    \Drupal::logger('sr')->error('API Request ' . print_r($body, true));

    try {
      $response = \Drupal::httpClient()->post($url, [
        'headers' => $headers,
        'body' => $body,
        'verify' => false, // Disable SSL verification.
      ]);

      // Decode and return the response.
      return json_decode($response->getBody()->getContents(), TRUE);
    }
    catch (RequestException $e) {
      // Log the error and rethrow the exception.
      \Drupal::logger('sr')->error('API request failed: @message', ['@message' => $e->getMessage()]);
      throw new \Exception('Failed to fetch data from the external API.');
    }
  }
}
