<?php

use CRM_Sqltasks_ExtensionUtil as E;
use Civi\Test\HeadlessInterface;
use Civi\Test\HookInterface;
use Civi\Test\TransactionalInterface;

/**
 * Test RunSQL Action
 *
 * @group headless
 */
class CRM_Sqltasks_Action_CallTaskTest extends \PHPUnit_Framework_TestCase implements HeadlessInterface, HookInterface {

  public function setUpHeadless() {
    return \Civi\Test::headless()
      ->installMe(__DIR__)
      ->apply();
  }

  public function setUp() {
    parent::setUp();
  }

  public function tearDown() {
    parent::tearDown();
  }

  public function testCallTask() {
    CRM_Core_DAO::executeQuery('DROP TABLE IF EXISTS tmp_test_action_calltask');
    CRM_Core_DAO::executeQuery('DROP TABLE IF EXISTS tmp_test_action_calltask_called');
    $data = [
      'name' => 'testCallTaskCalled',
      'description' => 'Test Task Description',
      'category' => 'Test Task Category',
      'scheduled' => 'monthly',
      'parallel_exec' => 0,
      'main_sql' => '',
      'post_sql' => '',
      'enabled' => '1',
      'sql_enabled' => '1',
      'sql_script' => 'CREATE TABLE tmp_test_action_calltask_called AS SELECT 1 AS contact_id',
    ];
    $calledTask = new CRM_Sqltasks_Task(NULL, $data);
    $calledTask->store();
    $data = [
      'name' => 'testCallTask',
      'description' => 'Test Task Description',
      'category' => 'Test Task Category',
      'scheduled' => 'monthly',
      'parallel_exec' => 0,
      'main_sql' => '',
      'post_sql' => '',
      'sql_enabled' => '1',
      'sql_script' => 'CREATE TABLE tmp_test_action_calltask AS SELECT 1 AS contact_id',
      'task_enabled' => '1',
      'task_tasks' => [
        $calledTask->getID(),
      ],
      'task_categories' => [],
    ];
    $task = new CRM_Sqltasks_Task(NULL, $data);
    $task->store();
    $log = $task->execute();

    $this->assertStringStartsWith("Action 'Run SQL' executed in", $log[1]);
    $this->assertStringStartsWith("Executed task 'testCallTaskCalled'", $log[2]);
    $this->assertStringStartsWith("Action 'Run SQL Task(s)' executed in", $log[3]);
    $executed = CRM_Core_DAO::singleValueQuery("SELECT COUNT(*) FROM tmp_test_action_calltask");
    $this->assertEquals(1, $executed, 'Table and row from main task SQL script should have been created');
    $executed = CRM_Core_DAO::singleValueQuery("SELECT COUNT(*) FROM tmp_test_action_calltask_called");
    $this->assertEquals(1, $executed, 'Table and row from called task SQL script should have been created');
  }

}
