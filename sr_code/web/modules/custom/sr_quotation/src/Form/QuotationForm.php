<?php

namespace Drupal\sr_quotation\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\file\Entity\File;

class QuotationForm extends FormBase
{

    public function getFormId()
    {
        return 'sr_quotation_form';
    }

    public function buildForm(array $form, FormStateInterface $form_state) {
  $form['#attached']['library'][] = 'sr_quotation/timepicker';

  // --------------------------------------------
  // Booking Information Section
  // --------------------------------------------
  $form['booking_info'] = [
    '#type' => 'details',
    '#title' => $this->t('Booking Information'),
    '#open' => TRUE,
    '#attributes' => ['class' => ['form-section']],
  ];

  $form['booking_info']['dates'] = [
    '#type' => 'container',
    '#attributes' => ['class' => ['form-row']],
  ];
  $form['booking_info']['dates']['check_in_date'] = [
    '#type' => 'textfield',
    '#title' => $this->t('Check-in Date'),
    '#required' => TRUE,
    '#attributes' => ['class' => ['datepicker']],
  ];
  $form['booking_info']['dates']['check_out_date'] = [
    '#type' => 'textfield',
    '#title' => $this->t('Check-out Date'),
    '#required' => TRUE,
    '#attributes' => ['class' => ['datepicker']],
  ];

  $form['booking_info']['times'] = [
    '#type' => 'container',
    '#attributes' => ['class' => ['form-row']],
  ];
  $form['booking_info']['times']['check_in_time'] = [
    '#type' => 'textfield',
    '#title' => $this->t('Check-In Time'),
    '#attributes' => ['class' => ['timepicker']],
    '#required' => TRUE,
  ];
  $form['booking_info']['times']['check_out_time'] = [
    '#type' => 'textfield',
    '#title' => $this->t('Check-Out Time'),
    '#attributes' => ['class' => ['timepicker']],
    '#required' => TRUE,
  ];
  $form['booking_info']['nights'] = [
    '#type' => 'number',
    '#title' => $this->t('Number of Nights'),
    '#required' => TRUE,
  ];

  // --------------------------------------------
  // Property Information Section
  // --------------------------------------------
  $form['property_info'] = [
    '#type' => 'details',
    '#title' => $this->t('Property Information'),
    '#open' => TRUE,
    '#attributes' => ['class' => ['form-section']],
  ];

  $form['property_info']['unit_type'] = [
    '#type' => 'select',
    '#title' => $this->t('Unit Type'),
    '#options' => [
      'Serviced Apartment' => 'Serviced Apartment',
      'Aparthotel' => 'Aparthotel',
      'Hotel Apartment' => 'Hotel Apartment',
      'Chalet' => 'Chalet',
      'Villa' => 'Villa',
      'Premium Homes' => 'Premium Homes',
      'Branded Residences' => 'Branded Residences',
      'Others' => 'Others',
    ],
    '#required' => TRUE,
  ];

  $form['property_info']['room_details'] = [
    '#type' => 'container',
    '#attributes' => ['class' => ['form-row']],
  ];
  $form['property_info']['room_details']['room_type'] = [
    '#type' => 'select',
    '#title' => $this->t('Room Type'),
    '#options' => [
      'Studio' => 'Studio',
      '1-bed Apartment' => '1-bed Apartment',
      '2-bed Apartment' => '2-bed Apartment',
      '3-bed Apartment' => '3-bed Apartment',
    ],
    '#required' => TRUE,
  ];
  $form['property_info']['room_details']['bathrooms'] = [
    '#type' => 'select',
    '#title' => $this->t('No. of Bathrooms'),
    '#options' => ['1' => '1', '2' => '2', '3' => '3'],
    '#required' => TRUE,
  ];
  $form['property_info']['apartment_size'] = [
    '#type' => 'number',
    '#title' => $this->t('Apartment Size (sqm)'),
  ];

  $form['property_info']['occupancy'] = [
    '#type' => 'container',
    '#attributes' => ['class' => ['form-row']],
  ];
  $form['property_info']['occupancy']['adults'] = [
    '#type' => 'number',
    '#title' => $this->t('No. of Adults'),
  ];
  $form['property_info']['occupancy']['kids'] = [
    '#type' => 'number',
    '#title' => $this->t('No. of Kids'),
  ];

  $form['property_info']['location_details'] = [
    '#type' => 'container',
    '#attributes' => ['class' => ['form-row']],
  ];
  $form['property_info']['location_details']['location'] = [
    '#type' => 'textfield',
    '#title' => $this->t('Location'),
  ];
  $form['property_info']['location_details']['distance'] = [
    '#type' => 'number',
    '#title' => $this->t('Distance (in km)'),
  ];
  $form['property_info']['map_link'] = [
    '#type' => 'textfield',
    '#title' => $this->t('Map Link'),
  ];
  $form['property_info']['description'] = [
    '#type' => 'textarea',
    '#title' => $this->t('Property Description'),
  ];

  // --------------------------------------------
  // Pricing and Financials
  // --------------------------------------------
  $form['financials'] = [
    '#type' => 'details',
    '#title' => $this->t('Pricing & Financials'),
    '#open' => TRUE,
    '#attributes' => ['class' => ['form-section']],
  ];
  $form['financials']['quote'] = [
    '#type' => 'textfield',
    '#title' => $this->t('Quote'),
  ];
  $form['financials']['taxes'] = [
    '#type' => 'textfield',
    '#title' => $this->t('Taxes'),
  ];
  $form['financials']['fx_rate'] = [
    '#type' => 'textfield',
    '#title' => $this->t('FX Rate'),
  ];

  // --------------------------------------------
  // Amenities & Policies
  // --------------------------------------------
  $form['extras'] = [
    '#type' => 'details',
    '#title' => $this->t('Amenities & Policies'),
    '#open' => TRUE,
    '#attributes' => ['class' => ['form-section']],
  ];

  $form['extras']['amenities'] = [
    '#type' => 'checkboxes',
    '#title' => $this->t('Amenities'),
    '#options' => [
      'AC' => 'AC',
      'TV' => 'TV',
      'Free WI-FI' => 'Free WI-FI',
      'Refrigerator' => 'Refrigerator',
      'Microwave/ Oven' => 'Microwave/ Oven',
      'Telephone' => 'Telephone',
      'Parking' => 'Parking',
      'Pool' => 'Pool',
      'Gymnasium' => 'Gymnasium',
      'Housekeeping' => 'Housekeeping',
      'Kitchen' => 'Kitchen',
      'Others' => 'Others',
    ],
  ];
  $form['extras']['cancellation_policy'] = [
    '#type' => 'textarea',
    '#title' => $this->t('Cancellation Policy'),
  ];
  $form['extras']['rules'] = [
    '#type' => 'textarea',
    '#title' => $this->t('Rules & Regulations'),
  ];
  $form['extras']['note'] = [
    '#type' => 'textarea',
    '#title' => $this->t('Note (Extra Details)'),
  ];

  // --------------------------------------------
  // Images
  // --------------------------------------------
  $form['images'] = [
    '#type' => 'details',
    '#title' => $this->t('Property Images'),
    '#open' => TRUE,
    '#attributes' => ['class' => ['form-section']],
  ];
  $form['images']['apartment_images'] = [
  '#type' => 'managed_file',
  '#title' => $this->t('Upload Images'),
  '#upload_location' => 'public://quotation_images/',
  '#multiple' => TRUE,
  '#upload_validators' => [
    'file_validate_extensions' => ['png jpg jpeg gif'],
    'file_validate_size' => [1024 * 1024 * 15],
  ],
  
  '#required' => FALSE,
];

  // --------------------------------------------
  // Submit Button
  // --------------------------------------------
  $form['actions']['submit'] = [
    '#type' => 'submit',
    '#value' => $this->t('Save Quotation'),
    '#button_type' => 'primary',
  ];

  $form['#theme'] = 'quotation_form';

  return $form;
}

public function validateForm(array &$form, FormStateInterface $form_state) {
  $files = $form_state->getValue('apartment_images', []);
  
  \Drupal::logger('sr_quotation')->notice('Files in validate: @files', [
    '@files' => print_r($files, TRUE)
  ]);
  
  // Check if files are being uploaded
  if (empty($files)) {
    \Drupal::logger('sr_quotation')->warning('No files received in validation');
  }
}

  public function submitForm(array &$form, FormStateInterface $form_state) {
  $values = $form_state->getValues();

  \Drupal::logger('sr_quotation')->notice('Form values: @values', [
    '@values' => print_r($values, TRUE)
  ]);
  
  // Handle file uploads
  $image_fids = [];
  if (!empty($values['apartment_images'])) {
    \Drupal::logger('sr_quotation')->notice('Processing files: @files', [
      '@files' => print_r($values['apartment_images'], TRUE)
    ]);
    
    foreach ($values['apartment_images'] as $fid) {
      if ($fid && $fid != 0) {
        $file = File::load($fid);
        if ($file) {
          \Drupal::logger('sr_quotation')->notice('File loaded: @name (@fid)', [
            '@name' => $file->getFilename(),
            '@fid' => $fid
          ]);
          
          $file->setPermanent();
          $file->save();
          $image_fids[] = $fid;
          
          \Drupal::logger('sr_quotation')->notice('File saved to: @uri', [
            '@uri' => $file->getFileUri()
          ]);
        }
      }
    }
  } else {
    \Drupal::logger('sr_quotation')->error('No apartment_images in form values');
  }

  // Prepare amenities data
  $amenities = '';
  if (is_array($values['amenities'])) {
    $selected_amenities = array_filter($values['amenities']);
    $amenities = implode(', ', $selected_amenities);
  }

  // Save to database
  \Drupal::database()->insert('sr_quotation')
    ->fields([
      'checkin_date' => $values['check_in_date'],
      'checkout_date' => $values['check_out_date'],
      'checkin_time' => $values['check_in_time'],
      'checkout_time' => $values['check_out_time'],
      'nights' => $values['nights'],
      'unit_type' => $values['unit_type'],
      'apartment_size' => $values['apartment_size'],
      'room_type' => $values['room_type'],
      'bathrooms' => $values['bathrooms'],
      'adults' => $values['adults'],
      'kids' => $values['kids'],
      'location' => $values['location'],
      'distance' => $values['distance'],
      'map_link' => $values['map_link'],
      'description' => $values['description'],
      'quote' => $values['quote'],
      'taxes' => $values['taxes'],
      'fx_rate' => $values['fx_rate'],
      'amenities' => $amenities,
      'cancellation_policy' => $values['cancellation_policy'],
      'images' => implode(',', $image_fids),
      'rules' => $values['rules'],
      'note' => $values['note'],
      'created' => \Drupal::time()->getRequestTime(),
    ])
    ->execute();

  \Drupal::messenger()->addMessage($this->t('Quotation submitted successfully.'));
  $form_state->setRedirect('sr_quotation.list');
}
}
