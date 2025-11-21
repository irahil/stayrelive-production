<?php

namespace Drupal\sr_quotation\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\file\Entity\File;
use Mpdf\Mpdf;
use Symfony\Component\HttpFoundation\Response;

class QuotationController extends ControllerBase
{

  /**
   * Display the quotation list dashboard.
   */
  public function list()
  {
    $results = \Drupal::database()->select('sr_quotation', 'q')
      ->fields('q', ['id', 'location', 'checkin_date', 'checkout_date', 'checkin_time', 'checkout_time', 'nights', 'quote', 'created', 'unit_type', 'room_type', 'bathrooms', 'apartment_size', 'adults', 'kids', 'distance', 'map_link', 'description', 'taxes', 'fx_rate', 'amenities', 'cancellation_policy', 'images', 'rules', 'note'])
      ->orderBy('created', 'DESC')
      ->execute()
      ->fetchAll();

    $rows = [];
    foreach ($results as $record) {
      // dump($record);
      $url = Url::fromRoute('sr_quotation.edit', ['id' => $record->id]);
      $link = Link::fromTextAndUrl($this->t('Edit'), $url)->toString();
      $duplicate_url = Url::fromRoute('sr_quotation.duplicate', ['id' => $record->id]);
      $duplicate_link = Link::fromTextAndUrl($this->t('Duplicate'), $duplicate_url)->toString();
      $rows[] = [
        'id' => $record->id,
        'title' => $record->unit_type . '-' . $record->room_type,
        'location' => $record->location,
        'checkin_date' => $record->checkin_date,
        'checkout_date' => $record->checkout_date,
        'created' => date('Y-m-d', $record->created),
        'edit_url' => $link . ' | ' . $duplicate_link,
      ];
    }

    return [
      '#theme' => 'quotation_dashboard',
      '#quotations' => $rows,
      '#add_link' => Url::fromRoute('sr_quotation.form')->toString(),
      '#attached' => [
        'library' => [
          'sr_quotation/quotation-dashboard-style', // optional for CSS
        ],
      ],
      '#cache' => [
        'max-age' => 0, // Disable caching completely
      ],
    ];
  }

  /**
   * Duplicate a quotation record.
   */
  public function duplicate($id)
  {
    $connection = \Drupal::database();
    $messenger = \Drupal::messenger();

    // Load original record
    $record = $connection->select('sr_quotation', 'q')
      ->fields('q')
      ->condition('id', $id)
      ->execute()
      ->fetchAssoc();

    if (!$record) {
      $messenger->addMessage($this->t('Quotation not found.'), 'error');
      return $this->redirect('sr_quotation.list');
    }

    // Remove ID and update created timestamp
    unset($record['id']);
    $record['created'] = \Drupal::time()->getRequestTime();

    $record['quote'] = 'Copy of ' . $record['quote'];

    // Insert new duplicated record and get new ID
    $new_id = $connection->insert('sr_quotation')
      ->fields($record)
      ->execute();

    // Show success message
    $messenger->addMessage($this->t('Quotation duplicated successfully. You can now edit it.'));

    // Redirect to edit page of new record
    return $this->redirect('sr_quotation.edit', ['id' => $new_id]);
  }


  /**
   * Download Quotation as PDF.
   */
  public function download($id)
  {
    $html = $this->generatePdfContent($id);

    $mpdf = new Mpdf([
      'default_font_size' => 10,
      'default_font' => 'dejavusans',
      'margin_left' => 10,
      'margin_right' => 10,
      'margin_top' => 10,
      'margin_bottom' => 10,
      'margin_header' => 5,
      'margin_footer' => 5,
    ]);
    $mpdf->shrink_tables_to_fit = 1;
    $mpdf->use_kwt = false;
    $mpdf->setAutoTopMargin = 'stretch';
    $mpdf->setAutoBottomMargin = 'stretch';

    $mpdf->SetDisplayMode('fullpage');
    $mpdf->WriteHTML($html);
    $mpdf->Output('quotation-' . $id . '.pdf', 'D');
    exit;
  }

  /**
   * Preview Quotation as inline PDF.
   */
  public function preview($id)
  {
    $html = $this->generatePdfContent($id);

    $mpdf = new Mpdf([
      'default_font_size' => 10,
      'default_font' => 'Georgia, serif',
      'margin_left' => 10,
      'margin_right' => 10,
      'margin_top' => 10,
      'margin_bottom' => 10,
      'margin_header' => 5,
      'margin_footer' => 5,
    ]);

    $mpdf->SetDisplayMode('fullpage');
    $mpdf->WriteHTML($html);

    return new Response(
      $mpdf->Output('', 'S'),
      200,
      [
        'Content-Type' => 'application/pdf',
        'Content-Disposition' => 'inline; filename="quotation-preview-' . $id . '.pdf"',
      ]
    );
  }

  private function generatePdfContent($id)
  {
    // Load record.
    $record = \Drupal::database()->select('sr_quotation', 'q')
      ->fields('q')
      ->condition('id', $id)
      ->execute()
      ->fetchObject();

    if (!$record) {
      throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException();
    }

    // Load image URLs.
    $image_urls = [];
    if (!empty($record->images)) {
      $fids = explode(',', $record->images);
      foreach ($fids as $fid) {
        $file = File::load($fid);
        if ($file) {
          $file_url = \Drupal::service('file_url_generator')->generateAbsoluteString($file->getFileUri());
          $image_urls[] = $file_url;
        }
      }
    }

    // Render Twig template `pdf--quotation.html.twig`.
    $render = [
      '#theme' => 'pdf__quotation',
      '#record' => $record,
      '#image_urls' => $image_urls,
    ];

    // Render the template into HTML string.
    return \Drupal::service('renderer')->renderRoot($render);
  }

  /**
   * Preview Quotation as HTML.
   */

  public function previewHtml($id)
  {
    $record = \Drupal::database()->select('sr_quotation', 'q')
      ->fields('q')
      ->condition('id', $id)
      ->execute()
      ->fetchObject();

    if (!$record) {
      throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException();
    }

    // Prepare image URLs.
    $image_urls = [];
    if (!empty($record->images)) {
      $fids = explode(',', $record->images);
      foreach ($fids as $fid) {
        $file = File::load($fid);
        if ($file) {
          $image_urls[] = \Drupal::service('file_url_generator')->generateAbsoluteString($file->getFileUri());
        }
      }
    }

    // Return as a normal render array (not PDF).
    return [
      '#theme' => 'pdf__quotation',
      '#record' => $record,
      '#image_urls' => $image_urls,
    ];
  }
  /**
   * Delete a quotation.
   */
  public function delete($id)
  {
    $connection = \Drupal::database();

    $connection->delete('sr_quotation')  // replace with your table name
      ->condition('id', $id)
      ->execute();

    $this->messenger()->addStatus(t('Quotation deleted successfully.'));
    return $this->redirect('sr_quotation.list');
  }
}



