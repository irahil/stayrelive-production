<?php

namespace Drupal\ch_property\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\taxonomy\Entity\Term;

/**
 * Class HomeSearchForm.
 */
class HomeSearchForm extends FormBase
{

  /**
   * {@inheritdoc}
   */
  public function getFormId()
  {
    return 'home_search_form';
  }

  /**
   * {@inheritdoc}
   */

  public function buildForm(array $form, FormStateInterface $form_state)
  {
    $current_path = \Drupal::service('path.current')->getPath();
    $is_homepage = \Drupal::service('path.matcher')->isFrontPage();
    $request = \Drupal::request()->query;
    $date_value = $request->get('date');
    $city_name = '';
    $city_tid = $request->get('field_city_target_id');

    if (!empty($city_tid) && is_numeric($city_tid)) {
      $term = Term::load($city_tid);
      if ($term) {
        $city_name = $term->getName();
      }
    }

    $form['city'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Location'),
      '#placeholder' => $this->t('Select city'),
      '#default_value' => $city_name,
      '#required' => TRUE,
      '#attributes' => [
        'id' => 'autocomplete_town_city',
        'class' => ['city-autocomplete'],
        'autocomplete' => 'off',
      ],
      '#attached' => [
        'library' => [
          'ch_property/autocomplete',
        ],
      ],
      '#prefix' => '<div class="autocomplete-wrapper">',
      '#suffix' => '</div>',
    ];


    $form['form'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Date'),
      '#default_value' => $is_homepage ? '' : $date_value,
      '#attributes' => [
        'id' => 'date-flatpicker',
        'class' => ['date-flatpicker'],
        'placeholder' => $this->t('Select Date'),
        'autocomplete' => 'off',
      ],
      '#attached' => [
        'library' => [
          'ch_property/flatpickr',
        ],
        'drupalSettings' => [
          'flatpickr' => [
            'dateFormat' => 'Y-m-d',
            'mode' => 'range',
          ],
        ],
      ],
    ];

    $form['rooms'] = [
      '#type' => 'select',
      '#title' => $this->t('Travellers'),
      '#default_value' => $request->get('rooms') ?? '1', // ✅ Preserve room count
      '#attributes' => [
        'class' => ['select-rooms'],
      ],
      '#options' => [
        '1' => '1 Adult',
        '2' => '2 Adults',
        '3' => '3 Adults',
        '4' => '4 Adults',
        '5' => '5 Adults',
        '6' => '6 Adults',
        '7' => '7 Adults',
        '8' => '8 Adults',
      ],
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Search'),
    ];

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state)
  {
    $city = trim($form_state->getValue('city'));
    $date = $form_state->getValue('form');
    $rooms = $form_state->getValue('rooms');

    // Convert city name to term ID
    $terms = \Drupal::entityTypeManager()
      ->getStorage('taxonomy_term')
      ->loadByProperties([
        'name' => $city,
        'vid' => 'city', // 👈 your vocabulary
      ]);

    if ($terms) {
      $term = reset($terms);
      $city_tid = $term->id();
    }

    $query = [
      'field_city_target_id' => $city_tid ?? NULL,
      'rooms' => $rooms,
    ];

    if (!empty($date)) {
      $query['date'] = $date;
    }

    // Debug: Log the URL being generated
  \Drupal::logger('ch_property')->debug('Redirecting to /property/search with query: @query', 
    ['@query' => print_r($query, TRUE)]
  );
    $url = Url::fromUri('internal:/property/search', [
      'query' => array_filter($query),
    ]);

     // Debug the generated URL
  \Drupal::logger('ch_property')->debug('Generated URL: @url', 
    ['@url' => $url->toString()]
  );

    $form_state->setRedirectUrl($url);
  }
}
