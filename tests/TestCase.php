<?php
/**
 * Base test case for Money Quiz tests
 *
 * @package MoneyQuiz
 */

namespace MoneyQuiz\Tests;

use WP_UnitTestCase;
use MoneyQuiz\Core\Plugin;
use MoneyQuiz\Core\Container;

/**
 * Base test case class
 */
abstract class TestCase extends WP_UnitTestCase {
    
    /**
     * @var Plugin
     */
    protected Plugin $plugin;
    
    /**
     * @var Container
     */
    protected Container $container;
    
    /**
     * Setup before each test
     */
    public function setUp(): void {
        parent::setUp();
        
        // Reset the plugin instance
        $this->plugin = Plugin::instance();
        $this->container = $this->plugin->get_container();
        
        // Clear any existing hooks
        $this->clear_hooks();
    }
    
    /**
     * Teardown after each test
     */
    public function tearDown(): void {
        parent::tearDown();
        
        // Clear singleton instances
        $this->clear_singletons();
    }
    
    /**
     * Clear all hooks
     */
    protected function clear_hooks(): void {
        global $wp_filter, $wp_actions, $wp_current_filter;
        
        $wp_filter = [];
        $wp_actions = [];
        $wp_current_filter = [];
    }
    
    /**
     * Clear singleton instances
     */
    protected function clear_singletons(): void {
        // Use reflection to clear singleton instances
        $reflection = new \ReflectionClass( Plugin::class );
        $instance = $reflection->getProperty( 'instance' );
        $instance->setAccessible( true );
        $instance->setValue( null, null );
    }
    
    /**
     * Get a private or protected property value
     * 
     * @param object $object   Object instance
     * @param string $property Property name
     * @return mixed
     */
    protected function get_private_property( $object, string $property ) {
        $reflection = new \ReflectionClass( $object );
        $property = $reflection->getProperty( $property );
        $property->setAccessible( true );
        
        return $property->getValue( $object );
    }
    
    /**
     * Set a private or protected property value
     * 
     * @param object $object   Object instance
     * @param string $property Property name
     * @param mixed  $value    Value to set
     */
    protected function set_private_property( $object, string $property, $value ): void {
        $reflection = new \ReflectionClass( $object );
        $property = $reflection->getProperty( $property );
        $property->setAccessible( true );
        $property->setValue( $object, $value );
    }
    
    /**
     * Call a private or protected method
     * 
     * @param object $object Object instance
     * @param string $method Method name
     * @param array  $args   Method arguments
     * @return mixed
     */
    protected function call_private_method( $object, string $method, array $args = [] ) {
        $reflection = new \ReflectionClass( $object );
        $method = $reflection->getMethod( $method );
        $method->setAccessible( true );
        
        return $method->invokeArgs( $object, $args );
    }
    
    /**
     * Create a test user with specific role
     * 
     * @param string $role User role
     * @return int User ID
     */
    protected function create_test_user( string $role = 'administrator' ): int {
        return $this->factory->user->create( [
            'role' => $role,
        ] );
    }
    
    /**
     * Create test quiz data
     * 
     * @return array
     */
    protected function create_test_quiz_data(): array {
        return [
            'title' => 'Test Quiz',
            'description' => 'Test quiz description',
            'questions' => [
                [
                    'text' => 'Test Question 1',
                    'options' => [
                        ['value' => 'a', 'label' => 'Option A'],
                        ['value' => 'b', 'label' => 'Option B'],
                    ],
                ],
                [
                    'text' => 'Test Question 2',
                    'options' => [
                        ['value' => 'c', 'label' => 'Option C'],
                        ['value' => 'd', 'label' => 'Option D'],
                    ],
                ],
            ],
        ];
    }
    
    /**
     * Assert that a hook has been registered
     * 
     * @param string $hook     Hook name
     * @param string $callback Expected callback
     * @param int    $priority Expected priority
     */
    protected function assertHookRegistered( string $hook, $callback, int $priority = 10 ): void {
        global $wp_filter;
        
        $this->assertArrayHasKey( $hook, $wp_filter );
        
        $found = false;
        if ( isset( $wp_filter[ $hook ][ $priority ] ) ) {
            foreach ( $wp_filter[ $hook ][ $priority ] as $registered ) {
                if ( $registered['function'] === $callback ) {
                    $found = true;
                    break;
                }
            }
        }
        
        $this->assertTrue( $found, "Hook '{$hook}' with callback not found at priority {$priority}" );
    }
    
    /**
     * Assert that an array has required keys
     * 
     * @param array $keys     Required keys
     * @param array $array    Array to check
     * @param string $message Optional message
     */
    protected function assertArrayHasKeys( array $keys, array $array, string $message = '' ): void {
        foreach ( $keys as $key ) {
            $this->assertArrayHasKey( $key, $array, $message ?: "Array missing required key: {$key}" );
        }
    }
    
    /**
     * Mock a WordPress function
     * 
     * @param string   $function Function name
     * @param callable $callback Replacement callback
     */
    protected function mock_function( string $function, callable $callback ): void {
        if ( ! function_exists( 'runkit_function_redefine' ) ) {
            $this->markTestSkipped( 'Runkit extension not available for function mocking' );
        }
        
        runkit_function_redefine( $function, '', $callback );
    }
}