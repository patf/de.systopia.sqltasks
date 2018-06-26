<?php

use CRM_Sqltasks_ExtensionUtil as E;
use Civi\Test\HeadlessInterface;
use Civi\Test\HookInterface;
use Civi\Test\TransactionalInterface;

/**
 * Test CreateActivity Action
 *
 * @group headless
 */
class CRM_Sqltasks_Action_CreateActivityTest extends \PHPUnit_Framework_TestCase implements HeadlessInterface, HookInterface {

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

  public function testCreateActivity() {
    CRM_Core_DAO::executeQuery('DROP TABLE IF EXISTS tmp_test_action_createactivity');
    CRM_Core_DAO::executeQuery("DELETE FROM civicrm_activity WHERE subject = 'testCreateActivity'");
    $data = [
      'name' => 'testCreateActivity',
      'description' => 'Test Task Description',
      'category' => 'Test Task Category',
      'scheduled' => 'monthly',
      'parallel_exec' => 0,
      'main_sql' => 'CREATE TABLE tmp_test_action_createactivity AS SELECT id as contact_id FROM civicrm_contact LIMIT 1',
      'post_sql' => '',
      'activity_enabled' => '1',
      'activity_contact_table' => 'tmp_test_action_createactivity',
      'activity_activity_type_id' => '3',
      'activity_status_id' => '2',
      'activity_subject' => 'testCreateActivity',
      'activity_details' => '',
      'activity_activity_date_time' => '',
      'activity_campaign_id' => '0',
      'activity_source_contact_id' => '1',
      'activity_assigned_to' => '',
    ];
    $task = new CRM_Sqltasks_Task(NULL, $data);
    $task->store();
    $log = $task->execute();

    $this->assertStringStartsWith("Action 'Create Activity' executed in", $log[1]);
    $this->assertEquals(
      2,
      CRM_Core_DAO::singleValueQuery("SELECT COUNT(*) FROM civicrm_activity JOIN civicrm_activity_contact ON civicrm_activity_contact.activity_id=civicrm_activity.id WHERE subject = 'testCreateActivity' AND contact_id IN (SELECT contact_id FROM tmp_test_action_createactivity)"),
      'Should have created and assigned the activity'
    );
  }

}
