<?php

namespace Drupal\sr_share\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\node\Entity\Node;
use Symfony\Component\HttpFoundation\Response;
use Mpdf\Mpdf;
use Drupal\Core\File\FileSystemInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class PdfController extends ControllerBase {

  public function generatePdf(Node $node) {
    if (!$node) {
      return new Response('Node not found.');
    }

    $this->truncateCacheTables();
    $node = \Drupal::entityTypeManager()->getStorage('node')->load($node->id());

    $images_html = '';
    $downloaded_files = [];
    if ($node->hasField('field_media') && !$node->get('field_media')->isEmpty()) {
      $serialized_data = $node->get('field_media')->value;
      $images_html = $this->processFirstFourImages($serialized_data, $downloaded_files);
    }

    $mpdf = $this->initializeMpdf();
    $this->generatePdfContent($mpdf, $node, $images_html);

    $response = new Response($mpdf->Output('', 'S'));
    $response->headers->set('Content-Type', 'application/pdf');
    $response->headers->set('Content-Disposition', 'attachment; filename="' . $this->cleanFilename($node->getTitle()) . '_' . time() . '.pdf"');

    $response->setMaxAge(0);
    $response->setSharedMaxAge(0);
    $response->headers->addCacheControlDirective('no-cache', true);
    $response->headers->addCacheControlDirective('must-revalidate', true);
    $response->headers->addCacheControlDirective('no-store', true);

    // Images in pdf_temp are only needed while mPDF is reading them above —
    // nothing else ever cleaned them up, so every PDF generated left its
    // downloaded images behind permanently, growing the directory forever.
    $this->deleteTempFiles($downloaded_files);

    return $response;
  }

  protected function initializeMpdf() {
    return new Mpdf([
      'mode' => 'utf-8',
      'format' => 'A4',
      'default_font' => 'dejavusans',
      'tempDir' => \Drupal::service('file_system')->getTempDirectory(),
      'allow_output_buffering' => true,
      'allow_remote_images' => true,
      'margin_top' => 30,
      'margin_bottom' => 30,
      'margin_left' => 15,
      'margin_right' => 15,
    ]);
  }

  protected function generatePdfContent($mpdf, $node, $images_html) {
    
    $mpdf->SetHTMLHeader('
    <div style="display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid #ccc; padding-bottom: 10px;">
      <img src="https://stayrelive.com/sites/default/files/sr-logo.png" alt="StayRelive" style="height: 30px;" />
      <div style="color: #0B8D9F; font-size: 12px; float: right;">
        Find <span style="color: #192452; font-size: 12px;">Apartments</span> wherever you travel.
      </div>
    </div>
  ');
  
    $mpdf->SetHTMLFooter('
    <div style="font-size: 10px; padding: 10px 20px; background-color: #192452; border-radius: 10px; color: rgb(214, 238, 242); display: flex; justify-content: center; align-items: center; text-align: center;">
      Page {PAGENO} | © ' . date('Y') . ' StayRelive | 
      <a style="color: rgb(214, 238, 242); text-decoration: none; margin-left: 5px;" href="mailto:contact@stayrelive.com">contact@stayrelive.com</a>
    </div>
  ');

    if (!empty($images_html)) {
        $mpdf->WriteHTML('
            <h2 class="section-title">' . $node->getTitle() . '</h2>
            <div class="image-grid">' . $images_html . '</div>
        ', \Mpdf\HTMLParserMode::HTML_BODY);
    }
    
    $bedrooms = $this->getFieldValue($node, 'field_total_bedrooms');
    $bathrooms = $this->getFieldValue($node, 'field_total_bathrooms');
    $location = $this->getFieldValue($node, 'field_display_address');
    $description_html = '';

    if ($node->hasField('field_description') && !$node->get('field_description')->isEmpty()) {
        $paragraphs = $node->get('field_description')->referencedEntities();
        
        foreach ($paragraphs as $paragraph) {
            if ($paragraph->bundle() == 'property_description') {
                $heading = $paragraph->get('field_heading')->value;
                $text = $paragraph->get('field_text')->value;
                
                if ($heading || $text) {
                    $description_html .= '<div class="description-item">';
                    if ($heading) {
                        $description_html .= '<h3 class="description-heading">' . $heading . '</h3>';
                    }
                    if ($text) {
                        $description_html .= '<div class="description-text">' . $text . '</div>';
                    }
                    $description_html .= '</div>';
                }
            }
        }
    }

    $bills_included = '';
    if ($node->hasField('field_bills_included') && !$node->get('field_bills_included')->isEmpty()) {
        $allowed_values = $node->getFieldDefinition('field_bills_included')->getFieldStorageDefinition()->getSetting('allowed_values');
        $selected_values = $node->get('field_bills_included')->getValue();
        
        $bills_items = [];
        foreach ($selected_values as $item) {
            if (isset($allowed_values[$item['value']])) {
                $bills_items[] = $allowed_values[$item['value']];
            }
        }
        $bills_included = implode(', ', $bills_items);
    }

    $amenities_html = '';
    if ($node->hasField('field_amenities') && !$node->get('field_amenities')->isEmpty()) {
        $amenity_terms = $node->get('field_amenities')->referencedEntities();
    
        if (!empty($amenity_terms)) {
            $amenities_html = '<div style="margin: 10px 0; padding: 5px 0; border-top: 1px solid #eee; border-bottom: 1px solid #eee;">';
            $amenities_html .= '<strong style="display: block; margin-bottom: 5px;">Amenities:</strong>';
            $amenities_html .= '<table style="width: 100%; border-collapse: collapse;">';
            
            // Split amenities into two columns
            $half = ceil(count($amenity_terms) / 2);
            $column1 = array_slice($amenity_terms, 0, $half);
            $column2 = array_slice($amenity_terms, $half);
    
            $amenities_html .= '<tr>'; // Start a row with two cells (columns)
            $amenities_html .= '<td style="vertical-align: top; width: 50%; padding: 0;">';
            $amenities_html .= '<ul style="list-style-type: disc; margin: 0; padding-left: 20px;">';
            
            foreach ($column1 as $term) {
                $amenities_html .= '<li>' . $term->getName() . '</li>';
            }
            $amenities_html .= '</ul>';
            $amenities_html .= '</td>';
    
            $amenities_html .= '<td style="vertical-align: top; width: 50%; padding: 0;">';
            $amenities_html .= '<ul style="list-style-type: disc; margin: 0; padding-left: 20px;">';

            foreach ($column2 as $term) {
                $amenities_html .= '<li>' . $term->getName() . '</li>';
            }
            $amenities_html .= '</ul>';
            $amenities_html .= '</td>';
            $amenities_html .= '</tr>';
    
            $amenities_html .= '</table></div>';
        }
    }

    $property_details = '
        <div style="font-family: Arial, sans-serif; line-height: 1.6;">
            ' . ($location ? '<div style="font-weight: bold; margin: 10px 0;">Address: ' . $location . '</div>' : '') . '
            <div style="margin: 10px 0;">
                ' . ($bedrooms ? '<span style="display: inline-block; margin-right: 20px;">Bedrooms: ' . $bedrooms . '</span>' : '') . '
                ' . ($bathrooms ? '<span style="display: inline-block; margin-right: 20px;">Bathrooms: ' . $bathrooms . '</span>' : '') . '
            </div>
            ' . ($bills_included ? '<div style="margin: 10px 0; padding: 5px 0; border-top: 1px solid #eee; border-bottom: 1px solid #eee;">Bills Included: ' . $bills_included . '</div>' : '') . '
            ' . $description_html . '
            ' . ($amenities_html ? '<div style="margin-top: 20px;">' . $amenities_html . '</div>' : '') . '
        </div>
      ';

    $mpdf->SetHTMLHeader('<div style="display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid #ccc; padding-bottom: 10px;">
      <img src="https://stayrelive.com/sites/default/files/sr-logo.png" alt="StayRelive" style="height: 30px;" />
      <div style="color: #0B8D9F; font-size: 12px; float: right;">
        Find <span style="color: #192452; font-size: 12px;">Apartments</span> wherever you travel.
      </div>
    </div>');
    $mpdf->WriteHTML($property_details, \Mpdf\HTMLParserMode::HTML_BODY);
    $mpdf->SetHTMLFooter('<div style="font-size: 10px; padding: 10px 20px; background-color: #192452; border-radius: 10px; color: rgb(214, 238, 242); display: flex; justify-content: center; align-items: center; text-align: center;">
      Page {PAGENO} | © ' . date('Y') . ' StayRelive | 
      <a style="color: rgb(214, 238, 242); text-decoration: none; margin-left: 5px;" href="mailto:contact@stayrelive.com">contact@stayrelive.com</a>
    </div>');
  }

  protected function getFieldValue(Node $node, $field_name) {
    if ($node->hasField($field_name) && !$node->get($field_name)->isEmpty()) {
      $field = $node->get($field_name);
      if ($field->getFieldDefinition()->getType() === 'text_with_summary') {
        return $field->processed;
      }
      return $field->value;
    }
    return '';
  }

  protected function truncateCacheTables() {
    \Drupal::entityTypeManager()->getStorage('node')->resetCache();
    
    $tables_to_truncate = [
      'cache_bootstrap', 
      'cache_config', 
      'cache_container', 
      'cache_data', 
      'cache_default', 
      'cache_discovery', 
      'cache_dynamic_page_cache', 
      'cache_entity', 
      'cache_menu', 
      'cache_page', 
      'cache_render', 
      'cache_toolbar'
    ];

    $connection = \Drupal::database();
    
    foreach ($tables_to_truncate as $table) {
      try {
        if ($connection->schema()->tableExists($table)) {
          $connection->truncate($table)->execute();
        }
      } catch (\Exception $e) {
        \Drupal::logger('sr_share')->error('Failed to truncate table @table: @error', [
          '@table' => $table,
          '@error' => $e->getMessage()
        ]);
      }
    }
  }

  protected function processFirstFourImages($serialized_data, array &$downloaded_files) {
    $images_html = '';
    $downloaded_count = 0;
    $max_images = 4;

    try {
      $data = unserialize($serialized_data);

      if (is_array($data)) {
        foreach ($data as $item) {
          if ($downloaded_count >= $max_images) break;

          if (isset($item['type']) && $item['type'] === 'image' && isset($item['url'])) {
            $local_path = $this->downloadImage($item['url']);
            if ($local_path) {
              $images_html .= '<img style="width: 42%; height: auto; border: 1px solid #ccc; padding:10px; margin: 10px; border-radius: 4px;" src="' . $local_path . '" />';
              $downloaded_files[] = $local_path;
              $downloaded_count++;
            }
          }
        }
      }
    } catch (\Exception $e) {
      \Drupal::logger('sr_share')->error('Image processing error: @error', ['@error' => $e->getMessage()]);
    }

    return $images_html;
  }

  protected function downloadImage($url) {
    $file_system = \Drupal::service('file_system');
    $http_client = new Client(['verify' => false, 'timeout' => 10]);

    try {
        $temp_dir = 'public://pdf_temp';
        $file_system->prepareDirectory($temp_dir, FileSystemInterface::CREATE_DIRECTORY);

        $filename = md5($url . time()) . '.' . pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION);
        $destination = $temp_dir . '/' . $filename;

        $response = $http_client->get($url, [
            'headers' => [
                'Cache-Control' => 'no-cache',
                'Pragma' => 'no-cache'
            ]
        ]);
        $file_system->saveData((string)$response->getBody(), $destination, FileSystemInterface::EXISTS_REPLACE);

        return $file_system->realpath($destination);
    } catch (RequestException $e) {
        \Drupal::logger('sr_share')->error('Image download failed: @url - @error', [
            '@url' => $url,
            '@error' => $e->getMessage()
        ]);
        return false;
    }
  }

  /**
   * Deletes the images downloaded into pdf_temp for one PDF generation.
   */
  protected function deleteTempFiles(array $paths) {
    foreach ($paths as $path) {
      try {
        if ($path && is_file($path)) {
          @unlink($path);
        }
      } catch (\Exception $e) {
        \Drupal::logger('sr_share')->error('Failed to delete temp PDF image @path: @error', [
          '@path' => $path,
          '@error' => $e->getMessage(),
        ]);
      }
    }
  }

  protected function cleanFilename($filename) {
    return preg_replace('/[^A-Za-z0-9_\-]/', '_', $filename);
  }
}