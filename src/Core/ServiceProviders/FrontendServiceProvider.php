<?php
/**
 * Frontend Service Provider
 *
 * Registers frontend-related services for the Money Quiz plugin.
 *
 * @package MoneyQuiz\Core\ServiceProviders
 * @since   7.0.0
 */

namespace MoneyQuiz\Core\ServiceProviders;

use MoneyQuiz\Core\AbstractServiceProvider;
use MoneyQuiz\Frontend\ShortcodeManager;
use MoneyQuiz\Frontend\QuizRenderer;
use MoneyQuiz\Frontend\ResultRenderer;
use MoneyQuiz\Frontend\AssetManager;
use MoneyQuiz\Frontend\AjaxHandler;
use MoneyQuiz\Frontend\SessionManager;
use MoneyQuiz\Frontend\ProgressTracker;
use MoneyQuiz\Frontend\FormValidator;

// Security: Prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * Frontend service provider class.
 *
 * @since 7.0.0
 */
class FrontendServiceProvider extends AbstractServiceProvider {

	/**
	 * Register services.
	 *
	 * @since 7.0.0
	 *
	 * @return void
	 */
	public function register(): void {
		// Register shortcode manager.
		$this->singleton(
			ShortcodeManager::class,
			function( $container ) {
				return new ShortcodeManager(
					$container->get( QuizRenderer::class ),
					$container->get( ResultRenderer::class )
				);
			}
		);

		// Register quiz renderer.
		$this->singleton(
			QuizRenderer::class,
			function( $container ) {
				return new QuizRenderer(
					$container->get( 'QuizRepository' ),
					$container->get( 'OutputEscaper' ),
					$container->get( SessionManager::class ),
					$container->get( 'NonceManager' )
				);
			}
		);

		// Register result renderer.
		$this->singleton(
			ResultRenderer::class,
			function( $container ) {
				return new ResultRenderer(
					$container->get( 'ResultRepository' ),
					$container->get( 'OutputEscaper' ),
					$container->get( SessionManager::class )
				);
			}
		);

		// Register frontend asset manager.
		$this->singleton(
			AssetManager::class,
			function( $container ) {
				return new AssetManager(
					$container->param( 'plugin.url' ),
					$container->param( 'plugin.version' ),
					$container->param( 'plugin.text_domain' )
				);
			}
		);

		// Register AJAX handler.
		$this->singleton(
			AjaxHandler::class,
			function( $container ) {
				return new AjaxHandler(
					$container->get( 'QuizRepository' ),
					$container->get( 'ResultRepository' ),
					$container->get( 'InputValidator' ),
					$container->get( 'NonceManager' ),
					$container->get( 'RateLimiter' ),
					$container->get( SessionManager::class )
				);
			}
		);

		// Register session manager.
		$this->singleton(
			SessionManager::class,
			function( $container ) {
				return new SessionManager(
					$container->param( 'plugin.text_domain' )
				);
			}
		);

		// Register progress tracker.
		$this->singleton(
			ProgressTracker::class,
			function( $container ) {
				return new ProgressTracker(
					$container->get( SessionManager::class )
				);
			}
		);

		// Register form validator.
		$this->singleton(
			FormValidator::class,
			function( $container ) {
				return new FormValidator(
					$container->get( 'InputValidator' ),
					$container->get( 'NonceManager' )
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
		// Only bootstrap frontend services on frontend.
		if ( is_admin() && ! wp_doing_ajax() ) {
			return;
		}

		// Initialize shortcodes.
		$shortcode_manager = $this->get( ShortcodeManager::class );
		add_action( 'init', [ $shortcode_manager, 'register_shortcodes' ] );

		// Initialize session handling.
		$session_manager = $this->get( SessionManager::class );
		add_action( 'init', [ $session_manager, 'init' ], 1 );

		// Enqueue frontend assets.
		$asset_manager = $this->get( AssetManager::class );
		add_action( 'wp_enqueue_scripts', [ $asset_manager, 'enqueue_assets' ] );

		// Register AJAX handlers.
		$this->register_ajax_handlers();

		// Add frontend-specific hooks.
		add_filter( 'body_class', [ $this, 'add_body_classes' ] );
		add_action( 'wp_head', [ $this, 'add_frontend_meta' ] );

		// Clean up expired sessions.
		add_action( 'money_quiz_hourly_cleanup', [ $session_manager, 'cleanup_expired_sessions' ] );

		// Schedule hourly cleanup if not already scheduled.
		if ( ! wp_next_scheduled( 'money_quiz_hourly_cleanup' ) ) {
			wp_schedule_event( time(), 'hourly', 'money_quiz_hourly_cleanup' );
		}
	}

	/**
	 * Register AJAX handlers.
	 *
	 * @since 7.0.0
	 *
	 * @return void
	 */
	private function register_ajax_handlers(): void {
		$ajax_handler = $this->get( AjaxHandler::class );

		// Public AJAX actions.
		$public_actions = [
			'money_quiz_start_quiz',
			'money_quiz_submit_answer',
			'money_quiz_get_results',
			'money_quiz_email_results',
			'money_quiz_track_event',
		];

		foreach ( $public_actions as $action ) {
			// Logged-in users
			add_action( 'wp_ajax_' . $action, function() use ( $ajax_handler, $action ) {
				// All AJAX handlers must verify nonce internally
				$ajax_handler->$action();
			} );
			
			// Non-logged-in users
			add_action( 'wp_ajax_nopriv_' . $action, function() use ( $ajax_handler, $action ) {
				// All AJAX handlers must verify nonce internally
				$ajax_handler->$action();
			} );
		}
	}

	/**
	 * Add body classes for Money Quiz pages.
	 *
	 * @since 7.0.0
	 *
	 * @param array $classes Body classes.
	 * @return array Modified classes.
	 */
	public function add_body_classes( array $classes ): array {
		global $post;

		if ( ! $post ) {
			return $classes;
		}

		// Check if page contains Money Quiz shortcode.
		if ( has_shortcode( $post->post_content, 'money_quiz' ) ||
		     has_shortcode( $post->post_content, 'money_quiz_result' ) ) {
			$classes[] = 'money-quiz-page';

			// Add theme-specific class for styling compatibility.
			$theme = wp_get_theme();
			$classes[] = 'money-quiz-theme-' . sanitize_html_class( $theme->get_stylesheet() );
		}

		return $classes;
	}

	/**
	 * Add frontend meta tags.
	 *
	 * @since 7.0.0
	 *
	 * @return void
	 */
	public function add_frontend_meta(): void {
		global $post;

		if ( ! $post || ! has_shortcode( $post->post_content, 'money_quiz' ) ) {
			return;
		}

		// Add meta tags for social sharing when on quiz pages.
		echo '<meta property="og:type" content="website" />' . PHP_EOL;
		echo '<meta name="twitter:card" content="summary_large_image" />' . PHP_EOL;
		
		// Allow customization via filter.
		$meta_tags = apply_filters( 'money_quiz_frontend_meta_tags', [] );
		
		foreach ( $meta_tags as $property => $content ) {
			printf(
				'<meta property="%s" content="%s" />' . PHP_EOL,
				esc_attr( $property ),
				esc_attr( $content )
			);
		}
	}
}