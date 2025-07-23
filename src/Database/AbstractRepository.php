<?php
/**
 * Abstract Repository
 *
 * Base repository class for database operations.
 *
 * @package MoneyQuiz\Database
 * @since   7.0.0
 */

namespace MoneyQuiz\Database;

use MoneyQuiz\Core\Logging\Logger;
use MoneyQuiz\Database\Cache\RepositoryCache;

// Security: Prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * Abstract repository class.
 *
 * @since 7.0.0
 */
abstract class AbstractRepository {

	/**
	 * Database instance.
	 *
	 * @var \wpdb
	 */
	protected \wpdb $db;

	/**
	 * Logger instance.
	 *
	 * @var Logger
	 */
	protected Logger $logger;

	/**
	 * Table name.
	 *
	 * @var string
	 */
	protected string $table;

	/**
	 * Primary key column.
	 *
	 * @var string
	 */
	protected string $primary_key = 'id';

	/**
	 * Cache instance.
	 *
	 * @var RepositoryCache|null
	 */
	protected ?RepositoryCache $cache = null;

	/**
	 * Cache enabled flag.
	 *
	 * @var bool
	 */
	protected bool $cache_enabled = true;

	/**
	 * Constructor.
	 *
	 * @since 7.0.0
	 *
	 * @param \wpdb  $db     Database instance.
	 * @param Logger $logger Logger instance.
	 */
	public function __construct( \wpdb $db, Logger $logger ) {
		$this->db = $db;
		$this->logger = $logger;
		$this->set_table_name();
		$this->initialize_cache();
	}

	/**
	 * Set table name.
	 *
	 * @since 7.0.0
	 *
	 * @return void
	 */
	abstract protected function set_table_name(): void;

	/**
	 * Initialize cache instance.
	 *
	 * @since 7.0.0
	 *
	 * @return void
	 */
	protected function initialize_cache(): void {
		if ( $this->cache_enabled ) {
			$repository_name = str_replace( $this->db->prefix . 'money_quiz_', '', $this->table );
			$this->cache = new RepositoryCache( $repository_name, $this->logger );
		}
	}

	/**
	 * Get query builder instance.
	 *
	 * @since 7.0.0
	 *
	 * @return QueryBuilder Query builder.
	 */
	protected function query(): QueryBuilder {
		return new QueryBuilder( $this->db, $this->table );
	}

	/**
	 * Find record by ID.
	 *
	 * @since 7.0.0
	 *
	 * @param int $id Record ID.
	 * @return array|null Record data or null.
	 */
	public function find( int $id ): ?array {
		// Check cache first
		$cache_key = 'find_' . $id;
		if ( $this->cache_enabled && $this->cache ) {
			$cached = $this->cache->get( $cache_key );
			if ( $cached !== false ) {
				return $cached;
			}
		}

		try {
			$result = $this->query()
				->where( $this->primary_key, $id )
				->first();

			// Cache the result
			if ( $this->cache_enabled && $this->cache && $result !== null ) {
				$this->cache->set( $cache_key, $result );
			}

			return $result;
		} catch ( \Exception $e ) {
			$this->logger->error( 'Repository find error', [
				'table' => $this->table,
				'id'    => $id,
				'error' => $e->getMessage(),
			] );
			return null;
		}
	}

	/**
	 * Find record by column.
	 *
	 * @since 7.0.0
	 *
	 * @param string $column Column name.
	 * @param mixed  $value  Column value.
	 * @return array|null Record data or null.
	 */
	public function findBy( string $column, $value ): ?array {
		// Check cache first
		$cache_key = 'findby_' . $column . '_' . md5( serialize( $value ) );
		if ( $this->cache_enabled && $this->cache ) {
			$cached = $this->cache->get( $cache_key );
			if ( $cached !== false ) {
				return $cached;
			}
		}

		try {
			$result = $this->query()
				->where( $column, $value )
				->first();

			// Cache the result
			if ( $this->cache_enabled && $this->cache && $result !== null ) {
				$this->cache->set( $cache_key, $result );
			}

			return $result;
		} catch ( \Exception $e ) {
			$this->logger->error( 'Repository findBy error', [
				'table'  => $this->table,
				'column' => $column,
				'value'  => $value,
				'error'  => $e->getMessage(),
			] );
			return null;
		}
	}

	/**
	 * Get all records.
	 *
	 * @since 7.0.0
	 *
	 * @param array $order Order by configuration.
	 * @return array Records.
	 */
	public function all( array $order = [] ): array {
		try {
			$query = $this->query();

			foreach ( $order as $column => $direction ) {
				$query->orderBy( $column, $direction );
			}

			return $query->get();
		} catch ( \Exception $e ) {
			$this->logger->error( 'Repository all error', [
				'table' => $this->table,
				'error' => $e->getMessage(),
			] );
			return [];
		}
	}

	/**
	 * Get paginated records.
	 *
	 * @since 7.0.0
	 *
	 * @param int   $page     Page number.
	 * @param int   $per_page Items per page.
	 * @param array $order    Order by configuration.
	 * @return array Paginated data.
	 */
	public function paginate( int $page = 1, int $per_page = 20, array $order = [] ): array {
		try {
			$offset = ( $page - 1 ) * $per_page;

			$query = $this->query();

			foreach ( $order as $column => $direction ) {
				$query->orderBy( $column, $direction );
			}

			$items = $query
				->limit( $per_page )
				->offset( $offset )
				->get();

			$total = $this->query()->count();

			return [
				'items'       => $items,
				'total'       => $total,
				'page'        => $page,
				'per_page'    => $per_page,
				'total_pages' => ceil( $total / $per_page ),
			];
		} catch ( \Exception $e ) {
			$this->logger->error( 'Repository paginate error', [
				'table' => $this->table,
				'page'  => $page,
				'error' => $e->getMessage(),
			] );
			return [
				'items'       => [],
				'total'       => 0,
				'page'        => $page,
				'per_page'    => $per_page,
				'total_pages' => 0,
			];
		}
	}

	/**
	 * Create new record.
	 *
	 * @since 7.0.0
	 *
	 * @param array $data Record data.
	 * @return int|false Insert ID or false.
	 */
	public function create( array $data ) {
		try {
			// Add timestamps if not present.
			if ( ! isset( $data['created_at'] ) ) {
				$data['created_at'] = current_time( 'mysql' );
			}
			if ( ! isset( $data['updated_at'] ) ) {
				$data['updated_at'] = current_time( 'mysql' );
			}

			$result = $this->query()->insert( $data );

			if ( $result !== false ) {
				$this->logger->info( 'Repository record created', [
					'table' => $this->table,
					'id'    => $result,
				] );

				// Invalidate relevant caches
				if ( $this->cache_enabled && $this->cache ) {
					$this->cache->delete( 'find_' . $result );
				}

				// Fire created event.
				do_action( "money_quiz_{$this->get_entity_name()}_created", $result, $data );
			}

			return $result;
		} catch ( \Exception $e ) {
			$this->logger->error( 'Repository create error', [
				'table' => $this->table,
				'error' => $e->getMessage(),
			] );
			return false;
		}
	}

	/**
	 * Update record.
	 *
	 * @since 7.0.0
	 *
	 * @param int   $id   Record ID.
	 * @param array $data Update data.
	 * @return bool True if successful.
	 */
	public function update( int $id, array $data ): bool {
		try {
			// Add updated timestamp.
			if ( ! isset( $data['updated_at'] ) ) {
				$data['updated_at'] = current_time( 'mysql' );
			}

			// Get old data for event.
			$old_data = $this->find( $id );

			$result = $this->query()
				->where( $this->primary_key, $id )
				->update( $data );

			if ( $result !== false ) {
				$this->logger->info( 'Repository record updated', [
					'table' => $this->table,
					'id'    => $id,
				] );

				// Invalidate relevant caches
				if ( $this->cache_enabled && $this->cache ) {
					$this->cache->delete( 'find_' . $id );
					// Also flush entire cache group as findBy queries may be affected
					$this->cache->flush();
				}

				// Fire updated event.
				do_action( "money_quiz_{$this->get_entity_name()}_updated", $id, $data, $old_data );

				return true;
			}

			return false;
		} catch ( \Exception $e ) {
			$this->logger->error( 'Repository update error', [
				'table' => $this->table,
				'id'    => $id,
				'error' => $e->getMessage(),
			] );
			return false;
		}
	}

	/**
	 * Delete record.
	 *
	 * @since 7.0.0
	 *
	 * @param int $id Record ID.
	 * @return bool True if successful.
	 */
	public function delete( int $id ): bool {
		try {
			// Get data for event.
			$data = $this->find( $id );

			$result = $this->query()
				->where( $this->primary_key, $id )
				->delete();

			if ( $result !== false ) {
				$this->logger->info( 'Repository record deleted', [
					'table' => $this->table,
					'id'    => $id,
				] );

				// Invalidate relevant caches
				if ( $this->cache_enabled && $this->cache ) {
					$this->cache->delete( 'find_' . $id );
					// Also flush entire cache group as findBy queries may be affected
					$this->cache->flush();
				}

				// Fire deleted event.
				do_action( "money_quiz_{$this->get_entity_name()}_deleted", $id, $data );

				return true;
			}

			return false;
		} catch ( \Exception $e ) {
			$this->logger->error( 'Repository delete error', [
				'table' => $this->table,
				'id'    => $id,
				'error' => $e->getMessage(),
			] );
			return false;
		}
	}

	/**
	 * Check if record exists.
	 *
	 * @since 7.0.0
	 *
	 * @param int $id Record ID.
	 * @return bool True if exists.
	 */
	public function exists( int $id ): bool {
		return $this->query()
			->where( $this->primary_key, $id )
			->count() > 0;
	}

	/**
	 * Count records.
	 *
	 * @since 7.0.0
	 *
	 * @param array $conditions Where conditions.
	 * @return int Count.
	 */
	public function count( array $conditions = [] ): int {
		try {
			$query = $this->query();

			foreach ( $conditions as $column => $value ) {
				$query->where( $column, $value );
			}

			return $query->count();
		} catch ( \Exception $e ) {
			$this->logger->error( 'Repository count error', [
				'table' => $this->table,
				'error' => $e->getMessage(),
			] );
			return 0;
		}
	}

	/**
	 * Begin database transaction.
	 *
	 * @since 7.0.0
	 *
	 * @return void
	 */
	public function beginTransaction(): void {
		$this->db->query( 'START TRANSACTION' );
	}

	/**
	 * Commit database transaction.
	 *
	 * @since 7.0.0
	 *
	 * @return void
	 */
	public function commit(): void {
		$this->db->query( 'COMMIT' );
	}

	/**
	 * Rollback database transaction.
	 *
	 * @since 7.0.0
	 *
	 * @return void
	 */
	public function rollback(): void {
		$this->db->query( 'ROLLBACK' );
	}

	/**
	 * Get entity name from table.
	 *
	 * @since 7.0.0
	 *
	 * @return string Entity name.
	 */
	protected function get_entity_name(): string {
		// Remove prefix and convert to singular.
		$name = str_replace( $this->db->prefix . 'money_quiz_', '', $this->table );
		return rtrim( $name, 's' );
	}

	/**
	 * Sanitize data before database operations.
	 *
	 * @since 7.0.0
	 *
	 * @param array $data Data to sanitize.
	 * @return array Sanitized data.
	 */
	protected function sanitize_data( array $data ): array {
		$sanitized = [];

		foreach ( $data as $key => $value ) {
			if ( is_string( $value ) ) {
				$sanitized[ $key ] = sanitize_text_field( $value );
			} elseif ( is_array( $value ) ) {
				$sanitized[ $key ] = $this->sanitize_data( $value );
			} else {
				$sanitized[ $key ] = $value;
			}
		}

		return $sanitized;
	}

	/**
	 * Validate data before operations.
	 *
	 * @since 7.0.0
	 *
	 * @param array $data  Data to validate.
	 * @param array $rules Validation rules.
	 * @return array Validation errors.
	 */
	protected function validate_data( array $data, array $rules ): array {
		$errors = [];

		foreach ( $rules as $field => $rule ) {
			if ( $rule['required'] && empty( $data[ $field ] ) ) {
				$errors[ $field ] = sprintf(
					/* translators: %s: field name */
					__( '%s is required.', 'money-quiz' ),
					$rule['label'] ?? $field
				);
			}

			if ( ! empty( $data[ $field ] ) && isset( $rule['type'] ) ) {
				switch ( $rule['type'] ) {
					case 'email':
						if ( ! is_email( $data[ $field ] ) ) {
							$errors[ $field ] = __( 'Invalid email address.', 'money-quiz' );
						}
						break;
					case 'url':
						if ( ! filter_var( $data[ $field ], FILTER_VALIDATE_URL ) ) {
							$errors[ $field ] = __( 'Invalid URL.', 'money-quiz' );
						}
						break;
					case 'integer':
						if ( ! is_numeric( $data[ $field ] ) || (int) $data[ $field ] != $data[ $field ] ) {
							$errors[ $field ] = __( 'Must be a whole number.', 'money-quiz' );
						}
						break;
				}
			}
		}

		return $errors;
	}
}