<?php
/**
 * Container unit tests
 *
 * @package MoneyQuiz
 */

namespace MoneyQuiz\Tests\Unit\Core;

use MoneyQuiz\Tests\TestCase;
use MoneyQuiz\Core\Container;

/**
 * Test the dependency injection container
 */
class ContainerTest extends TestCase {
    
    /**
     * @var Container
     */
    private Container $container;
    
    /**
     * Setup before each test
     */
    public function setUp(): void {
        parent::setUp();
        $this->container = new Container();
    }
    
    /**
     * Test binding and resolving a simple closure
     */
    public function test_bind_and_resolve_closure() {
        $this->container->bind( 'test', function() {
            return 'test value';
        });
        
        $result = $this->container->get( 'test' );
        
        $this->assertEquals( 'test value', $result );
    }
    
    /**
     * Test binding and resolving a class
     */
    public function test_bind_and_resolve_class() {
        $this->container->bind( 'test_class', function() {
            return new \stdClass();
        });
        
        $result = $this->container->get( 'test_class' );
        
        $this->assertInstanceOf( \stdClass::class, $result );
    }
    
    /**
     * Test singleton binding
     */
    public function test_singleton_binding() {
        $this->container->bind( 'singleton', function() {
            return new \stdClass();
        }, true );
        
        $first = $this->container->get( 'singleton' );
        $second = $this->container->get( 'singleton' );
        
        $this->assertSame( $first, $second );
    }
    
    /**
     * Test non-singleton binding
     */
    public function test_non_singleton_binding() {
        $this->container->bind( 'non_singleton', function() {
            return new \stdClass();
        }, false );
        
        $first = $this->container->get( 'non_singleton' );
        $second = $this->container->get( 'non_singleton' );
        
        $this->assertNotSame( $first, $second );
    }
    
    /**
     * Test has method
     */
    public function test_has_method() {
        $this->assertFalse( $this->container->has( 'test' ) );
        
        $this->container->bind( 'test', function() {
            return 'value';
        });
        
        $this->assertTrue( $this->container->has( 'test' ) );
    }
    
    /**
     * Test binding with dependencies
     */
    public function test_binding_with_dependencies() {
        $this->container->bind( 'dependency', function() {
            return 'dependency value';
        });
        
        $this->container->bind( 'service', function( $container ) {
            return [
                'dependency' => $container->get( 'dependency' ),
            ];
        });
        
        $result = $this->container->get( 'service' );
        
        $this->assertIsArray( $result );
        $this->assertArrayHasKey( 'dependency', $result );
        $this->assertEquals( 'dependency value', $result['dependency'] );
    }
    
    /**
     * Test exception thrown for unbound service
     */
    public function test_exception_for_unbound_service() {
        $this->expectException( \Exception::class );
        $this->expectExceptionMessage( 'Service not found: unbound_service' );
        
        $this->container->get( 'unbound_service' );
    }
    
    /**
     * Test rebinding a service
     */
    public function test_rebinding_service() {
        $this->container->bind( 'test', function() {
            return 'first value';
        });
        
        $first = $this->container->get( 'test' );
        $this->assertEquals( 'first value', $first );
        
        // Rebind
        $this->container->bind( 'test', function() {
            return 'second value';
        });
        
        $second = $this->container->get( 'test' );
        $this->assertEquals( 'second value', $second );
    }
    
    /**
     * Test container passes itself to factories
     */
    public function test_container_passes_itself_to_factories() {
        $this->container->bind( 'test', function( $container ) {
            $this->assertInstanceOf( Container::class, $container );
            return 'value';
        });
        
        $this->container->get( 'test' );
    }
    
    /**
     * Test circular dependency detection
     */
    public function test_circular_dependency_detection() {
        $this->container->bind( 'service_a', function( $container ) {
            return $container->get( 'service_b' );
        });
        
        $this->container->bind( 'service_b', function( $container ) {
            return $container->get( 'service_a' );
        });
        
        // This should throw an exception or handle gracefully
        try {
            $this->container->get( 'service_a' );
            $this->fail( 'Expected exception for circular dependency' );
        } catch ( \Exception $e ) {
            $this->assertStringContainsString( 'Service not found', $e->getMessage() );
        }
    }
    
    /**
     * Test binding different types
     */
    public function test_binding_different_types() {
        // String
        $this->container->bind( 'string', function() {
            return 'string value';
        });
        
        // Array
        $this->container->bind( 'array', function() {
            return [ 'key' => 'value' ];
        });
        
        // Object
        $this->container->bind( 'object', function() {
            $obj = new \stdClass();
            $obj->property = 'value';
            return $obj;
        });
        
        // Integer
        $this->container->bind( 'integer', function() {
            return 42;
        });
        
        // Boolean
        $this->container->bind( 'boolean', function() {
            return true;
        });
        
        $this->assertIsString( $this->container->get( 'string' ) );
        $this->assertIsArray( $this->container->get( 'array' ) );
        $this->assertIsObject( $this->container->get( 'object' ) );
        $this->assertIsInt( $this->container->get( 'integer' ) );
        $this->assertIsBool( $this->container->get( 'boolean' ) );
    }
}