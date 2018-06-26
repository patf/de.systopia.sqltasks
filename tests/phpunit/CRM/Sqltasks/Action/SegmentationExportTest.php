<?php

use CRM_Sqltasks_ExtensionUtil as E;
use Civi\Test\HeadlessInterface;
use Civi\Test\HookInterface;
use Civi\Test\TransactionalInterface;

/**
 * Test SegmentationExport Action
 *
 * NOTE: This test currently only runs if de.systopia.segmentation is present in
 * the extension directory.
 *
 * @group headless
 */
class CRM_Sqltasks_Action_SegmentationExportTest extends \PHPUnit_Framework_TestCase implements HeadlessInterface, HookInterface {
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

  public function testSegmentationExport() {
    if (!$this->_segmentationExtPresent) {
      return;
    }
    CRM_Core_DAO::executeQuery('DROP TABLE IF EXISTS tmp_test_action_segmentationexport');
    CRM_Core_DAO::executeQuery("DELETE FROM civicrm_segmentation_index WHERE name = 'testSegmentationExport'");
    CRM_Core_DAO::executeQuery('TRUNCATE TABLE civicrm_segmentation');
    CRM_Core_DAO::executeQuery("DELETE FROM civicrm_campaign WHERE name = 'testSegmentationExport'");
    $segmentId = $this->callApiSuccess('Segmentation', 'getsegmentid', [
      'name' => 'testSegmentationExport',
    ])['id'];
    $campaignId = $this->callApiSuccess('Campaign', 'create', array(
      'sequential' => 1,
      'name' => 'testSegmentationExport',
      'title' => 'testSegmentationExport',
    ))['id'];
    CRM_Core_DAO::executeQuery('DROP TABLE IF EXISTS tmp_test_action_segmentationexport');
    $tmp = tempnam(sys_get_temp_dir(), 'seg');
    $data = [
      'name' => 'testSegmentationExport',
      'description' => 'Test Task Description',
      'category' => 'Test Task Category',
      'scheduled' => 'monthly',
      'parallel_exec' => 0,
      'main_sql' => 'CREATE TABLE tmp_test_action_segmentationexport AS SELECT id as contact_id FROM civicrm_contact LIMIT 1',
      'post_sql' => '',
      'segmentation_assign_enabled' => '1',
      'segmentation_assign_table' => 'tmp_test_action_segmentationexport',
      'segmentation_assign_campaign_id' => $campaignId,
      'segmentation_assign_segment_name' => 'testSegmentationExport',
      'segmentation_assign_start' => 'leave',
      'segmentation_assign_segment_order' => '',
      'segmentation_assign_segment_order_table' => '',
      'segmentation_export_enabled' => '1',
      'segmentation_export_campaign_id' => $campaignId,
      'segmentation_export_segments' => [ $segmentId ],
      'segmentation_export_exporter' => [ 2 ],
      'segmentation_export_date_from' => '',
      'segmentation_export_date_to' => '',
      'segmentation_export_filename' => basename($tmp),
      'segmentation_export_path' => dirname($tmp),
      'segmentation_export_email' => '',
      'segmentation_export_email_template' => '1',
      'segmentation_export_upload' => '',
    ];
    $task = new CRM_Sqltasks_Task(NULL, $data);
    $task->store();
    $log = $task->execute();

    $this->assertStringStartsWith("Exporter 'Selektion (Excel)' to file", $log[4]);
    $this->assertStringStartsWith("Overwriting existing file", $log[5]);
    $this->assertStringStartsWith("Zipped file into", $log[6]);
    $this->assertStringStartsWith("Action 'Segmentation Export' executed in", $log[7]);
    $zip = new ZipArchive();
    $zip->open($tmp);
    $this->assertContains(
      'contact_id;titel;anrede;vorname;nachname;geburtsdatum;strasse;plz;ort;land;zielgruppe ID;zielgruppe;telefon;mobilnr;email;paket;textbaustein',
      $zip->getFromIndex(0)
    );
    $this->assertContains(
      "1;;An;;Default Organization;;;;;;{$segmentId};testSegmentationExport;;;;;",
      $zip->getFromIndex(0)
    );
  }

}
