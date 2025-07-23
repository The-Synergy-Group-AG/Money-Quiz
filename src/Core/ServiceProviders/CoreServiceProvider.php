<?php
/**
 * Core Service Provider
 *
 * Registers core services for the Money Quiz plugin.
 *
 * @package MoneyQuiz\Core\ServiceProviders
 * @since   7.0.0
 */

namespace MoneyQuiz\Core\ServiceProviders;

use MoneyQuiz\Core\AbstractServiceProvider;
use MoneyQuiz\Core\Plugin;
use MoneyQuiz\Core\Hooks\HookManager;
use MoneyQuiz\Core\Config\ConfigManager;
use MoneyQuiz\Core\Logging\Logger;
use MoneyQuiz\Core\Cache\CacheManager;
use MoneyQuiz\Core\VersionChecker;

// Security: Prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * Core service provider class.
 *
 * @since 7.0.0
 */
class CoreServiceProvider extends AbstractServiceProvider {

	/**
	 * Register services.
	 *
	 * @since 7.0.0
	 *
	 * @return void
	 */
	public function register(): void {
		// Register Plugin main class.
		$this->singleton(
			Plugin::class,
			function( $container ) {
				return new Plugin( $container );
			}
		);

		// Register HookManager.
		$this->singleton(
			HookManager::class,
			function( $container ) {
				return new HookManager();
			}
		);

		// Register ConfigManager.
		$this->singleton(
			ConfigManager::class,
			function( $container ) {
				return new ConfigManager(
					$container->param( 'plugin.dir' ) . 'config/'
				);
			}
		);

		// Register Logger.
		$this->singleton(
			Logger::class,
			function( $container ) {
				$upload_dir = wp_upload_dir();
				$log_dir = $upload_dir['basedir'] . '/money-quiz-logs/';
				
				// Create log directory if it doesn't exist.
				if ( ! file_exists( $log_dir ) ) {
					wp_mkdir_p( $log_dir );
					
					// Add .htaccess to protect log files.
					$htaccess_content = "Order deny,allow\nDeny from all";
					file_put_contents( $log_dir . '.htaccess', $htaccess_content );
				}
				
				return new Logger( $log_dir );
			}
		);

		// Register CacheManager.
		$this->singleton(
			CacheManager::class,
			function( $container ) {
				return new CacheManager(
					$container->param( 'plugin.text_domain' )
				);
			}
		);

		// Register version checker.
		$this->singleton(
			VersionChecker::class,
			function( $container ) {
				return new VersionChecker(
					$container->param( 'plugin.version' ),
					$container->param( 'plugin.min_php_version' ),
					$container->param( 'plugin.min_wp_version' ),
					'https://api.moneyquiz.com/v1/updates'
				);
			}
		);
	}

	/**
	 * Bootstrap services.
	 *
	 * @since 7.0.0
	 *
	 * @return void
	 */
	public function boot(): void {
		// Load plugin text domain.
		add_action( 'init', [ $this, 'load_textdomain' ] );

		// Initialize logging if debug mode is enabled.
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			$logger = $this->get( Logger::class );
			$logger->info( 'Money Quiz plugin initialized', [
				'version' => $this->param( 'plugin.version' ),
				'php_version' => PHP_VERSION,
				'wp_version' => get_bloginfo( 'version' ),
			] );
		}
	}

	/**
	 * Load plugin text domain.
	 *
	 * @since 7.0.0
	 *
	 * @return void
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain(
			$this->param( 'plugin.text_domain' ),
			false,
			dirname( $this->param( 'plugin.basename' ) ) . '/languages'
		);
	}
}