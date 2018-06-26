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
class CRM_Sqltasks_Action_RunSQLTest extends \PHPUnit_Framework_TestCase implements HeadlessInterface, HookInterface {

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

  public function testRunSQL() {
    CRM_Core_DAO::executeQuery('DROP TABLE IF EXISTS tmp_test_action_runsql');
    $data = [
      'name' => 'testRunSQL',
      'description' => 'Test Task Description',
      'category' => 'Test Task Category',
      'scheduled' => 'monthly',
      'parallel_exec' => 0,
      'main_sql' => '',
      'post_sql' => '',
      'sql_enabled' => '1',
      'sql_script' => 'CREATE TABLE tmp_test_action_runsql AS SELECT 1 AS contact_id',
    ];
    $task = new CRM_Sqltasks_Task(NULL, $data);
    $task->store();
    $log = $task->execute();

    $this->assertStringStartsWith("Action 'Run SQL' executed in", $log[1]);
    $executed = CRM_Core_DAO::singleValueQuery("SELECT COUNT(*) FROM tmp_test_action_runsql");
    $this->assertEquals(1, $executed, 'Table and row from SQL script should have been created');
  }

}
