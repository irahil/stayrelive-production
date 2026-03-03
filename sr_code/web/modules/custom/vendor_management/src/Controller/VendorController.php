<?php
namespace Drupal\vendor_management\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\vendor_management\vendor;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\user\Entity\User;

class VendorController extends ControllerBase {

  public function list() {
    $obj_vendor = new vendor(); 
    $arg_data = array('query' => array());
    $items_per_page = \Drupal::config('inventory_management.settings')->get('items_per_page');
    if (!$items_per_page) {
        $items_per_page = 20; // Default value if not set
    }
    $arr_result = $obj_vendor->searchVendor($arg_data, $items_per_page);

    if (is_array($arr_result['search_result']) && count($arr_result['search_result']) > 0) {
        $form['search_result']['result'] = array(
            '#type' => 'table',
            '#header' => array(
                $this->t('Name'),
                $this->t('Status'),
                $this->t('Requested By'),
                $this->t('Requested On'),
                $this->t('Action'),
            ),
            '#attributes' => array(
                'class' => array(
                'tbl-vendor',
                ),
            ),
        );

        foreach ($arr_result['search_result'] as $key_result => $val_result) {
            $edit_url = Url::fromRoute('vendor_management.vendor_edit', [
            'vendor_id' =>$val_result['id'],
            ], ['absolute' => TRUE])->toString();
            $link = Link::fromTextAndUrl(
                    $this->t('Edit'),
                Url::fromUri( $edit_url,
                    array('absolute' => TRUE,)))->toString();

            $form['search_result']['result'][$key_result]['name'] = array(
                '#title_display' => 'invisible',
                '#markup' => "".$val_result['name']."",
            );
            $form['search_result']['result'][$key_result]['status'] = array(
                '#title_display' => 'invisible',
                '#markup' => "".($val_result['status'] == TRUE) ? "Active" : "Inactive"."",
            );

            $email = "";
            if ($val_result['uid']>0) {
                $user = User::load($val_result['uid']);
                if ($user) {
                    $email = $user->getEmail();
                }
            }

            $form['search_result']['result'][$key_result]['created_by'] = array(
                '#title_display' => 'invisible',
                '#markup' => "".$email."",
            );
            $form['search_result']['result'][$key_result]['created_date'] = array(
                '#title_display' => 'invisible',
                '#markup' => "".date('Y-m-d H:i:s', $val_result['created'])."",
            );
            $form['search_result']['result'][$key_result]['edit'] = array(
                '#title_display' => 'invisible',
                '#markup' => $link,
            );
        }

        $form['search_result']['pager'] = array(
        '#type' => 'markup',
        '#markup' => $arr_result['search_pager'],
        );
    }
    else {
      $form['search_result']['no_result'] = [
        '#type' => 'item',
        '#title' => t('Result'),
        '#markup' => t('Sorry, no booking found!'),
      ];
    }


    return $form;
  }


}
