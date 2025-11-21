<?php


namespace Drupal\currency_layer_integration\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

class CLAdminForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'currency_layer_integration_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Form constructor.
    $form = parent::buildForm($form, $form_state);
    // Default settings.
    $config = $this->config('currency_layer_integration.settings');

    $currency_default = $config->get('currency_layer_integration.currency_list');
    $currency_default = (is_array($currency_default) && count($currency_default) > 0) ? $currency_default : array();

    $arr_data_list = \Drupal::service('currency_layer_integration.services')->getApiListData(TRUE);
    $currencyList = ($arr_data_list == FALSE) ? array('USD' => 'United States Dollar', 'EUR' => 'Euro', 'AED' => 'United Arab Emirates Dirham',
                        'INR' => 'Indian Rupee', 'GBP' => 'British Pound Sterling') : $arr_data_list;

    // Source text field.
    $form['api_endpoint'] = [
      '#type' => 'textfield',
      '#title' => $this->t('API Endpoint'),
      '#default_value' => $config->get('currency_layer_integration.api_endpoint'),
      '#description' => $this->t('Enter API Endpoint'),
    ];

    $form['api_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('API Keys'),
      '#default_value' => $config->get('currency_layer_integration.api_key'),
      '#description' => $this->t('Enter API Key'),
    ];

    $form['currency_list'] = array(
      '#type' => 'checkboxes',
      '#options' => $currencyList,
      '#title' => $this->t('Currency List'),
      '#default_value' => $currency_default,
    );

    $form['fetchdata'] = array (
      '#type' => 'submit',
      '#access' => TRUE,
      '#value' => 'Fetch new currency',
      '#weight' => 60,
      '#submit' => array('::submit_fetch_new_data'),
    );

    return $form;
  }


public function submit_fetch_new_data(array &$form, FormStateInterface $form_state) {
  $arr_data = \Drupal::service('currency_layer_integration.services')->getApiLiveData(FALSE);
  if (is_array($arr_data) && count($arr_data) > 0 ) {
    \Drupal::messenger()->addStatus("Currency fetched successfully!\n");
  } else {
    \Drupal::messenger()->addStatus("Unable to fetch latest currency!\n");
  }

}
  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {

  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('currency_layer_integration.settings');
    $config->set('currency_layer_integration.api_endpoint', $form_state->getValue('api_endpoint'));
    $config->set('currency_layer_integration.api_key', $form_state->getValue('api_key'));
    $config->set('currency_layer_integration.currency_list', $form_state->getValue('currency_list'));

    $config->save();
    return parent::submitForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'currency_layer_integration.settings',
    ];
  }

}