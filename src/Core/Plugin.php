<?php
/**
 * Main Plugin Class
 *
 * Core plugin class that coordinates all functionality.
 *
 * @package MoneyQuiz\Core
 * @since   7.0.0
 */

namespace MoneyQuiz\Core;

// Security: Prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * Main plugin class.
 *
 * @since 7.0.0
 */
class Plugin {

	/**
	 * Container instance.
	 *
	 * @var Container
	 */
	private Container $container;

	/**
	 * Plugin initialized flag.
	 *
	 * @var bool
	 */
	private bool $initialized = false;

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
	 * @throws \Exception If plugin already initialized.
	 */
	public function init(): void {
		if ( $this->initialized ) {
			throw new \Exception( 'Plugin has already been initialized.' );
		}

		// Register core hooks.
		$this->register_hooks();

		// Initialize components based on context.
		$this->initialize_components();

		// Mark as initialized.
		$this->initialized = true;

		// Fire action for extenders.
		do_action( 'money_quiz_initialized', $this );
	}

	/**
	 * Register core plugin hooks.
	 *
	 * @since 7.0.0
	 *
	 * @return void
	 */
	private function register_hooks(): void {
		// Plugin action links.
		add_filter(
			'plugin_action_links_' . $this->container->param( 'plugin.basename' ),
			[ $this, 'add_action_links' ]
		);

		// Plugin row meta.
		add_filter(
			'plugin_row_meta',
			[ $this, 'add_row_meta' ],
			10,
			2
		);

		// Handle upgrades.
		add_action( 'upgrader_process_complete', [ $this, 'handle_upgrade' ], 10, 2 );

		// Register uninstall hook.
		register_uninstall_hook(
			$this->container->param( 'plugin.file' ),
			[ __CLASS__, 'uninstall' ]
		);
	}

	/**
	 * Initialize components based on context.
	 *
	 * @since 7.0.0
	 *
	 * @return void
	 */
	private function initialize_components(): void {
		// Initialize hook manager.
		$hook_manager = $this->container->get( 'HookManager' );
		$hook_manager->init();

		// Load configuration.
		$config_manager = $this->container->get( 'ConfigManager' );
		$config_manager->load();

		// Check if we need to run setup.
		if ( $this->needs_setup() ) {
			$this->run_setup();
		}

		// Initialize context-specific components.
		if ( is_admin() ) {
			$this->initialize_admin();
		} else {
			$this->initialize_frontend();
		}

		// Always initialize API.
		$this->initialize_api();
	}

	/**
	 * Initialize admin components.
	 *
	 * @since 7.0.0
	 *
	 * @return void
	 */
	private function initialize_admin(): void {
		// Admin-specific initialization handled by AdminServiceProvider.
		do_action( 'money_quiz_admin_init', $this );
	}

	/**
	 * Initialize frontend components.
	 *
	 * @since 7.0.0
	 *
	 * @return void
	 */
	private function initialize_frontend(): void {
		// Frontend-specific initialization handled by FrontendServiceProvider.
		do_action( 'money_quiz_frontend_init', $this );
	}

	/**
	 * Initialize API components.
	 *
	 * @since 7.0.0
	 *
	 * @return void
	 */
	private function initialize_api(): void {
		// API-specific initialization handled by APIServiceProvider.
		do_action( 'money_quiz_api_init', $this );
	}

	/**
	 * Check if plugin needs setup.
	 *
	 * @since 7.0.0
	 *
	 * @return bool True if setup needed.
	 */
	private function needs_setup(): bool {
		$installed_version = get_option( 'money_quiz_version' );
		$current_version = $this->container->param( 'plugin.version' );

		return version_compare( $installed_version, $current_version, '<' );
	}

	/**
	 * Run plugin setup.
	 *
	 * @since 7.0.0
	 *
	 * @return void
	 */
	private function run_setup(): void {
		// Update version.
		update_option( 'money_quiz_version', $this->container->param( 'plugin.version' ) );

		// Run migrations if needed.
		try {
			$migration_manager = $this->container->get( 'MigrationManager' );
			$migration_manager->run();
		} catch ( \Exception $e ) {
			// Log error but don't break the plugin.
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'Money Quiz setup error: ' . $e->getMessage() );
			}
		}

		// Fire setup complete action.
		do_action( 'money_quiz_setup_complete', $this );
	}

	/**
	 * Add plugin action links.
	 *
	 * @since 7.0.0
	 *
	 * @param array $links Existing links.
	 * @return array Modified links.
	 */
	public function add_action_links( array $links ): array {
		$action_links = [
			'settings' => sprintf(
				'<a href="%s">%s</a>',
				esc_url( admin_url( 'admin.php?page=money-quiz-settings' ) ),
				esc_html__( 'Settings', 'money-quiz' )
			),
			'docs' => sprintf(
				'<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
				esc_url( 'https://docs.moneyquiz.com' ),
				esc_html__( 'Documentation', 'money-quiz' )
			),
		];

		return array_merge( $action_links, $links );
	}

	/**
	 * Add plugin row meta.
	 *
	 * @since 7.0.0
	 *
	 * @param array  $links       Existing links.
	 * @param string $plugin_file Plugin file path.
	 * @return array Modified links.
	 */
	public function add_row_meta( array $links, string $plugin_file ): array {
		if ( $plugin_file !== $this->container->param( 'plugin.basename' ) ) {
			return $links;
		}

		$row_meta = [
			'changelog' => sprintf(
				'<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
				esc_url( 'https://docs.moneyquiz.com/changelog' ),
				esc_html__( 'Changelog', 'money-quiz' )
			),
			'support' => sprintf(
				'<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
				esc_url( 'https://support.moneyquiz.com' ),
				esc_html__( 'Support', 'money-quiz' )
			),
		];

		return array_merge( $links, $row_meta );
	}

	/**
	 * Handle plugin upgrade.
	 *
	 * @since 7.0.0
	 *
	 * @param object $upgrader_object Upgrader object.
	 * @param array  $options         Upgrade options.
	 * @return void
	 */
	public function handle_upgrade( $upgrader_object, array $options ): void {
		if ( $options['action'] !== 'update' || $options['type'] !== 'plugin' ) {
			return;
		}

		$current_plugin = $this->container->param( 'plugin.basename' );

		foreach ( $options['plugins'] as $plugin ) {
			if ( $plugin === $current_plugin ) {
				// Clear caches.
				$cache_manager = $this->container->get( 'CacheManager' );
				$cache_manager->flush();

				// Run upgrade routines.
				do_action( 'money_quiz_upgraded', $this );
				break;
			}
		}
	}

	/**
	 * Get container instance.
	 *
	 * @since 7.0.0
	 *
	 * @return Container Container instance.
	 */
	public function get_container(): Container {
		return $this->container;
	}

	/**
	 * Get a service from the container.
	 *
	 * @since 7.0.0
	 *
	 * @param string $id Service identifier.
	 * @return mixed Service instance.
	 */
	public function get( string $id ) {
		return $this->container->get( $id );
	}

	/**
	 * Check if plugin is initialized.
	 *
	 * @since 7.0.0
	 *
	 * @return bool True if initialized.
	 */
	public function is_initialized(): bool {
		return $this->initialized;
	}

	/**
	 * Uninstall the plugin.
	 *
	 * @since 7.0.0
	 *
	 * @return void
	 */
	public static function uninstall(): void {
		// Check if user has permission.
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		// Get uninstall option.
		$remove_data = get_option( 'money_quiz_remove_data_on_uninstall', false );

		if ( ! $remove_data ) {
			return;
		}

		// Remove all plugin data.
		self::uninstall_database_tables();
		self::uninstall_options();
		self::uninstall_transients();
		self::uninstall_scheduled_events();
		self::uninstall_capabilities();
		self::uninstall_files();

		// Fire uninstall action.
		do_action( 'money_quiz_uninstalled' );
	}

	/**
	 * Drop database tables.
	 *
	 * @since 7.0.0
	 *
	 * @return void
	 */
	private static function uninstall_database_tables(): void {
		global $wpdb;

		$tables = [
			$wpdb->prefix . 'money_quiz_quizzes',
			$wpdb->prefix . 'money_quiz_questions',
			$wpdb->prefix . 'money_quiz_answers',
			$wpdb->prefix . 'money_quiz_results',
			$wpdb->prefix . 'money_quiz_responses',
			$wpdb->prefix . 'money_quiz_analytics',
			$wpdb->prefix . 'money_quiz_rate_limits',
		];

		foreach ( $tables as $table ) {
			$wpdb->query( "DROP TABLE IF EXISTS $table" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
	}

	/**
	 * Remove plugin options.
	 *
	 * @since 7.0.0
	 *
	 * @return void
	 */
	private static function uninstall_options(): void {
		$options = [
			'money_quiz_version',
			'money_quiz_settings',
			'money_quiz_remove_data_on_uninstall',
			'money_quiz_installed',
			'money_quiz_deactivated_at',
		];

		foreach ( $options as $option ) {
			delete_option( $option );
		}
	}

	/**
	 * Remove plugin transients.
	 *
	 * @since 7.0.0
	 *
	 * @return void
	 */
	private static function uninstall_transients(): void {
		global $wpdb;

		$wpdb->query( "DELETE FROM $wpdb->options WHERE option_name LIKE '_transient_money_quiz_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->query( "DELETE FROM $wpdb->options WHERE option_name LIKE '_transient_timeout_money_quiz_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Clear scheduled events.
	 *
	 * @since 7.0.0
	 *
	 * @return void
	 */
	private static function uninstall_scheduled_events(): void {
		$events = [
			'money_quiz_daily_cleanup',
			'money_quiz_hourly_cleanup',
		];

		foreach ( $events as $event ) {
			wp_clear_scheduled_hook( $event );
		}
	}

	/**
	 * Remove plugin capabilities.
	 *
	 * @since 7.0.0
	 *
	 * @return void
	 */
	private static function uninstall_capabilities(): void {
		$roles = [ 'administrator' ];
		$capabilities = [
			'manage_money_quiz',
			'create_money_quiz',
			'edit_money_quiz',
			'delete_money_quiz',
			'view_money_quiz_analytics',
			'export_money_quiz_data',
			'manage_money_quiz_settings',
		];

		foreach ( $roles as $role_name ) {
			$role = get_role( $role_name );
			if ( $role ) {
				foreach ( $capabilities as $capability ) {
					$role->remove_cap( $capability );
				}
			}
		}
	}

	/**
	 * Remove plugin files and directories.
	 *
	 * @since 7.0.0
	 *
	 * @return void
	 */
	private static function uninstall_files(): void {
		$upload_dir = wp_upload_dir();
		$directories = [
			$upload_dir['basedir'] . '/money-quiz-logs/',
			$upload_dir['basedir'] . '/money-quiz-exports/',
			$upload_dir['basedir'] . '/money-quiz-temp/',
		];

		foreach ( $directories as $directory ) {
			if ( is_dir( $directory ) ) {
				// Remove all files in directory.
				$files = glob( $directory . '*' );
				foreach ( $files as $file ) {
					if ( is_file( $file ) ) {
						unlink( $file );
					}
				}
				// Remove directory.
				rmdir( $directory );
			}
		}
	}
}