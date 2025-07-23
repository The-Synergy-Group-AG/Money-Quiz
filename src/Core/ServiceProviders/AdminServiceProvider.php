<?php
/**
 * Admin Service Provider
 *
 * Registers admin-related services for the Money Quiz plugin.
 *
 * @package MoneyQuiz\Core\ServiceProviders
 * @since   7.0.0
 */

namespace MoneyQuiz\Core\ServiceProviders;

use MoneyQuiz\Core\AbstractServiceProvider;
use MoneyQuiz\Admin\MenuManager;
use MoneyQuiz\Admin\DashboardController;
use MoneyQuiz\Admin\QuizController;
use MoneyQuiz\Admin\SettingsController;
use MoneyQuiz\Admin\AnalyticsController;
use MoneyQuiz\Admin\ImportExportController;
use MoneyQuiz\Admin\NoticeManager;
use MoneyQuiz\Admin\AdminAssets;
use MoneyQuiz\Admin\BulkActions;
use MoneyQuiz\Admin\ScreenOptions;

// Security: Prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * Admin service provider class.
 *
 * @since 7.0.0
 */
class AdminServiceProvider extends AbstractServiceProvider {

	/**
	 * Register services.
	 *
	 * @since 7.0.0
	 *
	 * @return void
	 */
	public function register(): void {
		// Register menu manager.
		$this->singleton(
			MenuManager::class,
			function( $container ) {
				return new MenuManager(
					$container->param( 'plugin.text_domain' )
				);
			}
		);

		// Register admin controllers.
		$this->singleton(
			DashboardController::class,
			function( $container ) {
				return new DashboardController(
					$container->get( 'AnalyticsRepository' ),
					$container->get( 'QuizRepository' )
				);
			}
		);

		$this->singleton(
			QuizController::class,
			function( $container ) {
				return new QuizController(
					$container->get( 'QuizRepository' ),
					$container->get( 'InputValidator' ),
					$container->get( 'NonceManager' )
				);
			}
		);

		$this->singleton(
			SettingsController::class,
			function( $container ) {
				return new SettingsController(
					$container->get( 'ConfigManager' ),
					$container->get( 'InputValidator' ),
					$container->get( 'NonceManager' )
				);
			}
		);

		$this->singleton(
			AnalyticsController::class,
			function( $container ) {
				return new AnalyticsController(
					$container->get( 'AnalyticsRepository' ),
					$container->get( 'OutputEscaper' )
				);
			}
		);

		$this->singleton(
			ImportExportController::class,
			function( $container ) {
				return new ImportExportController(
					$container->get( 'QuizRepository' ),
					$container->get( 'InputValidator' ),
					$container->get( 'NonceManager' )
				);
			}
		);

		// Register notice manager.
		$this->singleton(
			NoticeManager::class,
			function( $container ) {
				return new NoticeManager();
			}
		);

		// Register admin assets manager.
		$this->singleton(
			AdminAssets::class,
			function( $container ) {
				return new AdminAssets(
					$container->param( 'plugin.url' ),
					$container->param( 'plugin.version' )
				);
			}
		);

		// Register bulk actions handler.
		$this->singleton(
			BulkActions::class,
			function( $container ) {
				return new BulkActions(
					$container->get( 'QuizRepository' ),
					$container->get( 'NonceManager' )
				);
			}
		);

		// Register screen options handler.
		$this->singleton(
			ScreenOptions::class,
			function( $container ) {
				return new ScreenOptions();
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
		// Only bootstrap admin services in admin context.
		if ( ! is_admin() ) {
			return;
		}

		// Register admin menu.
		$menu_manager = $this->get( MenuManager::class );
		add_action( 'admin_menu', [ $menu_manager, 'register_menus' ] );

		// Register admin assets.
		$admin_assets = $this->get( AdminAssets::class );
		add_action( 'admin_enqueue_scripts', [ $admin_assets, 'enqueue_assets' ] );

		// Initialize notice manager.
		$notice_manager = $this->get( NoticeManager::class );
		add_action( 'admin_notices', [ $notice_manager, 'display_notices' ] );

		// Register AJAX handlers.
		$this->register_ajax_handlers();

		// Add admin-specific filters.
		add_filter( 'admin_body_class', [ $this, 'add_admin_body_class' ] );
		add_filter( 'admin_footer_text', [ $this, 'customize_admin_footer' ] );

		// Initialize bulk actions.
		$bulk_actions = $this->get( BulkActions::class );
		add_filter( 'bulk_actions-edit-money_quiz', [ $bulk_actions, 'register_bulk_actions' ] );
		add_filter( 'handle_bulk_actions-edit-money_quiz', [ $bulk_actions, 'handle_bulk_actions' ], 10, 3 );

		// Initialize screen options.
		$screen_options = $this->get( ScreenOptions::class );
		add_action( 'current_screen', [ $screen_options, 'add_screen_options' ] );
	}

	/**
	 * Register AJAX handlers.
	 *
	 * @since 7.0.0
	 *
	 * @return void
	 */
	private function register_ajax_handlers(): void {
		$ajax_actions = [
			'money_quiz_save_quiz'       => [ QuizController::class, 'ajax_save_quiz' ],
			'money_quiz_delete_quiz'     => [ QuizController::class, 'ajax_delete_quiz' ],
			'money_quiz_duplicate_quiz'  => [ QuizController::class, 'ajax_duplicate_quiz' ],
			'money_quiz_get_analytics'   => [ AnalyticsController::class, 'ajax_get_analytics' ],
			'money_quiz_export_data'     => [ ImportExportController::class, 'ajax_export_data' ],
			'money_quiz_import_data'     => [ ImportExportController::class, 'ajax_import_data' ],
			'money_quiz_dismiss_notice'  => [ NoticeManager::class, 'ajax_dismiss_notice' ],
		];

		foreach ( $ajax_actions as $action => $callback ) {
			add_action( 'wp_ajax_' . $action, function() use ( $callback, $action ) {
				// Verify nonce for all AJAX requests
				$nonce_manager = $this->get( NonceManager::class );
				if ( ! $nonce_manager->verify( $action . '_nonce' ) ) {
					wp_send_json_error( [ 'message' => __( 'Security verification failed.', 'money-quiz' ) ], 403 );
					wp_die();
				}
				
				$controller = $this->get( $callback[0] );
				call_user_func( [ $controller, $callback[1] ] );
			} );
		}
	}

	/**
	 * Add admin body classes.
	 *
	 * @since 7.0.0
	 *
	 * @param string $classes Admin body classes.
	 * @return string Modified classes.
	 */
	public function add_admin_body_class( string $classes ): string {
		$screen = get_current_screen();
		
		if ( $screen && strpos( $screen->id, 'money-quiz' ) !== false ) {
			$classes .= ' money-quiz-admin';
			
			// Add specific page class.
			$page_slug = sanitize_html_class( str_replace( 'money-quiz_page_', '', $screen->id ) );
			$classes .= ' money-quiz-' . $page_slug;
		}

		return $classes;
	}

	/**
	 * Customize admin footer text.
	 *
	 * @since 7.0.0
	 *
	 * @param string $text Footer text.
	 * @return string Modified footer text.
	 */
	public function customize_admin_footer( string $text ): string {
		$screen = get_current_screen();
		
		if ( $screen && strpos( $screen->id, 'money-quiz' ) !== false ) {
			$text = sprintf(
				/* translators: %s: plugin version */
				esc_html__( 'Money Quiz v%s | Built with security and performance in mind', 'money-quiz' ),
				esc_html( $this->param( 'plugin.version' ) )
			);
		}

		return $text;
	}
}