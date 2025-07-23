<?php
/**
 * Plugin Manager
 *
 * Centralizes all plugin hooks and initialization.
 *
 * @package MoneyQuiz\Core
 * @since   7.0.0
 */

namespace MoneyQuiz\Core;

// Security: Prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * Plugin manager class.
 *
 * @since 7.0.0
 */
class PluginManager {

	/**
	 * Container instance.
	 *
	 * @var Container|null
	 */
	private ?Container $container = null;

	/**
	 * Bootstrap instance.
	 *
	 * @var Bootstrap|null
	 */
	private ?Bootstrap $bootstrap = null;

	/**
	 * Register all plugin hooks.
	 *
	 * @since 7.0.0
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		// Initialize the plugin.
		add_action( 'plugins_loaded', [ $this, 'init_plugin' ], 0 );

		// Register activation hook.
		register_activation_hook( MONEY_QUIZ_PLUGIN_FILE, [ $this, 'activate' ] );

		// Register deactivation hook.
		register_deactivation_hook( MONEY_QUIZ_PLUGIN_FILE, [ $this, 'deactivate' ] );
	}

	/**
	 * Initialize the plugin.
	 *
	 * @since 7.0.0
	 *
	 * @return void
	 */
	public function init_plugin(): void {
		// Check WordPress version compatibility.
		if ( version_compare( get_bloginfo( 'version' ), MONEY_QUIZ_MINIMUM_WP_VERSION, '<' ) ) {
			add_action( 'admin_notices', [ $this, 'wp_version_notice' ] );
			return;
		}

		try {
			// Create dependency injection container.
			$this->container = new Container();
			
			// Set container instance for global access.
			Container::set_instance( $this->container );

			// Bootstrap the plugin.
			$this->bootstrap = new Bootstrap( $this->container );
			$this->bootstrap->init();
			
			// Store bootstrap instance in container.
			$this->container->set( 'bootstrap', function() {
				return $this->bootstrap;
			} );

			// Store plugin manager instance.
			$this->container->set( 'plugin_manager', function() {
				return $this;
			} );

		} catch ( \Exception $e ) {
			$this->handle_init_error( $e );
		}
	}

	/**
	 * Handle plugin activation.
	 *
	 * @since 7.0.0
	 *
	 * @return void
	 */
	public function activate(): void {
		require_once MONEY_QUIZ_PLUGIN_DIR . 'src/Core/Activator.php';
		Activator::activate();
	}

	/**
	 * Handle plugin deactivation.
	 *
	 * @since 7.0.0
	 *
	 * @return void
	 */
	public function deactivate(): void {
		require_once MONEY_QUIZ_PLUGIN_DIR . 'src/Core/Deactivator.php';
		Deactivator::deactivate();
	}

	/**
	 * Show WordPress version notice.
	 *
	 * @since 7.0.0
	 *
	 * @return void
	 */
	public function wp_version_notice(): void {
		printf(
			'<div class="notice notice-error"><p>%s</p></div>',
			sprintf(
				/* translators: %s: Required WordPress version */
				esc_html__( 'Money Quiz requires WordPress version %s or higher. Please upgrade WordPress to use this plugin.', 'money-quiz' ),
				MONEY_QUIZ_MINIMUM_WP_VERSION
			)
		);
	}

	/**
	 * Handle initialization error.
	 *
	 * @since 7.0.0
	 *
	 * @param \Exception $e Exception instance.
	 * @return void
	 */
	private function handle_init_error( \Exception $e ): void {
		// Log error if debug is enabled.
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( sprintf( 'Money Quiz initialization error: %s', $e->getMessage() ) );
			error_log( sprintf( 'Stack trace: %s', $e->getTraceAsString() ) );
		}

		// Show admin notice.
		add_action(
			'admin_notices',
			function() use ( $e ) {
				printf(
					'<div class="notice notice-error"><p>%s</p></div>',
					sprintf(
						/* translators: %s: Error message */
						esc_html__( 'Money Quiz failed to initialize: %s', 'money-quiz' ),
						esc_html( $e->getMessage() )
					)
				);
			}
		);
	}

	/**
	 * Get container instance.
	 *
	 * @since 7.0.0
	 *
	 * @return Container|null Container instance.
	 */
	public function get_container(): ?Container {
		return $this->container;
	}

	/**
	 * Get bootstrap instance.
	 *
	 * @since 7.0.0
	 *
	 * @return Bootstrap|null Bootstrap instance.
	 */
	public function get_bootstrap(): ?Bootstrap {
		return $this->bootstrap;
	}
}