<?php

namespace Drupal\neg_purger\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\purge_ui\Form\PurgerConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\neg_purger\Settings;

/**
 * {@inheritdoc}
 */
class PurgerForm extends PurgerConfigFormBase {

  /**
   * Constructs a \Drupal\purge_purger_http\Form\ConfigurationForm object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\purge\Plugin\Purge\Invalidation\InvalidationsServiceInterface $purge_invalidation_factory
   *   The invalidation objects factory service.
   */
  final public function __construct(ConfigFactoryInterface $config_factory) {
    $this->setConfigFactory($config_factory);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'neg_purger.configuration_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $settings = Settings::config();

    $form['host'] = [
      '#title' => $this->t('Host'),
      '#type' => 'textfield',
      '#description' => $this->t('Host to purge. May be a service dns host if needed.'),
      '#default_value' => ($settings->get('host') !== NULL) ? $settings->get('host') : '127.0.0.1:80',
      '#required' => TRUE,
    ];

    $form['type'] = [
      '#type' => 'select',
      '#title' => $this->t('Type'),
      '#description' => $this->t('What kind of host is host?'),
      '#default_value' => ($settings->get('type') !== NULL) ? $settings->get('type') : 'host',
      '#options' => [
        'host' => 'Single A record host',
        'srv' => 'SRV - Service discovery host',
      ],
      '#required' => TRUE,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitFormSuccess(array &$form, FormStateInterface $form_state) {
    $settings = Settings::editableConfig();

    $settings->set('type', $form_state->getValue('type'));
    $settings->set('host', $form_state->getValue('host'));

    $settings->save();
  }

}
