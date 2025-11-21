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
    $arr_country_list = commonUtil::get_term_list('country', 'country');

    $form['#attached']['library'][] = 'sr/sr_lib';
    $form['#attached']['library'][] = 'sr/sr_occupants';
    $form['#attached']['library'][] = 'sr/sr_autocomplete_lib';
    $arr_town_city = commonUtil::get_term_list('town_city');
    $str_country_list = 'var countries = ["'. implode('","', $arr_town_city) . '"];';

    $form['js_town_city'] = [
      '#markup' => $str_country_list,
    ];

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

    $url = Url::fromRoute('sr.search', [
      'town_city' => urlencode($form_state->getValue('town_city')),
      'date_range' => urlencode($form_state->getValue('date_range')),
      'adult' => urlencode($form_state->getValue('adult')),
      'kid' => urlencode($form_state->getValue('kid')),
    ], ['absolute' => TRUE])->toString();

    commonUtil::my_goto_t2($url);
  }
}
