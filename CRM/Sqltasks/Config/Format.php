<?php

/**
 * Utility class for the task configuration format
 */
class CRM_Sqltasks_Config_Format {

  /**
   * Current version of the task configuration format
   */
  const CURRENT = 2;

  /**
   * Determine the version of a task configuration
   *
   * @param $config array task configuration
   *
   * @return int
   * @throws \Exception
   */
  public static function getVersion($config) {
    if (!array_key_exists('version', $config)) {
      return 1;
    }

    if (!is_int($config['version'])) {
      throw new Exception( 'Invalid task configuration version: ' . $config['version']);
    }

    return $config['version'];
  }

  /**
   * Check whether a task configuration uses the current format version
   *
   * @param $config array task configuration
   *
   * @return bool
   * @throws \Exception
   */
  public static function isLatest($config) {
    if (self::getVersion($config) == self::CURRENT) {
      return TRUE;
    }

    return FALSE;
  }

  /**
   * Upgrade a task configuration to the latest version
   *
   * @param $config array task configuration of any supported version
   *
   * @return array upgraded task configuration
   * @throws \Exception
   */
  public static function toLatest($config) {
    $version = self::getVersion($config);
    if ($version > self::CURRENT) {
      throw new Exception( 'Incompatible task configuration version: ' . $version .
                          '. Please upgrade ' . CRM_Sqltasks_ExtensionUtil::LONG_NAME . ' to use this configuration.');
    }

    if ($version == self::CURRENT) {
      return $config;
    }

    switch ($version) {
      case 1:
        $upgrader = new CRM_Sqltasks_Upgrader_Config_V1($config);
    }

    return self::toLatest($upgrader->convert());
  }

  public static function toLatestFromExport($config) {
    var_dump($config);
    $version = self::getVersion($config['config']);
    var_dump($version);
    if ($version > self::CURRENT) {
      throw new Exception( 'Incompatible task configuration version: ' . $version .
        '. Please upgrade ' . CRM_Sqltasks_ExtensionUtil::LONG_NAME . ' to use this configuration.');
    }
    if ($version == self::CURRENT) {
      return $config;
    }

    switch ($version) {
      case 1:
        $upgrader = new CRM_Sqltasks_Upgrader_Config_V1($config['config']);
    }

    return self::toLatestFromExport($upgrader->convertFromExport($config));
  }
}