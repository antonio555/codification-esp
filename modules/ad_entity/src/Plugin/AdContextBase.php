<?php

namespace Drupal\ad_entity\Plugin;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\TypedData\Plugin\DataType\Map;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslationManager;
use Drupal\Component\Plugin\PluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for Advertising context plugins.
 */
abstract class AdContextBase extends PluginBase implements AdContextInterface, ContainerFactoryPluginInterface {

  /**
   * The string translation service.
   *
   * @var \Drupal\Core\StringTranslation\TranslationManager
   */
  protected $stringTranslation;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('string_translation')
    );
  }

  /**
   * Constructs an AdContextBase object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\StringTranslation\TranslationManager $translation_manager
   *   The string translation service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, TranslationManager $translation_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->stringTranslation = $translation_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $plugin_settings, Map $context_item, array $form, FormStateInterface $form_state) {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function massageSettings(array $plugin_settings) {
    return $plugin_settings;
  }

}
