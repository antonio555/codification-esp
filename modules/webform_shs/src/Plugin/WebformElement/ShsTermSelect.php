<?php

namespace Drupal\webform_shs\Plugin\WebformElement;

use Drupal\Core\Form\FormStateInterface;
use Drupal\webform\Plugin\WebformElement\WebformTermSelect;

/**
 * Provides a 'webform_shs_term_select' Webform element.
 *
 * @WebformElement(
 *   id = "webform_shs_term_select",
 *   label = @Translation("SHS term select"),
 *   description = @Translation("Provides a form element to select a single or multiple terms displayed an SHS element."),
 *   category = @Translation("Entity reference elements"),
 *   dependencies = {
 *     "taxonomy",
 *   }
 * )
 */
class ShsTermSelect extends WebformTermSelect {

  /**
   * {@inheritdoc}
   */
  public function getDefaultProperties() {
    $properties = parent::getDefaultProperties() + [
      'force_deepest' => FALSE,
      'force_deepest_error' => '',
    ];

    unset($properties['select2']);
    unset($properties['chosen']);
    unset($properties['breadcrumb']);
    unset($properties['breadcrumb_delimiter']);
    unset($properties['tree_delimiter']);

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function getTranslatableProperties() {
    return array_merge(parent::getTranslatableProperties(), ['force_deepest_error']);
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);
    $element_properties = $form_state->get('element_properties');

    $form['term_reference'] = [
      '#type' => 'fieldset',
      '#title' => t('Term reference settings'),
      '#weight' => -40,
    ];
    $form['term_reference']['vocabulary'] = [
      '#type' => 'webform_entity_select',
      '#title' => $this->t('Vocabulary'),
      '#target_type' => 'taxonomy_vocabulary',
      '#selection_handler' => 'default:taxonomy_vocabulary',
    ];
    $form['term_reference']['force_deepest'] = [
      '#type' => 'checkbox',
      '#title' => t('Force selection of deepest level'),
      '#default_value' => isset($element_properties['force_deepest']) ? $element_properties['force_deepest'] : FALSE,
      '#description' => t('Force users to select terms from the deepest level.'),
    ];
    $form['term_reference']['force_deepest_error'] = [
      '#type' => 'textfield',
      '#title' => t('Custom force deepest error message'),
      '#default_value' => isset($element_properties['force_deepest_error']) ? $element_properties['force_deepest_error'] : FALSE,
      '#description' => t('If set, this message will be used when a user does not choose the deepest option, instead of the default "You need to select a term from the deepest level in field X." message.'),
      '#states' => [
        'visible' => [
          ':input[name="properties[force_deepest]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    return $form;
  }

}
