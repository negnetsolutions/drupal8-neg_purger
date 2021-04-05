<?php

namespace Drupal\neg_purger;

/**
 * Purger settings.
 */
class Settings {
  const CONFIGNAME = 'neg_purger.settings';

  /**
   * Gets a config object.
   */
  public static function config() {
    return \Drupal::config(self::CONFIGNAME);
  }

  /**
   * Gets an editable config object.
   */
  public static function editableConfig() {
    return \Drupal::service('config.factory')->getEditable(self::CONFIGNAME);
  }

}
