<?php

namespace Drupal\webform_shs\Element;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element\Select;
use Drupal\Core\Url;
use Drupal\webform\Element\WebformTermReferenceTrait;

/**
 * Provides a webform element for an shs term select menu.
 *
 * @FormElement("webform_shs_term_select")
 */
class ShsTermSelect extends Select {

  use WebformTermReferenceTrait;

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    return [
      '#vocabulary' => '',
      '#force_deepest' => FALSE,
      '#force_deepest_error' => '',
    ] + parent::getInfo();
  }

  /**
   * {@inheritdoc}
   */
  public static function processSelect(&$element, FormStateInterface $form_state, &$complete_form) {
    self::setOptions($element);

    $element = parent::processSelect($element, $form_state, $complete_form);

    // Must convert this element['#type'] to a 'select' to prevent
    // "Illegal choice %choice in %name element" validation error.
    // @see \Drupal\Core\Form\FormValidator::performRequiredValidation
    $element['#type'] = 'select';

    // AJAX errors occur when submitting the element config on the webform ui
    // path. \Drupal\shs\Plugin\Field\FieldWidget\OptionsShsWidget also stops
    // rendering on the field ui pages.
    $route = \Drupal::routeMatch()->getRouteObject();
    if (\Drupal::service('router.admin_context')->isAdminRoute($route)) {
      return $element;
    }

    $default_value = isset($element['#value']) ? $element['#value'] : NULL;
    $settings = [
      'required' => $element['#required'],
      'multiple' => $element['#webform_multiple'],
      'anyLabel' => isset($element['#empty_option']) ? $element['#empty_option'] : t('- None -'),
      'anyValue' => '_none',
      'force_deepest' => $element['#force_deepest'],
      'addNewLabel' => t('Add another item'),
    ];

    /** @var \Drupal\shs\WidgetDefaults $widget_defaults */
    $widget_defaults = \Drupal::service('shs.widget_defaults');
    $bundle = $element['#vocabulary'];
    $cardinality = $element['#multiple'] ? -1 : 1;

    // Define default parents for the widget.
    $parents = $widget_defaults->getInitialParentDefaults($settings['anyValue'], $cardinality);
    if ($default_value) {
      $parents = $widget_defaults->getParentDefaults($default_value, $settings['anyValue'], 'taxonomy_term', $cardinality);
    }

    $settings_shs = [
      'settings' => $settings,
      'bundle' => $bundle,
      'baseUrl' => 'shs-term-data',
      'cardinality' => $cardinality,
      'parents' => $parents,
      'defaultValue' => $default_value,
    ];

    $hooks = [
      'shs_js_settings',
      sprintf('shs_%s_js_settings', $element['#webform_key']),
    ];
    // Allow other modules to override the settings.
    \Drupal::moduleHandler()->alter($hooks, $settings_shs, $bundle, $element['#webform_key']);

    $element['#shs'] = $settings_shs;
    $element['#shs']['classes'] = shs_get_class_definitions($element['#webform_key']);
    $element['#attributes']['class'][] = 'shs-enabled';
    $element['#attributes']['data-shs-selector'] = $element['#webform_key'];
    $element['#attached']['library'][] = 'shs/shs.form';
    $element['#attached']['drupalSettings']['shs'] = [$element['#webform_key'] => $element['#shs']];
    $element['#element_validate'][] = [self::class, 'validateForceDeepest'];

    return $element;
  }

  /**
   * Form API callback. Validate the force deepest option.
   *
   * @param array $element
   *   The element.
   * @param FormStateInterface $form_state
   *   The form state.
   */
  public static function validateForceDeepest(array &$element, FormStateInterface $form_state) {
    if (empty($element['#force_deepest'])) {
      return;
    }

    if (!empty($element['#force_deepest_error'])) {
      $message = $element['#force_deepest_error'];
    }
    else {
      $message = t('You need to select a term from the deepest level in field @name.', ['@name' => $element['#title']]);
    }

    $value = $form_state->getValue($element['#name']);
    if (!is_array($value)) {
      $value = [$value];
    }

    foreach ($value as $element_value) {
      // If nothing was selected.
      if (($element['#shs']['settings']['anyValue'] === $element_value)) {
        // Skip this value row and check the next one.
        if (!$element['#required']) {
          continue;
        }
        // Ensure there were options to select from before setting the error.
        elseif (count($element['#options']) > 1) {
          $form_state->setError($element, $message);
          return;
        }
      }
      elseif (shs_term_has_children($element_value)) {
        $form_state->setError($element, $message);
        return;
      }

    }
  }

}
