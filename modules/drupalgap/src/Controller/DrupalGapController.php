<?php

/**
 * @file
 * Contains \Drupal\drupalgap\Controller\DrupalGapController.
 */

namespace Drupal\DrupalGap\Controller;

use Drupal\Core\Controller\ControllerBase;
use GuzzleHttp\Psr7\Response;


/**
 * Returns responses for jDrupal module routes.
 */
class DrupalGapController extends ControllerBase {

  /**
   * Return the jDrupal configuration page.
   *
   * @return string
   *   A render array containing our jDrupal configuration page content.
   */
  public function DrupalGapConfig() {
    $output = array();

    // Module help link.
    if (\Drupal::moduleHandler()->moduleExists('help')) {
      $output['help'] = array(
        '#markup' => \Drupal\Core\Link::fromTextAndUrl(
          t('DrupalGap Module HELP'),
          \Drupal\Core\Url::fromUri('base:admin/help/drupalgap')
        )->toString()
      );
    }
    else {

      // The help module isn't enabled, provide a few links for help.
      $output['links'] = array(
        '#theme' => 'item_list',
        '#items' => array(
          \Drupal\Core\Link::fromTextAndUrl(
            t('DrupalGap Docs'),
            \Drupal\Core\Url::fromUri('http://docs.drupalgap.org/8')
          )->toString(),
          \Drupal\Core\Link::fromTextAndUrl(
            t('Troubleshoot DrupalGap'),
            \Drupal\Core\Url::fromUri('http://docs.drupalgap.org/8/Resources/Troubleshoot')
          )->toString()
        )
      );

    }

    // Connection test.
    $output['connection_test'] = array(
      '#markup' => '<div id="dg-msg" class="messages messages--warning">' .
          t('Testing connection...') .
        '</div>',
      '#attached' => array(
        'library' => array(
          'jdrupal/jdrupal',
          'drupalgap/dg_connection_test'
        )
      )
    );
    return $output;
  }

}
