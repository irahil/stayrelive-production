<?php
namespace Drupal\sr\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\ConfigFormBase;

class CommissionForm extends ConfigFormBase {
  var $arr_source = array(
    'spacest' => 'Spacest',
    'plumguide' => 'Plumguide',
    'ratehawk' => 'RateHawk',
    'interhome' => 'InterHome',
    'rategain' => 'RateGain',
  );

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'commission_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'commission.settings',
    ];
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('commission.settings');

    // For Agent
    $form['agent'] = [
      '#type' => 'details',
      '#title' => $this->t('For Agent'),
      '#open' => TRUE,
    ];

    foreach ($this->arr_source as $key_source => $val_source) {
      // $key_name = 'agent_' . $key_source . '_percentage';
      $key_name = $key_source . '_agent_percentage';;
      $value_percentage = $config->get($key_name);

      $form['agent']['fieldset_' . $key_source] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Sales commission of ' . $val_source),
        '#attributes' => ['class' => ['SectionContainer']],
      ];

      $form['agent']['fieldset_' . $key_source][$key_name] = [
        '#type' => 'number',
        '#title' => $this->t('Commission Percentage'),
        '#required' => TRUE,
        '#default_value' => $value_percentage ?: 0,
        '#min' => '0',
        '#max' => '100',
      ];
    }

    // For Rest of the World
    $form['rest_of_the_world'] = [
      '#type' => 'details',
      '#title' => $this->t('Rest of the World'),
      '#open' => TRUE,
    ];

    foreach ($this->arr_source as $key_source => $val_source) {
      // $key_name = 'rest_' . $key_source . '_percentage';
      $key_name = $key_source . '_percentage';
      $value_percentage = $config->get($key_name);

      $form['rest_of_the_world']['fieldset_' . $key_source] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Sales commission of ' . $val_source),
        '#attributes' => ['class' => ['SectionContainer']],
      ];

      $form['rest_of_the_world']['fieldset_' . $key_source][$key_name] = [
        '#type' => 'number',
        '#title' => $this->t('Commission Percentage'),
        '#required' => TRUE,
        '#default_value' => $value_percentage ?: 0,
        '#min' => '0',
        '#max' => '100',
      ];
    }

    // Actions
    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
      '#weight' => 110,
    ];

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('commission.settings');

    // Save Agent commissions
    foreach ($this->arr_source as $key_source => $val_source) {
      $key_name = $key_source . '_agent_percentage';
      $config->set($key_name, $form_state->getValue($key_name));
    }

    // Save Rest of the World commissions
    foreach ($this->arr_source as $key_source => $val_source) {
      $key_name = $key_source . '_percentage';
      $config->set($key_name, $form_state->getValue($key_name));
    }

    $config->save();
    parent::submitForm($form, $form_state);
  }
}