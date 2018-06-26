<?php

use CRM_Sqltasks_ExtensionUtil as E;
use Civi\Test\HeadlessInterface;
use Civi\Test\HookInterface;
use Civi\Test\TransactionalInterface;

/**
 * Tests overall task logic
 *
 * @group headless
 */
class CRM_Sqltasks_TaskTest extends \PHPUnit_Framework_TestCase implements HeadlessInterface, HookInterface {

  public function setUpHeadless() {
    return \Civi\Test::headless()
      ->installMe(__DIR__)
      ->apply();
  }

  public function setUp() {
    CRM_Core_DAO::executeQuery('TRUNCATE TABLE civicrm_sqltasks');
    parent::setUp();
  }

  public function tearDown() {
    parent::tearDown();
  }

  public function testCreateTask() {
    $data = [
      'name' => 'testCreateTask',
      'description' => 'Test Task Description',
      'category' => 'Test Task Category',
      'scheduled' => 'monthly',
      'parallel_exec' => 0,
      'main_sql' => 'CREATE TABLE IF NOT EXISTS tmp_test_task AS SELECT 1 AS contact_id',
      'post_sql' => 'DROP TABLE IF EXISTS tmp_test_task',
    ];
    $task = new CRM_Sqltasks_Task(NULL, $data);
    $task->store();
    $query = CRM_Core_DAO::executeQuery("SELECT * FROM civicrm_sqltasks WHERE name = 'testCreateTask'");
    $query->fetch();
    $this->assertEquals('testCreateTask', $query->name);
    $this->assertEquals('Test Task Description', $query->description);
    $this->assertEquals('Test Task Category', $query->category);
    $this->assertEquals('monthly', $query->scheduled);
    $this->assertEquals(0, $query->parallel_exec);
    $this->assertEquals('CREATE TABLE IF NOT EXISTS tmp_test_task AS SELECT 1 AS contact_id', $query->main_sql);
    $this->assertEquals('DROP TABLE IF EXISTS tmp_test_task', $query->post_sql);
  }

  public function testUpdateTask() {
    $data = [
      'name' => 'testUpdateTask',
      'description' => 'Test Task Description',
      'category' => 'Test Task Category',
      'scheduled' => 'monthly',
      'parallel_exec' => 0,
      'main_sql' => 'CREATE TABLE IF NOT EXISTS tmp_test_task AS SELECT 1 AS contact_id',
      'post_sql' => 'DROP TABLE IF EXISTS tmp_test_task',
    ];
    $task = new CRM_Sqltasks_Task(NULL, $data);
    $task->store();
    $taskId = CRM_Core_DAO::singleValueQuery("SELECT id FROM civicrm_sqltasks WHERE name = 'testUpdateTask'");
    $data = [
      'name' => 'testUpdateTask2',
      'description' => 'Test Task Description 2',
      'category' => 'Test Task Category 2',
      'scheduled' => 'daily',
      'parallel_exec' => 1,
      'main_sql' => 'CREATE TABLE IF NOT EXISTS tmp_test_task_2 AS SELECT 1 AS contact_id',
      'post_sql' => 'DROP TABLE IF EXISTS tmp_test_task_2',
    ];
    $task = new CRM_Sqltasks_Task($taskId, $data);
    $task->store();
    $query = CRM_Core_DAO::executeQuery("SELECT * FROM civicrm_sqltasks WHERE name = 'testUpdateTask2'");
    $query->fetch();
    $this->assertEquals('testUpdateTask2', $query->name);
    $this->assertEquals('Test Task Description 2', $query->description);
    $this->assertEquals('Test Task Category 2', $query->category);
    $this->assertEquals('daily', $query->scheduled);
    $this->assertEquals(1, $query->parallel_exec);
    $this->assertEquals('CREATE TABLE IF NOT EXISTS tmp_test_task_2 AS SELECT 1 AS contact_id', $query->main_sql);
    $this->assertEquals('DROP TABLE IF EXISTS tmp_test_task_2', $query->post_sql);
  }

  public function testExecuteTask() {
    CRM_Core_DAO::executeQuery('DROP TABLE IF EXISTS tmp_test_execute');
    CRM_Core_DAO::executeQuery('DROP TABLE IF EXISTS tmp_test_execute_post');

    $data = [
      'name' => 'testExecuteTask',
      'description' => 'Test Task Description',
      'category' => 'Test Task Category',
      'scheduled' => 'monthly',
      'parallel_exec' => 0,
      'main_sql' => 'CREATE TABLE tmp_test_execute AS SELECT 1 AS contact_id',
      'post_sql' => 'CREATE TABLE tmp_test_execute_post AS SELECT 1 AS contact_id',
    ];

    $task = new CRM_Sqltasks_Task(NULL, $data);
    $task->store();
    $log = $task->execute();
    $this->assertStringStartsWith("Script 'Main SQL' executed in", $log[0]);
    $this->assertStringStartsWith("Script 'Post SQL' executed in", $log[1]);
    $query = CRM_Core_DAO::executeQuery("SELECT * FROM civicrm_sqltasks WHERE name = 'testExecuteTask'");
    $query->fetch();
    $executed = CRM_Core_DAO::singleValueQuery("SELECT COUNT(*) FROM tmp_test_execute");
    $this->assertEquals(1, $executed, 'Table and row from Main SQL should have been created');
    $executed = CRM_Core_DAO::singleValueQuery("SELECT COUNT(*) FROM tmp_test_execute_post");
    $this->assertEquals(1, $executed, 'Table and row from Post SQL should have been created');
    $this->assertGreaterThan(0, $query->last_runtime);
    $this->assertNotEmpty($query->last_execution, 'Task last execution time should have been set');
  }

  public function testFailTask() {
    $data = [
      'name' => 'testFailTask',
      'description' => 'Test Task Description',
      'category' => 'Test Task Category',
      'scheduled' => 'monthly',
      'parallel_exec' => 0,
      'main_sql' => 'totally valid SQL',
      'post_sql' => 'also valid',
    ];

    $task = new CRM_Sqltasks_Task(NULL, $data);
    $task->store();
    $log = $task->execute();

    $this->assertStringStartsWith("Script 'Main SQL' failed", $log[0]);
    $this->assertStringStartsWith("Script 'Post SQL' failed", $log[1]);
  }

}
