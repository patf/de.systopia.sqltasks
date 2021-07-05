<?php

/**
 * Get planning list of schedule dates based on entered dates params
 *
 * @param $params
 *
 * @return array
 */
function civicrm_api3_sqltask_get_planning_schedule_dates($params) {
  $taskSchedule = new CRM_Sqltasks_TaskSchedule(
    $params['schedule_frequency'],
    isset($params['schedule_month']) ? $params['schedule_month'] : null,
    isset($params['schedule_weekday']) ? $params['schedule_weekday'] : null,
    isset($params['schedule_day']) ? $params['schedule_day'] : null,
    isset($params['schedule_hour']) ? $params['schedule_hour'] : null,
    isset($params['schedule_minute']) ? $params['schedule_minute'] : null,
    isset($params['schedule_start_date']) ? $params['schedule_start_date'] : null,
    isset($params['last_execution']) ? $params['last_execution'] : null
  );

  if (!empty($params['iteration_count'])) {
    return civicrm_api3_create_success([
      'schedule' => $taskSchedule->getSchedule($params['iteration_count']),
      'messages' => $taskSchedule->getScheduleMessages(),
    ]);
  }

  return civicrm_api3_create_success([
    'schedule' => $taskSchedule->getSchedule(),
    'messages' => $taskSchedule->getScheduleMessages(),
  ]);
}

/**
 * This is used for documentation and validation.
 *
 * @param array $params description of fields supported by this API call
 * @return void
 * @see http://wiki.civicrm.org/confluence/display/CRMDOC/API+Architecture+Standards
 */
function _civicrm_api3_sqltask_get_planning_schedule_dates_spec(&$params) {
  $params['schedule_frequency'] = [
    'name' => 'schedule_frequency',
    'api.required' => 1,
    'type' => CRM_Utils_Type::T_STRING,
    'title' => 'Schedule frequency',
  ];
  $params['schedule_month'] = [
    'name' => 'schedule_month',
    'api.required' => 0,
    'type' => CRM_Utils_Type::T_INT,
    'title' => 'Schedule month',
  ];
  $params['schedule_weekday'] = [
    'name' => 'schedule_weekday',
    'api.required' => 0,
    'type' => CRM_Utils_Type::T_INT,
    'title' => 'Schedule weekday',
  ];
  $params['schedule_day'] = [
    'name' => 'schedule_day',
    'api.required' => 0,
    'type' => CRM_Utils_Type::T_INT,
    'title' => 'Schedule day',
  ];
  $params['schedule_hour'] = [
    'name' => 'schedule_hour',
    'api.required' => 0,
    'type' => CRM_Utils_Type::T_INT,
    'title' => 'Schedule hour',
  ];
  $params['schedule_minute'] = [
    'name' => 'schedule_minute',
    'api.required' => 0,
    'type' => CRM_Utils_Type::T_INT,
    'title' => 'Schedule minute',
  ];
  $params['schedule_start_date'] = [
    'name' => 'schedule_start_date',
    'api.required' => 0,
    'type' => CRM_Utils_Type::T_STRING,
    'title' => 'Schedule start date',
  ];
  $params['last_execution'] = [
    'name' => 'last_execution',
    'api.required' => 0,
    'type' => CRM_Utils_Type::T_STRING,
    'title' => 'Last execution date',
  ];
  $params['iteration_count'] = [
    'name' => 'iteration_count',
    'api.required' => 0,
    'type' => CRM_Utils_Type::T_INT,
    'title' => 'Iteration count',
  ];
}
