<?php

namespace Drupal\neg_purger\Plugin\Purge\Purger;

use GuzzleHttp\ClientInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Utility\Token;
use Drupal\purge\Plugin\Purge\Purger\PurgerBase;
use Drupal\purge\Plugin\Purge\Purger\PurgerInterface;
use Drupal\purge\Plugin\Purge\Invalidation\InvalidationInterface;

/**
 * Abstract base class for HTTP based configurable purgers.
 */
abstract class NegPurgerBase extends PurgerBase implements PurgerInterface {

  /**
   * @var \GuzzleHttp\Client
   */
  protected $client;

  /**
   * The token service.
   *
   * @var \Drupal\Core\Utility\Token.
   */
  protected $token;

  /**
   * Constructs the Varnish purger.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \GuzzleHttp\ClientInterface $http_client
   *   An HTTP client that can perform remote requests.
   * @param \Drupal\Core\Utility\Token $token
   *   The token service.
   */
  function __construct(array $configuration, $plugin_id, $plugin_definition, ClientInterface $http_client, Token $token) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->client = $http_client;
    $this->token = $token;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('http_client'),
      $container->get('token')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function delete() {
  }

  /**
   * {@inheritdoc}
   */
  public function getCooldownTime() {
    return 0.0;
  }

  /**
   * {@inheritdoc}
   */
  public function getIdealConditionsLimit() {
    return 100;
  }

  /**
   * Retrieve all configured headers that need to be set.
   *
   * @param array $token_data
   *   An array of keyed objects, to pass on to the token service.
   *
   * @return string[]
   *   Associative array with header values and field names in the key.
   */
  protected function getHeaders($token_data) {
    $headers = [];
    $headers['user-agent'] = 'neg_purger module for Drupal 8.';

    $h = [
      [
        'field' => 'Cache-Tags',
        'value' => '[invalidation:expression]',
      ],
    ];

    foreach ($h as $header) {
      // According to https://tools.ietf.org/html/rfc2616#section-4.2, header
      // names are case-insensitive. Therefore, to aid easy overrides by end
      // users, we lower all header names so that no doubles are sent.
      $headers[strtolower($header['field'])] = $this->token->replace(
        $header['value'],
        $token_data
      );
    }
    return $headers;
  }

  /**
   * {@inheritdoc}
   */
  public function getLabel() {
    return "Neg ECS Purger";
  }

  /**
   * Retrieve the Guzzle connection options to set.
   *
   * @param array $token_data
   *   An array of keyed objects, to pass on to the token service.
   *
   *
   * @return mixed[]
   *   Associative array with option/value pairs.
   */
  protected function getOptions($token_data) {
    $opt = [
      'http_errors' => TRUE,
      'connect_timeout' => 1.0,
      'timeout' => 1.0,
      'headers' => $this->getHeaders($token_data),
    ];
    return $opt;
  }

  /**
   * {@inheritdoc}
   */
  public function getTimeHint() {

    // When runtime measurement is enabled, we just use the base implementation.
    // if ($this->settings->runtime_measurement) {
    if (TRUE) {
      return parent::getTimeHint();
    }

    // Theoretically connection timeouts and general timeouts can add up, so
    // we add up our assumption of the worst possible time it takes as well.
    return 1.0 + 1.0;
  }

  /**
   * {@inheritdoc}
   */
  public function getTypes() {
    return ['tag'];
  }

  /**
   * Retrieve the URI to connect to.
   *
   * @param array $token_data
   *   An array of keyed objects, to pass on to the token service.
   *
   * @return string
   *   URL string representation.
   */
  protected function getUri($token_data) {
    return sprintf(
      'http://127.0.0.1:80%',
      $this->token->replace('/', $token_data)
    );
  }

  /**
   * {@inheritdoc}
   */
  public function hasRuntimeMeasurement() {
    return TRUE;
  }

}
