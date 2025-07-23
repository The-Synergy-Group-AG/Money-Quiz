<?php
/**
 * Security Service Provider
 *
 * Registers security-related services for the Money Quiz plugin.
 *
 * @package MoneyQuiz\Core\ServiceProviders
 * @since   7.0.0
 */

namespace MoneyQuiz\Core\ServiceProviders;

use MoneyQuiz\Core\AbstractServiceProvider;
use MoneyQuiz\Security\InputValidator;
use MoneyQuiz\Security\OutputEscaper;
use MoneyQuiz\Security\NonceManager;
use MoneyQuiz\Security\RateLimiter;
use MoneyQuiz\Security\CSRFProtection;
use MoneyQuiz\Security\AccessControl;
use MoneyQuiz\Security\SecurityAuditor;

// Security: Prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * Security service provider class.
 *
 * @since 7.0.0
 */
class SecurityServiceProvider extends AbstractServiceProvider {

	/**
	 * Register services.
	 *
	 * @since 7.0.0
	 *
	 * @return void
	 */
	public function register(): void {
		// Register InputValidator.
		$this->singleton(
			InputValidator::class,
			function( $container ) {
				return new InputValidator();
			}
		);

		// Register OutputEscaper.
		$this->singleton(
			OutputEscaper::class,
			function( $container ) {
				return new OutputEscaper();
			}
		);

		// Register NonceManager.
		$this->singleton(
			NonceManager::class,
			function( $container ) {
				return new NonceManager(
					$container->param( 'plugin.text_domain' )
				);
			}
		);

		// Register RateLimiter with database backend.
		$this->singleton(
			RateLimiter::class,
			function( $container ) {
				global $wpdb;
				return new RateLimiter(
					$wpdb,
					$container->param( 'plugin.text_domain' )
				);
			}
		);

		// Register CSRFProtection.
		$this->singleton(
			CSRFProtection::class,
			function( $container ) {
				return new CSRFProtection(
					$container->get( NonceManager::class )
				);
			}
		);

		// Register AccessControl.
		$this->singleton(
			AccessControl::class,
			function( $container ) {
				return new AccessControl();
			}
		);

		// Register SecurityAuditor.
		$this->singleton(
			SecurityAuditor::class,
			function( $container ) {
				return new SecurityAuditor(
					$container->get( 'Logger' )
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
		// Initialize CSRF protection.
		$csrf = $this->get( CSRFProtection::class );
		add_action( 'init', [ $csrf, 'init' ] );

		// Initialize rate limiter cleanup.
		$rate_limiter = $this->get( RateLimiter::class );
		add_action( 'money_quiz_daily_cleanup', [ $rate_limiter, 'cleanup' ] );

		// Add security headers.
		add_action( 'send_headers', [ $this, 'add_security_headers' ] );

		// Prevent user enumeration.
		add_filter( 'rest_endpoints', [ $this, 'disable_user_endpoints' ] );
		
		// Remove version strings from assets.
		add_filter( 'style_loader_src', [ $this, 'remove_version_strings' ], 9999 );
		add_filter( 'script_loader_src', [ $this, 'remove_version_strings' ], 9999 );

		// Schedule daily security cleanup if not already scheduled.
		if ( ! wp_next_scheduled( 'money_quiz_daily_cleanup' ) ) {
			wp_schedule_event( time(), 'daily', 'money_quiz_daily_cleanup' );
		}
	}

	/**
	 * Add security headers.
	 *
	 * @since 7.0.0
	 *
	 * @return void
	 */
	public function add_security_headers(): void {
		// Only add headers on frontend and Money Quiz admin pages.
		if ( is_admin() && ! $this->is_money_quiz_page() ) {
			return;
		}

		header( 'X-Content-Type-Options: nosniff' );
		header( 'X-Frame-Options: SAMEORIGIN' );
		header( 'X-XSS-Protection: 1; mode=block' );
		header( 'Referrer-Policy: strict-origin-when-cross-origin' );
		
		// Add CSP header for Money Quiz pages.
		if ( $this->is_money_quiz_page() ) {
			// Generate nonce for inline scripts/styles.
			$nonce = wp_create_nonce( 'money_quiz_csp' );
			
			// Store nonce for use in templates.
			$GLOBALS['money_quiz_csp_nonce'] = $nonce;
			
			// Strict CSP without unsafe-inline or unsafe-eval.
			$csp = sprintf(
				"default-src 'self'; " .
				"script-src 'self' 'nonce-%s'; " .
				"style-src 'self' 'nonce-%s'; " .
				"img-src 'self' data: https:; " .
				"font-src 'self'; " .
				"connect-src 'self'; " .
				"frame-ancestors 'none'; " .
				"base-uri 'self'; " .
				"form-action 'self';",
				$nonce,
				$nonce
			);
			
			header( 'Content-Security-Policy: ' . $csp );
		}
	}

	/**
	 * Check if current page is a Money Quiz admin page.
	 *
	 * @since 7.0.0
	 *
	 * @return bool True if Money Quiz page.
	 */
	private function is_money_quiz_page(): bool {
		if ( ! is_admin() ) {
			return false;
		}

		$screen = get_current_screen();
		if ( ! $screen ) {
			return false;
		}

		return strpos( $screen->id, 'money-quiz' ) !== false;
	}

	/**
	 * Disable user enumeration endpoints.
	 *
	 * @since 7.0.0
	 *
	 * @param array $endpoints REST endpoints.
	 * @return array Modified endpoints.
	 */
	public function disable_user_endpoints( array $endpoints ): array {
		// Remove user endpoints to prevent enumeration.
		if ( isset( $endpoints['/wp/v2/users'] ) ) {
			unset( $endpoints['/wp/v2/users'] );
		}
		if ( isset( $endpoints['/wp/v2/users/(?P<id>[\d]+)'] ) ) {
			unset( $endpoints['/wp/v2/users/(?P<id>[\d]+)'] );
		}

		return $endpoints;
	}

	/**
	 * Remove version strings from assets.
	 *
	 * @since 7.0.0
	 *
	 * @param string $src Asset source URL.
	 * @return string Modified URL.
	 */
	public function remove_version_strings( string $src ): string {
		// Only remove versions from Money Quiz assets.
		if ( strpos( $src, 'money-quiz' ) === false ) {
			return $src;
		}

		return remove_query_arg( 'ver', $src );
	}
}