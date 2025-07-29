<?php
/**
 * Singleton Trait
 *
 * @package MoneyQuiz
 * @since 4.0.0
 */

namespace MoneyQuiz\Traits;

/**
 * Trait to implement singleton pattern
 * 
 * This trait provides a thread-safe singleton implementation
 * following modern PHP best practices
 */
trait SingletonTrait {
    
    /**
     * @var static|null The single instance of the class
     */
    private static ?self $instance = null;
    
    /**
     * Get the singleton instance
     * 
     * @return static
     */
    public static function instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new static();
        }
        
        return self::$instance;
    }
    
    /**
     * Protected constructor to prevent direct instantiation
     */
    protected function __construct() {
        // Override in implementing class
    }
    
    /**
     * Prevent cloning of the instance
     * 
     * @return void
     * @throws \Exception
     */
    public function __clone() {
        throw new \Exception( 'Cannot clone a singleton instance.' );
    }
    
    /**
     * Prevent unserializing of the instance
     * 
     * @return void
     * @throws \Exception
     */
    public function __wakeup() {
        throw new \Exception( 'Cannot unserialize a singleton instance.' );
    }
    
    /**
     * Reset the singleton instance (useful for testing)
     * 
     * @return void
     */
    public static function reset(): void {
        self::$instance = null;
    }
}