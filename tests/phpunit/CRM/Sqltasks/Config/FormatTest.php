<?php

use CRM_Sqltasks_ExtensionUtil as E;
use Civi\Test\HeadlessInterface;
use Civi\Test\HookInterface;
use Civi\Test\TransactionalInterface;

/**
 * Test configuration format utility class
 *
 * @group headless
 */
class CRM_Sqltasks_Config_FormatTest extends \PHPUnit_Framework_TestCase implements HeadlessInterface, HookInterface {

  const SAMPLE_V1 = '{"segmentation_assign_enabled":"1","segmentation_assign_table":"tmp_foobar","segmentation_assign_campaign_id":"1","segmentation_assign_segment_name":"foobar","segmentation_assign_clear":"1","segmentation_assign_start":"leave","segmentation_assign_segment_order":"","segmentation_assign_segment_order_table":""}';

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

  public function testVersion1IsNotLatest() {
    $this->assertFalse(CRM_Sqltasks_Config_Format::isLatest(json_decode(self::SAMPLE_V1, TRUE)));
  }

  public function testVersion2IsLatest() {
    $config = json_decode('{"version":2,"actions":[]}', TRUE);
    $this->assertTrue(CRM_Sqltasks_Config_Format::isLatest($config));
  }

  public function testVersion1ToLatest() {
    $this->assertTrue(
      CRM_Sqltasks_Config_Format::isLatest(
        CRM_Sqltasks_Config_Format::toLatest(json_decode(self::SAMPLE_V1, TRUE))
      )
    );
  }

}