<?php

use CRM_Sqltasks_ExtensionUtil as E;
use Civi\Test\HeadlessInterface;
use Civi\Test\HookInterface;
use Civi\Test\TransactionalInterface;

/**
 * Test SyncTag Action
 *
 * @group headless
 */
class CRM_Sqltasks_Action_SyncTagTest extends \PHPUnit_Framework_TestCase implements HeadlessInterface, HookInterface {

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

  public function testSyncTag() {
    CRM_Core_DAO::executeQuery('DROP TABLE IF EXISTS tmp_test_action_synctag');
    CRM_Core_DAO::executeQuery("DELETE FROM civicrm_entity_tag WHERE tag_id = 1");
    $data = [
      'name' => 'testSyncTag',
      'description' => 'Test Task Description',
      'category' => 'Test Task Category',
      'scheduled' => 'monthly',
      'parallel_exec' => 0,
      'main_sql' => 'CREATE TABLE tmp_test_action_synctag AS SELECT id as contact_id FROM civicrm_contact LIMIT 1',
      'post_sql' => '',
      'tag_enabled' => '1',
      'tag_contact_table' => 'tmp_test_action_synctag',
      'tag_tag_id' => '1',
      'tag_entity_table' => 'civicrm_contact',
    ];
    $task = new CRM_Sqltasks_Task(NULL, $data);
    $task->store();
    $log = $task->execute();

    $this->assertStringStartsWith("Action 'Synchronise Tag' executed in", $log[1]);
    $this->assertEquals(
      1,
      CRM_Core_DAO::singleValueQuery("SELECT COUNT(*) FROM civicrm_entity_tag WHERE tag_id = 1 AND entity_table = 'civicrm_contact' AND entity_id IN (SELECT contact_id FROM tmp_test_action_synctag)"),
      'Should have assigned a tag'
    );
  }

}
