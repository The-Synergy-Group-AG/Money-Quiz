<?php
/**
 * Container test
 *
 * @package MoneyQuiz\Tests\Unit\Core
 */

namespace MoneyQuiz\Tests\Unit\Core;

use MoneyQuiz\Tests\TestCase;
use MoneyQuiz\Core\Container;
use MoneyQuiz\Core\Exceptions\ContainerException;
use MoneyQuiz\Core\Exceptions\NotFoundException;

/**
 * Container test class.
 *
 * @covers \MoneyQuiz\Core\Container
 */
class ContainerTest extends TestCase {

	/**
	 * Test service registration and retrieval.
	 */
	public function test_service_registration(): void {
		$this->container->set( 'test_service', function() {
			return new \stdClass();
		} );

		$this->assertTrue( $this->container->has( 'test_service' ) );
		$this->assertInstanceOf( \stdClass::class, $this->container->get( 'test_service' ) );
	}

	/**
	 * Test singleton registration.
	 */
	public function test_singleton_registration(): void {
		$this->container->singleton( 'singleton_service', function() {
			return new \stdClass();
		} );

		$instance1 = $this->container->get( 'singleton_service' );
		$instance2 = $this->container->get( 'singleton_service' );

		$this->assertSame( $instance1, $instance2 );
	}

	/**
	 * Test factory registration.
	 */
	public function test_factory_registration(): void {
		$this->container->factory( 'factory_service', function() {
			return new \stdClass();
		} );

		$instance1 = $this->container->get( 'factory_service' );
		$instance2 = $this->container->get( 'factory_service' );

		$this->assertNotSame( $instance1, $instance2 );
	}

	/**
	 * Test parameter registration.
	 */
	public function test_parameter_registration(): void {
		$this->container->parameter( 'test_param', 'test_value' );

		$this->assertTrue( $this->container->has( 'test_param' ) );
		$this->assertEquals( 'test_value', $this->container->get( 'test_param' ) );
		$this->assertEquals( 'test_value', $this->container->param( 'test_param' ) );
	}

	/**
	 * Test not found exception.
	 */
	public function test_not_found_exception(): void {
		$this->expectException( NotFoundException::class );
		$this->container->get( 'non_existent_service' );
	}

	/**
	 * Test circular dependency detection.
	 */
	public function test_circular_dependency_detection(): void {
		$this->container->set( 'service_a', function( $container ) {
			return $container->get( 'service_b' );
		} );

		$this->container->set( 'service_b', function( $container ) {
			return $container->get( 'service_a' );
		} );

		$this->expectException( ContainerException::class );
		$this->expectExceptionMessage( 'Circular dependency' );

		$this->container->get( 'service_a' );
	}

	/**
	 * Test empty service ID validation.
	 */
	public function test_empty_service_id_validation(): void {
		$this->expectException( ContainerException::class );
		$this->expectExceptionMessage( 'Service ID cannot be empty' );

		$this->container->set( '', function() {
			return new \stdClass();
		} );
	}

	/**
	 * Test service override.
	 */
	public function test_service_override(): void {
		$this->container->set( 'test_service', function() {
			return 'original';
		} );

		$this->assertEquals( 'original', $this->container->get( 'test_service' ) );

		$this->container->set( 'test_service', function() {
			return 'overridden';
		} );

		$this->assertEquals( 'overridden', $this->container->get( 'test_service' ) );
	}

	/**
	 * Test bulk service registration.
	 */
	public function test_bulk_service_registration(): void {
		$services = [
			'service1' => function() { return 'value1'; },
			'service2' => function() { return 'value2'; },
		];

		$this->container->register_services( $services );

		$this->assertEquals( 'value1', $this->container->get( 'service1' ) );
		$this->assertEquals( 'value2', $this->container->get( 'service2' ) );
	}

	/**
	 * Test get service IDs.
	 */
	public function test_get_service_ids(): void {
		$this->container->set( 'service1', function() { return 'value1'; } );
		$this->container->singleton( 'service2', function() { return 'value2'; } );
		$this->container->factory( 'service3', function() { return 'value3'; } );

		$ids = $this->container->get_service_ids();

		$this->assertContains( 'service1', $ids );
		$this->assertContains( 'service2', $ids );
		$this->assertContains( 'service3', $ids );
	}

	/**
	 * Test container clear.
	 */
	public function test_container_clear(): void {
		$this->container->set( 'test_service', function() {
			return 'value';
		} );

		$this->assertTrue( $this->container->has( 'test_service' ) );

		$this->container->clear();

		$this->assertFalse( $this->container->has( 'test_service' ) );
	}

	/**
	 * Test static instance management.
	 */
	public function test_static_instance_management(): void {
		$container = new Container();
		Container::set_instance( $container );

		$this->assertSame( $container, Container::get_instance() );
	}
}