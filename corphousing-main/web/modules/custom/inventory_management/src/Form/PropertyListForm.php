<?php

namespace Drupal\inventory_management\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\inventory_management\property;
use Drupal\Core\Url;
use Drupal\user\Entity\User;
use Drupal\node\Entity\Node;
use Drupal\vendor_management\vendor;
use Drupal\common_utilities\Utilities\commonUtil;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class PropertyListForm extends FormBase {

  public function getFormId() {
    return 'property_list_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {

    $arr_admin_roles = array('administrator', 'site_admin');
    $arr_vendor_roles = array('vendor');

    $user = User::load(\Drupal::currentUser()->id());
    $roles = $user->getRoles();
    $property_name = \Drupal::request()->query->get('property_name');
    $city = \Drupal::request()->query->get('city');
    $status = (string) \Drupal::request()->query->get('status');
    $vendor_id = \Drupal::request()->query->get('vendor_id');

    $arr_query = array('sort' => 'created', 'order' => 'DESC');
    if (!empty($property_name)) {
      $arr_query['property_name'] = $property_name;
    }

    if (!empty($city)) {
      $arr_query['city'] = $city;
    }

    if (!empty($vendor_id)) {
      $arr_query['vendor_id'] = $vendor_id;
    }

    if ($status == '1' || $status == '0' || $status == 'All') {
      $arr_query['status'] = $status;
    }

    $is_admin = FALSE;
    if (array_intersect($roles, $arr_admin_roles)) {
      // Validate if the user is an admin, if yes show all properties
      $is_admin = TRUE;
    } elseif (array_intersect($roles, $arr_vendor_roles)) {
      $vendor_id = $user->field_vendor_id->value;
      $arr_query['vendor_id'] = $vendor_id;
    } else {
      throw new AccessDeniedHttpException();
    }

    $arg_data = array('query' => $arr_query);

    $obj_property = new property(); 
    $items_per_page = \Drupal::config('inventory_management.settings')->get('items_per_page');
    if (!$items_per_page) {
        $items_per_page = 20; // Default value if not set
    }
    $arr_result = $obj_property->searchProperty($arg_data, $items_per_page);
    $arg_data_count = $arg_data;
    $arg_data_count['query']['status'] = "1";
    $count_published = $obj_property->countProperty($arg_data_count);
    $arg_data_count['query']['status'] = "0";
    $count_unpublished = $obj_property->countProperty($arg_data_count);
    $count_total = $arr_result['result_count'];

    // Build export URL with current search params
    $export_query = [];
    if (!empty($property_name)) {
      $export_query['property_name'] = $property_name;
    }
    if (!empty($city)) {
      $export_query['city'] = $city;
    }
    if ($status == '0' || $status == '1') {
      $export_query['status'] = $status;
    }
    if (!empty($vendor_id)) {
      $export_query['vendor_id'] = $vendor_id;
    }

    $form['count_published'] = [
      '#type' => 'markup',
      '#markup' => $count_published,
    ];
    
    $form['count_unpublished'] = [
      '#type' => 'markup',
      '#markup' => $count_unpublished,
    ];

    $form['count_total'] = [
      '#type' => 'markup',
      '#markup' => $count_total,
    ];

    $form['add_property_link'] = [
        '#type' => 'link',
        '#title' => 'Add Property',
        '#url' => Url::fromRoute('inventory_management.property_add'),
    ];

    $form['bulk_upload_link'] = [
        '#type' => 'link',
        '#title' => 'Bulk Upload Property',
        '#url' => Url::fromRoute('inventory_management.file_list'),
    ];

    $form['vendor_list_link'] = [
      '#type' => 'link',
      '#title' => $this->t('Vendor List'),
      '#url' => Url::fromRoute('vendor_management.vendor_list'),
      '#prefix' => '<div class="action-link action-link--vendor-list">',
      '#suffix' => '</div>',
      '#access' => $is_admin,
    ];

    $form['export_csv_link'] = [
    '#type' => 'link',
    '#title' => 'Export Properties',
    '#url' => Url::fromRoute('inventory_management.property_export_csv', [], ['query' => $export_query]),
    '#attributes' => ['class' => ['export-csv-link']],
    ];

    $form['search'] = array(
      '#type' => 'fieldset',
      '#title' => $this
        ->t(''),
    );

    $form['search']['property_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Property Name'),
      '#attributes' => array(
        'autocomplete' => array('off'),
        'placeholder' => array('Enter property name'),
      ),
      '#default_value' => (!empty($property_name)) ? $property_name : '',
    ];

    $arr_city = commonUtil::get_term_list('city');
    $city_options = ['' => $this->t('- All -')] + $arr_city;
    $form['search']['city'] = [
      '#type' => 'select',
      '#title' => $this->t('City'),
      '#options' => $city_options,
      '#default_value' => ($city !== NULL && $city !== '') ? (string) $city : '',
    ];

    $form['search']['status'] = [
      '#type' => 'select',
      '#title' => $this->t('Status'),
      '#options' => ['All' => 'All', '1' => 'Published', '0' => 'Unpublished'],
      '#default_value' => ($status == '0' || $status == '1') ? $status : '',
    ];

    // Show vendor list only for admins.
    if (array_intersect($roles, $arr_admin_roles)) {
      $obj_vendor = new vendor(); 
      $arg_data = array('query' => array());
      $arr_vendor = $obj_vendor->searchVendor($arg_data, 5000);
      $arr_vendor_list = array('' => "All");
      if (is_array($arr_vendor['search_result']) && count($arr_vendor['search_result']) > 0) {
          foreach ($arr_vendor['search_result'] as $val_vendor) {
              $arr_vendor_list[$val_vendor['id']] = $val_vendor['name'];
          }
          
          $form['search']['vendor_id'] = [
            '#type' => 'select',
            '#title' => $this->t('Vendor'),
            '#options' => $arr_vendor_list,
            '#default_value' => (!empty($vendor_id)) ? $vendor_id : '',
          ];
      }
    }

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Search'),
      '#weight' => 110,
    ];

    if (is_array($arr_result['search_result']) && count($arr_result['search_result']) > 0) {
        $form['search_result']['result'] = array(
            '#type' => 'table',
            '#header' => array(
                $this->t(''),
                $this->t('Title'),
                $this->t('Display Name'),
                $this->t('Status'),
                $this->t('Created By'),
                $this->t('Created On'),
            ),
            '#attributes' => array(
                'class' => array(
                'tbl-vendor',
                ),
            ),
        );

        foreach ($arr_result['search_result'] as $key_result => $val_result) {
            $edit_url = Url::fromRoute('inventory_management.property_edit', [
            'id' =>$val_result['nid'],
            ], ['absolute' => TRUE])->toString();
            $delete_url = Url::fromRoute('inventory_management.property_delete', [
            'id' =>$val_result['nid'],
            ], ['absolute' => TRUE])->toString();

            $form['search_result']['result'][$key_result]['edit'] = array(
                '#type' => 'select',
                '#title_display' => 'invisible',
                '#options' => [
                    '' => $this->t('Select Action'),
                    'manage' => '⚙️ ' . $this->t('Manage'),
                    'delete' => '🗑️ ' . $this->t('Delete'),
                ],
                '#default_value' => '',
                '#attributes' => [
                    'class' => ['form-select', 'form-element', 'property-action-dropdown'],
                    'data-edit-url' => $edit_url,
                    'data-delete-url' => $delete_url,
                    'onchange' => 'handleActionChange(this)',
                ],
            );

            // Get property name from the node instead of title (which shows display_name)
            $property_name = '';
            $display_name = '';
            if (!empty($val_result['nid'])) {
                $node = Node::load($val_result['nid']);
                if ($node) {
                    if ($node->hasField('field_property_name')) {
                        $property_name = $node->get('field_property_name')->getString();
                    }
                    if ($node->hasField('field_display_name') && !$node->get('field_display_name')->isEmpty()) {
                        $display_name = $node->get('field_display_name')->getString();
                    }
                }
            }
            // Fallback to title if property_name is not available
            if (empty($property_name)) {
                $property_name = $val_result['title'] ?? '';
            }

            $form['search_result']['result'][$key_result]['title'] = array(
                '#title_display' => 'invisible',
                '#markup' => "".$property_name."",
            );
            
            $form['search_result']['result'][$key_result]['display_name'] = array(
                '#title_display' => 'invisible',
                '#markup' => "".$display_name."",
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
                '#markup' => "".date('d-m-Y H:i:s', $val_result['created'])."",
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
        '#markup' => t('Sorry, no record found!'),
      ];
    }

    $form['#theme'] = 'property_list';
    $form['#attached']['library'][] = 'inventory_management/property_list';

    // Results are built from ?property_name=&city=&status=&vendor_id= — without these
    // contexts, rendered output can be cached from another query string (city ignored).
    $form['#cache']['contexts'][] = 'user';
    $form['#cache']['contexts'][] = 'user.roles';
    $form['#cache']['contexts'][] = 'url.query_args:property_name';
    $form['#cache']['contexts'][] = 'url.query_args:city';
    $form['#cache']['contexts'][] = 'url.query_args:status';
    $form['#cache']['contexts'][] = 'url.query_args:vendor_id';

    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state) {

  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $query = [];

    $property_name = $form_state->getValue('property_name');
    if (!empty($property_name)) {
      $query['property_name'] = $property_name;
    }

    $city = $form_state->getValue('city');
    if (!empty($city)) {
      $query['city'] = $city;
    }

    $status = $form_state->getValue('status');
    if ($status !== NULL) {
      $query['status'] = $status;
    }

    $vendor_id = $form_state->getValue('vendor_id');
    if (!empty($vendor_id)) {
      $query['vendor_id'] = $vendor_id;
    }

    // Redirect to the same page with query params.
    $form_state->setRedirect('inventory_management.property_list', [], ['query' => $query]);

  }

}
