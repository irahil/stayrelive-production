<?php

namespace Drupal\sr_share\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\node\Entity\Node;
use Symfony\Component\HttpFoundation\Response;
use Mpdf\Mpdf;
use Drupal\taxonomy\Entity\Term;

class PdfController extends ControllerBase
{
  public function generatePdf(Node $node)
  {
    if (!$node) {
      return new Response('Node not found.');
    }

    $node = \Drupal::entityTypeManager()
      ->getStorage('node')
      ->load($node->id());
    $mpdf = $this->initializeMpdf();

    $this->generatePdfContent($mpdf, $node);

    $response = new Response($mpdf->Output('', 'S'));
    $response->headers->set('Content-Type', 'application/pdf');
    $response->headers->set(
      'Content-Disposition',
      'attachment; filename="' .
        $this->cleanFilename($node->getTitle()) .
        '_' .
        time() .
        '.pdf"'
    );

    return $response;
  }

  protected function initializeMpdf()
  {
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

  protected function generatePdfContent($mpdf, $node)
  {
    // Header & Footer
    $mpdf->SetHTMLHeader('
      <div style="display:flex;justify-content:space-between;align-items:center;border-bottom:1px solid #ccc;padding-bottom:8px;">
        <img src="/modules/custom/sr_share/images/corporate.png" alt="Corporate" height="42"/>
        <div style="color:#0B8D9F;font-size:12px;">Find <span style="color:#192452;">Apartments</span> wherever you travel.</div>
      </div>
    ');

    $mpdf->SetHTMLFooter('
      <div style="font-size:10px;padding:10px;background-color:#192452;color:#d6eef2;text-align:center;">
        Page {PAGENO} | © ' .
      date('Y') .
      ' Corporate Housing by stayrelive
      </div>
    ');

    // Property Images
    $images_html = '';
    if (
      $node->hasField('field_media') &&
      !$node->get('field_media')->isEmpty()
    ) {
      foreach ($node->get('field_media')->referencedEntities() as $media) {
        if (
          $media->hasField('field_media_image') &&
          !$media->get('field_media_image')->isEmpty()
        ) {
          $uri = $media->get('field_media_image')->entity->getFileUri();
          $url = file_create_url($uri);
          $images_html .=
            '<img src="' .
            $url .
            '" style="width:48%;margin:1%;border-radius:5px;">';
        }
      }
    }

    if ($images_html) {
      $mpdf->WriteHTML(
        '<h2 style="border-bottom:2px solid #0B8D9F;">Property Images</h2>' .
          $images_html,
        \Mpdf\HTMLParserMode::HTML_BODY
      );
    }

    // Overview Property Description
    $description_html = '';
    if (
      $node->hasField('field_description') &&
      !$node->get('field_description')->isEmpty()
    ) {
      $field_description = $node->get('field_description');
      $paragraphs = [];

      if (method_exists($field_description, 'referencedEntities')) {
        $paragraphs = $field_description->referencedEntities();
      }

      if (!empty($paragraphs)) {
        $description_html .=
          '<h2 style="color: #192452; font-size: 18px; margin: 20px 0 10px 0; border-bottom: 2px solid #0B8D9F; padding-bottom: 5px;">Property Description</h2>';
        foreach ($paragraphs as $paragraph) {
          if ($paragraph->bundle() == 'property_description') {
            $heading = $paragraph->get('field_heading')->value ?? '';
            $text = $paragraph->get('field_text')->value ?? '';

            if ($heading || $text) {
              if ($heading) {
                $description_html .=
                  '<h3 style="color: #333; font-size: 16px; margin: 15px 0 5px 0;">' .
                  $heading .
                  '</h3>';
              }
              if ($text) {
                $description_html .=
                  '<div style="margin: 10px 0; line-height: 1.6; color: #555;">' .
                  $text .
                  '</div>';
              }
            }
          }
        }
      } elseif ($field_description->value) {
        $description_html .=
          '<h2 style="color: #192452; font-size: 18px; margin: 20px 0 10px 0; border-bottom: 2px solid #0B8D9F; padding-bottom: 5px;">Property Description</h2>';
        $description_html .=
          '<div style="margin: 10px 0; line-height: 1.6; color: #555;">' .
          $field_description->value .
          '</div>';
      }
    }

    if ($description_html) {
      $mpdf->WriteHTML($description_html, \Mpdf\HTMLParserMode::HTML_BODY);
    }

    // Rooms Section
    $room_nids = $this->getRelatedRooms($node);
    if (!empty($room_nids)) {
      $mpdf->WriteHTML(
        '<h2 style="color:#192452;border-bottom:2px solid #0B8D9F;">Room Types</h2>',
        \Mpdf\HTMLParserMode::HTML_BODY
      );
      foreach ($room_nids as $room_nid) {
        $room = Node::load($room_nid);
        if (!$room) {
          continue;
        }

        $mpdf->WriteHTML('<div>', \Mpdf\HTMLParserMode::HTML_BODY);
        $mpdf->WriteHTML(
          '<h3 style="color:#0B8D9F;">' . $room->getTitle() . '</h3>'
        );

        // Room Images
        if (
          $room->hasField('field_room_media') &&
          !$room->get('field_room_media')->isEmpty()
        ) {
          $images_html = '';
          foreach (
            $room->get('field_room_media')->referencedEntities()
            as $media
          ) {
            if (
              $media->hasField('field_media_image') &&
              !$media->get('field_media_image')->isEmpty()
            ) {
              $url = file_create_url(
                $media->get('field_media_image')->entity->getFileUri()
              );
              $images_html .=
                '<img src="' .
                $url .
                '" style="width:48%;margin:1%;border-radius:4px;">';
            }
          }
          $mpdf->WriteHTML('<div>' . $images_html . '</div>');
        }

        // Room Details
        $details = [
          'Room Type' => $this->getTermLabels($room, 'field_room_type'),
          'Number of Units' => $this->getFieldValue(
            $room,
            'field_number_of_units'
          ),
          'Contact Name' => $this->getFieldValue(
            $room,
            'field_room_contact_name'
          ),
          'Contact Mobile' => $this->getFieldValue(
            $room,
            'field_room_contact_mobile'
          ),
          'Bedrooms' => $this->getFieldValue($room, 'field_bedrooms'),
          'Bathrooms' => $this->getFieldValue($room, 'field_bathrooms'),
          'Tax Details' => $this->getFieldValue($room, 'field_tax_details'),
          'Amenities' => $this->getTermLabels($room, 'field_room_amenities'),
          // 'Price (₹)' => $this->getFieldValue($room, 'field_room_price'),
        ];

        $details_html =
          '<table style="width:100%;border-collapse:collapse;margin-top:10px;">';
        foreach ($details as $label => $val) {
          if ($val) {
            $details_html .=
              '<tr><td style="width:40%;padding:4px 8px;font-weight:bold;">' .
              $label .
              '</td><td style="padding:4px 8px;">' .
              $val .
              '</td></tr>';
          }
        }
        $details_html .= '</table>';
        $mpdf->WriteHTML($details_html);

        // Room Description
        if (
          $room->hasField('field_room_description') &&
          !$room->get('field_room_description')->isEmpty()
        ) {
          $desc = $room->get('field_room_description')->value;
          $mpdf->WriteHTML(
            '<div style="margin-top:10px;color:#555;">' . $desc . '</div>'
          );
        }

        $mpdf->WriteHTML('</div>'); // room box
      }
    }

    // Amenities
    if (
      $node->hasField('field_amenities') &&
      !$node->get('field_amenities')->isEmpty()
    ) {
      $amenities = $this->getTermLabels($node, 'field_amenities');
      $mpdf->WriteHTML(
        '<h2 style="color:#192452;border-bottom:2px solid #0B8D9F;">Amenities</h2><div>' .
          $amenities .
          '</div>'
      );
    }
  }
  

  protected function getFieldValue($node, $field)
  {
    if ($node->hasField($field) && !$node->get($field)->isEmpty()) {
      return $node->get($field)->value;
    }
    return '';
  }

  protected function getTermLabels($node, $field)
  {
    if ($node->hasField($field) && !$node->get($field)->isEmpty()) {
      $labels = [];
      foreach ($node->get($field)->referencedEntities() as $term) {
        $labels[] = $term->label();
      }
      return implode(', ', $labels);
    }
    return '';
  }

  protected function getRelatedRooms($node)
  {
    $query = \Drupal::entityTypeManager()
      ->getStorage('node')
      ->getQuery()
      ->condition('type', 'room')
      ->condition('field_parent_id', $node->id())
      ->accessCheck(false);
    return $query->execute();
  }

  protected function cleanFilename($name)
  {
    return preg_replace('/[^A-Za-z0-9_\-]/', '_', $name);
  }
}