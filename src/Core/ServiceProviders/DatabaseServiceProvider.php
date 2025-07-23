<?php
/**
 * Database Service Provider
 *
 * Registers database-related services for the Money Quiz plugin.
 *
 * @package MoneyQuiz\Core\ServiceProviders
 * @since   7.0.0
 */

namespace MoneyQuiz\Core\ServiceProviders;

use MoneyQuiz\Core\AbstractServiceProvider;
use MoneyQuiz\Database\Connection;
use MoneyQuiz\Database\Migration\MigrationManager;
use MoneyQuiz\Database\QueryBuilder;
use MoneyQuiz\Database\Schema;
use MoneyQuiz\Database\Repository\QuizRepository;
use MoneyQuiz\Database\Repository\ResultRepository;
use MoneyQuiz\Database\Repository\AnalyticsRepository;

// Security: Prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * Database service provider class.
 *
 * @since 7.0.0
 */
class DatabaseServiceProvider extends AbstractServiceProvider {

	/**
	 * Register services.
	 *
	 * @since 7.0.0
	 *
	 * @return void
	 */
	public function register(): void {
		// Register database connection wrapper.
		$this->singleton(
			Connection::class,
			function( $container ) {
				global $wpdb;
				return new Connection( $wpdb );
			}
		);

		// Register query builder factory.
		$this->factory(
			QueryBuilder::class,
			function( $container ) {
				return new QueryBuilder(
					$container->get( Connection::class )
				);
			}
		);

		// Register schema manager.
		$this->singleton(
			Schema::class,
			function( $container ) {
				return new Schema(
					$container->get( Connection::class )
				);
			}
		);

		// Register migration manager.
		$this->singleton(
			MigrationManager::class,
			function( $container ) {
				return new MigrationManager(
					$container->get( Schema::class ),
					$container->param( 'plugin.version' )
				);
			}
		);

		// Register repositories.
		$this->singleton(
			QuizRepository::class,
			function( $container ) {
				return new QuizRepository(
					$container->get( Connection::class ),
					$container->get( QueryBuilder::class )
				);
			}
		);

		$this->singleton(
			ResultRepository::class,
			function( $container ) {
				return new ResultRepository(
					$container->get( Connection::class ),
					$container->get( QueryBuilder::class )
				);
			}
		);

		$this->singleton(
			AnalyticsRepository::class,
			function( $container ) {
				return new AnalyticsRepository(
					$container->get( Connection::class ),
					$container->get( QueryBuilder::class )
				);
			}
		);

		// Register table names as parameters.
		$this->register_table_names();
	}

	/**
	 * Bootstrap services.
	 *
	 * @since 7.0.0
	 *
	 * @return void
	 */
	public function boot(): void {
		// Run migrations on admin init.
		if ( is_admin() ) {
			add_action( 'admin_init', [ $this, 'run_migrations' ] );
		}

		// Add cleanup actions.
		add_action( 'money_quiz_daily_cleanup', [ $this, 'cleanup_old_data' ] );
		
		// Register custom table names with wpdb.
		$this->register_wpdb_tables();
	}

	/**
	 * Register table names as parameters.
	 *
	 * @since 7.0.0
	 *
	 * @return void
	 */
	private function register_table_names(): void {
		global $wpdb;

		$tables = [
			'table.quizzes'    => $wpdb->prefix . 'money_quiz_quizzes',
			'table.questions'  => $wpdb->prefix . 'money_quiz_questions',
			'table.answers'    => $wpdb->prefix . 'money_quiz_answers',
			'table.results'    => $wpdb->prefix . 'money_quiz_results',
			'table.responses'  => $wpdb->prefix . 'money_quiz_responses',
			'table.analytics'  => $wpdb->prefix . 'money_quiz_analytics',
			'table.rate_limits' => $wpdb->prefix . 'money_quiz_rate_limits',
		];

		foreach ( $tables as $key => $table_name ) {
			$this->parameter( $key, $table_name );
		}
	}

	/**
	 * Register custom tables with wpdb.
	 *
	 * @since 7.0.0
	 *
	 * @return void
	 */
	private function register_wpdb_tables(): void {
		global $wpdb;

		// Register custom tables for use with wpdb.
		$wpdb->money_quiz_quizzes    = $this->param( 'table.quizzes' );
		$wpdb->money_quiz_questions  = $this->param( 'table.questions' );
		$wpdb->money_quiz_answers    = $this->param( 'table.answers' );
		$wpdb->money_quiz_results    = $this->param( 'table.results' );
		$wpdb->money_quiz_responses  = $this->param( 'table.responses' );
		$wpdb->money_quiz_analytics  = $this->param( 'table.analytics' );
		$wpdb->money_quiz_rate_limits = $this->param( 'table.rate_limits' );

		// Add to tables array for proper charset support.
		$wpdb->tables[] = 'money_quiz_quizzes';
		$wpdb->tables[] = 'money_quiz_questions';
		$wpdb->tables[] = 'money_quiz_answers';
		$wpdb->tables[] = 'money_quiz_results';
		$wpdb->tables[] = 'money_quiz_responses';
		$wpdb->tables[] = 'money_quiz_analytics';
		$wpdb->tables[] = 'money_quiz_rate_limits';
	}

	/**
	 * Run database migrations.
	 *
	 * @since 7.0.0
	 *
	 * @return void
	 */
	public function run_migrations(): void {
		// Only run on Money Quiz admin pages to avoid performance impact.
		if ( ! $this->is_money_quiz_admin_page() ) {
			return;
		}

		try {
			$migration_manager = $this->get( MigrationManager::class );
			$migration_manager->run();
		} catch ( \Exception $e ) {
			// Log error but don't break the admin.
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'Money Quiz migration error: ' . $e->getMessage() );
			}
		}
	}

	/**
	 * Clean up old data.
	 *
	 * @since 7.0.0
	 *
	 * @return void
	 */
	public function cleanup_old_data(): void {
		try {
			$analytics_repo = $this->get( AnalyticsRepository::class );
			$analytics_repo->cleanup_old_entries( 90 ); // Keep 90 days of data.

			$result_repo = $this->get( ResultRepository::class );
			$result_repo->cleanup_incomplete_results( 7 ); // Remove incomplete after 7 days.
		} catch ( \Exception $e ) {
			// Log error but don't break the cleanup process.
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'Money Quiz cleanup error: ' . $e->getMessage() );
			}
		}
	}

	/**
	 * Check if current page is Money Quiz admin page.
	 *
	 * @since 7.0.0
	 *
	 * @return bool True if Money Quiz admin page.
	 */
	private function is_money_quiz_admin_page(): bool {
		$screen = get_current_screen();
		return $screen && strpos( $screen->id, 'money-quiz' ) !== false;
	}
}