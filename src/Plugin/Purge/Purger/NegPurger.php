<?php

namespace Drupal\neg_purger\Plugin\Purge\Purger;

use Drupal\purge\Plugin\Purge\Purger\PurgerInterface;
use Drupal\purge\Plugin\Purge\Invalidation\InvalidationInterface;

/**
 * HTTP Purger.
 *
 * @PurgePurger(
 *   id = "negpurger",
 *   label = @Translation("Neg ECS Purger"),
 *   cooldown_time = 0.0,
 *   description = @Translation("Configurable purger that makes HTTP requests for each given invalidation instruction."),
 *   configform = "\Drupal\neg_purger\Form\PurgerForm",
 *   multi_instance = FALSE,
 *   types = {},
 * )
 */
class NegPurger extends NegPurgerBase implements PurgerInterface {

  /**
   * {@inheritdoc}
   */
  public function invalidate(array $invalidations) {

    // Iterate every single object and fire a request per object.
    foreach ($invalidations as $invalidation) {
      $token_data = ['invalidation' => $invalidation];
      $opt = $this->getOptions($token_data);

      foreach ($this->getUris($token_data) as $uri) {
        try {
          $this->client->request('BAN', $uri, $opt);
          $invalidation->setState(InvalidationInterface::SUCCEEDED);
        }
        catch (RequestException $e) {
          $invalidation->setState(InvalidationInterface::SUCCEEDED);
        }
        catch (\Exception $e) {
          $invalidation->setState(InvalidationInterface::FAILED);

          // Log as much useful information as we can.
          $headers = $opt['headers'];
          unset($opt['headers']);
          $debug = json_encode(str_replace("\n", ' ', [
            'msg' => $e->getMessage(),
            'uri' => $uri,
            'method' => 'BAN',
            'guzzle_opt' => $opt,
            'headers' => $headers,
          ]));
          $this->logger()->emergency("item failed due @e, details (JSON): @debug",
            ['@e' => get_class($e), '@debug' => $debug]);
        }
      }
    }
  }

}
