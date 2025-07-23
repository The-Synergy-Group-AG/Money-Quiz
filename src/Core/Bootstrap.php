<?php
/**
 * Plugin Bootstrap
 *
 * Initializes the plugin and manages the bootstrap sequence.
 *
 * @package MoneyQuiz\Core
 * @since   7.0.0
 */

namespace MoneyQuiz\Core;

use MoneyQuiz\Core\ServiceProviders\CoreServiceProvider;
use MoneyQuiz\Core\ServiceProviders\SecurityServiceProvider;
use MoneyQuiz\Core\ServiceProviders\DatabaseServiceProvider;
use MoneyQuiz\Core\ServiceProviders\AdminServiceProvider;
use MoneyQuiz\Core\ServiceProviders\FrontendServiceProvider;
use MoneyQuiz\Core\ServiceProviders\APIServiceProvider;
use MoneyQuiz\Core\ServiceProviders\Phase2SecurityServiceProvider;
use MoneyQuiz\Core\ServiceProviders\Phase3CoreServiceProvider;
use MoneyQuiz\Core\ServiceProviders\Phase4FeaturesServiceProvider;

// Security: Prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * Bootstrap class.
 *
 * @since 7.0.0
 */
class Bootstrap {

	/**
	 * Container instance.
	 *
	 * @var Container
	 */
	private Container $container;

	/**
	 * Plugin instance.
	 *
	 * @var Plugin|null
	 */
	private ?Plugin $plugin = null;

	/**
	 * Service providers.
	 *
	 * @var array<class-string<ServiceProviderInterface>>
	 */
	private array $providers = [
		CoreServiceProvider::class,
		SecurityServiceProvider::class,
		DatabaseServiceProvider::class,
		AdminServiceProvider::class,
		FrontendServiceProvider::class,
		APIServiceProvider::class,
		Phase2SecurityServiceProvider::class,
		Phase3CoreServiceProvider::class,
		Phase4FeaturesServiceProvider::class,
	];

	/**
	 * Bootstrap status.
	 *
	 * @var bool
	 */
	private bool $booted = false;

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
	 * Initialize the plugin.
	 *
	 * @since 7.0.0
	 *
	 * @return void
	 *
	 * @throws \Exception If plugin fails to initialize.
	 */
	public function init(): void {
		if ( $this->booted ) {
			throw new \Exception( 'Plugin has already been bootstrapped.' );
		}

		// Register core parameters.
		$this->register_parameters();

		// Register service providers.
		$this->register_providers();

		// Boot service providers.
		$this->boot_providers();

		// Initialize the plugin.
		$this->initialize_plugin();

		// Mark as booted.
		$this->booted = true;
	}

	/**
	 * Register core parameters in the container.
	 *
	 * @since 7.0.0
	 *
	 * @return void
	 */
	private function register_parameters(): void {
		$this->container->parameter( 'plugin.version', MONEY_QUIZ_VERSION );
		$this->container->parameter( 'plugin.file', MONEY_QUIZ_PLUGIN_FILE );
		$this->container->parameter( 'plugin.dir', MONEY_QUIZ_PLUGIN_DIR );
		$this->container->parameter( 'plugin.url', MONEY_QUIZ_PLUGIN_URL );
		$this->container->parameter( 'plugin.basename', MONEY_QUIZ_PLUGIN_BASENAME );
		$this->container->parameter( 'plugin.text_domain', 'money-quiz' );
		$this->container->parameter( 'plugin.min_wp_version', MONEY_QUIZ_MINIMUM_WP_VERSION );
		$this->container->parameter( 'plugin.min_php_version', MONEY_QUIZ_MINIMUM_PHP_VERSION );
	}

	/**
	 * Register service providers.
	 *
	 * @since 7.0.0
	 *
	 * @return void
	 *
	 * @throws \Exception If a provider fails to register.
	 */
	private function register_providers(): void {
		foreach ( $this->providers as $provider_class ) {
			if ( ! class_exists( $provider_class ) ) {
				throw new \Exception(
					sprintf( 'Service provider class not found: %s', $provider_class )
				);
			}

			$provider = new $provider_class( $this->container );

			if ( ! $provider instanceof ServiceProviderInterface ) {
				throw new \Exception(
					sprintf( 'Invalid service provider: %s', $provider_class )
				);
			}

			// Register the provider.
			$provider->register();

			// Store provider instance for booting.
			$this->container->set(
				'provider.' . $provider_class,
				function() use ( $provider ) {
					return $provider;
				}
			);
		}
	}

	/**
	 * Boot service providers.
	 *
	 * @since 7.0.0
	 *
	 * @return void
	 */
	private function boot_providers(): void {
		foreach ( $this->providers as $provider_class ) {
			$provider = $this->container->get( 'provider.' . $provider_class );
			
			if ( $provider instanceof ServiceProviderInterface ) {
				$provider->boot();
			}
		}
	}

	/**
	 * Initialize the main plugin class.
	 *
	 * @since 7.0.0
	 *
	 * @return void
	 */
	private function initialize_plugin(): void {
		// Create and initialize the plugin instance.
		$this->plugin = $this->container->get( Plugin::class );
		$this->plugin->init();
	}

	/**
	 * Get the plugin instance.
	 *
	 * @since 7.0.0
	 *
	 * @return Plugin|null Plugin instance or null if not initialized.
	 */
	public function get_plugin(): ?Plugin {
		return $this->plugin;
	}

	/**
	 * Get the container instance.
	 *
	 * @since 7.0.0
	 *
	 * @return Container Container instance.
	 */
	public function get_container(): Container {
		return $this->container;
	}

	/**
	 * Check if the plugin has been bootstrapped.
	 *
	 * @since 7.0.0
	 *
	 * @return bool True if booted, false otherwise.
	 */
	public function is_booted(): bool {
		return $this->booted;
	}

	/**
	 * Add a custom service provider.
	 *
	 * @since 7.0.0
	 *
	 * @param string $provider_class Service provider class name.
	 * @return void
	 *
	 * @throws \Exception If called after bootstrap.
	 */
	public function add_provider( string $provider_class ): void {
		if ( $this->booted ) {
			throw new \Exception( 'Cannot add providers after bootstrap.' );
		}

		$this->providers[] = $provider_class;
	}
}