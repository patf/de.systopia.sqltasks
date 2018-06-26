<?php

use CRM_Sqltasks_ExtensionUtil as E;
use Civi\Test\HeadlessInterface;
use Civi\Test\HookInterface;
use Civi\Test\TransactionalInterface;

/**
 * Test configuration upgrade from V1 to V2
 *
 * @group headless
 */
class CRM_Sqltasks_Upgrader_Config_V1Test extends \PHPUnit_Framework_TestCase implements HeadlessInterface, HookInterface {

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

}