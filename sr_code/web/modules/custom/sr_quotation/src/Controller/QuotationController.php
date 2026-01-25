<?php

namespace Drupal\sr_quotation\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\file\Entity\File;
use Drupal\user\Entity\User;
use Mpdf\Mpdf;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Drupal\Core\Site\Settings;

/**
 * Handles all quotation operations.
 */
class QuotationController extends ControllerBase
{

  /**
   * @var \Drupal\Core\Database\Connection
   */
  protected $db;

  /**
   * Inject database.
   */
  public function __construct(Connection $db)
  {
    $this->db = $db;
  }

  public static function create(ContainerInterface $container)
  {
    return new static(
      $container->get('database')
    );
  }

  /**
   * Dashboard List Page.
   */
  public function list()
  {
    $query = $this->db->select('sr_quotation', 'q')
      ->fields('q')
      ->orderBy('created', 'DESC');

    $request = \Drupal::request();
    $filters = [
      'serial' => $request->query->get('serial'),
      'booker' => $request->query->get('booker'),
      'partner' => $request->query->get('partner'),
      'date_from' => $request->query->get('date_from'),
      'date_to' => $request->query->get('date_to'),
    ];

    $query = $this->db->select('sr_quotation', 'q')
      ->fields('q')
      ->orderBy('created', 'DESC');

    /** 1. Filter by Serial Number */
    if (!empty($filters['serial'])) {
      // Serial format: SR-001-2025 → extract numeric ID part
      $serial_parts = explode('-', $filters['serial']);
      if (isset($serial_parts[1])) {
        $query->condition('id', (int) $serial_parts[1]);
      }
    }

    /** 2. Filter by Booker ID or Name */
    if (!empty($filters['booker'])) {
      $booker_value = $filters['booker'];

      // Search by UID or name
      // $uids = \Drupal::entityQuery('user')
      //   ->condition('name', '%' . $booker_value . '%', 'LIKE')
      //   ->accessCheck(FALSE)
      //   ->execute();

      $query->condition('booker_name', '%' . $filters['booker'] . '%', 'LIKE');
    }

    /** 3. Filter by Travel Partner / Corporate */
    if (!empty($filters['partner'])) {
      $query->condition('travel_partner', '%' . $filters['partner'] . '%', 'LIKE');
    }

    /** 4. Filter by Date Range */
    if (!empty($filters['date_from'])) {
      $query->condition('checkin_date', strtotime($filters['date_from'] . ' 00:00:00'), '>=');
    }
    if (!empty($filters['date_to'])) {
      $query->condition('checkout_date', strtotime($filters['date_to'] . ' 23:59:59'), '<=');
    }

    $results = $query->execute()->fetchAll();

    $rows = [];
    foreach ($results as $record) {
      $encrypted_id = $this->encryptId($record->id);

      // Format ID as SR-001-2025
      $year = date('Y', $record->created);
      $formatted_id = 'SR-' . str_pad($record->id, 3, '0', STR_PAD_LEFT) . '-' . $year;

      // Load User
      $author_name = "Unknown";
      if (!empty($record->uid)) {
        if ($user = User::load($record->uid)) {
          $author_name = $user->getDisplayName();
        }
      }

      // Links
      $edit_url = Link::fromTextAndUrl('Edit', Url::fromRoute('sr_quotation.edit', ['id' => $record->id]))->toString();
      $duplicate_url = Link::fromTextAndUrl('Duplicate', Url::fromRoute('sr_quotation.duplicate', ['id' => $record->id]))->toString();

      $rows[] = [
        'id' => $record->id,
        'encrypted_id' => $encrypted_id,
        'formatted_id' => $formatted_id,
        'preview_url' => Url::fromRoute(
          'sr_quotation.preview_encrypted',
          ['token' => $encrypted_id]
        )->toString(),
        'title' => $record->unit_type . ' - ' . $record->room_type,
        'location' => $record->location,
        'created' => date('Y-m-d', $record->created),
        'author' => $author_name,
      ];
    }

    return [
      '#theme' => 'quotation_dashboard',
      '#quotations' => $rows,
      '#add_link' => Url::fromRoute('sr_quotation.form')->toString(),
      '#attached' => ['library' => ['sr_quotation/quotation-dashboard-style']],
      '#cache' => ['max-age' => 0],
    ];
  }

  /**
   * Duplicate a quotation.
   */
  public function duplicate($id)
  {

    $record = $this->db->select('sr_quotation', 'q')
      ->fields('q')
      ->condition('id', $id)
      ->execute()
      ->fetchAssoc();

    if (!$record) {
      $this->messenger()->addError('Quotation not found.');
      return $this->redirect('sr_quotation.list');
    }

    unset($record['id']);
    $record['created'] = time();
    $record['uid'] = \Drupal::currentUser()->id();   // Important
    $record['quote'] = 'Copy of ' . $record['quote'];

    // Insert
    $new_id = $this->db->insert('sr_quotation')
      ->fields($record)
      ->execute();

    $this->messenger()->addStatus('Quotation duplicated successfully.');

    return $this->redirect('sr_quotation.edit', ['id' => $new_id]);
  }

  /**
   * Generate PDF Download.
   */
  public function download($id)
  {
    $html = $this->buildPdfHtml($id);

    $mpdf = new Mpdf([
      'default_font_size' => 10,
      'default_font' => 'dejavusans',
      'margin_left' => 10,
      'margin_right' => 10,
      'margin_top' => 10,
      'margin_bottom' => 10,
    ]);

    $mpdf->WriteHTML($html);
    $mpdf->Output('quotation-' . $id . '.pdf', 'D');
    exit;
  }

  /**
   * Preview inline PDF.
   */
  public function preview($id)
  {
    $html = $this->buildPdfHtml($id);

    $mpdf = new Mpdf([
      'default_font_size' => 10,
      'default_font' => 'dejavusans',
      'margin_left' => 5,
      'margin_right' => 5,
    ]);

    $mpdf->WriteHTML($html);

    return new Response(
      $mpdf->Output('', 'S'),
      200,
      [
        'Content-Type' => 'application/pdf',
        'Content-Disposition' => 'inline; filename="quotation-' . $id . '.pdf"',
      ]
    );
  }

  /**
   * Build HTML for PDF.
   */
  /**
   * Build HTML for PDF.
   */
  private function buildPdfHtml($id)
  {
    $record = $this->db->select('sr_quotation', 'q')
      ->fields('q')
      ->condition('id', $id)
      ->execute()
      ->fetchObject();

    if (!$record) {
      throw new NotFoundHttpException();
    }

    /* ----------------------------------------
     * 4. Load Images
     * -------------------------------------- */
    $image_urls = [];
    if (!empty($record->images)) {
      foreach (explode(',', $record->images) as $fid) {
        if ($file = File::load($fid)) {
          $image_urls[] = \Drupal::service('file_url_generator')
            ->generateAbsoluteString($file->getFileUri());
        }
      }
    }

    /* ----------------------------------------
     * 4b. Load Location Screenshots
     * -------------------------------------- */
    $location_screenshot_urls = [];
    $location_screenshot_value = isset($record->location_screenshot) ? $record->location_screenshot : '';
    if (!empty($location_screenshot_value)) {
      \Drupal::logger('sr_quotation')->debug('Loading location screenshots. Raw value: @value', [
        '@value' => $location_screenshot_value
      ]);
      $fids = array_filter(array_map('trim', explode(',', $location_screenshot_value)));
      \Drupal::logger('sr_quotation')->debug('Location screenshot FIDs: @fids', [
        '@fids' => print_r($fids, TRUE)
      ]);
      foreach ($fids as $fid) {
        if (is_numeric($fid) && $file = File::load($fid)) {
          $url = \Drupal::service('file_url_generator')
            ->generateAbsoluteString($file->getFileUri());
          $location_screenshot_urls[] = $url;
          \Drupal::logger('sr_quotation')->debug('Loaded location screenshot: @url', ['@url' => $url]);
        } else {
          \Drupal::logger('sr_quotation')->warning('Failed to load location screenshot FID: @fid', ['@fid' => $fid]);
        }
      }
    } else {
      \Drupal::logger('sr_quotation')->debug('No location_screenshot data found. Value: @value', [
        '@value' => $location_screenshot_value ?: 'empty'
      ]);
    }

    /* ----------------------------------------
     * 5. Prepare clean data for Twig
     * -------------------------------------- */
    $data = $this->prepareQuotationData($record);

    $render = [
      '#theme' => 'pdf__quotation',
      '#data' => $data,
      '#image_urls' => $image_urls,
      '#location_screenshot_urls' => $location_screenshot_urls,
      '#cache' => ['max-age' => 0],
    ];

    return \Drupal::service('renderer')->renderRoot($render);
  }


  /**
   * HTML view (not PDF).
   */

  public function previewHtml($id)
  {
    $record = $this->db->select('sr_quotation', 'q')
      ->fields('q')
      ->condition('id', $id)
      ->execute()
      ->fetchObject();

    if (!$record) {
      throw new NotFoundHttpException();
    }

    /* Images */
    $image_urls = [];
    if (!empty($record->images)) {
      foreach (explode(',', $record->images) as $fid) {
        if ($file = File::load($fid)) {
          $image_urls[] = \Drupal::service('file_url_generator')
            ->generateAbsoluteString($file->getFileUri());
        }
      }
    }

    /* Location Screenshots */
    $location_screenshot_urls = [];
    if (!empty($record->location_screenshot)) {
      $fids = array_filter(array_map('trim', explode(',', $record->location_screenshot)));
      foreach ($fids as $fid) {
        if (is_numeric($fid) && $file = File::load($fid)) {
          $location_screenshot_urls[] = \Drupal::service('file_url_generator')
            ->generateAbsoluteString($file->getFileUri());
        }
      }
    }

    /* Same data as PDF */
    $data = $this->prepareQuotationData($record);

    return [
      '#theme' => 'quotation_preview',
      '#data' => $data,
      '#image_urls' => $image_urls,
      '#location_screenshot_urls' => $location_screenshot_urls,
      '#attached' => [
        'library' => ['sr_quotation/quotation-pdf-preview'],
      ],
      '#cache' => ['max-age' => 0],
    ];
  }


  /**
   * Prepare quotation data for Twig (Preview + PDF).
   */
  private function prepareQuotationData($record)
  {
    /* SilverDoor Reference */
    $quotation_id = 'E-' . $record->id;

    /* Dates */
    $checkin_date = !empty($record->checkin_date)
      ? date('d F Y', strtotime($record->checkin_date))
      : '';

    $checkout_date = !empty($record->checkout_date)
      ? date('d F Y', strtotime($record->checkout_date))
      : '';

    /* Times */
    $checkin_time = !empty($record->checkin_time)
      ? $record->checkin_time
      : '';

    $checkout_time = !empty($record->checkout_time)
      ? $record->checkout_time
      : '';

    /* Nights */
    $nights = $record->nights;
    if (empty($nights) && $record->checkin_date && $record->checkout_date) {
      $start = new \DateTime($record->checkin_date);
      $end = new \DateTime($record->checkout_date);
      $nights = $start->diff($end)->days;
    }

    /* Supplier Reference - Parse dropdown and text */
    $supplier_reference = '';
    $supplier_reference_text = '';
    if (!empty($record->supplier_reference)) {
      if (strpos($record->supplier_reference, ' | ') !== FALSE) {
        list($supplier_reference, $supplier_reference_text) = explode(' | ', $record->supplier_reference, 2);
        $supplier_reference = trim($supplier_reference);
        $supplier_reference_text = trim($supplier_reference_text);
      } else {
        $supplier_reference = trim($record->supplier_reference);
      }
    }

    return [
      'booker_name' => $record->booker_name,
      'travel_partner' => $record->travel_partner,
      'quotation_id' => $quotation_id,
      'checkin_date' => $checkin_date,
      'checkout_date' => $checkout_date,
      'checkin_time' => $checkin_time,
      'checkout_time' => $checkout_time,
      'nights' => $nights,

      'room_type' => $record->room_type,
      'room_category' => $record->room_category ?? '',
      'unit_type' => $record->unit_type,
      'apartment_size' => $record->apartment_size,
      'bathrooms' => $record->bathrooms,
      'adults' => $record->adults ?? '',
      'kids' => $record->kids ?? '',

      'location' => $record->location,
      'distance' => $record->distance,
      'map_link' => $record->map_link ?? '',

      'currency' => $record->currency,
      'quote' => $record->quote,
      'taxes' => $record->taxes,
      'fx_rate' => $record->fx_rate,
      'addon_prices' => $record->addon_prices ?? '',

      'cancellation_policy' => $record->cancellation_policy,
      'Housekeeping' => $record->rules,
      'rules' => $record->rules,
      'property_description' => $record->description,
      'amenities' => $record->amenities,
      'note' => $record->note,
      'supplier_reference' => $supplier_reference,
      'supplier_reference_text' => $supplier_reference_text,
      'confirmation_status' => $record->confirmation_status ?? '',
    ];
  }


  /**
   * Delete Quotation.
   */
  public function delete($id)
  {

    $exists = $this->db->select('sr_quotation', 'q')
      ->fields('q', ['id'])
      ->condition('id', $id)
      ->execute()
      ->fetchField();

    if (!$exists) {
      $this->messenger()->addError('Quotation not found.');
      return $this->redirect('sr_quotation.list');
    }

    $this->db->delete('sr_quotation')
      ->condition('id', $id)
      ->execute();

    $this->messenger()->addStatus('Quotation deleted successfully.');
    return $this->redirect('sr_quotation.list');
  }

  /**
   * Export Quotations to Excel (CSV).
   */
  public function exportExcel()
  {

    $rows = $this->db->select('sr_quotation', 'q')
      ->fields('q')
      ->orderBy('created', 'DESC')
      ->execute()
      ->fetchAll();

    $filename = 'quotation-export-' . date('Ymd') . '.csv';
    $header = [
      "ID",
      "Serial No",
      "Title",
      "Location",
      "Booker",
      "Corporate",
      "Created",
    ];

    $output = fopen('php://memory', 'w');
    fputcsv($output, $header);

    foreach ($rows as $r) {
      $year = date('Y', $r->created);
      $serial = 'SR-' . str_pad($r->id, 3, '0', STR_PAD_LEFT) . '-' . $year;

      $author = "Unknown";
      if ($u = User::load($r->uid)) {
        $author = $u->getDisplayName();
      }

      fputcsv($output, [
        $r->id,
        $serial,
        $r->unit_type . ' - ' . $r->room_type,
        $r->location,
        $author,
        $r->corporate_name,
        date('Y-m-d', $r->created),
      ]);
    }

    fseek($output, 0);
    return new Response(
      stream_get_contents($output),
      200,
      [
        'Content-Type' => 'text/csv',
        'Content-Disposition' => 'attachment; filename="' . $filename . '"',
      ]
    );
  }

  /**
   * Load main property images.
   */
  private function loadImages($record)
  {
    $image_urls = [];

    if (!empty($record->images)) {
      $fids = array_filter(array_map('trim', explode(',', $record->images)));

      foreach ($fids as $fid) {
        if (is_numeric($fid) && $file = File::load($fid)) {
          $image_urls[] = \Drupal::service('file_url_generator')
            ->generateAbsoluteString($file->getFileUri());
        }
      }
    }

    return $image_urls;
  }

  /**
   * Load location screenshots.
   */
  private function loadLocationScreenshots($record)
  {
    $urls = [];

    if (!empty($record->location_screenshot)) {
      $fids = array_filter(array_map('trim', explode(',', $record->location_screenshot)));

      foreach ($fids as $fid) {
        if (is_numeric($fid) && $file = File::load($fid)) {
          $urls[] = \Drupal::service('file_url_generator')
            ->generateAbsoluteString($file->getFileUri());
        }
      }
    }

    return $urls;
  }


  /**
   * Encrypt quotation ID for URL.
   */
  private function encryptId($id)
  {
    $key = substr(hash('sha256', Settings::get('hash_salt')), 0, 16);
    $iv = substr(hash('sha256', 'quotation_iv'), 0, 16);

    // 👇 Make payload longer & unpredictable
    $payload = json_encode([
      'id' => (int) $id,
      'ts' => time(),               // timestamp
      'rnd' => bin2hex(random_bytes(8)), // random salt
    ]);

    $encrypted = openssl_encrypt(
      $payload,
      'AES-128-CTR',
      $key,
      OPENSSL_RAW_DATA,
      $iv
    );

    return rtrim(strtr(base64_encode($encrypted), '+/', '-_'), '=');
  }

  /**
   * Decrypt quotation ID from URL.
   */
  private function decryptId($token)
  {
    $key = substr(hash('sha256', Settings::get('hash_salt')), 0, 16);
    $iv = substr(hash('sha256', 'quotation_iv'), 0, 16);

    $decoded = base64_decode(strtr($token, '-_', '+/'));

    $decrypted = openssl_decrypt(
      $decoded,
      'AES-128-CTR',
      $key,
      OPENSSL_RAW_DATA,
      $iv
    );

    if (!$decrypted) {
      return NULL;
    }

    $data = json_decode($decrypted, TRUE);

    return isset($data['id']) ? (int) $data['id'] : NULL;
  }

  public function previewHtmlEncrypted($token)
  {
    $id = $this->decryptId($token);

    if (empty($id) || !is_numeric($id)) {
      throw new NotFoundHttpException();
    }

    $record = $this->db->select('sr_quotation', 'q')
      ->fields('q')
      ->condition('id', (int) $id)
      ->execute()
      ->fetchObject();

    if (!$record) {
      throw new NotFoundHttpException();
    }

    $data = $this->prepareQuotationData($record);

    return [
      '#theme' => 'quotation_preview',
      '#data' => $data,
      '#image_urls' => $this->loadImages($record),
      '#location_screenshot_urls' => $this->loadLocationScreenshots($record),
      '#cache' => ['max-age' => 0],
    ];
  }


}


