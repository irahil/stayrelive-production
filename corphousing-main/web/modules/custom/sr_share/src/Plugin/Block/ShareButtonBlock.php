<?php

namespace Drupal\sr_share\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\Entity\Node;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;

/**
 * Provides a 'ShareButton' block.
 *
 * @Block(
 *   id = "share_button_block",
 *   admin_label = @Translation("Share Button Block"),
 * )
 */
class ShareButtonBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {

    // Get the current URL.
    $current_url = \Drupal::request()->getUri();
    $node = \Drupal::routeMatch()->getParameter('node');
    if ($node instanceof Node) {
      // Generate the link to the PDF export.
      $link = \Drupal\Core\Link::createFromRoute(
        $this->t('Share as PDF'),
        'pdf_export.export',
        ['node' => $node->id()],
        ['attributes' => ['class' => ['pdf-download-button']]]
      )->toString();


    return [
      '#markup' => $this->t('
      <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#exampleModalCenter">
        Share
      </button>

      <div class="modal fade" id="exampleModalCenter" tabindex="-1" aria-labelledby="exampleModalCenterTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title" id="exampleModalCenterTitle" onclick="myFunction()">Share</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
            <div class="copy-url">
              <span id="copy-url">Copy Link</span><br>
            </div>
            <div class="share-whatsapp">
              <span id="share-whatsapp">Share on WhatsApp</span><br>
              </div>
              <div class="download-pdf" id="download-pdf">
              ' . $link . '
              </div>
            </div>
          </div>
        </div>
      </div>
      '),
      '#attached' => [
        'library' => [
          'sr_share/sr_share',
        ],
        'drupalSettings' => [
          'sr_share' => [
            'currentUrl' => $current_url,
            'link' => $link,
          ],
      ],
      ],
    ];
  }
  return [
    '#markup' => $this->t('This block is only available on node pages.'),
  ];
}

/**
   * {@inheritdoc}
   */
  public function blockAccess(AccountInterface $account) {
    return \Drupal\Core\Access\AccessResult::allowedIfHasPermission($account, 'access content');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition
    );
  }

}
