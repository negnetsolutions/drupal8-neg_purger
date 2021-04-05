<?php

namespace Drupal\neg_purger\Plugin\Purge\DiagnosticCheck;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\purge\Plugin\Purge\Purger\PurgersServiceInterface;
use Drupal\purge\Plugin\Purge\DiagnosticCheck\DiagnosticCheckInterface;
use Drupal\purge\Plugin\Purge\DiagnosticCheck\DiagnosticCheckBase;
use Drupal\neg_purger\Plugin\Purge\Purger\NegPurgerBase;

/**
 * Verifies that only fully configured Varnish purgers load.
 *
 * @PurgeDiagnosticCheck(
 *   id = "negconfiguration",
 *   title = @Translation("Neg ECS Purger"),
 *   description = @Translation("Verifies that only fully configured Neg purgers load."),
 *   dependent_queue_plugins = {},
 *   dependent_purger_plugins = {"negpurger"}
 * )
 */
class ConfigurationCheck extends DiagnosticCheckBase implements DiagnosticCheckInterface {

  /**
   * @var \Drupal\purge\Plugin\Purge\Purger\PurgersServiceInterface
   */
  protected $purgePurgers;

  /**
   * Constructs a \Drupal\purge\Plugin\Purge\DiagnosticCheck\PurgerAvailableCheck object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\purge\Plugin\Purge\Purger\PurgersServiceInterface $purge_purgers
   *   The purge executive service, which wipes content from external caches.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, PurgersServiceInterface $purge_purgers) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->purgePurgers = $purge_purgers;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('purge.purgers')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function run() {
    try {
      $hosts = NegPurgerBase::listHosts();
    }
    catch (\Exception $e) {
      $this->recommendation = $e->getMessage();
      return self::SEVERITY_ERROR;
    }

    $msg = "Neg Purger Configured Successfully.\nHosts: ";
    foreach ($hosts as $host) {
      $msg .= "\n$host";
    }

    $this->recommendation = $msg;
    return self::SEVERITY_OK;
  }

}
