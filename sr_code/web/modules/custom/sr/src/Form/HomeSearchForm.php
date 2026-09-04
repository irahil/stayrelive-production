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

class HomeSearchForm extends FormBase {
  public function getFormId() {
    return 'home';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $current_user = \Drupal::currentUser();
    $current_roles = $current_user->getRoles();

    $form['#attached']['library'][] = 'sr/sr_lib';
    $form['#attached']['library'][] = 'sr/sr_occupants';
    $form['#attached']['library'][] = 'sr/sr_autocomplete_lib';
    $form['#attached']['library'][] = 'sr/sr_search_loader';
    // City suggestions are fetched from sr.town_city_autocomplete as the
    // user types (see sr_autocomplete.js) rather than loaded here — the
    // town_city vocabulary has ~71k terms, and loading/hydrating all of
    // them to embed as a JS array on every page load was exhausting PHP's
    // memory limit on every request.

    // Marks this form for sr_search_loader.js — submitting it navigates
    // to /property/search, so a loading overlay covers that transition.
    $form['#attributes']['class'][] = 'sr-search-form';

    $form['filter'] = array(
      '#type' => 'fieldset',
      '#title' => $this
        ->t('Filter'),
      '#attributes' => array(
          'class' => array('SectionContainer'),
      )
    );

    $form['filter']['town_city'] = [
      '#type' => 'textfield',
      '#title' => $this->t('City'),
      '#required' => TRUE,
      '#attributes' => array(
        'id' => array('autocomplete_town_city'),
        'autocomplete' => array('off'),
        'placeholder' => array('Select a city'),
      )
    ];
    //'#attributes' => array('onChange' => 'this.form.submit()'),

    $form['filter']['date'] = array(
      '#type' => 'fieldset',
      '#title' => $this
        ->t('Date'),
      '#attributes' => array(
          'class' => array('SectionContainer'),
      )
    );

    $form['filter']['date']['date_range'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Date Range'),
      '#date_date_format' => 'Y-m-d',
      '#attributes' => array(
        'id' => array('flatpickr_date_range'),
        'placeholder' => array('Dates'),
      )
    ];

    $form['filter']['rooms'] = [
      '#type' => 'select',
      '#title' => $this->t('Rooms'),
      '#options' => [
        '1' => $this->t('1 Room'),
        '2' => $this->t('2 Room'),
      ],
      '#default_value' => '1',
      '#attributes' => array(
        'id' => array('edit-rooms'),
      ),
    ];

    $form['filter']['occupants'] = array(
      '#type' => 'fieldset',
      '#title' => $this
        ->t('Occupants'),
      '#attributes' => array(
          'class' => array('SectionContainer'),
      )
    );

    $form['filter']['occupants']['adult'] = array(
      '#type' => 'number',
      '#title' => $this->t('Adult Count'),
      '#default_value' => '1',
      '#attributes' => array(
        'placeholder' => array('Adults'),
        'id' => array('edit-adult'),
      )
    );

    $form['filter']['occupants']['kid'] = array(
      '#type' => 'number',
      '#title' => $this->t('Kids Count'),
      '#default_value' => '0',
      '#attributes' => array(
        'placeholder' => array('Kids'),
        'id' => array('edit-kid'),
      )
    );

    $form['filter']['actions'] = [
      '#type' => 'actions',
    ];

    $form['filter']['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Search'),
      '#weight' => 110,
    ];

    $form['#theme'] = 'sr_search_block';

    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state) {
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {

    // child_age[] selects are generated client-side (sr_occupants.js) to
    // match the current Kids Count, one per child — they aren't declared
    // FAPI elements, so they're read from raw user input rather than
    // $form_state->getValue().
    $child_ages = array_filter((array) ($form_state->getUserInput()['child_age'] ?? []), function ($v) {
      return $v !== '';
    });

    $url = Url::fromRoute('sr.search', [
      'town_city' => urlencode($form_state->getValue('town_city')),
      'date_range' => urlencode($form_state->getValue('date_range')),
      'rooms' => urlencode($form_state->getValue('rooms')),
      'adult' => urlencode($form_state->getValue('adult')),
      'kid' => urlencode($form_state->getValue('kid')),
      'child_age' => urlencode(implode(',', $child_ages)),
    ], ['absolute' => TRUE])->toString();

    commonUtil::my_goto_t2($url);
  }
}
