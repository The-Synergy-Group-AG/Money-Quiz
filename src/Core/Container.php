<?php
/**
 * Dependency Injection Container
 *
 * Implements PSR-11 compatible container for managing plugin dependencies.
 *
 * @package MoneyQuiz\Core
 * @since   7.0.0
 */

namespace MoneyQuiz\Core;

use Psr\Container\ContainerInterface;
use MoneyQuiz\Core\Exceptions\ContainerException;
use MoneyQuiz\Core\Exceptions\NotFoundException;

// Security: Prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * Dependency injection container.
 *
 * @since 7.0.0
 */
class Container implements ContainerInterface {

	/**
	 * Registered services.
	 *
	 * @var array<string, callable>
	 */
	private array $services = [];

	/**
	 * Resolved service instances.
	 *
	 * @var array<string, mixed>
	 */
	private array $instances = [];

	/**
	 * Service factories for creating instances.
	 *
	 * @var array<string, callable>
	 */
	private array $factories = [];

	/**
	 * Service parameters.
	 *
	 * @var array<string, mixed>
	 */
	private array $parameters = [];

	/**
	 * Services currently being resolved (for circular dependency detection).
	 *
	 * @var array<string, bool>
	 */
	private array $resolving = [];

	/**
	 * Singleton instance of the container.
	 *
	 * @var Container|null
	 */
	private static ?Container $instance = null;

	/**
	 * Mutex for thread-safe operations.
	 *
	 * @var object
	 */
	private static object $mutex;

	/**
	 * Initialize static properties.
	 *
	 * @return void
	 */
	private static function init_static(): void {
		if ( ! isset( self::$mutex ) ) {
			self::$mutex = new \stdClass();
		}
	}

	/**
	 * Get the singleton instance (thread-safe).
	 *
	 * @since 7.0.0
	 *
	 * @return Container|null Container instance.
	 */
	public static function get_instance(): ?Container {
		self::init_static();
		
		// Use WordPress transient locking for thread safety.
		$lock_key = 'money_quiz_container_lock';
		$max_wait = 5; // Maximum seconds to wait for lock.
		$waited = 0;
		
		// Wait for lock if another process is setting instance.
		while ( get_transient( $lock_key ) && $waited < $max_wait ) {
			usleep( 100000 ); // Wait 0.1 seconds.
			$waited += 0.1;
		}
		
		return self::$instance;
	}

	/**
	 * Set the singleton instance (thread-safe).
	 *
	 * @since 7.0.0
	 *
	 * @param Container $container Container instance.
	 * @return void
	 */
	public static function set_instance( Container $container ): void {
		self::init_static();
		
		// Use WordPress transient for locking.
		$lock_key = 'money_quiz_container_lock';
		
		// Set lock.
		set_transient( $lock_key, true, 5 );
		
		try {
			// Only set if not already set (double-check pattern).
			if ( self::$instance === null ) {
				self::$instance = $container;
			}
		} finally {
			// Always release lock.
			delete_transient( $lock_key );
		}
	}

	/**
	 * Register a service in the container.
	 *
	 * @since 7.0.0
	 *
	 * @param string   $id       Service identifier.
	 * @param callable $resolver Service resolver callable.
	 * @return void
	 *
	 * @throws ContainerException If service ID is invalid.
	 */
	public function set( string $id, callable $resolver ): void {
		if ( empty( $id ) ) {
			throw new ContainerException( 'Service ID cannot be empty.' );
		}

		$this->services[ $id ] = $resolver;
		
		// Clear any existing instance.
		unset( $this->instances[ $id ] );
	}

	/**
	 * Register a singleton service.
	 *
	 * @since 7.0.0
	 *
	 * @param string   $id       Service identifier.
	 * @param callable $resolver Service resolver callable.
	 * @return void
	 */
	public function singleton( string $id, callable $resolver ): void {
		$this->set(
			$id,
			function( $container ) use ( $id, $resolver ) {
				if ( ! isset( $this->instances[ $id ] ) ) {
					$this->instances[ $id ] = $resolver( $container );
				}
				return $this->instances[ $id ];
			}
		);
	}

	/**
	 * Register a factory service.
	 *
	 * @since 7.0.0
	 *
	 * @param string   $id      Service identifier.
	 * @param callable $factory Factory callable.
	 * @return void
	 */
	public function factory( string $id, callable $factory ): void {
		$this->factories[ $id ] = $factory;
	}

	/**
	 * Set a parameter value.
	 *
	 * @since 7.0.0
	 *
	 * @param string $key   Parameter key.
	 * @param mixed  $value Parameter value.
	 * @return void
	 */
	public function parameter( string $key, $value ): void {
		$this->parameters[ $key ] = $value;
	}

	/**
	 * Get a service from the container.
	 *
	 * @since 7.0.0
	 *
	 * @param string $id Service identifier.
	 * @return mixed The service instance.
	 *
	 * @throws NotFoundException If service is not found.
	 * @throws ContainerException If service cannot be resolved.
	 */
	public function get( string $id ) {
		// Check for circular dependencies.
		if ( isset( $this->resolving[ $id ] ) ) {
			throw new ContainerException(
				sprintf( 'Circular dependency detected while resolving service: %s', $id )
			);
		}

		// Return existing instance if available.
		if ( isset( $this->instances[ $id ] ) ) {
			return $this->instances[ $id ];
		}

		// Check if it's a parameter.
		if ( isset( $this->parameters[ $id ] ) ) {
			return $this->parameters[ $id ];
		}

		// Check if it's a factory.
		if ( isset( $this->factories[ $id ] ) ) {
			return $this->factories[ $id ]( $this );
		}

		// Resolve the service.
		if ( ! $this->has( $id ) ) {
			throw new NotFoundException(
				sprintf( 'Service "%s" not found in container.', $id )
			);
		}

		try {
			$this->resolving[ $id ] = true;
			$instance = $this->services[ $id ]( $this );
			unset( $this->resolving[ $id ] );
			
			return $instance;
		} catch ( \Exception $e ) {
			unset( $this->resolving[ $id ] );
			throw new ContainerException(
				sprintf( 'Error resolving service "%s": %s', $id, $e->getMessage() ),
				0,
				$e
			);
		}
	}

	/**
	 * Check if a service exists in the container.
	 *
	 * @since 7.0.0
	 *
	 * @param string $id Service identifier.
	 * @return bool True if service exists, false otherwise.
	 */
	public function has( string $id ): bool {
		return isset( $this->services[ $id ] ) 
			|| isset( $this->instances[ $id ] ) 
			|| isset( $this->factories[ $id ] )
			|| isset( $this->parameters[ $id ] );
	}

	/**
	 * Get a parameter value.
	 *
	 * @since 7.0.0
	 *
	 * @param string $key     Parameter key.
	 * @param mixed  $default Default value if parameter not found.
	 * @return mixed Parameter value or default.
	 */
	public function param( string $key, $default = null ) {
		return $this->parameters[ $key ] ?? $default;
	}

	/**
	 * Check if a parameter exists.
	 *
	 * @since 7.0.0
	 *
	 * @param string $key Parameter key.
	 * @return bool True if parameter exists, false otherwise.
	 */
	public function has_param( string $key ): bool {
		return isset( $this->parameters[ $key ] );
	}

	/**
	 * Register multiple services at once.
	 *
	 * @since 7.0.0
	 *
	 * @param array<string, callable> $services Array of service ID => resolver pairs.
	 * @return void
	 */
	public function register_services( array $services ): void {
		foreach ( $services as $id => $resolver ) {
			$this->set( $id, $resolver );
		}
	}

	/**
	 * Register multiple singletons at once.
	 *
	 * @since 7.0.0
	 *
	 * @param array<string, callable> $singletons Array of service ID => resolver pairs.
	 * @return void
	 */
	public function register_singletons( array $singletons ): void {
		foreach ( $singletons as $id => $resolver ) {
			$this->singleton( $id, $resolver );
		}
	}

	/**
	 * Clear all services and instances.
	 *
	 * @since 7.0.0
	 *
	 * @return void
	 */
	public function clear(): void {
		$this->services   = [];
		$this->instances  = [];
		$this->factories  = [];
		$this->parameters = [];
		$this->resolving  = [];
	}

	/**
	 * Get all registered service IDs.
	 *
	 * @since 7.0.0
	 *
	 * @return array<string> List of service IDs.
	 */
	public function get_service_ids(): array {
		return array_unique(
			array_merge(
				array_keys( $this->services ),
				array_keys( $this->instances ),
				array_keys( $this->factories )
			)
		);
	}

	/**
	 * Set plugin manager reference.
	 *
	 * @since 7.0.0
	 *
	 * @param PluginManager $manager Plugin manager instance.
	 * @return void
	 */
	public static function set_plugin_manager( PluginManager $manager ): void {
		// Store reference if container exists.
		if ( self::$instance ) {
			self::$instance->set( 'plugin_manager', function() use ( $manager ) {
				return $manager;
			} );
		}
	}
}