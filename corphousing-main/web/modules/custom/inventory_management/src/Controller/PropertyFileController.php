<?php
namespace Drupal\inventory_management\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\inventory_management\files;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\file\Entity\File;
use Drupal\user\Entity\User;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PropertyFileController extends ControllerBase {

  public function list() {
    $arr_admin_roles = array('administrator', 'site_admin');

    $user = User::load(\Drupal::currentUser()->id());
    $roles = $user->getRoles();

    if (!array_intersect($roles, $arr_admin_roles)) {
      // Validate if the user is an admin, if yes show all properties
      throw new AccessDeniedHttpException();
    }

    $form['upload_property_link'] = [
        '#type' => 'link',
        '#title' => 'Upload Property',
        '#url' => Url::fromRoute('inventory_management.property_upload'),
    ];

    $form['property_list_link'] = [
        '#type' => 'link',
        '#title' => 'Property Listing',
        '#url' => Url::fromRoute('inventory_management.property_list'),
        '#attributes' => [
            'class' => ['button', 'button--primary', 'property-list-button'],
        ],
    ];

    $form['back_link'] = [
        '#type' => 'link',
        '#title' => 'Back',
        '#url' => Url::fromRoute('inventory_management.property_list'),
        '#attributes' => [
            'class' => ['back-link-button'],
        ],
    ];

    $obj_propertyfile = new files();

    $arg_data = array('query' => array());
    $items_per_page = \Drupal::config('inventory_management.settings')->get('items_per_page');
    if (!$items_per_page) {
        $items_per_page = 20; // Default value if not set
    }
    $arr_result = $obj_propertyfile->searchPropertyFile($arg_data, $items_per_page);

    if (is_array($arr_result['search_result']) && count($arr_result['search_result']) > 0) {
        $form['search_result']['result'] = array(
            '#type' => 'table',
            '#header' => array(
                $this->t('File'),
                $this->t('Uploaded By'),
                $this->t('Uploaded On'),
                $this->t('Status'),
                $this->t('Error'),
            ),
            '#attributes' => array(
                'class' => array(
                'tbl-vendor',
                ),
            ),
        );

        foreach ($arr_result['search_result'] as $key_result => $val_result) {
            $file = File::load($val_result['file__target_id']);

            if ($file) {
                // Get the full URI (e.g., public://my-folder/myfile.txt)
                $uri = $file->getFileUri();
                $stream = \Drupal::service('stream_wrapper_manager')->getViaUri($uri);
                $url = "#";
                if (is_object($stream)) {
                    $url = $stream->getExternalUrl();
                }
                
                $username = '';
                if ($val_result['uid'] > 0) {
                    $user = User::load($val_result['uid']);

                    if ($user) {
                        $username = $user->getAccountName();
                    }
                }
                
                $form['search_result']['result'][$key_result]['file'] = array(
                    '#title_display' => 'invisible',
                    '#markup' => "<a href='".$url."' target='_blank'>Download File</a>",
                );
                $form['search_result']['result'][$key_result]['uploaded_by'] = array(
                    '#title_display' => 'invisible',
                    '#markup' => "".$username."",
                );
                $form['search_result']['result'][$key_result]['uploaded_on'] = array(
                    '#title_display' => 'invisible',
                    '#markup' => "".date('m/d/Y H:i:s', $val_result['created'])."",
                );
                $form['search_result']['result'][$key_result]['file_status'] = array(
                    '#title_display' => 'invisible',
                    '#markup' => "".$val_result['file_status']."",
                );
                $form['search_result']['result'][$key_result]['error'] = array(
                    '#title_display' => 'invisible',
                    '#markup' => "".$val_result['error__value']."",
                );
            }

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
        '#markup' => t('Sorry, no record found!'),
      ];
    }

    $form['#theme'] = 'property_file_list';

    return $form;
  }

  /**
   * Download CSV reference file.
   */
  public function downloadCsvReference() {
    $arr_admin_roles = array('administrator', 'site_admin');
    $user = User::load(\Drupal::currentUser()->id());
    $roles = $user->getRoles();

    if (!array_intersect($roles, $arr_admin_roles)) {
      throw new AccessDeniedHttpException();
    }

    $module_path = \Drupal::service('extension.list.module')->getPath('inventory_management');
    $file_path = \Drupal::service('file_system')->realpath($module_path . '/property_upload_reference.csv');

    if (file_exists($file_path)) {
      $response = new BinaryFileResponse($file_path);
      $response->setContentDisposition('attachment', 'property_upload_reference.csv');
      return $response;
    }

    throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException('CSV file not found.');
  }

}
