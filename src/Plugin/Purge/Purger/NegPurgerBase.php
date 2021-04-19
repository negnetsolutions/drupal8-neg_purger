<?php

namespace Drupal\neg_purger\Plugin\Purge\Purger;

use GuzzleHttp\ClientInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Utility\Token;
use Drupal\purge\Plugin\Purge\Purger\PurgerBase;
use Drupal\purge\Plugin\Purge\Purger\PurgerInterface;
use Drupal\neg_purger\Settings;

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
   * @var \Drupal\Core\Utility\Token
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
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ClientInterface $http_client, Token $token) {
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
    $headers['X-Api-Password'] = 'g@dTJw.moMsyZYPN6x8Vvw2b3';

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
   * @return mixed[]
   *   Associative array with option/value pairs.
   */
  protected function getOptions($token_data) {
    $opt = [
      'http_errors' => TRUE,
      'connect_timeout' => 0.25,
      'timeout' => 0.5,
      'headers' => $this->getHeaders($token_data),
    ];
    return $opt;
  }

  /**
   * {@inheritdoc}
   */
  public function getTimeHint() {

    // When runtime measurement is enabled, we just use the base implementation.
    // if ($this->settings->runtime_measurement) {.
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
   * Gets hosts from SRV records.
   */
  protected static function getHostsFromSrvRecord($host) {
    $hosts = [];

    $r = new \Net_DNS2_Resolver([
      'timeout' => 1,
    ]);

    try {
      $result = $r->query($host, 'SRV');

      foreach ($result->answer as $answer) {
        // Get's A record from answer target.
        try {
          $resultA = $r->query($answer->target, 'A');
          if (isset($resultA->answer) && count($resultA->answer) > 0 && property_exists($resultA->answer[0], 'address') && strlen($resultA->answer[0]->address) > 0) {
            $hosts[] = $resultA->answer[0]->address . ':' . $answer->port;
          }
        }
        catch (\Exception $e) {
        }
      }
    }
    catch (\Exception $e) {
    }

    if (count($hosts) === 0) {
      // Default to localhost since we have issues with returning no hosts.
      $hosts[] = '127.0.0.1:80';
    }

    return $hosts;
  }

  /**
   * Lists all hosts.
   */
  public static function listHosts() {
    $settings = Settings::config();
    $type = ($settings->get('type') !== NULL) ? $settings->get('type') : 'host';
    $host = ($settings->get('host') !== NULL) ? $settings->get('host') : '127.0.0.1:80';

    $hosts = [];

    if ($type === 'host') {
      $hosts[] = $host;
    }
    else {
      $hosts = self::getHostsFromSrvRecord($host);
    }

    return $hosts;
  }

  /**
   * Gets all URIs to connect to.
   */
  protected function getUris($token_data) {
    $settings = Settings::config();
    $type = ($settings->get('type') !== NULL) ? $settings->get('type') : 'host';
    $host = ($settings->get('host') !== NULL) ? $settings->get('host') : '127.0.0.1:80';

    $hosts = [];

    if ($type === 'host') {
      $hosts[] = $this->getUri($host, $token_data);
    }
    else {
      // SRV records.
      foreach (self::getHostsFromSrvRecord($host) as $record) {
        $hosts[] = $this->getUri($record, $token_data);
      }
    }

    return $hosts;
  }

  /**
   * Retrieve the URI to connect to.
   *
   * @param string $host
   *   A string with the host to connect to.
   * @param array $token_data
   *   An array of keyed objects, to pass on to the token service.
   *
   * @return string
   *   URL string representation.
   */
  protected function getUri(string $host, array $token_data) {
    return sprintf(
      "http://$host%",
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
