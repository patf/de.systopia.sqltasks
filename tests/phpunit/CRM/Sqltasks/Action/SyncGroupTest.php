<?php

use CRM_Sqltasks_ExtensionUtil as E;
use Civi\Test\HeadlessInterface;
use Civi\Test\HookInterface;
use Civi\Test\TransactionalInterface;

/**
 * Test SyncGroup Action
 *
 * @group headless
 */
class CRM_Sqltasks_Action_SyncGroupTest extends \PHPUnit_Framework_TestCase implements HeadlessInterface, HookInterface {
  use \Civi\Test\Api3TestTrait;

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

  public function testSyncGroup() {
    CRM_Core_DAO::executeQuery('DROP TABLE IF EXISTS tmp_test_action_syncgroup');
    CRM_Core_DAO::executeQuery("DELETE FROM civicrm_group WHERE name = 'testSyncGroup'");
    $groupId = $this->callApiSuccess('Group', 'create', array(
      'sequential' => 1,
      'name' => 'testSyncGroup',
      'title' => 'testSyncGroup',
    ))['id'];
    $data = [
      'name' => 'testSyncGroup',
      'description' => 'Test Task Description',
      'category' => 'Test Task Category',
      'scheduled' => 'monthly',
      'parallel_exec' => 0,
      'main_sql' => 'CREATE TABLE tmp_test_action_syncgroup AS SELECT id as contact_id FROM civicrm_contact LIMIT 1',
      'post_sql' => '',
      'group_enabled' => '1',
      'group_contact_table' => 'tmp_test_action_syncgroup',
      'group_group_id' => $groupId,
    ];
    $task = new CRM_Sqltasks_Task(NULL, $data);
    $task->store();
    $log = $task->execute();

    $this->assertStringStartsWith("Action 'Synchronise Group' executed in", $log[1]);
    $this->assertEquals(
      1,
      CRM_Core_DAO::singleValueQuery("SELECT COUNT(*) FROM civicrm_group_contact WHERE group_id = %0 AND status = 'Added' AND contact_id IN (SELECT contact_id FROM tmp_test_action_syncgroup)", [[$groupId, 'Integer']]),
      'Should have assigned group'
    );
    $this->assertEquals(
      1,
      CRM_Core_DAO::singleValueQuery("SELECT COUNT(*) FROM civicrm_subscription_history WHERE group_id = %0 AND status = 'Added' AND contact_id IN (SELECT contact_id FROM tmp_test_action_syncgroup)", [[$groupId, 'Integer']]),
      'Should have created subscription history'
    );
  }

}
