<?php

/**
 * Get test list of schedule dates based on task values
 *
 * @param $params
 *
 * @return array
 */
function civicrm_api3_sqltask_get_schedule_dates($params) {
  $taskSchedule = CRM_Sqltasks_TaskSchedule::getScheduleForTaskId($params['task_id']);

  // Override 'start_schedule_date' which is got from task to custom value to calculate schedule.
  // Uses when you need to calculate schedule based on different 'start_schedule_date'
  // but other params is got from task.
  if (isset($params['planning_start_schedule_date'])) {
    $taskSchedule->cleanMessages();
    $taskSchedule->setStartDate($params['planning_start_schedule_date']);
  }

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
function _civicrm_api3_sqltask_get_schedule_dates_spec(&$params) {
  $params['task_id'] = [
    'name' => 'task_id',
    'api.required' => 1,
    'type' => CRM_Utils_Type::T_INT,
    'title' => 'Task ID',
    'description' => 'Task ID',
  ];
  $params['iteration_count'] = [
    'name' => 'iteration_count',
    'api.required' => 0,
    'type' => CRM_Utils_Type::T_INT,
    'title' => 'Iteration count',
  ];
  $params['planning_start_schedule_date'] = [
    'name' => 'planning_start_schedule_date',
    'api.required' => 0,
    'type' => CRM_Utils_Type::T_STRING,
    'title' => 'Planning start schedule date',
    'description' => 'Use this param to calculate task planning schedule, if this param is empty "start schedule date" gets from task.',
  ];
}
