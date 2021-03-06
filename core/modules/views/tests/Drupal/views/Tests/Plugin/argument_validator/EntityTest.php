<?php

/**
 * @file
 * Contains \Drupal\views\Tests\Plugin\argument_validator\EntityTest.
 */

namespace Drupal\views\Tests\Plugin\argument_validator;

use Drupal\Tests\UnitTestCase;
use Drupal\views\Plugin\views\argument_validator\Entity;

/**
 * Tests the generic entity argument validator.
 *
 * @group Drupal
 * @group Views
 *
 * @see \Drupal\views\Plugin\views\argument_validator\Entity
 */
class EntityTest extends UnitTestCase {

  /**
   * The view executable.
   *
   * @var \Drupal\views\ViewExecutable
   */
  protected $executable;

  /**
   * The view display.
   *
   * @var \Drupal\views\Plugin\views\display\DisplayPluginBase
   */
  protected $display;

  /**
   * The entity manager.
   *
   * @var \PHPUnit_Framework_MockObject_MockObject|\Drupal\Core\Entity\EntityManager
   */
  protected $entityManager;

  /**
   * The tested argument validator.
   *
   * @var \Drupal\views\Plugin\views\argument_validator\Entity
   */
  protected $argumentValidator;

  public static function getInfo() {
    return array(
      'name' => 'Argument validator: Entity',
      'description' => 'Tests the generic entity argument validator.',
      'group' => 'Views Plugin',
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->entityManager = $this->getMockBuilder('Drupal\Core\Entity\EntityManager')
      ->disableOriginalConstructor()
      ->getMock();

    $mock_entity = $this->getMockBuilder('Drupal\Core\Entity\Entity')
      ->disableOriginalConstructor()
      ->setMethods(array('bundle', 'access'))
      ->getMock();
    $mock_entity->expects($this->any())
      ->method('bundle')
      ->will($this->returnValue('test_bundle'));
    $mock_entity->expects($this->any())
      ->method('access')
      ->will($this->returnValueMap(array(
        array('test_op', NULL, TRUE),
        array('test_op_2', NULL, FALSE),
        array('test_op_3', NULL, TRUE),
      )));

    $mock_entity_bundle_2 = $this->getMockBuilder('Drupal\Core\Entity\Entity')
      ->disableOriginalConstructor()
      ->setMethods(array('bundle', 'access'))
      ->getMock();
    $mock_entity_bundle_2->expects($this->any())
      ->method('bundle')
      ->will($this->returnValue('test_bundle_2'));
    $mock_entity_bundle_2->expects($this->any())
      ->method('access')
      ->will($this->returnValueMap(array(
        array('test_op_3', NULL, TRUE),
      )));


    $storage_controller = $this->getMock('Drupal\Core\Entity\EntityStorageControllerInterface');

    // Setup values for IDs passed as strings or numbers.
    $value_map = array(
      array(array(), array()),
      array(array(1), array(1 => $mock_entity)),
      array(array('1'), array(1 => $mock_entity)),
      array(array(1, 2), array(1 => $mock_entity, 2 => $mock_entity_bundle_2)),
      array(array('1', '2'), array(1 => $mock_entity, 2 => $mock_entity_bundle_2)),
      array(array(2), array(2 => $mock_entity_bundle_2)),
      array(array('2'), array(2 => $mock_entity_bundle_2)),
    );
    $storage_controller->expects($this->any())
      ->method('loadMultiple')
      ->will($this->returnValueMap($value_map));

    $this->entityManager->expects($this->any())
      ->method('getStorageController')
      ->will($this->returnValue($storage_controller));

    $this->executable = $this->getMockBuilder('Drupal\views\ViewExecutable')
      ->disableOriginalConstructor()
      ->getMock();
    $this->display = $this->getMockBuilder('Drupal\views\Plugin\views\display\DisplayPluginBase')
      ->disableOriginalConstructor()
      ->getMock();

    $definition = array(
      'entity_type' => 'entity_test',
    );

    $this->argumentValidator = new Entity(array(), 'entity_test', $definition, $this->entityManager);
  }

  /**
   * Tests the validate argument method with no access and bundles.
   *
   * @see \Drupal\views\Plugin\views\argument_validator\Entity::validateArgument()
   */
  public function testValidateArgumentNoAccess() {
    $options = array();
    $options['access'] = FALSE;
    $options['bundles'] = array();
    $this->argumentValidator->init($this->executable, $this->display, $options);

    $this->assertFalse($this->argumentValidator->validateArgument(3));
    $this->assertFalse($this->argumentValidator->validateArgument(''));

    $this->assertTrue($this->argumentValidator->validateArgument(1));
    $this->assertTrue($this->argumentValidator->validateArgument(2));
    $this->assertFalse($this->argumentValidator->validateArgument('1,2'));
  }

  /**
   * Tests the validate argument method with access and no bundles.
   *
   * @see \Drupal\views\Plugin\views\argument_validator\Entity::validateArgument()
   */
  public function testValidateArgumentAccess() {
    $options = array();
    $options['access'] = TRUE;
    $options['bundles'] = array();
    $options['operation'] = 'test_op';
    $this->argumentValidator->init($this->executable, $this->display, $options);

    $this->assertFalse($this->argumentValidator->validateArgument(3));
    $this->assertFalse($this->argumentValidator->validateArgument(''));

    $this->assertTrue($this->argumentValidator->validateArgument(1));

    $options = array();
    $options['access'] = TRUE;
    $options['bundles'] = array();
    $options['operation'] = 'test_op_2';
    $this->argumentValidator->init($this->executable, $this->display, $options);

    $this->assertFalse($this->argumentValidator->validateArgument(3));
    $this->assertFalse($this->argumentValidator->validateArgument(''));

    $this->assertFalse($this->argumentValidator->validateArgument(1));
    $this->assertFalse($this->argumentValidator->validateArgument(2));
  }

  /**
   * Tests the validate argument method with bundle checking.
   */
  public function testValidateArgumentBundle() {
    $options = array();
    $options['access'] = FALSE;
    $options['bundles'] = array('test_bundle' => 1);
    $this->argumentValidator->init($this->executable, $this->display, $options);

    $this->assertTrue($this->argumentValidator->validateArgument(1));
    $this->assertFalse($this->argumentValidator->validateArgument(2));
  }

  /**
   * Tests the validate argument method with multiple argument splitting.
   */
  public function testValidateArgumentMultiple() {
    $options = array();
    $options['access'] = TRUE;
    $options['bundles'] = array();
    $options['operation'] = 'test_op';
    $options['multiple'] = TRUE;
    $this->argumentValidator->init($this->executable, $this->display, $options);

    $this->assertTrue($this->argumentValidator->validateArgument('1'));
    $this->assertFalse($this->argumentValidator->validateArgument('2'));

    $this->assertFalse($this->argumentValidator->validateArgument('1,2'));
    $this->assertFalse($this->argumentValidator->validateArgument('1+2'));

    $options = array();
    $options['access'] = TRUE;
    $options['bundles'] = array();
    $options['operation'] = 'test_op_3';
    $options['multiple'] = TRUE;
    $this->argumentValidator->init($this->executable, $this->display, $options);

    $this->assertTrue($this->argumentValidator->validateArgument('1,2'));
    $this->assertTrue($this->argumentValidator->validateArgument('1+2'));
  }

}
