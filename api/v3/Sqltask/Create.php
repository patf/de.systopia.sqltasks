<?php

/**
 * Create or update a task
 *
 * @param $params
 *
 * @return array
 * @throws \Exception
 */
function civicrm_api3_sqltask_create($params) {
  $taskParamNames = [
    'name', 'description', 'category', 'scheduled', 'parallel_exec',
    'input_required','enabled', 'weight', 'run_permissions', 'abort_on_error', 'schedule_start_date'
  ];

  $booleanParams = ['input_required', 'enabled', 'abort_on_error'];
  foreach ($booleanParams as $booleanParam) {
    if (array_key_exists($booleanParam, $params) && !($params[$booleanParam] == '1' || $params[$booleanParam] == '0')) {
      return civicrm_api3_create_error('Field \'' . $booleanParam . '\' must be \'0\' or \'1\'.');
    }
  }

  $taskParams = [];

  foreach ($taskParamNames as $name) {
    if (array_key_exists($name, $params)) {
      $taskParams[$name] = $params[$name];
    }
  }

  //validate config field:
  if (isset($params['config']) && !is_array($params['config'])) {
    return civicrm_api3_create_error('Config must be array type.');
  }

  if (isset($params['config'])) {
    $requiredConfigFields = ['actions'];
    $configNotExistFields = [];
    foreach ($requiredConfigFields as $field) {
      if (!isset($params['config'][$field])) {
        $configNotExistFields[] = $field;
      }
    }
  }

  if (!empty($configNotExistFields)) {
    return civicrm_api3_create_error('Config error!. Required fields: ' . implode(', ', $configNotExistFields));
  }

  if (empty($params['id'])) {
    $newParams = $taskParams;
    if (array_key_exists('config', $params)) {
      $newParams += $params['config'];
    }
    $task = new CRM_Sqltasks_Task($params['id'], $newParams);
    $task->store();
  } else {
    $task = CRM_Sqltasks_Task::getTask($params['id']);
    if (empty($task)) {
      return civicrm_api3_create_error('Task(id=' . $params['id'] . ') does not exist.');
    }

    if ($task->isArchived()) {
      return civicrm_api3_create_error('Task(id=' . $params['id'] . ') is archived. Can not update any fields. To update any fields please unarchive the task.');
    }

    // Compare last_modified timestamps to prevent unintended concurrent changes
    if (
      !empty($params["last_modified"])
      && !empty($task->getAttribute("last_modified"))
      && $params["last_modified"] !== $task->getAttribute("last_modified")
    ) {
      $lastModifiedFormatted = date("H:i:s, j M Y", strtotime($task->getAttribute("last_modified")));

      return civicrm_api3_create_error(
        "This task has been modified by another user at {$lastModifiedFormatted}",
        [ "error_type" => "CONCURRENT_CHANGES" ]
      );
    }

    foreach ($taskParams as $name => $value) {
      $task->setAttribute($name, $value, TRUE);
    }

    if (isset($params['config'])) {
      $task->setConfiguration($params['config'], TRUE);
    }
  }

  return civicrm_api3_create_success($task->getPreparedTask());
}

/**
 * This is used for documentation and validation.
 *
 * @param array $params description of fields supported by this API call
 * @return void
 * @see http://wiki.civicrm.org/confluence/display/CRMDOC/API+Architecture+Standards
 */
function _civicrm_api3_sqltask_create_spec(&$params) {
  $params['id'] = [
    'name'         => 'id',
    'api.required' => 0,
    'api.aliases'  => ['task_id'],
    'type'         => CRM_Utils_Type::T_INT,
    'title'        => 'Task ID',
    'description'  => 'Unique task ID',
  ];

  $params['name'] = [
    'name'         => 'name',
    'api.required' => 0,
    'type'         => CRM_Utils_Type::T_STRING,
    'title'        => 'Name',
  ];

  $params['description'] = [
    'name'         => 'description',
    'api.required' => 0,
    'type'         => CRM_Utils_Type::T_STRING,
    'title'        => 'Description',
  ];

  $params['run_permissions'] = [
    'name'         => 'run_permissions',
    'api.required' => 0,
    'type'         => CRM_Utils_Type::T_STRING,
    'title'        => 'Run permissions',
  ];

  $params['category'] = [
    'name'         => 'category',
    'api.required' => 0,
    'type'         => CRM_Utils_Type::T_STRING,
    'title'        => 'Category',
  ];

  $params['weight'] = [
    'name'         => 'weight',
    'api.required' => 0,
    'type'         => CRM_Utils_Type::T_INT,
    'title'        => 'Weight',
    'description'  => 'Determines the order in which tasks are executed (lower is executed earlier)'
  ];

  $params['scheduled'] = [
    'name'         => 'scheduled',
    'api.required' => 0,
    'type'         => CRM_Utils_Type::T_STRING,
    'title'        => 'Schedule',
    'description'  => 'Frequency at which the task should be executed by cron',
    'options'      => [
      'always'  => 'always',
      'hourly'  => 'hourly',
      'daily'   => 'daily',
      'weekly'  => 'weekly',
      'monthly' => 'monthly',
      'yearly'  => 'yearly',
    ],
  ];

  $params['parallel_exec'] = [
    'name'         => 'parallel_exec',
    'api.required' => 0,
    'type'         => CRM_Utils_Type::T_INT,
    'title'        => 'Allow parallel execution?',
    'description'  => 'Whether to allow multiple instances of this task to run at the same time',
  ];

  $params['input_required'] = [
    'name'         => 'input_required',
    'api.required' => 0,
    'type'         => CRM_Utils_Type::T_BOOLEAN,
    'title'        => 'Require user input?',
    'description'  => 'Whether this task requires user input prior to execution',
  ];

  $params['enabled'] = [
    'name'         => 'enabled',
    'api.required' => 0,
    'type'         => CRM_Utils_Type::T_BOOLEAN,
    'title'        => 'Enable task?',
    'description'  => 'Whether to enable task execution by cron according to schedule',
  ];

  $params['config'] = [
    'name'         => 'config',
    'api.required' => 0,
    'type'         => CRM_Utils_Type::T_TEXT,
    'title'        => 'Configuration',
    'description'  => 'Task configuration, including actions, as an array',
  ];

  $params['abort_on_error'] = [
    'name'         => 'abort_on_error',
    'api.required' => 0,
    'type'         => CRM_Utils_Type::T_BOOLEAN,
    'title'        => 'Abort task execution on error?',
    'description'  => 'Whether this task should stop execution if an action produces an error',
  ];

  $params['last_modified'] = [
    'name'         => 'last_modified',
    'api.required' => 0,
    'type'         => CRM_Utils_Type::T_STRING,
    'title'        => 'Last Modification Date',
    'description'  => 'Date/Time of the last configuration change',
  ];

  $params['schedule_start_date'] = [
    'name'         => 'schedule_start_date',
    'api.required' => 0,
    'type'         => CRM_Utils_Type::T_STRING,
    'title'        => 'Schedule start date',
  ];
}
