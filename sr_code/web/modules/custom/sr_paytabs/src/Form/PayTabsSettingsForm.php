<?php

namespace Drupal\sr_paytabs\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

class PayTabsSettingsForm extends ConfigFormBase {

  protected function getEditableConfigNames() {
    return ['sr_paytabs.settings'];
  }

  public function getFormId() {
    return 'sr_paytabs_settings_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('sr_paytabs.settings');

    $form['sandbox_mode'] = [
      '#type'          => 'checkbox',
      '#title'         => $this->t('Sandbox / Test Mode'),
      '#description'   => $this->t('Enable to use PayTabs test credentials. Disable for live payments.'),
      '#default_value' => $config->get('sandbox_mode') ?? TRUE,
    ];

    $form['credentials'] = [
      '#type'  => 'fieldset',
      '#title' => $this->t('PayTabs Credentials'),
    ];

    $form['credentials']['profile_id'] = [
      '#type'          => 'textfield',
      '#title'         => $this->t('Profile ID'),
      '#description'   => $this->t('Found in PayTabs merchant dashboard under Developers.'),
      '#required'      => TRUE,
      '#default_value' => $config->get('profile_id'),
    ];

    $has_key = !empty($config->get('server_key'));
    $form['credentials']['server_key'] = [
      '#type'        => 'password',
      '#title'       => $this->t('Server Key'),
      '#description' => $has_key
        ? $this->t('Leave blank to keep the existing key.')
        : $this->t('Required. Used for server-side API calls. Keep this secret.'),
      '#required'    => !$has_key,
      '#attributes'  => [
        'autocomplete' => 'off',
        'placeholder'  => $has_key ? $this->t('Saved — enter new value to change') : '',
      ],
    ];

    $form['credentials']['client_key'] = [
      '#type'          => 'textfield',
      '#title'         => $this->t('Client Key'),
      '#description'   => $this->t('Used for client-side PayTabs.js (optional).'),
      '#default_value' => $config->get('client_key'),
      '#attributes'    => ['autocomplete' => 'off'],
    ];

    $form['endpoint'] = [
      '#type'          => 'select',
      '#title'         => $this->t('Region / Endpoint'),
      '#required'      => TRUE,
      '#options'       => [
        'https://secure.paytabs.sa'     => $this->t('Saudi Arabia — secure.paytabs.sa'),
        'https://secure.paytabs.com'    => $this->t('UAE — secure.paytabs.com'),
        'https://secure.paytabs.com.eg' => $this->t('Egypt — secure.paytabs.com.eg'),
        'https://secure.paytabs.com.jo' => $this->t('Jordan — secure.paytabs.com.jo'),
        'https://secure.paytabs.com.om' => $this->t('Oman — secure.paytabs.com.om'),
        'https://secure.paytabs.com.kw' => $this->t('Kuwait — secure.paytabs.com.kw'),
      ],
      '#default_value' => $config->get('endpoint') ?: 'https://secure.paytabs.sa',
    ];

    $base = \Drupal::request()->getSchemeAndHttpHost();
    $form['urls'] = [
      '#type'        => 'fieldset',
      '#title'       => $this->t('Callback & Return URLs'),
    ];
    $form['urls']['callback_url'] = [
      '#type'          => 'textfield',
      '#title'         => $this->t('IPN / Callback URL (auto-generated)'),
      '#default_value' => $base . '/payment/ipn',
      '#attributes'    => ['readonly' => 'readonly'],
    ];
    $form['urls']['return_url_note'] = [
      '#type'          => 'textfield',
      '#title'         => $this->t('Return URL (auto-generated example)'),
      '#default_value' => $base . '/payment/return/{booking_id}',
      '#attributes'    => ['readonly' => 'readonly'],
    ];
    $form['urls']['callback_url_override'] = [
      '#type'          => 'textfield',
      '#title'         => $this->t('Callback URL Override'),
      '#description'   => $this->t('For local/sandbox testing only. Enter the full IPN URL e.g. <em>https://abc.devtunnels.ms/payment/ipn</em>. Leave blank in production.'),
      '#default_value' => $config->get('callback_url_override'),
    ];
    $form['urls']['return_url_override'] = [
      '#type'          => 'textfield',
      '#title'         => $this->t('Return URL Base Override'),
      '#description'   => $this->t('For local/sandbox testing only. Enter the base host only e.g. <em>https://abc.devtunnels.ms</em> (no trailing slash). Leave blank in production.'),
      '#default_value' => $config->get('return_url_override'),
    ];

    return parent::buildForm($form, $form_state);
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('sr_paytabs.settings');
    $config
      ->set('profile_id',            trim($form_state->getValue('profile_id')))
      ->set('client_key',            trim($form_state->getValue('client_key')))
      ->set('endpoint',              $form_state->getValue('endpoint'))
      ->set('sandbox_mode',          (bool) $form_state->getValue('sandbox_mode'))
      ->set('callback_url_override', trim($form_state->getValue('callback_url_override') ?? ''))
      ->set('return_url_override',   trim($form_state->getValue('return_url_override') ?? ''));

    $new_key = trim($form_state->getValue('server_key') ?? '');
    if (!empty($new_key)) {
      $config->set('server_key', $new_key);
    }

    $config->save();
    parent::submitForm($form, $form_state);
  }

}
