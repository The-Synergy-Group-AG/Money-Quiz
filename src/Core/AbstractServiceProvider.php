<?php
/**
 * Abstract Service Provider
 *
 * Base class for service providers in the Money Quiz plugin.
 *
 * @package MoneyQuiz\Core
 * @since   7.0.0
 */

namespace MoneyQuiz\Core;

// Security: Prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * Abstract service provider class.
 *
 * @since 7.0.0
 */
abstract class AbstractServiceProvider implements ServiceProviderInterface {

	/**
	 * Container instance.
	 *
	 * @var Container
	 */
	protected Container $container;

	/**
	 * Services provided by this provider.
	 *
	 * @var array<string>
	 */
	protected array $provided = [];

	/**
	 * Constructor.
	 *
	 * @since 7.0.0
	 *
	 * @param Container $container Dependency injection container.
	 */
	public function __construct( Container $container ) {
		$this->container = $container;
	}

	/**
	 * Register services with the container.
	 *
	 * @since 7.0.0
	 *
	 * @return void
	 */
	abstract public function register(): void;

	/**
	 * Bootstrap services.
	 *
	 * Default implementation does nothing.
	 * Override in child classes if needed.
	 *
	 * @since 7.0.0
	 *
	 * @return void
	 */
	public function boot(): void {
		// Override in child classes if needed.
	}

	/**
	 * Get the services provided by this provider.
	 *
	 * @since 7.0.0
	 *
	 * @return array<string> List of service identifiers.
	 */
	public function provides(): array {
		return $this->provided;
	}

	/**
	 * Register a singleton service.
	 *
	 * @since 7.0.0
	 *
	 * @param string   $id       Service identifier.
	 * @param callable $resolver Service resolver.
	 * @return void
	 */
	protected function singleton( string $id, callable $resolver ): void {
		$this->container->singleton( $id, $resolver );
		$this->provided[] = $id;
	}

	/**
	 * Register a service.
	 *
	 * @since 7.0.0
	 *
	 * @param string   $id       Service identifier.
	 * @param callable $resolver Service resolver.
	 * @return void
	 */
	protected function bind( string $id, callable $resolver ): void {
		$this->container->set( $id, $resolver );
		$this->provided[] = $id;
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
	protected function factory( string $id, callable $factory ): void {
		$this->container->factory( $id, $factory );
		$this->provided[] = $id;
	}

	/**
	 * Register a parameter.
	 *
	 * @since 7.0.0
	 *
	 * @param string $key   Parameter key.
	 * @param mixed  $value Parameter value.
	 * @return void
	 */
	protected function parameter( string $key, $value ): void {
		$this->container->parameter( $key, $value );
	}

	/**
	 * Get a service from the container.
	 *
	 * @since 7.0.0
	 *
	 * @param string $id Service identifier.
	 * @return mixed Service instance.
	 */
	protected function get( string $id ) {
		return $this->container->get( $id );
	}

	/**
	 * Check if a service exists in the container.
	 *
	 * @since 7.0.0
	 *
	 * @param string $id Service identifier.
	 * @return bool True if service exists.
	 */
	protected function has( string $id ): bool {
		return $this->container->has( $id );
	}

	/**
	 * Get a parameter value.
	 *
	 * @since 7.0.0
	 *
	 * @param string $key     Parameter key.
	 * @param mixed  $default Default value.
	 * @return mixed Parameter value.
	 */
	protected function param( string $key, $default = null ) {
		return $this->container->param( $key, $default );
	}
}