<?php

namespace Drupal\sr\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

class RateGainSettingsForm extends ConfigFormBase {

  protected function getEditableConfigNames() {
    return ['sr.settings'];
  }

  public function getFormId() {
    return 'sr_rategain_settings_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('sr.settings');

    $form['credentials'] = [
      '#type'  => 'fieldset',
      '#title' => $this->t('RateGain SmartDistribution Credentials'),
    ];

    $has_key = !empty($config->get('rategain_api_key'));
    $form['credentials']['rategain_api_key'] = [
      '#type'          => 'password',
      '#title'         => $this->t('API Key'),
      '#description'   => $has_key
        ? $this->t('Leave blank to keep the existing key.')
        : $this->t('Required. Provided by RateGain.'),
      '#required'      => !$has_key,
      '#attributes'    => [
        'autocomplete' => 'off',
        'placeholder'  => $has_key ? $this->t('Saved — enter new value to change') : '',
      ],
    ];

    $has_secret = !empty($config->get('rategain_api_secret'));
    $form['credentials']['rategain_api_secret'] = [
      '#type'          => 'password',
      '#title'         => $this->t('API Secret'),
      '#description'   => $has_secret
        ? $this->t('Leave blank to keep the existing secret.')
        : $this->t('Required. Provided by RateGain. Keep this secret.'),
      '#required'      => !$has_secret,
      '#attributes'    => [
        'autocomplete' => 'off',
        'placeholder'  => $has_secret ? $this->t('Saved — enter new value to change') : '',
      ],
    ];

    return parent::buildForm($form, $form_state);
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('sr.settings');

    $new_key = trim($form_state->getValue('rategain_api_key') ?? '');
    if (!empty($new_key)) {
      $config->set('rategain_api_key', $new_key);
    }

    $new_secret = trim($form_state->getValue('rategain_api_secret') ?? '');
    if (!empty($new_secret)) {
      $config->set('rategain_api_secret', $new_secret);
    }

    $config->save();
    parent::submitForm($form, $form_state);
  }

}
