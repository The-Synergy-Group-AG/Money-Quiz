<?php
/**
 * API Service Provider
 *
 * Registers REST API-related services for the Money Quiz plugin.
 *
 * @package MoneyQuiz\Core\ServiceProviders
 * @since   7.0.0
 */

namespace MoneyQuiz\Core\ServiceProviders;

use MoneyQuiz\Core\AbstractServiceProvider;
use MoneyQuiz\API\Router;
use MoneyQuiz\API\Authentication;
use MoneyQuiz\API\Controllers\QuizController;
use MoneyQuiz\API\Controllers\ResultController;
use MoneyQuiz\API\Controllers\AnalyticsController;
use MoneyQuiz\API\Controllers\SettingsController;
use MoneyQuiz\API\Middleware\RateLimitMiddleware;
use MoneyQuiz\API\Middleware\AuthenticationMiddleware;
use MoneyQuiz\API\Middleware\ValidationMiddleware;
use MoneyQuiz\API\ResponseFormatter;
use MoneyQuiz\Core\Exceptions\RateLimitExceededException;

// Security: Prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * API service provider class.
 *
 * @since 7.0.0
 */
class APIServiceProvider extends AbstractServiceProvider {

	/**
	 * API namespace.
	 *
	 * @var string
	 */
	private const API_NAMESPACE = 'money-quiz/v1';

	/**
	 * Register services.
	 *
	 * @since 7.0.0
	 *
	 * @return void
	 */
	public function register(): void {
		// Register API router.
		$this->singleton(
			Router::class,
			function( $container ) {
				return new Router( self::API_NAMESPACE );
			}
		);

		// Register authentication handler.
		$this->singleton(
			Authentication::class,
			function( $container ) {
				return new Authentication(
					$container->get( 'NonceManager' ),
					$container->get( 'AccessControl' )
				);
			}
		);

		// Register response formatter.
		$this->singleton(
			ResponseFormatter::class,
			function( $container ) {
				return new ResponseFormatter(
					$container->get( 'OutputEscaper' )
				);
			}
		);

		// Register API controllers.
		$this->singleton(
			QuizController::class,
			function( $container ) {
				return new QuizController(
					$container->get( 'QuizRepository' ),
					$container->get( 'InputValidator' ),
					$container->get( ResponseFormatter::class )
				);
			}
		);

		$this->singleton(
			ResultController::class,
			function( $container ) {
				return new ResultController(
					$container->get( 'ResultRepository' ),
					$container->get( 'InputValidator' ),
					$container->get( ResponseFormatter::class )
				);
			}
		);

		$this->singleton(
			AnalyticsController::class,
			function( $container ) {
				return new AnalyticsController(
					$container->get( 'AnalyticsRepository' ),
					$container->get( ResponseFormatter::class )
				);
			}
		);

		$this->singleton(
			SettingsController::class,
			function( $container ) {
				return new SettingsController(
					$container->get( 'ConfigManager' ),
					$container->get( 'InputValidator' ),
					$container->get( ResponseFormatter::class )
				);
			}
		);

		// Register middleware.
		$this->singleton(
			RateLimitMiddleware::class,
			function( $container ) {
				return new RateLimitMiddleware(
					$container->get( 'RateLimiter' )
				);
			}
		);

		$this->singleton(
			AuthenticationMiddleware::class,
			function( $container ) {
				return new AuthenticationMiddleware(
					$container->get( Authentication::class )
				);
			}
		);

		$this->singleton(
			ValidationMiddleware::class,
			function( $container ) {
				return new ValidationMiddleware(
					$container->get( 'InputValidator' )
				);
			}
		);

		// Register API namespace parameter.
		$this->parameter( 'api.namespace', self::API_NAMESPACE );
	}

	/**
	 * Bootstrap services.
	 *
	 * @since 7.0.0
	 *
	 * @return void
	 */
	public function boot(): void {
		// Register REST API routes.
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );

		// Add API-specific filters.
		add_filter( 'rest_authentication_errors', [ $this, 'check_authentication' ] );
		add_filter( 'rest_pre_dispatch', [ $this, 'pre_dispatch' ], 10, 3 );
		add_filter( 'rest_post_dispatch', [ $this, 'post_dispatch' ], 10, 3 );
		
		// Handle rate limit exceptions globally.
		add_action( 'rest_api_init', [ $this, 'register_exception_handler' ] );

		// Add CORS headers if enabled.
		if ( $this->is_cors_enabled() ) {
			add_action( 'rest_api_init', [ $this, 'add_cors_headers' ] );
		}
	}

	/**
	 * Register API routes.
	 *
	 * @since 7.0.0
	 *
	 * @return void
	 */
	public function register_routes(): void {
		$router = $this->get( Router::class );

		// Quiz routes.
		$router->get( '/quizzes', [ QuizController::class, 'index' ] );
		$router->get( '/quizzes/(?P<id>\d+)', [ QuizController::class, 'show' ] );
		$router->post( '/quizzes', [ QuizController::class, 'create' ] );
		$router->put( '/quizzes/(?P<id>\d+)', [ QuizController::class, 'update' ] );
		$router->delete( '/quizzes/(?P<id>\d+)', [ QuizController::class, 'delete' ] );

		// Result routes.
		$router->get( '/results', [ ResultController::class, 'index' ] );
		$router->get( '/results/(?P<id>\d+)', [ ResultController::class, 'show' ] );
		$router->post( '/results', [ ResultController::class, 'create' ] );

		// Analytics routes.
		$router->get( '/analytics/overview', [ AnalyticsController::class, 'overview' ] );
		$router->get( '/analytics/quizzes/(?P<id>\d+)', [ AnalyticsController::class, 'quiz_stats' ] );
		$router->get( '/analytics/export', [ AnalyticsController::class, 'export' ] );

		// Settings routes.
		$router->get( '/settings', [ SettingsController::class, 'index' ] );
		$router->post( '/settings', [ SettingsController::class, 'update' ] );

		// Apply middleware.
		$this->apply_middleware( $router );

		// Register all routes.
		$router->register();
	}

	/**
	 * Apply middleware to routes.
	 *
	 * @since 7.0.0
	 *
	 * @param Router $router Router instance.
	 * @return void
	 */
	private function apply_middleware( Router $router ): void {
		// Apply rate limiting to all routes.
		$rate_limiter = $this->get( RateLimitMiddleware::class );
		$router->middleware( '*', [ $rate_limiter, 'handle' ] );

		// Apply authentication to protected routes.
		$auth_middleware = $this->get( AuthenticationMiddleware::class );
		$protected_routes = [
			'/quizzes' => [ 'POST', 'PUT', 'DELETE' ],
			'/results' => [ 'GET' ],
			'/analytics/*' => [ 'GET' ],
			'/settings' => [ 'GET', 'POST' ],
		];

		foreach ( $protected_routes as $route => $methods ) {
			foreach ( $methods as $method ) {
				$router->middleware( $route, [ $auth_middleware, 'handle' ], $method );
			}
		}
	}

	/**
	 * Check authentication for API requests.
	 *
	 * @since 7.0.0
	 *
	 * @param WP_Error|null|true $result Authentication result.
	 * @return WP_Error|null|true Modified result.
	 */
	public function check_authentication( $result ) {
		// Only check for our API namespace.
		if ( ! $this->is_money_quiz_request() ) {
			return $result;
		}

		$authentication = $this->get( Authentication::class );
		return $authentication->check( $result );
	}

	/**
	 * Pre-dispatch hook for API requests.
	 *
	 * @since 7.0.0
	 *
	 * @param mixed           $result  Dispatch result.
	 * @param WP_REST_Server  $server  Server instance.
	 * @param WP_REST_Request $request Request object.
	 * @return mixed Modified result.
	 */
	public function pre_dispatch( $result, $server, $request ) {
		if ( ! $this->is_money_quiz_request() ) {
			return $result;
		}

		// Log API request if debug mode is enabled.
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			$logger = $this->get( 'Logger' );
			$logger->debug( 'API Request', [
				'method' => $request->get_method(),
				'route' => $request->get_route(),
				'params' => $request->get_params(),
			] );
		}

		return $result;
	}

	/**
	 * Post-dispatch hook for API requests.
	 *
	 * @since 7.0.0
	 *
	 * @param WP_REST_Response $response Response object.
	 * @param WP_REST_Server   $server   Server instance.
	 * @param WP_REST_Request  $request  Request object.
	 * @return WP_REST_Response Modified response.
	 */
	public function post_dispatch( $response, $server, $request ) {
		if ( ! $this->is_money_quiz_request() ) {
			return $response;
		}

		// Add custom headers.
		$response->header( 'X-Money-Quiz-Version', $this->param( 'plugin.version' ) );
		
		// Add dynamic rate limit headers if available.
		if ( $this->has( 'RateLimiter' ) ) {
			$rate_limiter = $this->get( 'RateLimiter' );
			$identifier = $this->get_rate_limit_identifier();
			$limit = $this->get( 'ConfigManager' )->get( 'security.rate_limit_attempts', 100 );
			$remaining = $rate_limiter->get_remaining_attempts( $identifier, 'api_request' );
			
			$response->header( 'X-RateLimit-Limit', (string) $limit );
			$response->header( 'X-RateLimit-Remaining', (string) max( 0, $remaining ) );
			$response->header( 'X-RateLimit-Reset', (string) ( time() + 3600 ) );
		}

		return $response;
	}

	/**
	 * Add CORS headers for API requests.
	 *
	 * Security: Only allows origins explicitly whitelisted via filter.
	 * No wildcard (*) origins are allowed for security.
	 *
	 * @since 7.0.0
	 *
	 * @return void
	 */
	public function add_cors_headers(): void {
		// Get allowed origins from filter - empty by default for security.
		$allowed_origins = apply_filters( 'money_quiz_api_cors_origins', [] );

		if ( empty( $allowed_origins ) ) {
			return;
		}

		// Validate origins are HTTPS (except localhost for development).
		$allowed_origins = array_filter( $allowed_origins, function( $origin ) {
			$parsed = wp_parse_url( $origin );
			return ( $parsed['scheme'] === 'https' ) || 
			       ( $parsed['host'] === 'localhost' && defined( 'WP_DEBUG' ) && WP_DEBUG );
		} );

		$origin = get_http_origin();

		// Strict comparison - origin must exactly match whitelist.
		if ( $origin && in_array( $origin, $allowed_origins, true ) ) {
			header( 'Access-Control-Allow-Origin: ' . esc_url_raw( $origin ) );
			header( 'Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS' );
			header( 'Access-Control-Allow-Headers: Content-Type, Authorization, X-WP-Nonce' );
			header( 'Access-Control-Allow-Credentials: true' );
			header( 'Access-Control-Max-Age: 3600' ); // 1 hour cache for preflight.
		}
	}

	/**
	 * Check if CORS is enabled.
	 *
	 * CORS is disabled by default for security. To enable:
	 * add_filter( 'money_quiz_api_cors_enabled', '__return_true' );
	 * add_filter( 'money_quiz_api_cors_origins', function( $origins ) {
	 *     return [ 'https://trusted-domain.com' ];
	 * } );
	 *
	 * @since 7.0.0
	 *
	 * @return bool True if enabled.
	 */
	private function is_cors_enabled(): bool {
		return apply_filters( 'money_quiz_api_cors_enabled', false );
	}

	/**
	 * Check if current request is for Money Quiz API.
	 *
	 * @since 7.0.0
	 *
	 * @return bool True if Money Quiz request.
	 */
	private function is_money_quiz_request(): bool {
		$request_uri = $_SERVER['REQUEST_URI'] ?? '';
		return strpos( $request_uri, '/wp-json/' . self::API_NAMESPACE ) !== false;
	}

	/**
	 * Get rate limit identifier for current request.
	 *
	 * @since 7.0.0
	 *
	 * @return string Rate limit identifier.
	 */
	private function get_rate_limit_identifier(): string {
		// Use authenticated user ID if available.
		if ( is_user_logged_in() ) {
			return 'user_' . get_current_user_id();
		}

		// Fall back to IP address.
		$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
		return 'ip_' . $ip;
	}

	/**
	 * Register exception handler for rate limiting.
	 *
	 * @since 7.0.0
	 *
	 * @return void
	 */
	public function register_exception_handler(): void {
		set_exception_handler( function( $exception ) {
			if ( $exception instanceof RateLimitExceededException ) {
				$response = new \WP_REST_Response( [
					'code' => 'rate_limit_exceeded',
					'message' => $exception->getMessage() ?: __( 'Rate limit exceeded. Please try again later.', 'money-quiz' ),
					'data' => [
						'retry_after' => $exception->get_retry_after(),
					],
				], 429 );
				
				// Get dynamic rate limit values.
				$limit = 100; // Default
				if ( $this->has( 'ConfigManager' ) ) {
					$config = $this->get( 'ConfigManager' );
					$limit = $config->get( 'security.rate_limit_attempts', 100 );
				}
				
				$response->header( 'Retry-After', $exception->get_retry_after() );
				$response->header( 'X-RateLimit-Limit', (string) $limit );
				$response->header( 'X-RateLimit-Remaining', '0' );
				$response->header( 'X-RateLimit-Reset', (string) ( time() + $exception->get_retry_after() ) );
				
				wp_send_json( $response->get_data(), 429 );
				exit;
			}
			
			// Re-throw if not a rate limit exception.
			throw $exception;
		} );
	}
}