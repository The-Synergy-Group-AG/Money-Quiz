<?php
/**
 * Service Provider Interface
 *
 * Defines the contract for service providers in the Money Quiz plugin.
 *
 * @package MoneyQuiz\Core
 * @since   7.0.0
 */

namespace MoneyQuiz\Core;

// Security: Prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * Service provider interface.
 *
 * @since 7.0.0
 */
interface ServiceProviderInterface {

	/**
	 * Register services with the container.
	 *
	 * This method is called during the registration phase.
	 * Use this to bind services, parameters, and factories to the container.
	 *
	 * @since 7.0.0
	 *
	 * @return void
	 */
	public function register(): void;

	/**
	 * Bootstrap services.
	 *
	 * This method is called after all providers have been registered.
	 * Use this to initialize services, add hooks, and perform setup.
	 *
	 * @since 7.0.0
	 *
	 * @return void
	 */
	public function boot(): void;

	/**
	 * Get the services provided by this provider.
	 *
	 * Returns an array of service identifiers that this provider registers.
	 * This is used for optimization and debugging purposes.
	 *
	 * @since 7.0.0
	 *
	 * @return array<string> List of service identifiers.
	 */
	public function provides(): array;
}