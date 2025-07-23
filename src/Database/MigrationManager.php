<?php
/**
 * Migration Manager
 *
 * Manages database migrations for the Money Quiz plugin.
 *
 * @package MoneyQuiz\Database
 * @since   7.0.0
 */

namespace MoneyQuiz\Database;

use MoneyQuiz\Core\Logging\Logger;

// Security: Prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * Migration manager class.
 *
 * @since 7.0.0
 */
class MigrationManager {

	/**
	 * Database instance.
	 *
	 * @var \wpdb
	 */
	private \wpdb $db;

	/**
	 * Logger instance.
	 *
	 * @var Logger
	 */
	private Logger $logger;

	/**
	 * Table prefix.
	 *
	 * @var string
	 */
	private string $prefix;

	/**
	 * Migrations table name.
	 *
	 * @var string
	 */
	private string $migrations_table;

	/**
	 * Constructor.
	 *
	 * @since 7.0.0
	 *
	 * @param \wpdb  $db     Database instance.
	 * @param Logger $logger Logger instance.
	 * @param string $prefix Table prefix.
	 */
	public function __construct( \wpdb $db, Logger $logger, string $prefix ) {
		$this->db = $db;
		$this->logger = $logger;
		$this->prefix = $prefix;
		$this->migrations_table = $db->prefix . 'money_quiz_migrations';
	}

	/**
	 * Run all pending migrations.
	 *
	 * @since 7.0.0
	 *
	 * @return bool True if successful.
	 */
	public function run(): bool {
		try {
			// Ensure migrations table exists.
			$this->ensure_migrations_table();

			// Get all migration files.
			$migrations = $this->get_migration_files();

			// Get completed migrations.
			$completed = $this->get_completed_migrations();

			// Run pending migrations.
			foreach ( $migrations as $migration ) {
				if ( ! in_array( $migration['name'], $completed, true ) ) {
					$this->run_migration( $migration );
				}
			}

			return true;
		} catch ( \Exception $e ) {
			$this->logger->error( 'Migration failed', [ 'error' => $e->getMessage() ] );
			return false;
		}
	}

	/**
	 * Ensure migrations table exists.
	 *
	 * @since 7.0.0
	 *
	 * @return void
	 */
	private function ensure_migrations_table(): void {
		$charset_collate = $this->db->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS {$this->migrations_table} (
			id INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
			migration VARCHAR(255) NOT NULL,
			batch INT(11) NOT NULL,
			created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY migration (migration)
		) {$charset_collate}";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Get migration files.
	 *
	 * @since 7.0.0
	 *
	 * @return array Migration files.
	 */
	private function get_migration_files(): array {
		$migrations_dir = dirname( __DIR__, 2 ) . '/database/migrations';
		$migrations = [];

		if ( ! is_dir( $migrations_dir ) ) {
			return $migrations;
		}

		$files = glob( $migrations_dir . '/*.php' );
		if ( ! $files ) {
			return $migrations;
		}

		foreach ( $files as $file ) {
			$name = basename( $file, '.php' );
			$migrations[] = [
				'name' => $name,
				'file' => $file,
			];
		}

		// Sort by name (assumes timestamp prefix).
		usort(
			$migrations,
			function( $a, $b ) {
				return strcmp( $a['name'], $b['name'] );
			}
		);

		return $migrations;
	}

	/**
	 * Get completed migrations.
	 *
	 * @since 7.0.0
	 *
	 * @return array Completed migration names.
	 */
	private function get_completed_migrations(): array {
		$results = $this->db->get_col(
			"SELECT migration FROM {$this->migrations_table} ORDER BY id"
		);

		return $results ?: [];
	}

	/**
	 * Run a single migration.
	 *
	 * @since 7.0.0
	 *
	 * @param array $migration Migration data.
	 * @return void
	 *
	 * @throws \Exception On migration failure.
	 */
	private function run_migration( array $migration ): void {
		$this->logger->info( 'Running migration', [ 'migration' => $migration['name'] ] );

		// Start transaction.
		$this->db->query( 'START TRANSACTION' );

		try {
			// Include migration file.
			require_once $migration['file'];

			// Get migration class name.
			$class_name = $this->get_migration_class( $migration['name'] );
			if ( ! class_exists( $class_name ) ) {
				throw new \Exception( "Migration class {$class_name} not found" );
			}

			// Run migration.
			$instance = new $class_name( $this->db, $this->prefix );
			if ( ! method_exists( $instance, 'up' ) ) {
				throw new \Exception( "Migration {$class_name} missing up() method" );
			}

			$instance->up();

			// Record migration.
			$this->record_migration( $migration['name'] );

			// Commit transaction.
			$this->db->query( 'COMMIT' );

			$this->logger->info( 'Migration completed', [ 'migration' => $migration['name'] ] );
		} catch ( \Exception $e ) {
			// Rollback transaction.
			$this->db->query( 'ROLLBACK' );
			throw $e;
		}
	}

	/**
	 * Get migration class name from file name.
	 *
	 * @since 7.0.0
	 *
	 * @param string $filename Migration filename.
	 * @return string Class name.
	 */
	private function get_migration_class( string $filename ): string {
		// Remove timestamp prefix (e.g., 2024_01_01_000000_).
		$class_part = preg_replace( '/^\d{4}_\d{2}_\d{2}_\d{6}_/', '', $filename );

		// Convert to StudlyCase.
		$class_name = str_replace( ' ', '', ucwords( str_replace( '_', ' ', $class_part ) ) );

		return "MoneyQuiz\\Database\\Migrations\\{$class_name}";
	}

	/**
	 * Record completed migration.
	 *
	 * @since 7.0.0
	 *
	 * @param string $name Migration name.
	 * @return void
	 */
	private function record_migration( string $name ): void {
		$batch = $this->get_next_batch_number();

		$this->db->insert(
			$this->migrations_table,
			[
				'migration' => $name,
				'batch'     => $batch,
			],
			[ '%s', '%d' ]
		);
	}

	/**
	 * Get next batch number.
	 *
	 * @since 7.0.0
	 *
	 * @return int Batch number.
	 */
	private function get_next_batch_number(): int {
		$max_batch = $this->db->get_var(
			"SELECT MAX(batch) FROM {$this->migrations_table}"
		);

		return ( (int) $max_batch ) + 1;
	}

	/**
	 * Rollback migrations.
	 *
	 * @since 7.0.0
	 *
	 * @param int $steps Number of batches to rollback.
	 * @return bool True if successful.
	 */
	public function rollback( int $steps = 1 ): bool {
		try {
			$current_batch = $this->get_last_batch_number();
			if ( ! $current_batch ) {
				$this->logger->info( 'No migrations to rollback' );
				return true;
			}

			for ( $i = 0; $i < $steps; $i++ ) {
				$batch = $current_batch - $i;
				if ( $batch < 1 ) {
					break;
				}

				$this->rollback_batch( $batch );
			}

			return true;
		} catch ( \Exception $e ) {
			$this->logger->error( 'Rollback failed', [ 'error' => $e->getMessage() ] );
			return false;
		}
	}

	/**
	 * Get last batch number.
	 *
	 * @since 7.0.0
	 *
	 * @return int|null Batch number.
	 */
	private function get_last_batch_number(): ?int {
		$batch = $this->db->get_var(
			"SELECT MAX(batch) FROM {$this->migrations_table}"
		);

		return $batch ? (int) $batch : null;
	}

	/**
	 * Rollback a batch of migrations.
	 *
	 * @since 7.0.0
	 *
	 * @param int $batch Batch number.
	 * @return void
	 *
	 * @throws \Exception On rollback failure.
	 */
	private function rollback_batch( int $batch ): void {
		// Get migrations in batch.
		$migrations = $this->db->get_col(
			$this->db->prepare(
				"SELECT migration FROM {$this->migrations_table} 
				WHERE batch = %d 
				ORDER BY id DESC",
				$batch
			)
		);

		if ( empty( $migrations ) ) {
			return;
		}

		foreach ( $migrations as $migration_name ) {
			$this->rollback_migration( $migration_name );
		}
	}

	/**
	 * Rollback a single migration.
	 *
	 * @since 7.0.0
	 *
	 * @param string $name Migration name.
	 * @return void
	 *
	 * @throws \Exception On rollback failure.
	 */
	private function rollback_migration( string $name ): void {
		$this->logger->info( 'Rolling back migration', [ 'migration' => $name ] );

		// Start transaction.
		$this->db->query( 'START TRANSACTION' );

		try {
			// Find migration file.
			$migrations_dir = dirname( __DIR__, 2 ) . '/database/migrations';
			$file = $migrations_dir . '/' . $name . '.php';

			if ( ! file_exists( $file ) ) {
				throw new \Exception( "Migration file not found: {$file}" );
			}

			// Include migration file.
			require_once $file;

			// Get migration class.
			$class_name = $this->get_migration_class( $name );
			if ( ! class_exists( $class_name ) ) {
				throw new \Exception( "Migration class {$class_name} not found" );
			}

			// Run rollback.
			$instance = new $class_name( $this->db, $this->prefix );
			if ( ! method_exists( $instance, 'down' ) ) {
				throw new \Exception( "Migration {$class_name} missing down() method" );
			}

			$instance->down();

			// Remove migration record.
			$this->db->delete(
				$this->migrations_table,
				[ 'migration' => $name ],
				[ '%s' ]
			);

			// Commit transaction.
			$this->db->query( 'COMMIT' );

			$this->logger->info( 'Migration rolled back', [ 'migration' => $name ] );
		} catch ( \Exception $e ) {
			// Rollback transaction.
			$this->db->query( 'ROLLBACK' );
			throw $e;
		}
	}

	/**
	 * Get migration status.
	 *
	 * @since 7.0.0
	 *
	 * @return array Status information.
	 */
	public function status(): array {
		$all_migrations = $this->get_migration_files();
		$completed = $this->get_completed_migrations();
		$pending = [];

		foreach ( $all_migrations as $migration ) {
			if ( ! in_array( $migration['name'], $completed, true ) ) {
				$pending[] = $migration['name'];
			}
		}

		return [
			'total'     => count( $all_migrations ),
			'completed' => count( $completed ),
			'pending'   => count( $pending ),
			'migrations' => [
				'completed' => $completed,
				'pending'   => $pending,
			],
		];
	}

	/**
	 * Reset all migrations.
	 *
	 * @since 7.0.0
	 *
	 * @param bool $confirm Confirmation flag.
	 * @return bool True if successful.
	 */
	public function reset( bool $confirm = false ): bool {
		if ( ! $confirm ) {
			$this->logger->warning( 'Migration reset requires confirmation' );
			return false;
		}

		try {
			// Get all batches in reverse order.
			$batches = $this->db->get_col(
				"SELECT DISTINCT batch FROM {$this->migrations_table} ORDER BY batch DESC"
			);

			// Rollback each batch.
			foreach ( $batches as $batch ) {
				$this->rollback_batch( (int) $batch );
			}

			$this->logger->info( 'All migrations reset' );
			return true;
		} catch ( \Exception $e ) {
			$this->logger->error( 'Reset failed', [ 'error' => $e->getMessage() ] );
			return false;
		}
	}
}