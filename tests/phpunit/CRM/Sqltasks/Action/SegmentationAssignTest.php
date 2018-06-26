<?php

use CRM_Sqltasks_ExtensionUtil as E;
use Civi\Test\HeadlessInterface;
use Civi\Test\HookInterface;
use Civi\Test\TransactionalInterface;

/**
 * Test SegmentationAssign Action
 *
 * NOTE: This test currently only runs if de.systopia.segmentation is present in
 * the extension directory.
 *
 * @group headless
 */
class CRM_Sqltasks_Action_SegmentationAssignTest extends \PHPUnit_Framework_TestCase implements HeadlessInterface, HookInterface {
  use \Civi\Test\Api3TestTrait;

  private $_segmentationExtPresent = FALSE;

  public function setUpHeadless() {
    $test = \Civi\Test::headless()->installMe(__DIR__);
    // TODO: find a better way to do this
    $segmentationExtPath = __DIR__ . '/../../../../../../de.systopia.segmentation';
    if (is_dir($segmentationExtPath)) {
      $this->_segmentationExtPresent = TRUE;
      $test->install('de.systopia.segmentation');
    }
    return $test->apply();
  }

  public function setUp() {
    parent::setUp();
  }

  public function tearDown() {
    parent::tearDown();
  }

  public function testSegmentationAssign() {
    if (!$this->_segmentationExtPresent) {
      return;
    }
    CRM_Core_DAO::executeQuery('DROP TABLE IF EXISTS tmp_test_action_segmentationassign');
    CRM_Core_DAO::executeQuery("DELETE FROM civicrm_segmentation_index WHERE name = 'testSegmentationAssign'");
    CRM_Core_DAO::executeQuery('TRUNCATE TABLE civicrm_segmentation');
    CRM_Core_DAO::executeQuery("DELETE FROM civicrm_campaign WHERE name = 'testSyncGroup'");
    $campaignId = $this->callApiSuccess('Campaign', 'create', array(
      'sequential' => 1,
      'name' => 'testSyncGroup',
      'title' => 'testSyncGroup',
    ))['id'];
    $data = [
      'name' => 'testSegmentationAssign',
      'description' => 'Test Task Description',
      'category' => 'Test Task Category',
      'scheduled' => 'monthly',
      'parallel_exec' => 0,
      'main_sql' => 'CREATE TABLE tmp_test_action_segmentationassign AS SELECT id as contact_id FROM civicrm_contact LIMIT 1',
      'post_sql' => '',
      'segmentation_assign_enabled' => '1',
      'segmentation_assign_table' => 'tmp_test_action_segmentationassign',
      'segmentation_assign_campaign_id' => $campaignId,
      'segmentation_assign_segment_name' => 'testSegmentationAssign',
      'segmentation_assign_start' => 'leave',
      'segmentation_assign_segment_order' => '',
      'segmentation_assign_segment_order_table' => '',
    ];
    $task = new CRM_Sqltasks_Task(NULL, $data);
    $task->store();
    $log = $task->execute();

    $this->assertEquals("Resolved 1 segment(s).", $log[1]);
    $this->assertEquals("Assigned 1 new contacts to segment 'testSegmentationAssign'.", $log[2]);
    $this->assertStringStartsWith("Action 'Assign to Campaign (Segmentation)' executed in", $log[3]);
    $this->assertEquals(
      1,
      CRM_Core_DAO::singleValueQuery("SELECT COUNT(*) FROM civicrm_segmentation WHERE campaign_id = %0 AND entity_id IN (SELECT contact_id FROM tmp_test_action_segmentationassign)", [[$campaignId, 'Integer']]),
      'Should have created subscription history'
    );
  }

}
