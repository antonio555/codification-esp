<?php

namespace Drupal\uc_usps\Plugin\Ubercart\ShippingQuote;

use Drupal\Core\Form\FormStateInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\uc_order\OrderInterface;

/**
 * Provides a percentage rate shipping quote plugin.
 *
 * @UbercartShippingQuote(
 *   id = "usps",
 *   admin_label = @Translation("USPS Domestic")
 * )
 */
class USPSDomesticRate extends USPSRateBase {
//  id = "usps_env",

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'base_rate' => 0,
      'product_rate' => 0,
      'field' => '',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $fields = ['' => $this->t('- None -')];
    $result = \Drupal::entityQuery('field_config')
      ->condition('field_type', 'number')
      ->execute();
    foreach (FieldConfig::loadMultiple($result) as $field) {
      $fields[$field->getName()] = $field->label();
    }

    $form['base_rate'] = [
      '#type' => 'uc_price',
      '#title' => $this->t('Base price'),
      '#description' => $this->t('The starting price for shipping costs.'),
      '#default_value' => $this->configuration['base_rate'],
      '#required' => TRUE,
    ];
    $form['product_rate'] = [
      '#type' => 'number',
      '#title' => $this->t('Default product shipping rate'),
      '#min' => 0,
      '#step' => 'any',
      '#description' => $this->t('The percentage of the item price to add to the shipping cost for an item.'),
      '#default_value' => $this->configuration['product_rate'],
      '#field_suffix' => $this->t('% (percent)'),
      '#required' => TRUE,
    ];
    $form['field'] = [
      '#type' => 'select',
      '#title' => $this->t('Product shipping rate override field'),
      '#description' => $this->t('Overrides the default shipping rate per product for this percentage rate shipping method, when the field is attached to a product content type and has a value.'),
      '#options' => $fields,
      '#default_value' => $this->configuration['field'],
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->configuration['base_rate'] = $form_state->getValue('base_rate');
    $this->configuration['product_rate'] = $form_state->getValue('product_rate');
    $this->configuration['field'] = $form_state->getValue('field');
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->t('USPS Web Tools® rate');
  }

  /**
   * {@inheritdoc}
   */
  public function getQuotes(OrderInterface $order) {
    $rate = $this->configuration['base_rate'];
    $field = $this->configuration['field'];

    foreach ($order->products as $product) {
      if (isset($product->nid->entity->$field->value)) {
        $product_rate = $product->nid->entity->$field->value * $product->qty->value;
      }
      else {
        $product_rate = $this->configuration['product_rate'] * $product->qty->value;
      }

      $rate += $product->price->value * floatval($product_rate) / 100;
    }

    return [$rate];
  }

  /**
   * Callback for retrieving USPS shipping quote.
   *
   * @param $products
   *   Array of cart contents.
   * @param $details
   *   Order details other than product information.
   * @param $method
   *   The shipping method to create the quote.
   *
   * @return
   *   JSON object containing rate, error, and debugging information.
   */
  //public function getQuotes(OrderInterface $order) {
  public function quote($products, $details, $method) {
    $usps_config = \Drupal::config('uc_usps.settings');
    $quote_config = \Drupal::config('uc_quote.settings');
    // The uc_quote AJAX query can fire before the customer has completely
    // filled out the destination address, so check to see whether the address
    // has all needed fields. If not, abort.
    $destination = (object) $details;

    // Country code is always needed.
    if (empty($destination->country)) {
      // Skip this shipping method.
      return [];
    }

    // Shipments to the US also need zone and postal_code.
    if (($destination->country == 'US') &&
        (empty($destination->zone) || empty($destination->postal_code))) {
      // Skip this shipping method.
      return [];
    }

    // USPS Production server.
    $connection_url = 'http://production.shippingapis.com/ShippingAPI.dll';

    // Initialize $debug_data to prevent PHP notices here and in uc_quote.
    $debug_data = ['debug' => NULL, 'error' => []];
    $services = [];
    $addresses = [$quote_config->get('store_default_address')];
    $packages = $this->packageProducts($products, $addresses);
    if (!count($packages)) {
      return [];
    }

    foreach ($packages as $key => $ship_packages) {
      $orig = $addresses[$key];
      $orig->email = uc_store_email();

      if (strpos($method['id'], 'intl') && ($destination->country != 'US')) {
        // Build XML for international rate request.
        $request = $this->intlRateRequest($ship_packages, $orig, $destination);
      }
      elseif ($destination->country == 'US') {
        // Build XML for domestic rate request.
        $request = $this->rateRequest($ship_packages, $orig, $destination);
      }

      $account = \Drupal::currentUser();
      if ($account->hasPermission('configure quotes') && $quote_config->get('display_debug')) {
        $debug_data['debug'] .= htmlentities(urldecode($request)) . "<br />\n";
      }

      // Send request
      $result = \Drupal::httpClient()
        ->post($connection_url, NULL, $request)
        ->send();

      if ($account->hasPermission('configure quotes') && $quote_config->get('display_debug')) {
        $debug_data['debug'] .= htmlentities($result->getBody(TRUE)) . "<br />\n";
      }

      $rate_type = $usps_config->get('online_rates');
      $response = new SimpleXMLElement($result->getBody(TRUE));

      // Map double-encoded HTML markup in service names to Unicode characters.
      $service_markup = [
        '&lt;sup&gt;&amp;reg;&lt;/sup&gt;'   => '®',
        '&lt;sup&gt;&amp;trade;&lt;/sup&gt;' => '™',
        '&lt;sup&gt;&#174;&lt;/sup&gt;'      => '®',
        '&lt;sup&gt;&#8482;&lt;/sup&gt;'     => '™',
        '**'                                 => '',
      ];
      // Use this map to fix USPS service names.
      if (strpos($method['id'], 'intl')) {
        // Find and replace markup in International service names.
        foreach ($response->xpath('//Service') as $service) {
          $service->SvcDescription = str_replace(array_keys($service_markup), $service_markup, $service->SvcDescription);
        }
      }
      else {
        // Find and replace markup in Domestic service names.
        foreach ($response->xpath('//Postage') as $postage) {
          $postage->MailService = str_replace(array_keys($service_markup), $service_markup, $postage->MailService);
        }
      }

      if (isset($response->Package)) {
        foreach ($response->Package as $package) {
          if (isset($package->Error)) {
            $debug_data['error'][] = (string) $package->Error[0]->Description . '<br />';
          }
          else {
            if (strpos($method['id'], 'intl')) {
              foreach ($package->Service as $service) {
                $id = (string) $service['ID'];
                $services[$id]['label'] = t('U.S.P.S. @service', ['@service' => (string) $service->SvcDescription]);
                // Markup rate before customer sees it.
                if (!isset($services[$id]['rate'])) {
                  $services[$id]['rate'] = 0;
                }
                $services[$id]['rate'] += $this->rateMarkup((string) $service->Postage);
              }
            }
            else {
              foreach ($package->Postage as $postage) {
                $classid = (string) $postage['CLASSID'];
                if ($classid === '0') {
                  if ((string) $postage->MailService == "First-Class Mail® Parcel") {
                    $classid = 'zeroParcel';
                  }
                  elseif ((string) $postage->MailService == "First-Class Mail® Letter") {
                    $classid = 'zeroFlat';
                  }
                  else {
                    $classid = 'zero';
                  }
                }
                if (!isset($services[$classid]['rate'])) {
                  $services[$classid]['rate'] = 0;
                }
                $services[$classid]['label'] = t('U.S.P.S. @service', ['@service' => (string) $postage->MailService]);
                // Markup rate before customer sees it.
                // Rates are stored differently if the ONLINE $rate_type is
                // requested. First Class doesn't have online rates, so if
                // CommercialRate is missing use Rate instead.
                if ($rate_type && !empty($postage->CommercialRate)) {
                  $services[$classid]['rate'] += $this->rateMarkup((string) $postage->CommercialRate);
                }
                else {
                  $services[$classid]['rate'] += $this->rateMarkup((string) $postage->Rate);
                }
              }
            }
          }
        }
      }
    }

    // Strip leading 'usps_'.
    $method_services = substr($method['id'] . '_services', 5);
//$method_services is the name of the callback function
//  array_keys($method['quote']['accessorials'])

    $usps_services = array_filter($usps_config->get($method_services));
    foreach ($services as $service => $quote) {
      if (!in_array($service, $usps_services)) {
        unset($services[$service]);
      }
    }
    foreach ($services as $key => $quote) {
      if (isset($quote['rate'])) {
        $services[$key]['rate'] = $quote['rate'];
        $services[$key]['option_label'] = $this->getDisplayLabel($quote['label']);
      }
    }

    uasort($services, 'uc_quote_price_sort');

    // Merge debug data into $services.  This is necessary because
    // $debug_data is not sortable by a 'rate' key, so it has to be
    // kept separate from the $services data until this point.
    if (isset($debug_data['debug']) ||
        (isset($debug_data['error']) && count($debug_data['error']))) {
      $services['data'] = $debug_data;
    }

    return $services;
  }

  /**
   * Constructs a quote request for domestic shipments.
   *
   * @param array $packages
   *   Array of packages received from the cart.
   * @param $origin
   *   Delivery origin address information.
   * @param $destination
   *   Delivery destination address information.
   *
   * @return string
   *   RateV4Request XML document to send to USPS.
   */
  public function rateRequest(array $packages, $origin, $destination) {
    $usps_config = \Drupal::config('uc_usps.settings');
    $request  = '<RateV4Request USERID="' . $usps_config->get('user_id') . '">';
    $request .= '<Revision>2</Revision>';

    $rate_type = $usps_config->get('online_rates');

    $package_id = 0;
    foreach ($packages as $package) {
      $qty = $package->qty;
      for ($i = 0; $i < $qty; $i++) {
        $request .= '<Package ID="' . $package_id . '">' .
          '<Service>' . ($rate_type ? 'ONLINE' : 'ALL') . '</Service>' .
          '<ZipOrigination>' . substr(trim($origin->postal_code), 0, 5) . '</ZipOrigination>' .
          '<ZipDestination>' . substr(trim($destination->postal_code), 0, 5) . '</ZipDestination>' .
          '<Pounds>' . intval($package->pounds) . '</Pounds>' .
          '<Ounces>' . number_format($package->ounces, 1, '.', '') . '</Ounces>' .
          '<Container>' . $package->container . '</Container>' .
          '<Size>' . $package->size . '</Size>' .
          '<Width>' . $package->width . '</Width>' .
          '<Length>' . $package->length . '</Length>' .
          '<Height>' . $package->height . '</Height>' .
          '<Girth>' . $package->girth . '</Girth>' .
          '<Value>' . $package->price . '</Value>' .
          '<Machinable>' . ($package->machinable ? 'TRUE' : 'FALSE') . '</Machinable>' .
          '<ReturnLocations>TRUE</ReturnLocations>' .
          '<ShipDate Option="EMSH">' . \Drupal::service('date.formatter')->format(\Drupal::time()->getCurrentTime(), 'custom', 'd-M-Y', 'America/New_York', 'en') . '</ShipDate>';

          // Check if we need to add any special services to this package.
          if ($usps_config->get('insurance')            ||
             $usps_config->get('delivery_confirmation') ||
             $usps_config->get('signature_confirmation')  ) {

            $request .= '<SpecialServices>';

            if ($usps_config->get('insurance')) {
              $request .= '<SpecialService>1</SpecialService>';
            }
            if ($usps_config->get('delivery_confirmation')) {
              $request .= '<SpecialService>13</SpecialService>';
            }
            if ($usps_config->get('signature_confirmation')) {
              $request .= '<SpecialService>15</SpecialService>';
            }

            $request .= '</SpecialServices>';
          }

          // Close off Package tag.
          $request .= '</Package>';

        $package_id++;
      }
    }
    $request .= '</RateV4Request>';

    return 'API=RateV4&XML=' . UrlHelper::encodePath($request);
  }

}
