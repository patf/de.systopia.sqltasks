<?php


/**
 * Configuration upgrader for converting from format 1 to 2
 */
class CRM_Sqltasks_Upgrader_Config_V1 {
  protected $_config;

  public function __construct($config) {
    $this->_config = $config;
    // V1 did not have an explicit version field
    if (array_key_exists('version', $this->_config)) {
      throw new Exception( 'Found version attribute in SQLTasks V1 configuration');
    }
  }

  public function convert() {
    // available config prefixes, using fixed order from V1
    $prefixToTypeList = [
      'segmentation_assign' => 'SegmentationAssign',
      'activity' => 'CreateActivity',
      'api' => 'APICall',
      'csv' => 'CSVExport',
      'tag' => 'SyncTag',
      'group' => 'SyncGroup',
      'segmentation_export' => 'SegmentationExport',
      'task' => 'CallTask',
      'sql' => 'RunSQL',
      'success' => 'SuccessHandler',
      'error' => 'ErrorHandler',
    ];

    /* V2 sample:
      {
        "version": 2,
        "actions": [
          {
            "type": "CreateActivity",
            "enabled": true,
            ...
          }
        ]
      }
    */
    $newConfig = ['version' => 2, 'actions' => []];

    // iterate over all prefixes
    foreach ($prefixToTypeList as $prefix => $type) {
      $action = NULL;
      // iterate over all config keys and copy those starting with the prefix
      foreach ($this->_config as $key => $value) {
        if (strpos($key, $prefix . '_') !== FALSE) {
          $itemName = str_replace($prefix . '_', '', $key);
          if (is_null($action)) {
            $action = ['type' => $type];
          }
          $action[$itemName] = $value;
        }
      }
      if (!is_null($action)) {
        $newConfig['actions'][] = $action;
      }
    }

    return $newConfig;
  }

  public function convertFromExport($exportedConfig) {
    $exportedConfig['config'] = $this->convert();
    if (!empty($exportedConfig['main_sql'])) {
      array_unshift($exportedConfig['config']['actions'], [
        'type' => 'RunSQL',
        'script' => $exportedConfig['main_sql'],
      ]);
    }
    unset($exportedConfig['main_sql']);

    if (!empty($exportedConfig['post_sql'])) {
      array_push($exportedConfig['config']['actions'], [
        'type' => 'RunSQL',
        'script' => $exportedConfig['post_sql'],
      ]);
    }
    unset($exportedConfig['post_sql']);


    return $exportedConfig;
  }

  public function convertTask($task) {
    $newConfig = $this->convert($task->getConfiguration());
    array_unshift($newConfig['actions'], [
      'type' => 'RunSQL',
      'script' => $task->getAttribute('main_sql'),
    ]);
    array_push($newConfig['actions'], [
      'type' => 'RunSQL',
      'script' => $task->getAttribute('post_sql'),
    ]);
    $task->setConfiguration($newConfig);
    $task->store();
  }
}