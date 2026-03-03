<?php
namespace Drupal\sr\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\Entity\Node;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\user\Entity\User;
use Drupal\Core\Entity\EntityInterface;
use Drupal\views\Views;
use Drupal\common_utilities\Utilities\commonUtil;
use Drupal\sr\Controller\SrController;
use Drupal\booking\Entity\Booking;
use Symfony\Component\HttpFoundation;
use Drupal\Core\Datetime\DrupalDateTime;


class PropertySubForm extends FormBase {
  var $arr_request_param = array(
    'country_code' => '', 'pid' => '',
    'price_min' => '', 'price_max' => '',
    'bathroom' => '', 'bedroom' => '',
    'date_range' => '', 'town_city' => '',
    'adult' => '', 'kid' => '',
    'amenities' => '', 'sort' => '',
  );

  public function getFormId() {
    return 'price';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $flag_product_detail = true;
    $flag_show_price = true;
    $form['error'] = array(
      '#title_display' => 'invisible',
      '#markup' => "Sorry, something went wrong. Please try again later.",
      '#prefix' => '<div class="form-error">',
      '#suffix' => '</div>',
    );

    $pid = \Drupal::request()->query->get('pid');

    if ($pid == '' || $pid <= 0) {
      $pid = 0;
      $node = \Drupal::routeMatch()->getParameter('node');
      if ($node instanceof \Drupal\node\NodeInterface) {
        $pid = $node->id();
      } else {
        // redirect user to error page.
      }
    }

    if ($pid <= 0) {
      return $form;
    }

    # Load from node id
    $node = Node::load($pid);
    if ($node == NULL || !$node->isPublished()) {
      return $form;
    }

    unset($form['error']);

    $current_user = \Drupal\user\Entity\User::load(\Drupal::currentUser()->id());

    $form_param = array();

    foreach ($this->arr_request_param as $key_param => $val_param) {
      $query_value = \Drupal::request()->query->get($key_param);
      if ($query_value != '') {
        $form_param[$key_param] = urldecode(trim($query_value));
      } else {
        $form_param[$key_param] = '';
      }
    }

    // Create an entity query for nodes.
    $query = \Drupal::entityQuery('node')
    ->condition('status', 1)
    ->condition('type', 'property')
    ->condition('field_parent_id', $pid)
    ->accessCheck(FALSE);

    // Execute the query to get node IDs.
    $nids = $query->execute();

    if (!empty($nids)) {
      $reference_id = $node->get('field_reference_id')->getString();
      $arr_reference_id = explode('--', $reference_id);
      $reference_id = $arr_reference_id[0];

/*
      $from_date = "2025-05-01";
      $to_date = "2025-05-30";
//$reference_id = "10469533";
      $arr_api_param = array('hid' => $reference_id, 'checkin' => $from_date, 'checkout' => $to_date);


      $arr_api_result = \Drupal::service('sr.services')->getRHPrice($arr_api_param);
*/

      $form['sub_property_result'] = array(
        '#type' => 'fieldset',
        '#title' => $this
          ->t('Room Types'),
        '#attributes' => array(
            'class' => array('SectionContainer'),
        )
      );

      $form['sub_property_result']['result'] = array(
        '#type' => 'table',
        '#header' => array(
          $this->t(''),
          $this->t(''),
          $this->t(''),
          $this->t(''),
          $this->t(''),
        ),
        '#attributes' => array(
          'class' => array(
            'tbl-sub-property',
          ),
        ),
      );

// Set a limit (e.g., 5)
$limit = 6;

// Slice the array to limit the number of nodes
$nids_limited = array_slice($nids, 0, $limit);

      // Load the nodes if needed.
      $nodes = \Drupal\node\Entity\Node::loadMultiple($nids_limited);
      $counter=0;
      foreach ($nodes as $node) {
        // Access node fields and properties.
        $title = $node->getTitle();

        // Generate the node URL.
        $url = $node->toUrl();
        $url->setOption('query', $form_param);
        $absolute_url_with_query = $url->setAbsolute()->toString();

        // Get image
        $image_url = "https://images.unsplash.com/photo-1517840901100-8179e982acb7?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxzZWFyY2h8Mnx8aG90ZWx8ZW58MHx8MHx8fDA%3D&w=1000&q=80";
        $field_media = $node->get('field_media')->value;
        if ($field_media != ""){
          $arr_img = unserialize($field_media);
          if(is_array($arr_img) && !empty($arr_img) && $arr_img[0]['url']) {
            $image_url = $arr_img[0]['url'];
          }
        }
        $image = "<img src='".$image_url."' width='100' height='100' loading='lazy'/>";

        $form_param['pid'] = $node->id();
        $booking_url =  Url::fromRoute('sr.booking', array_map('urlencode', $form_param)
        , ['absolute' => TRUE])->toString();

        $bedroom = $node->get('field_total_bedrooms')->value;
        $bathroom = $node->get('field_total_bathrooms')->value;
        $property_source = $node->get('field_property_source')->value;
        $form['sub_property_result']['result'][$counter]['url'] = array(
          '#title_display' => 'invisible',
          '#markup' => $absolute_url_with_query,
        );

        $form['sub_property_result']['result'][$counter]['image'] = array(
          '#title_display' => 'invisible',
          '#markup' => $image,
          '#prefix' => '<div class="result-image-sub">',
          '#suffix' => '<span class="more-icon"><i class="fa-solid fa-circle-info"></i></span></div>',
        );
        $arr_title = explode('--', $title);
        $title = end($arr_title);
        $form['sub_property_result']['result'][$counter]['title'] = array(
          '#title_display' => 'invisible',
          '#markup' => $title,
          '#prefix' => '<div class="result-title">',
          '#suffix' => '</div>',
        );

        $form['sub_property_result']['result'][$counter]['info'] = array(
          '#title_display' => 'invisible',
          '#markup' => $bedroom . " Bed & ",
        );

        $form['sub_property_result']['result'][$counter]['bathroom'] = array(
          '#title_display' => 'invisible',
          '#markup' => $bathroom . " Bath",
        );

        // Modified info section to handle Studio Apartment case

        if ($property_source == 'ratehawk' && $bedroom == 0) {
          $form['sub_property_result']['result'][$counter]['info'] = array(
              '#title_display' => 'invisible',
              '#markup' => 'Studio Apartment',
          );
          
          // Hide the bathroom field or show it differently if needed
          $form['sub_property_result']['result'][$counter]['bathroom'] = array(
              '#title_display' => 'invisible',
              '#markup' => $bathroom . " Bath",
          );
        } else {
          $form['sub_property_result']['result'][$counter]['info'] = array(
              '#title_display' => 'invisible',
              '#markup' => $bedroom . " Bed & ",
          );
          
          $form['sub_property_result']['result'][$counter]['bathroom'] = array(
              '#title_display' => 'invisible',
              '#markup' => $bathroom . " Bath",
          );
        }

        $form['sub_property_result']['result'][$counter]['price'] = array(
          '#title_display' => 'invisible',
          '#markup' => '',
        );

        $form['sub_property_result']['result'][$counter]['booking_link'] = array(
          '#title_display' => 'invisible',
          '#markup' => $booking_url,
        );
        $counter++;
      }
    }

    $form['#theme'] = 'sr_property_sub_form';

    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state) {

  }

  public function submitForm(array &$form, FormStateInterface $form_state) {

  }
}
