<?php

use CRM_Sqltasks_ExtensionUtil as E;
use Civi\Test\HeadlessInterface;
use Civi\Test\HookInterface;
use Civi\Test\TransactionalInterface;

/**
 * Test ResultHandler Action
 *
 * @group headless
 */
class CRM_Sqltasks_Action_ResultHandlerTest extends \PHPUnit_Framework_TestCase implements HeadlessInterface, HookInterface {

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

  public function testSuccessHandler() {
    CRM_Core_DAO::executeQuery('DROP TABLE IF EXISTS tmp_test_action_successhandler');
    CRM_Core_DAO::executeQuery("DELETE FROM civicrm_activity WHERE subject = 'testSuccessHandler'");
    $mailUtils = new CiviMailUtils($this, TRUE);
    $data = [
      'name' => 'testSuccessHandler',
      'description' => 'Test Task Description',
      'category' => 'Test Task Category',
      'scheduled' => 'monthly',
      'parallel_exec' => 0,
      'main_sql' => '',
      'post_sql' => '',
      'sql_enabled' => '1',
      'sql_script' => 'CREATE TABLE tmp_test_action_successhandler AS SELECT 1 AS contact_id',
      'activity_enabled' => '1',
      'activity_contact_table' => 'tmp_test_action_successhandler',
      'activity_activity_type_id' => '3',
      'activity_status_id' => '2',
      'activity_subject' => 'testSuccessHandler',
      'activity_details' => '',
      'activity_activity_date_time' => '',
      'activity_campaign_id' => '0',
      'activity_source_contact_id' => '1',
      'activity_assigned_to' => '',
      'success_enabled' => '1',
      'success_table' => '',
      'success_email' => 'successhandler@example.com',
      'success_email_template' => '1',
    ];
    $task = new CRM_Sqltasks_Task(NULL, $data);
    $task->store();
    $log = $task->execute();

    $this->assertStringStartsWith("Action 'Run SQL' executed in", $log[1]);
    $this->assertStringStartsWith("Action 'Create Activity' executed in", $log[2]);
    $mailUtils->checkMailLog([
      'successhandler@example.com'
    ]);
    $mailUtils->stop();
  }

  public function testErrorHandler() {
    CRM_Core_DAO::executeQuery('DROP TABLE IF EXISTS tmp_test_action_errorhandler');
    CRM_Core_DAO::executeQuery("DELETE FROM civicrm_activity WHERE subject = 'testSuccessHandler'");
    $mailUtils = new CiviMailUtils($this, TRUE);
    $data = [
      'name' => 'testErrorHandler',
      'description' => 'Test Task Description',
      'category' => 'Test Task Category',
      'scheduled' => 'monthly',
      'parallel_exec' => 0,
      'main_sql' => '',
      'post_sql' => '',
      'sql_enabled' => '1',
      'sql_script' => 'CREATE TABLE tmp_test_action_errorhandler AS SELECT 1 AS contact_id',
      'activity_enabled' => '1',
      'activity_contact_table' => 'tmp_test_action_errorhandler',
      'activity_activity_type_id' => '999999', // this should cause an error
      'activity_status_id' => '2',
      'activity_subject' => 'testErrorHandler',
      'activity_details' => '',
      'activity_activity_date_time' => '',
      'activity_campaign_id' => '0',
      'activity_source_contact_id' => '1',
      'activity_assigned_to' => '',
      'error_enabled' => '1',
      'error_table' => '',
      'error_email' => 'errorhandler@example.com',
      'error_email_template' => '1',
    ];
    $task = new CRM_Sqltasks_Task(NULL, $data);
    $task->store();
    $log = $task->execute();

    $this->assertStringStartsWith("Action 'Run SQL' executed in", $log[1]);
    $this->assertStringStartsWith("Error in action 'Create Activity': '999999' is not a valid option for field activity_type_id", $log[2]);
    $mailUtils->checkMailLog([
      'errorhandler@example.com'
    ]);
    $mailUtils->stop();
  }

}
