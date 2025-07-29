<?php
/**
 * Dependency Injection Container
 *
 * @package MoneyQuiz
 * @since 4.0.0
 */

namespace MoneyQuiz\Core;

use MoneyQuiz\Exceptions\ContainerException;
use MoneyQuiz\Exceptions\NotFoundException;
use MoneyQuiz\Interfaces\ContainerInterface;

/**
 * Service Container implementation
 * 
 * Provides dependency injection functionality following PSR-11 principles
 */
class Container implements ContainerInterface {
    
    /**
     * @var array<string, callable> Service factories
     */
    private array $factories = [];
    
    /**
     * @var array<string, mixed> Resolved service instances
     */
    private array $instances = [];
    
    /**
     * @var array<string, bool> Singleton flags
     */
    private array $singletons = [];
    
    /**
     * @var array<string, bool> Services currently being resolved (circular dependency detection)
     */
    private array $resolving = [];
    
    /**
     * Register a service in the container
     * 
     * @param string   $id       Service identifier
     * @param callable $factory  Service factory callable
     * @param bool     $singleton Whether to create as singleton (default: true)
     * @return void
     */
    public function bind( string $id, callable $factory, bool $singleton = true ): void {
        $this->factories[ $id ] = $factory;
        $this->singletons[ $id ] = $singleton;
        
        // Clear any existing instance
        unset( $this->instances[ $id ] );
    }
    
    /**
     * Register a singleton service
     * 
     * @param string   $id      Service identifier
     * @param callable $factory Service factory callable
     * @return void
     */
    public function singleton( string $id, callable $factory ): void {
        $this->bind( $id, $factory, true );
    }
    
    /**
     * Register a factory service (new instance each time)
     * 
     * @param string   $id      Service identifier
     * @param callable $factory Service factory callable
     * @return void
     */
    public function factory( string $id, callable $factory ): void {
        $this->bind( $id, $factory, false );
    }
    
    /**
     * Register an existing instance
     * 
     * @param string $id       Service identifier
     * @param mixed  $instance Service instance
     * @return void
     */
    public function instance( string $id, $instance ): void {
        $this->instances[ $id ] = $instance;
        $this->singletons[ $id ] = true;
        unset( $this->factories[ $id ] );
    }
    
    /**
     * Get a service from the container
     * 
     * @param string $id Service identifier
     * @return mixed
     * @throws NotFoundException If service not found
     * @throws ContainerException If service cannot be resolved
     */
    public function get( string $id ) {
        // Return existing singleton instance if available
        if ( isset( $this->instances[ $id ] ) && $this->is_singleton( $id ) ) {
            return $this->instances[ $id ];
        }
        
        // Check if service is registered
        if ( ! $this->has( $id ) ) {
            throw new NotFoundException( 
                sprintf( 'Service "%s" not found in container.', $id ) 
            );
        }
        
        // Detect circular dependencies
        if ( isset( $this->resolving[ $id ] ) ) {
            throw new ContainerException(
                sprintf( 'Circular dependency detected while resolving "%s".', $id )
            );
        }
        
        // Mark as being resolved
        $this->resolving[ $id ] = true;
        
        try {
            // Resolve the service
            $instance = $this->resolve( $id );
            
            // Store singleton instances
            if ( $this->is_singleton( $id ) ) {
                $this->instances[ $id ] = $instance;
            }
            
            return $instance;
            
        } finally {
            // Always clean up resolving flag
            unset( $this->resolving[ $id ] );
        }
    }
    
    /**
     * Check if a service exists in the container
     * 
     * @param string $id Service identifier
     * @return bool
     */
    public function has( string $id ): bool {
        return isset( $this->factories[ $id ] ) || isset( $this->instances[ $id ] );
    }
    
    /**
     * Resolve a service
     * 
     * @param string $id Service identifier
     * @return mixed
     * @throws ContainerException If service cannot be resolved
     */
    private function resolve( string $id ) {
        if ( isset( $this->instances[ $id ] ) ) {
            return $this->instances[ $id ];
        }
        
        if ( ! isset( $this->factories[ $id ] ) ) {
            throw new ContainerException(
                sprintf( 'No factory registered for service "%s".', $id )
            );
        }
        
        try {
            // Call the factory with the container as parameter
            return call_user_func( $this->factories[ $id ], $this );
        } catch ( \Throwable $e ) {
            throw new ContainerException(
                sprintf( 'Error resolving service "%s": %s', $id, $e->getMessage() ),
                0,
                $e
            );
        }
    }
    
    /**
     * Check if a service is registered as singleton
     * 
     * @param string $id Service identifier
     * @return bool
     */
    private function is_singleton( string $id ): bool {
        return $this->singletons[ $id ] ?? true;
    }
    
    /**
     * Remove a service from the container
     * 
     * @param string $id Service identifier
     * @return void
     */
    public function remove( string $id ): void {
        unset( 
            $this->factories[ $id ],
            $this->instances[ $id ],
            $this->singletons[ $id ]
        );
    }
    
    /**
     * Clear all services from the container
     * 
     * @return void
     */
    public function clear(): void {
        $this->factories = [];
        $this->instances = [];
        $this->singletons = [];
        $this->resolving = [];
    }
    
    /**
     * Get all registered service identifiers
     * 
     * @return array<string>
     */
    public function keys(): array {
        return array_unique( 
            array_merge( 
                array_keys( $this->factories ), 
                array_keys( $this->instances ) 
            ) 
        );
    }
    
    /**
     * Magic method to get services as properties
     * 
     * @param string $name Service identifier
     * @return mixed
     */
    public function __get( string $name ) {
        return $this->get( $name );
    }
    
    /**
     * Magic method to check if service exists
     * 
     * @param string $name Service identifier
     * @return bool
     */
    public function __isset( string $name ): bool {
        return $this->has( $name );
    }
}