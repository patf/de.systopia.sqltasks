<?php

/**
 * Utility functions
 */
class CRM_Sqltasks_Utils {

  public static function isSegmentationInstalled() {
    // TODO: cache? but be aware, unit tests need this uncached atm
    return civicrm_api3('Extension', 'getcount', [
      'full_name' => 'de.systopia.segmentation',
      'status'    => 'installed',
    ]) == 1;
  }

}
