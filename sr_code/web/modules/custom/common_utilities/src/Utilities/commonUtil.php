<?php
namespace Drupal\common_utilities\Utilities;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\user\Entity\User;
use Drupal\taxonomy\Entity\Term;
use Drupal\views\Views;

class commonUtil {
    public static function formatCurrency($price) {
        $formatted_price = 0;
        if ($price > 0) {
            $formatted_price = number_format($price, 2, ".", ",");
        }
        return $formatted_price;
    }

    public static function getProjectListByStage($project_stage) {
        return (Url::fromRoute('projects.list',
          array(
            'project_stage' => urldecode($project_stage),
        ))->toString());
    }

    public static function getKeyFromArray($arr_project_stage, $arr_search) {
        $list_keys = '';
        foreach($arr_search as $val_search) {
            $key = array_search(strtolower($val_search), array_map('strtolower', $arr_project_stage));
            if ($key != '') {
                $list_keys.= $key.",";
            }
        }
        $list_keys = rtrim($list_keys, ",");
        return $list_keys;
    }

    public static function check_role_to_allow($current_roles, $arr_allow_role) {
        foreach ($arr_allow_role as $val_allow_role) {
            if (in_array($val_allow_role, $current_roles)) {
                return true;
            }
        }
        return false;
    }

    public static function secondsToWords($seconds) {
        $ret = "";

        // get the days
        $days = intval(intval($seconds) / (3600*24));
        if($days> 0) {
            //$ret .= "$days days ";
            return "$days days ";
        }

        // get the hours
        $hours = (intval($seconds) / 3600) % 24;
        if($hours > 0) {
            //$ret .= "$hours hours ";
            return  "$hours hours ";
        }

        //get the minutes
        $minutes = (intval($seconds) / 60) % 60;
        if($minutes > 0) {
            //$ret .= "$minutes minutes ";
            return "$minutes minutes ";
        }

        // get the seconds
        $seconds = intval($seconds) % 60;
        if ($seconds > 0) {
            //$ret .= "$seconds seconds";
            return "$seconds seconds";
        }
        //return $ret;
    }

    public static function getTermById($id) {
        if ($id != NULL) {
            $term = Term::load($id);
            return $term->getName();
        }
    }

    public static function getUsersByRole($role = '') {
        $userlist = array();
        if($role == '') {
            $ids = \Drupal::entityQuery('user')
            ->condition('status', 1)
            ->condition('uid', 1, '>')
            ->execute();
        } else {
            $ids = \Drupal::entityQuery('user')
            ->condition('status', 1)
            ->condition('uid', 1, '>')
            ->condition('roles', $role)
            ->execute();
        }

        $users = User::loadMultiple($ids);
        foreach($users as $user){
          $username = $user->get('name')->value;
          $uid = $user->get('uid')->value;
          $userlist[$uid] = $username;
        }

        // If user not found
        if (is_array($userlist) && count($userlist) <= 0) {
          $userlist = array('0' => 'User not found');
        } else {
          $userlist['0'] = 'Select user';
        }

        ksort($userlist);
        return $userlist;
    }

    public static function getUsersEmailByRole($role = '') {
        $userlist = array();
        if($role == '') {
            $ids = \Drupal::entityQuery('user')
            ->condition('status', 1)
            ->condition('uid', 1, '>')
            ->execute();
        } else {
            $ids = \Drupal::entityQuery('user')
            ->condition('status', 1)
            ->condition('roles', $role)
            ->condition('uid', 1, '>')
            ->execute();
        }
        $users = User::loadMultiple($ids);
        foreach($users as $user){
          $email = $user->get('mail')->value;
          $uid = $user->get('uid')->value;
          $userlist[$uid] = $email;
        }
        return $userlist;
    }

    public static function fn_array_key_first(array $arr) {
        foreach($arr as $key => $value) {
            return $key;
        }
        return NULL;
    }

    public static function getUsernameById($uid) {
      if ($uid > 0) {
        $account = \Drupal\user\Entity\User::load($uid);
        if ($account != NULL)
        return $account->getUsername();
      }
      return false;
    }

    public static function getEmailById($uid) {
      if ($uid > 0) {
        $account = \Drupal\user\Entity\User::load($uid);
        return $account->getEmail();
      }
      return false;
    }

    public static function clean_string($string) {
        $string = str_replace(' ', '-', $string); // Replaces all spaces with hyphens.

        return preg_replace('/[^A-Za-z0-9\-]/', '', $string); // Removes special chars.
    }

    public static function generate_iwo_code($iwo_id) {
        //$iwo_code = "IWO - ".sprintf('%08d', $iwo_id);
        $iwo_code = "IWO - ".$iwo_id;
        return $iwo_code;
    }

    public static function get_term_list($vid, $type = "name") {
        $arr_term_list = array();

        $query = \Drupal::entityQuery('taxonomy_term');
        $query->condition('vid', $vid);
        $query->sort('tid');
        $query->accessCheck(TRUE);
        $tids = $query->execute();
        $terms = \Drupal\taxonomy\Entity\Term::loadMultiple($tids);

        foreach($terms as $term) {
            $tid = $term->id();
            if ($type == "all") {
                $arr_term_list[$tid]['name'] = $term->get("name")->value;
                if ($vid == "compliances" || $vid == "business_vertical") {
                    $arr_term_list[$tid]['short_name'] = $term->get("field_short_name")->value;
                }
                if ($vid == "compliances") {
                    $arr_term_list[$tid]['mandatory'] = $term->get("field_mandatory")->value;
                    $arr_term_list[$tid]['team'] = $term->get("field_team")->value;
                    $arr_term_list[$tid]['parent'] = $term->parent[0]->target_id;
                }
            } elseif ($type == "color") {
                $arr_term_list[$tid]['name'] = $term->get("name")->value;
                $arr_term_list[$tid]['color'] = $term->get("field_graph_color")->value;
            } elseif ($type == "country") {
                $arr_term_list[$tid] = $term->get("field_country_name")->value;
            } else {
                $arr_term_list[$tid] = $term->get("name")->value;
            }
        }
        return $arr_term_list;
    }

    public static function my_generate_hyperlink($text, $path) {
        return Link::fromTextAndUrl(t($text),
            Url::fromUri( $path,
                array('absolute' => TRUE,)))->toString();
    }

    public static function my_generate_hyperlink_internal($text, $path) {
        return Link::fromTextAndUrl(t($text),
            Url::fromUri('internal:' . $path,
                array('absolute' => TRUE,)))->toString();
    }

    public static function my_generate_url($path) {
        return Url::fromUri('internal:' . $path,
            array('absolute' => TRUE,))->toString();
    }

    public static function my_goto($path) {
        //$path = self::my_generate_url($path);
        $response = new RedirectResponse( $path, 302);
        $response->send();
        //return $response = new RedirectResponse($path, 302);
        //\Drupal::service('request_stack')->getCurrentRequest()->query->set('destination', $path);
        return;
    }

    public static function my_goto_t2($path) {
        \Drupal::service('request_stack')->getCurrentRequest()->query->set('destination', $path);
        return;
    }

    public static function my_goto_error() {
        $path = self::my_generate_url("/error");
        //$response = new RedirectResponse( $path, 302);
        //$response->send();
        \Drupal::service('request_stack')->getCurrentRequest()->query->set('destination', $path);
        return;
    }

    public static function my_round($number) {
        return round(($number),2,PHP_ROUND_HALF_UP);
    }

    public static function my_number_format($number, $type = 'inr', $decimal = 0) {
        if ($type == 'usd') {
            return '$ ' . number_format($number, $decimal);
        }
        return 'INR ' . number_format($number, $decimal);
    }

    public static function my_round_format($number, $type = 'inr', $decimal = 0) {
        return self::my_number_format(self::my_round($number), $type, $decimal);
    }

    public static function getTagValues($str, $type = '') {
        if ($type == 'gtlt') {
            preg_match("/<(.+?)>(.+?)<[\/](.+?)>/", $str, $matches);
        } else {
            preg_match("/{(.+?)}(.+?){[\/](.+?)}/", $str, $matches);
        }
        return array('tag' => $matches[1], 'content' => $matches[2]);
    }

    public static function clean($string) {
        // Replaces all spaces with hyphens.
        $string = preg_replace('/[^A-Za-z0-9-$&*!<>|#@;\:,.\/():\'\" ]/', '-', $string); // Removes special chars.
        return trim($string);
    }

    public static function mime_content_type($filename) {
        $mime_types = array(

            'txt' => 'text/plain',
            'htm' => 'text/html',
            'html' => 'text/html',
            'php' => 'text/html',
            'css' => 'text/css',
            'js' => 'application/javascript',
            'json' => 'application/json',
            'xml' => 'application/xml',
            'swf' => 'application/x-shockwave-flash',
            'flv' => 'video/x-flv',

            // images
            'png' => 'image/png',
            'jpe' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'jpg' => 'image/jpeg',
            'gif' => 'image/gif',
            'bmp' => 'image/bmp',
            'ico' => 'image/vnd.microsoft.icon',
            'tiff' => 'image/tiff',
            'tif' => 'image/tiff',
            'svg' => 'image/svg+xml',
            'svgz' => 'image/svg+xml',

            // archives
            'zip' => 'application/zip',
            'rar' => 'application/x-rar-compressed',
            'exe' => 'application/x-msdownload',
            'msi' => 'application/x-msdownload',
            'cab' => 'application/vnd.ms-cab-compressed',

            // audio/video
            'mp3' => 'audio/mpeg',
            'qt' => 'video/quicktime',
            'mov' => 'video/quicktime',

            // adobe
            'pdf' => 'application/pdf',
            'psd' => 'image/vnd.adobe.photoshop',
            'ai' => 'application/postscript',
            'eps' => 'application/postscript',
            'ps' => 'application/postscript',

            // ms office
            'doc' => 'application/msword',
            'rtf' => 'application/rtf',
            'xls' => 'application/vnd.ms-excel',
            'ppt' => 'application/vnd.ms-powerpoint',

            // open office
            'odt' => 'application/vnd.oasis.opendocument.text',
            'ods' => 'application/vnd.oasis.opendocument.spreadsheet',
        );

        $ext = strtolower(array_pop(explode('.',$filename)));
        if (array_key_exists($ext, $mime_types)) {
            return $mime_types[$ext];
        }
        elseif (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME);
            $mimetype = finfo_file($finfo, $filename);
            finfo_close($finfo);
            return $mimetype;
        }
        else {
            return 'application/octet-stream';
        }
    }

    public static function fn_get_phone_code() {
        $arr_phone_code['Afghanistan'] = '93';
        $arr_phone_code['Albania'] = '355';
        $arr_phone_code['Algeria'] = '213';
        $arr_phone_code['American Samoa'] = '1-684';
        $arr_phone_code['Andorra'] = '376';
        $arr_phone_code['Angola'] = '244';
        $arr_phone_code['Anguilla'] = '1-264';
        $arr_phone_code['Antarctica'] = '672';
        $arr_phone_code['Antigua and Barbuda'] = '1-268';
        $arr_phone_code['Argentina'] = '54';
        $arr_phone_code['Armenia'] = '374';
        $arr_phone_code['Aruba'] = '297';
        $arr_phone_code['Australia'] = '61';
        $arr_phone_code['Austria'] = '43';
        $arr_phone_code['Azerbaijan'] = '994';
        $arr_phone_code['Bahamas'] = '1-242';
        $arr_phone_code['Bahrain'] = '973';
        $arr_phone_code['Bangladesh'] = '880';
        $arr_phone_code['Barbados'] = '1-246';
        $arr_phone_code['Belarus'] = '375';
        $arr_phone_code['Belgium'] = '32';
        $arr_phone_code['Belize'] = '501';
        $arr_phone_code['Benin'] = '229';
        $arr_phone_code['Bermuda'] = '1-441';
        $arr_phone_code['Bhutan'] = '975';
        $arr_phone_code['Bolivia'] = '591';
        $arr_phone_code['Bosnia and Herzegovina'] = '387';
        $arr_phone_code['Botswana'] = '267';
        $arr_phone_code['Brazil'] = '55';
        $arr_phone_code['British Indian Ocean Territory'] = '246';
        $arr_phone_code['British Virgin Islands'] = '1-284';
        $arr_phone_code['Brunei'] = '673';
        $arr_phone_code['Bulgaria'] = '359';
        $arr_phone_code['Burkina Faso'] = '226';
        $arr_phone_code['Burundi'] = '257';
        $arr_phone_code['Cambodia'] = '855';
        $arr_phone_code['Cameroon'] = '237';
        $arr_phone_code['Canada'] = '1';
        $arr_phone_code['Cape Verde'] = '238';
        $arr_phone_code['Cayman Islands'] = '1-345';
        $arr_phone_code['Central African Republic'] = '236';
        $arr_phone_code['Chad'] = '235';
        $arr_phone_code['Chile'] = '56';
        $arr_phone_code['China'] = '86';
        $arr_phone_code['Christmas Island'] = '61';
        $arr_phone_code['Cocos Islands'] = '61';
        $arr_phone_code['Colombia'] = '57';
        $arr_phone_code['Comoros'] = '269';
        $arr_phone_code['Cook Islands'] = '682';
        $arr_phone_code['Costa Rica'] = '506';
        $arr_phone_code['Croatia'] = '385';
        $arr_phone_code['Cuba'] = '53';
        $arr_phone_code['Curacao'] = '599';
        $arr_phone_code['Cyprus'] = '357';
        $arr_phone_code['Czech Republic'] = '420';
        $arr_phone_code['Democratic Republic of the Congo'] = '243';
        $arr_phone_code['Denmark'] = '45';
        $arr_phone_code['Djibouti'] = '253';
        $arr_phone_code['Dominica'] = '1-767';
        $arr_phone_code['Dominican Republic'] = '1-809';
        $arr_phone_code['East Timor'] = '670';
        $arr_phone_code['Ecuador'] = '593';
        $arr_phone_code['Egypt'] = '20';
        $arr_phone_code['El Salvador'] = '503';
        $arr_phone_code['Equatorial Guinea'] = '240';
        $arr_phone_code['Eritrea'] = '291';
        $arr_phone_code['Estonia'] = '372';
        $arr_phone_code['Ethiopia'] = '251';
        $arr_phone_code['Falkland Islands'] = '500';
        $arr_phone_code['Faroe Islands'] = '298';
        $arr_phone_code['Fiji'] = '679';
        $arr_phone_code['Finland'] = '358';
        $arr_phone_code['France'] = '33';
        $arr_phone_code['French Polynesia'] = '689';
        $arr_phone_code['Gabon'] = '241';
        $arr_phone_code['Gambia'] = '220';
        $arr_phone_code['Georgia'] = '995';
        $arr_phone_code['Germany'] = '49';
        $arr_phone_code['Ghana'] = '233';
        $arr_phone_code['Gibraltar'] = '350';
        $arr_phone_code['Greece'] = '30';
        $arr_phone_code['Greenland'] = '299';
        $arr_phone_code['Grenada'] = '1-473';
        $arr_phone_code['Guam'] = '1-671';
        $arr_phone_code['Guatemala'] = '502';
        $arr_phone_code['Guernsey'] = '44-1481';
        $arr_phone_code['Guinea'] = '224';
        $arr_phone_code['Guinea-Bissau'] = '245';
        $arr_phone_code['Guyana'] = '592';
        $arr_phone_code['Haiti'] = '509';
        $arr_phone_code['Honduras'] = '504';
        $arr_phone_code['Hong Kong'] = '852';
        $arr_phone_code['Hungary'] = '36';
        $arr_phone_code['Iceland'] = '354';
        $arr_phone_code['India'] = '91';
        $arr_phone_code['Indonesia'] = '62';
        $arr_phone_code['Iran'] = '98';
        $arr_phone_code['Iraq'] = '964';
        $arr_phone_code['Ireland'] = '353';
        $arr_phone_code['Isle of Man'] = '44-1624';
        $arr_phone_code['Israel'] = '972';
        $arr_phone_code['Italy'] = '39';
        $arr_phone_code['Ivory Coast'] = '225';
        $arr_phone_code['Jamaica'] = '1-876';
        $arr_phone_code['Japan'] = '81';
        $arr_phone_code['Jersey'] = '44-1534';
        $arr_phone_code['Jordan'] = '962';
        $arr_phone_code['Kazakhstan'] = '7';
        $arr_phone_code['Kenya'] = '254';
        $arr_phone_code['Kiribati'] = '686';
        $arr_phone_code['Kosovo'] = '383';
        $arr_phone_code['Kuwait'] = '965';
        $arr_phone_code['Kyrgyzstan'] = '996';
        $arr_phone_code['Laos'] = '856';
        $arr_phone_code['Latvia'] = '371';
        $arr_phone_code['Lebanon'] = '961';
        $arr_phone_code['Lesotho'] = '266';
        $arr_phone_code['Liberia'] = '231';
        $arr_phone_code['Libya'] = '218';
        $arr_phone_code['Liechtenstein'] = '423';
        $arr_phone_code['Lithuania'] = '370';
        $arr_phone_code['Luxembourg'] = '352';
        $arr_phone_code['Macau'] = '853';
        $arr_phone_code['Macedonia'] = '389';
        $arr_phone_code['Madagascar'] = '261';
        $arr_phone_code['Malawi'] = '265';
        $arr_phone_code['Malaysia'] = '60';
        $arr_phone_code['Maldives'] = '960';
        $arr_phone_code['Mali'] = '223';
        $arr_phone_code['Malta'] = '356';
        $arr_phone_code['Marshall Islands'] = '692';
        $arr_phone_code['Mauritania'] = '222';
        $arr_phone_code['Mauritius'] = '230';
        $arr_phone_code['Mayotte'] = '262';
        $arr_phone_code['Mexico'] = '52';
        $arr_phone_code['Micronesia'] = '691';
        $arr_phone_code['Moldova'] = '373';
        $arr_phone_code['Monaco'] = '377';
        $arr_phone_code['Mongolia'] = '976';
        $arr_phone_code['Montenegro'] = '382';
        $arr_phone_code['Montserrat'] = '1-664';
        $arr_phone_code['Morocco'] = '212';
        $arr_phone_code['Mozambique'] = '258';
        $arr_phone_code['Myanmar'] = '95';
        $arr_phone_code['Namibia'] = '264';
        $arr_phone_code['Nauru'] = '674';
        $arr_phone_code['Nepal'] = '977';
        $arr_phone_code['Netherlands'] = '31';
        $arr_phone_code['Netherlands Antilles'] = '599';
        $arr_phone_code['New Caledonia'] = '687';
        $arr_phone_code['New Zealand'] = '64';
        $arr_phone_code['Nicaragua'] = '505';
        $arr_phone_code['Niger'] = '227';
        $arr_phone_code['Nigeria'] = '234';
        $arr_phone_code['Niue'] = '683';
        $arr_phone_code['North Korea'] = '850';
        $arr_phone_code['Northern Mariana Islands'] = '1-670';
        $arr_phone_code['Norway'] = '47';
        $arr_phone_code['Oman'] = '968';
        $arr_phone_code['Pakistan'] = '92';
        $arr_phone_code['Palau'] = '680';
        $arr_phone_code['Palestine'] = '970';
        $arr_phone_code['Panama'] = '507';
        $arr_phone_code['Papua New Guinea'] = '675';
        $arr_phone_code['Paraguay'] = '595';
        $arr_phone_code['Peru'] = '51';
        $arr_phone_code['Philippines'] = '63';
        $arr_phone_code['Pitcairn'] = '64';
        $arr_phone_code['Poland'] = '48';
        $arr_phone_code['Portugal'] = '351';
        $arr_phone_code['Puerto Rico'] = '1-787';
        $arr_phone_code['Qatar'] = '974';
        $arr_phone_code['Republic of the Congo'] = '242';
        $arr_phone_code['Reunion'] = '262';
        $arr_phone_code['Romania'] = '40';
        $arr_phone_code['Russia'] = '7';
        $arr_phone_code['Rwanda'] = '250';
        $arr_phone_code['Saint Barthelemy'] = '590';
        $arr_phone_code['Saint Helena'] = '290';
        $arr_phone_code['Saint Kitts and Nevis'] = '1-869';
        $arr_phone_code['Saint Lucia'] = '1-758';
        $arr_phone_code['Saint Martin'] = '590';
        $arr_phone_code['Saint Pierre and Miquelon'] = '508';
        $arr_phone_code['Saint Vincent and the Grenadines'] = '1-784';
        $arr_phone_code['Samoa'] = '685';
        $arr_phone_code['San Marino'] = '378';
        $arr_phone_code['Sao Tome and Principe'] = '239';
        $arr_phone_code['Saudi Arabia'] = '966';
        $arr_phone_code['Senegal'] = '221';
        $arr_phone_code['Serbia'] = '381';
        $arr_phone_code['Seychelles'] = '248';
        $arr_phone_code['Sierra Leone'] = '232';
        $arr_phone_code['Singapore'] = '65';
        $arr_phone_code['Sint Maarten'] = '1-721';
        $arr_phone_code['Slovakia'] = '421';
        $arr_phone_code['Slovenia'] = '386';
        $arr_phone_code['Solomon Islands'] = '677';
        $arr_phone_code['Somalia'] = '252';
        $arr_phone_code['South Africa'] = '27';
        $arr_phone_code['South Korea'] = '82';
        $arr_phone_code['South Sudan'] = '211';
        $arr_phone_code['Spain'] = '34';
        $arr_phone_code['Sri Lanka'] = '94';
        $arr_phone_code['Sudan'] = '249';
        $arr_phone_code['Suriname'] = '597';
        $arr_phone_code['Svalbard and Jan Mayen'] = '47';
        $arr_phone_code['Swaziland'] = '268';
        $arr_phone_code['Sweden'] = '46';
        $arr_phone_code['Switzerland'] = '41';
        $arr_phone_code['Syria'] = '963';
        $arr_phone_code['Taiwan'] = '886';
        $arr_phone_code['Tajikistan'] = '992';
        $arr_phone_code['Tanzania'] = '255';
        $arr_phone_code['Thailand'] = '66';
        $arr_phone_code['Togo'] = '228';
        $arr_phone_code['Tokelau'] = '690';
        $arr_phone_code['Tonga'] = '676';
        $arr_phone_code['Trinidad and Tobago'] = '1-868';
        $arr_phone_code['Tunisia'] = '216';
        $arr_phone_code['Turkey'] = '90';
        $arr_phone_code['Turkmenistan'] = '993';
        $arr_phone_code['Turks and Caicos Islands'] = '1-649';
        $arr_phone_code['Tuvalu'] = '688';
        $arr_phone_code['U.S. Virgin Islands'] = '1-340';
        $arr_phone_code['Uganda'] = '256';
        $arr_phone_code['Ukraine'] = '380';
        $arr_phone_code['United Arab Emirates'] = '971';
        $arr_phone_code['United Kingdom'] = '44';
        $arr_phone_code['United States'] = '1';
        $arr_phone_code['Uruguay'] = '598';
        $arr_phone_code['Uzbekistan'] = '998';
        $arr_phone_code['Vanuatu'] = '678';
        $arr_phone_code['Vatican'] = '379';
        $arr_phone_code['Venezuela'] = '58';
        $arr_phone_code['Vietnam'] = '84';
        $arr_phone_code['Wallis and Futuna'] = '681';
        $arr_phone_code['Western Sahara'] = '212';
        $arr_phone_code['Yemen'] = '967';
        $arr_phone_code['Zambia'] = '260';
        $arr_phone_code['Zimbabwe'] = '263';

        $arr_output = array();
        foreach ($arr_phone_code as $key => $value) {
            $arr_output[$value] = $key . " (+".$value.")";
        }

        return $arr_output;
    }
}