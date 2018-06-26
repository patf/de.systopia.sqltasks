<?php
/*-------------------------------------------------------+
| SYSTOPIA SQL TASKS EXTENSION                           |
| Copyright (C) 2018 SYSTOPIA                            |
| Author: B. Endres (endres@systopia.de)                 |
+--------------------------------------------------------+
| This program is released as free software under the    |
| Affero GPL license. You can redistribute it and/or     |
| modify it under the terms of this license which you    |
| can read by viewing the included agpl.txt or online    |
| at www.gnu.org/licenses/agpl.html. Removal of this     |
| copyright header is strictly prohibited without        |
| written permission from the original author(s).        |
+--------------------------------------------------------*/

use CRM_Sqltasks_ExtensionUtil as E;

/**
 * This actions allows you to run arbitrary SQL statements
 *
 */
class CRM_Sqltasks_Action_RunSQL extends CRM_Sqltasks_Action {

  /**
   * Get identifier string
   */
  public function getID() {
    return 'sql'; //'0';
  }

  /**
   * Get a human readable name
   */
  public function getName() {
    return E::ts('Run SQL');
  }

  public function getDefaultOrder() {
    return 100;
  }

  /**
   * Build the configuration UI
   */
  public function buildForm(&$form) {
    parent::buildForm($form);

    $form->add(
      'hidden',
      "actions[{$this->getID()}][type]",
      'RunSQL'
    );

    $form->add(
      'textarea',
      $this->getID() . '_script',// "actions[{$this->getID()}][script]",
      E::ts('SQL Script'),
      array('rows' => 8, 'cols' => 60),
      TRUE
    );
  }


  /**
   * Check if this action is configured correctly
   */
  public function checkConfiguration() {
    parent::checkConfiguration();
    $entity = $this->getConfigValue('script');
    if (empty($entity)) {
      throw new Exception("SQL Script not provided", 1);
    }
  }

  /**
   * RUN this action
   */
  public function execute() {
    // has_executed is always false for RunSQL
    $this->resetHasExecuted();
    //$this->log('hi');
    try {
      // prepare
      $config = CRM_Core_Config::singleton();
      $script = html_entity_decode($this->getConfigValue('script'));

      // run the whole script (see CRM-20428 and
      //   https://github.com/systopia/de.systopia.sqltasks/issues/2)
      if (version_compare(CRM_Utils_System::version(), '4.7.20', '<')) {
        CRM_Utils_File::sourceSQLFile($config->dsn, $script, NULL, TRUE);
      }
      else {
        CRM_Utils_File::runSqlQuery($config->dsn, $script);
      }
    }
    catch (Exception $e) {
      $this->error_count += 1;
      $this->log("SQL statement failed: " . $e->getMessage());
    }
  }

}
