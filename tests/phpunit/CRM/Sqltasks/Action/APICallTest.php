<?php

use CRM_Sqltasks_ExtensionUtil as E;
use Civi\Test\HeadlessInterface;
use Civi\Test\HookInterface;
use Civi\Test\TransactionalInterface;

/**
 * Test APICall Action
 *
 * @group headless
 */
class CRM_Sqltasks_Action_APICallTest extends \PHPUnit_Framework_TestCase implements HeadlessInterface, HookInterface {

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

  public function testAPICall() {
    CRM_Core_DAO::executeQuery('DROP TABLE IF EXISTS tmp_test_action_apicall');
    CRM_Core_DAO::executeQuery("DELETE FROM civicrm_phone WHERE phone = '1800testAPICall'");
    $data = [
      'name' => 'testAPICall',
      'description' => 'Test Task Description',
      'category' => 'Test Task Category',
      'scheduled' => 'monthly',
      'parallel_exec' => 0,
      'main_sql' => 'CREATE TABLE tmp_test_action_apicall AS SELECT id as contact_id FROM civicrm_contact LIMIT 1',
      'post_sql' => '',
      'api_enabled' => '1',
      'api_table' => 'tmp_test_action_apicall',
      'api_entity' => 'Phone',
      'api_action' => 'create',
      'api_parameters' => "contact_id={contact_id}\r\nphone=1800testAPICall",
    ];
    $task = new CRM_Sqltasks_Task(NULL, $data);
    $task->store();
    $log = $task->execute();

    $this->assertEquals('1 API call(s) successfull.', $log[1]);
    $this->assertStringStartsWith("Action 'API Call' executed in", $log[2]);
    $this->assertEquals(
      1,
      CRM_Core_DAO::singleValueQuery("SELECT COUNT(*) FROM civicrm_phone WHERE phone = '1800testAPICall' AND contact_id IN (SELECT contact_id FROM tmp_test_action_apicall)"),
      'Should have created a new phone number'
    );
  }

  public function testExclude() {
    CRM_Core_DAO::executeQuery('DROP TABLE IF EXISTS tmp_test_action_apicall');
    CRM_Core_DAO::executeQuery("DELETE FROM civicrm_phone WHERE phone LIKE '1800test%'");
    $data = [
      'name' => 'testAPICall',
      'description' => 'Test Task Description',
      'category' => 'Test Task Category',
      'scheduled' => 'monthly',
      'parallel_exec' => 0,
      'main_sql' => "CREATE TABLE tmp_test_action_apicall (contact_id INT(10), exclude BOOL, phone varchar(255));
                     INSERT INTO tmp_test_action_apicall SELECT id as contact_id, 0 as exclude, '1800testInclude' as phone FROM civicrm_contact LIMIT 1;
                     INSERT INTO tmp_test_action_apicall SELECT id as contact_id, 0 as exclude, '1800testExclude' as phone FROM civicrm_contact LIMIT 1",
      'post_sql' => '',
      'api_enabled' => '1',
      'api_table' => 'tmp_test_action_apicall',
      'api_entity' => 'Phone',
      'api_action' => 'create',
      'api_parameters' => "contact_id={contact_id}\r\nphone={phone}",
    ];
    $task = new CRM_Sqltasks_Task(NULL, $data);
    $task->store();
    $log = $task->execute();

    $this->assertEquals('1 API call(s) successfull.', $log[1]);
    $this->assertStringStartsWith("Action 'API Call' executed in", $log[2]);
    $this->assertEquals(
      1,
      CRM_Core_DAO::singleValueQuery("SELECT COUNT(*) FROM civicrm_phone WHERE phone = '1800testInclude' AND contact_id IN (SELECT contact_id FROM tmp_test_action_apicall)"),
      'Should have created a new phone number for row with exclude=0'
    );
    $this->assertEquals(
      0,
      CRM_Core_DAO::singleValueQuery("SELECT COUNT(*) FROM civicrm_phone WHERE phone = '1800testExclude' AND contact_id IN (SELECT contact_id FROM tmp_test_action_apicall)"),
      'Should not have created a new phone number for row with exclude=1'
    );
  }

}
