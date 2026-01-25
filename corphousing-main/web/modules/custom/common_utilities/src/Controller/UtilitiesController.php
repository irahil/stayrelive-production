<?php
namespace Drupal\common_utilities\Controller;

class UtilitiesController {
    /**
     * Clears cache
     *
     * @return array
     * A simple renderable array.
     */
    public function rebuildCache() {
        \Drupal::service("router.builder")->rebuild();
        //drupal_flush_all_caches();
        $build = [
            '#markup' => 'Cache flushed.!',
        ];
        return $build;
    }
}
?>