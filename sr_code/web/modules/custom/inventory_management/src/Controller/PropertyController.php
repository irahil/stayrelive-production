<?php
namespace Drupal\inventory_management\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\inventory_management\property;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\user\Entity\User;
use Drupal\node\Entity\Node;
use Drupal\vendor_management\vendor;

class PropertyController extends ControllerBase {
    /**
     * List properties.
     *
     * @return array
     *   A render array containing the property list.
     */
  public function list() {
    $arr_admin_roles = array('administrator', 'site_admin');
    $arr_vendor_roles = array('vendor');

    $user = User::load(\Drupal::currentUser()->id());
    $roles = $user->getRoles();
    if (array_intersect($roles, $arr_admin_roles)) {
      // Validate if the user is an admin, if yes show all properties
      $arg_data = array('query' => array('status' => '1', 'sort' => 'created', 'order' => 'DESC'));
    } elseif (array_intersect($roles, $arr_vendor_roles)) {
      $vendor_id = $user->field_vendor_id->value;
      $arg_data = array('query' => array('field_vendor_id' => $vendor_id, 'status' => '1', 'sort' => 'created', 'order' => 'DESC'));
    } else {
        $form['error'] = array(
        '#title_display' => 'invisible',
        '#markup' => "Sorry, something went wrong. Please try again later.",
        '#prefix' => '<div class="form-error">',
        '#suffix' => '</div>',
        );
        return $form;
    }
    
    $obj_property = new property(); 
    $items_per_page = \Drupal::config('inventory_management.settings')->get('items_per_page');
    if (!$items_per_page) {
        $items_per_page = 20; // Default value if not set
    }
    $arr_result = $obj_property->searchProperty($arg_data, $items_per_page);

    $form['add_property_link'] = [
        '#type' => 'link',
        '#title' => 'Add Property',
        '#url' => Url::fromRoute('inventory_management.property_add'),
    ];

    $form['upload_property_link'] = [
        '#type' => 'link',
        '#title' => 'Upload Property',
        '#url' => Url::fromRoute('inventory_management.property_upload'),
    ];

    $form['search'] = array(
      '#type' => 'fieldset',
      '#title' => $this
        ->t('Search'),
    );

    $form['search']['property_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Property Name'),
      '#required' => TRUE,
      '#attributes' => array(
        'autocomplete' => array('off'),
        'placeholder' => array('Enter property name'),
      )
    ];

    $form['search']['status'] = [
      '#type' => 'select',
      '#title' => $this->t('Status'),
      '#options' => array('0' => 'Unpublished', '1' => 'Published'),
    ];

    $obj_vendor = new vendor(); 
    $arg_data = array('query' => array());
    $arr_vendor = $obj_vendor->searchVendor($arg_data, 5000);
    $arr_vendor_list = array();
    if (is_array($arr_vendor['search_result']) && count($arr_vendor['search_result']) > 0) {
        foreach ($arr_vendor['search_result'] as $val_vendor) {
            $arr_vendor_list[$val_vendor['id']] = $val_vendor['name'];
        }

        $form['search']['vendor'] = [
        '#type' => 'select',
        '#title' => $this->t('Vendor'),
        '#options' => $arr_vendor_list,
        ];
    }

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Search'),
      '#weight' => 110,
        '#validate' => [[get_class($this), 'validateSearchForm']],
        '#submit' => [[get_class($this), 'submitSearchForm']],
    ];

    if (is_array($arr_result['search_result']) && count($arr_result['search_result']) > 0) {
        $form['search_result']['result'] = array(
            '#type' => 'table',
            '#header' => array(
                $this->t(''),
                $this->t('Title'),
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
            $link = Link::fromTextAndUrl(
                    $this->t('Manage'),
                Url::fromUri( $edit_url,
                    array('absolute' => TRUE,)))->toString();

            $form['search_result']['result'][$key_result]['edit'] = array(
                '#title_display' => 'invisible',
                '#markup' => $link,
            );

            $form['search_result']['result'][$key_result]['title'] = array(
                '#title_display' => 'invisible',
                '#markup' => "".$val_result['title']."",
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

    return $form;
  }

  /**
 * Form validation handler for the property search form.
 */
public static function validateSearchForm(array &$form, \Drupal\Core\Form\FormStateInterface $form_state) {
  $property_name = $form_state->getValue('property_name');
  if (!empty($property_name) && strlen($property_name) < 3) {
    $form_state->setErrorByName('property_name', t('Property name must be at least 3 characters.'));
  }
}

/**
 * Form submission handler for the property search form.
 */
public static function submitSearchForm(array &$form, \Drupal\Core\Form\FormStateInterface $form_state) {
  $query = [];

  $property_name = $form_state->getValue('property_name');
  if (!empty($property_name)) {
    $query['property_name'] = $property_name;
  }

  $status = $form_state->getValue('status');
  if ($status !== NULL) {
    $query['status'] = $status;
  }

  $vendor = $form_state->getValue('vendor');
  if (!empty($vendor)) {
    $query['vendor'] = $vendor;
  }

  // Redirect to the same page with query params.
  $form_state->setRedirect('inventory_management.property_list', [], ['query' => $query]);
}

  /**
   * Delete a property.
   *
   * @param int $id
   *   The node ID of the property to delete.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   A redirect response to the property list page.
   */
  public function delete($id) {
    // Load the property node.
    $node = Node::load($id);

    if (!$node) {
      \Drupal::messenger()->addError($this->t('Property not found.'));
      return $this->redirect('inventory_management.property_list');
    }

    // Verify it's a property node.
    if ($node->bundle() !== 'property') {
      \Drupal::messenger()->addError($this->t('Invalid property.'));
      return $this->redirect('inventory_management.property_list');
    }

    // Get property title for the message.
    $property_title = $node->getTitle();

    // Delete the property node.
    try {
      $node->delete();
      \Drupal::messenger()->addMessage($this->t('Property "@title" has been deleted.', ['@title' => $property_title]));
    }
    catch (\Exception $e) {
      \Drupal::logger('inventory_management')->error('Error deleting property @id: @message', [
        '@id' => $id,
        '@message' => $e->getMessage(),
      ]);
      \Drupal::messenger()->addError($this->t('An error occurred while deleting the property.'));
    }

    // Redirect to the property list page.
    return $this->redirect('inventory_management.property_list');
  }

  /**
   * Delete a room.
   *
   * @param int $pid
   *   The property ID.
   * @param int $id
   *   The room node ID to delete.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   A redirect response to the property edit page.
   */
  public function deleteRoom($pid, $id) {
    // Load the property node to validate.
    $property_node = Node::load($pid);
    if (!$property_node || $property_node->bundle() !== 'property') {
      \Drupal::messenger()->addError($this->t('Property not found.'));
      return $this->redirect('inventory_management.property_list');
    }

    // Load the room node.
    $room_node = Node::load($id);

    if (!$room_node) {
      \Drupal::messenger()->addError($this->t('Room not found.'));
      return $this->redirect('inventory_management.property_edit', ['id' => $pid]);
    }

    // Verify it's a room node and belongs to the property.
    if ($room_node->bundle() !== 'property') {
      \Drupal::messenger()->addError($this->t('Invalid room.'));
      return $this->redirect('inventory_management.property_edit', ['id' => $pid]);
    }

    // Verify the room belongs to the property.
    $parent_id = $room_node->get('field_parent_id')->getString();
    if ($parent_id != $pid) {
      \Drupal::messenger()->addError($this->t('Room does not belong to this property.'));
      return $this->redirect('inventory_management.property_edit', ['id' => $pid]);
    }

    // Get room title for the message.
    $room_title = $room_node->getTitle();

    // Delete the room node.
    try {
      $room_node->delete();
      \Drupal::messenger()->addMessage($this->t('Room "@title" has been deleted.', ['@title' => $room_title]));
    }
    catch (\Exception $e) {
      \Drupal::logger('inventory_management')->error('Error deleting room @id: @message', [
        '@id' => $id,
        '@message' => $e->getMessage(),
      ]);
      \Drupal::messenger()->addError($this->t('An error occurred while deleting the room.'));
    }

    // Redirect to the property edit page.
    return $this->redirect('inventory_management.property_edit', ['id' => $pid]);
  }

}
