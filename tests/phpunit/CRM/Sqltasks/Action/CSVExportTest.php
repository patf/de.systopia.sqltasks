<?php

use CRM_Sqltasks_ExtensionUtil as E;
use Civi\Test\HeadlessInterface;
use Civi\Test\HookInterface;
use Civi\Test\TransactionalInterface;

/**
 * Test CSVExport Action
 *
 * @group headless
 */
class CRM_Sqltasks_Action_CSVExportTest extends \PHPUnit_Framework_TestCase implements HeadlessInterface, HookInterface {

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

  public function testFileExport() {
    CRM_Core_DAO::executeQuery('DROP TABLE IF EXISTS tmp_test_action_csvexport');
    $tmp = tempnam(sys_get_temp_dir(), 'csv');
    $data = [
      'name' => 'testTaskIsCreated',
      'description' => 'Test Task Description',
      'category' => 'Test Task Category',
      'scheduled' => 'monthly',
      'parallel_exec' => 0,
      'main_sql' => "CREATE TABLE tmp_test_action_csvexport AS SELECT id as contact_id, 'bazbar' AS foobar FROM civicrm_contact LIMIT 1",
      'post_sql' => '',
      'csv_enabled' => '1',
      'csv_table' => 'tmp_test_action_csvexport',
      'csv_encoding' => 'UTF-8',
      'csv_delimiter' => ';',
      'csv_headers' => "contact_id=contact_id\r\nfoobar=foobar",
      'csv_filename' => basename($tmp),
      'csv_path' => dirname($tmp),
      'csv_email' => '',
      'csv_email_template' => '1',
      'csv_upload' => '',
    ];
    $task = new CRM_Sqltasks_Task(NULL, $data);
    $task->store();
    $log = $task->execute();

    $this->assertStringStartsWith('Written 1 records to', $log[1]);
    $this->assertStringStartsWith("Action 'CSV Export' executed in", $log[2]);
    $this->assertFileEquals(__DIR__ . '/../../../../fixtures/csvexport.csv', $tmp);
  }

}
