<?php

/**
 * Calculates task schedule dates
 */
class CRM_Sqltasks_TaskSchedule {

  /**
   * Start schedule date
   *
   * @var DateTime
   */
  private $startDate;

  /**
   * Schedule frequency
   *
   * @var string
   */
  private $frequency;

  /**
   * Schedule month
   *
   * @var int
   */
  private $month;

  /**
   * Schedule weekday
   *
   * @var int
   */
  private $weekday;

  /**
   * Schedule day
   *
   * @var int
   */
  private $day;

  /**
   * Schedule hour
   *
   * @var int
   */
  private $hour;

  /**
   * Schedule minute
   *
   * @var int
   */
  private $minute;

  /**
   * Current time
   *
   * @var DateTime
   */
  private $now;

  /**
   * Error/info messages
   *
   * @var array
   */
  private $messages;

  /**
   * CRM_Sqltasks_TaskSchedule constructor.
   *
   * @param string $frequency
   * @param int $month
   * @param int $weekday
   * @param int $day
   * @param int $hour
   * @param int $minute
   * @param string $startDate
   * @throws Exception
   */
  public function __construct($frequency, $month = 0, $weekday = null, $day = 0, $hour = 0, $minute = 0, $startDate = null) {
    if (!in_array($frequency, CRM_Sqltasks_Task::getAvailableFrequencies())) {
      throw new API_Exception('Invalid frequency(' . $frequency . ').');
    }
    $this->frequency = $frequency;

    if (!in_array((int) $month, [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12])) {
      throw new API_Exception('Invalid month(' . $month . ').');
    }
    $this->month = (int) $month;

    if (!in_array((int) $weekday, [1, 2, 3, 4, 5, 6, 7])) {
      throw new API_Exception('Invalid weekday(' . $weekday . ').');
    }
    $this->weekday = (int) $weekday;

    $availableDays = [];
    for ($i = 1; $i <= 31; $i++) {
      $availableDays[] = $i;
    }
    if (!in_array((int) $day, $availableDays)) {
      throw new API_Exception('Invalid month(' . $day . ').');
    }
    $this->day = (int) $day;

    $availableHour = [];
    for ($i = 0; $i <= 24; $i++) {
      $availableHour[] = $i;
    }
    if (!in_array((int) $hour, $availableHour)) {
      throw new API_Exception('Invalid month(' . $hour . ').');
    }
    $this->hour = (int) $hour;

    $availableMinute = [];
    for ($i = 0; $i <= 60; $i++) {
      $availableMinute[] = $i;
    }
    if (!in_array((int) $minute, $availableMinute)) {
      throw new API_Exception('Invalid month(' . $minute . ').');
    }
    $this->minute = (int) $minute;
    $this->now = new DateTime();
    $this->setStartDate($startDate);
  }

  /**
   * Sets start schedule date
   *
   * @param $startDate
   * @throws Exception
   */
  public function setStartDate($startDate) {
    if (is_null($startDate)) {
      $this->startDate = $this->now ;
    } elseif($startDate == '') {
      $this->addInfoMessage('Schedule start date is empty. Task schedule generates form now time.');
      $this->startDate = $this->now;
    } else {
      try {
        $this->startDate = new DateTime($startDate);
      } catch (Exception $e) {
        throw new API_Exception('Invalid start schedule date(' . $startDate . '). Error message: ' . $e->getMessage());
      }

      if ($this->startDate < $this->now) {
        $this->addInfoMessage('"schedule start date" is older then now. Task schedule generates form now time.');
        $this->startDate = $this->now;
      }
    }
  }

  /**
   * Get task schedule by task id
   *
   * @param $taskId
   * @return CRM_Sqltasks_TaskSchedule
   * @throws Exception
   */
  public static function getScheduleForTaskId($taskId) {
    $task = CRM_Sqltasks_Task::getTask($taskId);
    if (empty($task)) {
      throw new API_Exception('Task(id=' . $taskId . ') does not exist.');
    }

    return self::getScheduleForTask($task);
  }

  /**
   * Get task schedule by task object
   *
   * @param $task CRM_Sqltasks_Task
   * @return CRM_Sqltasks_TaskSchedule
   * @throws Exception
   */
  public static function getScheduleForTask(CRM_Sqltasks_Task $task) {
    $config = $task->getConfiguration();

    return new self(
      $task->getAttribute('scheduled'),
      $config['scheduled_month'],
      $config['scheduled_weekday'],
      $config['scheduled_day'],
      $config['scheduled_hour'],
      $config['scheduled_minute'],
      $task->getAttribute('schedule_start_date')
    );
  }

  /**
   * Calculates the next execution date
   * When the job will be executed, on the first cron call after this date
   *
   * @return DateTime
   * @throws Exception
   */
  public function getNextExecutionDate() {
    $startYear = (int) $this->startDate->format('Y');
    $startMonth = (int) $this->startDate->format('m');
    $startWeek = (int) $this->startDate->format('W');
    $startDay = (int) $this->startDate->format('d');
    $startHour = (int) $this->startDate->format('H');

    $nexExecutionDate = new DateTime();
    switch ($this->frequency) {
      case 'always':
        $nexExecutionDate = $this->startDate;
        break;

      case 'hourly':
        $nexExecutionDate->setDate($startYear, $startMonth, $startDay);
        $nexExecutionDate->setTime($startHour, $this->minute, 0);

        if ($nexExecutionDate < $this->now) {
          $nexExecutionDate->modify('+1 hour');
        }
        break;

      case 'daily':
        $nexExecutionDate->setDate($startYear, $startMonth, $startDay);
        $nexExecutionDate->setTime($this->hour, $this->minute, 0);

        if ($nexExecutionDate < $this->now) {
          $nexExecutionDate->modify('+1 day');
        }
        break;

      case 'weekly':
        $nexExecutionDate->setISODate($startYear, $startWeek, $this->weekday);
        $nexExecutionDate->setTime($this->hour, $this->minute, 0);

        if ($nexExecutionDate < $this->now) {
          $nexExecutionDate->modify('+1 week');
        }
        break;

      case 'monthly':
        $nexExecutionDate->setDate($startYear, $startMonth, $this->day);
        $nexExecutionDate->setTime($this->hour, $this->minute, 0);

        if ($nexExecutionDate < $this->now) {
          $nexExecutionDate->modify('+1 month');
        }
        break;

      case 'yearly':
        $nexExecutionDate->setDate($startYear, $this->month, $this->day);
        $nexExecutionDate->setTime($this->hour, $this->minute, 0);

        if ($nexExecutionDate < $this->now) {
          $nexExecutionDate->modify('+1 year');
        }
        break;
      default:
    }

    return $nexExecutionDate;
  }

  /**
   * Get execution task schedule
   *
   * @param int $iterationCount
   * @return array
   * @throws Exception
   */
  public function getSchedule($iterationCount = 3) {
    $schedule = [];
    $nexExecutionDate = $this->getNextExecutionDate();

    if ($this->frequency == 'always') {
      if ($this->startDate > $this->now) {
        $this->addInfoMessage('Frequency is "always". After "schedule start date" task will executed The task will be executed each time when CiviCRM cron is running.');
      } else {
        $this->addInfoMessage('Frequency is "always". The task will be executed each time when CiviCRM cron is running.');
      }
    }

    for ($i = 1; $i <= $iterationCount; $i++) {
      $executionDate = $nexExecutionDate->format('Y-m-d H:i:s');
      $schedule[] = [
        'iteration' => $i,
        'execution_date' => $executionDate,
        'execution_date_formatted' => CRM_Utils_Date::customFormat($executionDate),
        'execute_in_time' => $this->generateInTime($nexExecutionDate),
      ];

      switch ($this->frequency) {
        case 'hourly':
          $nexExecutionDate->modify('+1 hour');
          break;

        case 'daily':
          $nexExecutionDate->modify('+1 day');
          break;

        case 'weekly':
          $nexExecutionDate->modify('+1 week');
          break;

        case 'monthly':
          $nexExecutionDate->modify('+1 month');
          break;

        case 'yearly':
          $nexExecutionDate->modify('+1 year');
          break;
        default:
      }
    }

    return $schedule;
  }

  /**
   * Generates in time
   *
   * @param DateTime $nexExecutionDate
   * @return string
   */
  private function generateInTime(DateTime $nexExecutionDate) {
    $format = '';
    $interval = date_diff($this->now, $nexExecutionDate);

    if (!empty($interval->format('%y')))  {
      $format .= ' %y years';
    }

    if (!empty($interval->format('%m')))  {
      $format .= ' %m months';
    }

    if (!empty($interval->format('%d')))  {
      $format .= ' %d days';
    }

    if (!empty($interval->format('%h')))  {
      $format .= ' %h h';
    }

    if (!empty($interval->format('%i')))  {
      $format .= ' %i min';
    }

    if (!empty($interval->format('%s')))  {
      $format .= ' %s s';
    }

    return (empty($format)) ? ' - ' : $interval->format($format);
  }

  /**
   * Gets error/info messages generated while preparing schedule
   *
   * @return array
   */
  public function getScheduleMessages() {
    return $this->messages;
  }

  /**
   * Cleans messages
   *
   * @return array
   */
  public function cleanMessages() {
    return $this->messages = [];
  }

  /**
   * Add error message
   *
   * @param $message
   */
  public function addErrorMessage($message) {
    $this->messages[] = [
      'type' => 'error',
      'content' => $message,
    ];
  }

  /**
   * Add info message
   *
   * @param $message
   */
  public function addInfoMessage($message) {
    $this->messages[] = [
      'type' => 'info',
      'content' => $message,
    ];
  }

}
