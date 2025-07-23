<?php
/**
 * Query Builder
 *
 * Provides a fluent interface for building secure database queries.
 *
 * @package MoneyQuiz\Database
 * @since   7.0.0
 */

namespace MoneyQuiz\Database;

// Security: Prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * Query builder class.
 *
 * @since 7.0.0
 */
class QueryBuilder {

	/**
	 * Database instance.
	 *
	 * @var \wpdb
	 */
	private \wpdb $db;

	/**
	 * Table name.
	 *
	 * @var string
	 */
	private string $table;

	/**
	 * Query type.
	 *
	 * @var string
	 */
	private string $type = 'select';

	/**
	 * Select columns.
	 *
	 * @var array
	 */
	private array $select = [ '*' ];

	/**
	 * Where conditions.
	 *
	 * @var array
	 */
	private array $where = [];

	/**
	 * Order by clauses.
	 *
	 * @var array
	 */
	private array $order_by = [];

	/**
	 * Group by columns.
	 *
	 * @var array
	 */
	private array $group_by = [];

	/**
	 * Having conditions.
	 *
	 * @var array
	 */
	private array $having = [];

	/**
	 * Join clauses.
	 *
	 * @var array
	 */
	private array $joins = [];

	/**
	 * Query limit.
	 *
	 * @var int|null
	 */
	private ?int $limit = null;

	/**
	 * Query offset.
	 *
	 * @var int|null
	 */
	private ?int $offset = null;

	/**
	 * Insert/Update data.
	 *
	 * @var array
	 */
	private array $data = [];

	/**
	 * Constructor.
	 *
	 * @since 7.0.0
	 *
	 * @param \wpdb  $db    Database instance.
	 * @param string $table Table name.
	 */
	public function __construct( \wpdb $db, string $table ) {
		$this->db = $db;
		$this->table = $table;
	}

	/**
	 * Set select columns.
	 *
	 * @since 7.0.0
	 *
	 * @param string|array $columns Columns to select.
	 * @return self
	 */
	public function select( $columns = '*' ): self {
		$this->type = 'select';
		$this->select = is_array( $columns ) ? $columns : [ $columns ];
		return $this;
	}

	/**
	 * Add where condition.
	 *
	 * @since 7.0.0
	 *
	 * @param string $column   Column name.
	 * @param mixed  $value    Value to compare.
	 * @param string $operator Comparison operator.
	 * @param string $boolean  Boolean operator (AND/OR).
	 * @return self
	 */
	public function where( string $column, $value, string $operator = '=', string $boolean = 'AND' ): self {
		$this->where[] = [
			'column'   => $column,
			'value'    => $value,
			'operator' => $operator,
			'boolean'  => $boolean,
		];
		return $this;
	}

	/**
	 * Add OR where condition.
	 *
	 * @since 7.0.0
	 *
	 * @param string $column   Column name.
	 * @param mixed  $value    Value to compare.
	 * @param string $operator Comparison operator.
	 * @return self
	 */
	public function orWhere( string $column, $value, string $operator = '=' ): self {
		return $this->where( $column, $value, $operator, 'OR' );
	}

	/**
	 * Add where IN condition.
	 *
	 * @since 7.0.0
	 *
	 * @param string $column  Column name.
	 * @param array  $values  Values array.
	 * @param string $boolean Boolean operator.
	 * @return self
	 */
	public function whereIn( string $column, array $values, string $boolean = 'AND' ): self {
		return $this->where( $column, $values, 'IN', $boolean );
	}

	/**
	 * Add where NOT IN condition.
	 *
	 * @since 7.0.0
	 *
	 * @param string $column  Column name.
	 * @param array  $values  Values array.
	 * @param string $boolean Boolean operator.
	 * @return self
	 */
	public function whereNotIn( string $column, array $values, string $boolean = 'AND' ): self {
		return $this->where( $column, $values, 'NOT IN', $boolean );
	}

	/**
	 * Add where NULL condition.
	 *
	 * @since 7.0.0
	 *
	 * @param string $column  Column name.
	 * @param string $boolean Boolean operator.
	 * @return self
	 */
	public function whereNull( string $column, string $boolean = 'AND' ): self {
		return $this->where( $column, null, 'IS NULL', $boolean );
	}

	/**
	 * Add where NOT NULL condition.
	 *
	 * @since 7.0.0
	 *
	 * @param string $column  Column name.
	 * @param string $boolean Boolean operator.
	 * @return self
	 */
	public function whereNotNull( string $column, string $boolean = 'AND' ): self {
		return $this->where( $column, null, 'IS NOT NULL', $boolean );
	}

	/**
	 * Add join clause.
	 *
	 * @since 7.0.0
	 *
	 * @param string $table     Table to join.
	 * @param string $first     First column.
	 * @param string $operator  Join operator.
	 * @param string $second    Second column.
	 * @param string $type      Join type.
	 * @return self
	 */
	public function join( string $table, string $first, string $operator, string $second, string $type = 'INNER' ): self {
		$this->joins[] = [
			'table'    => $table,
			'first'    => $first,
			'operator' => $operator,
			'second'   => $second,
			'type'     => $type,
		];
		return $this;
	}

	/**
	 * Add left join.
	 *
	 * @since 7.0.0
	 *
	 * @param string $table    Table to join.
	 * @param string $first    First column.
	 * @param string $operator Join operator.
	 * @param string $second   Second column.
	 * @return self
	 */
	public function leftJoin( string $table, string $first, string $operator, string $second ): self {
		return $this->join( $table, $first, $operator, $second, 'LEFT' );
	}

	/**
	 * Add order by clause.
	 *
	 * @since 7.0.0
	 *
	 * @param string $column    Column to order by.
	 * @param string $direction Sort direction.
	 * @return self
	 */
	public function orderBy( string $column, string $direction = 'ASC' ): self {
		$this->order_by[] = [
			'column'    => $column,
			'direction' => strtoupper( $direction ),
		];
		return $this;
	}

	/**
	 * Add group by clause.
	 *
	 * @since 7.0.0
	 *
	 * @param string|array $columns Columns to group by.
	 * @return self
	 */
	public function groupBy( $columns ): self {
		$columns = is_array( $columns ) ? $columns : [ $columns ];
		$this->group_by = array_merge( $this->group_by, $columns );
		return $this;
	}

	/**
	 * Add having condition.
	 *
	 * @since 7.0.0
	 *
	 * @param string $column   Column name.
	 * @param mixed  $value    Value to compare.
	 * @param string $operator Comparison operator.
	 * @param string $boolean  Boolean operator.
	 * @return self
	 */
	public function having( string $column, $value, string $operator = '=', string $boolean = 'AND' ): self {
		$this->having[] = [
			'column'   => $column,
			'value'    => $value,
			'operator' => $operator,
			'boolean'  => $boolean,
		];
		return $this;
	}

	/**
	 * Set query limit.
	 *
	 * @since 7.0.0
	 *
	 * @param int $limit Limit value.
	 * @return self
	 */
	public function limit( int $limit ): self {
		$this->limit = $limit;
		return $this;
	}

	/**
	 * Set query offset.
	 *
	 * @since 7.0.0
	 *
	 * @param int $offset Offset value.
	 * @return self
	 */
	public function offset( int $offset ): self {
		$this->offset = $offset;
		return $this;
	}

	/**
	 * Execute query and get results.
	 *
	 * @since 7.0.0
	 *
	 * @return array Query results.
	 */
	public function get(): array {
		$sql = $this->build_select_query();
		$results = $this->db->get_results( $sql, ARRAY_A );
		return $results ?: [];
	}

	/**
	 * Get first result.
	 *
	 * @since 7.0.0
	 *
	 * @return array|null First result or null.
	 */
	public function first(): ?array {
		$this->limit( 1 );
		$results = $this->get();
		return ! empty( $results ) ? $results[0] : null;
	}

	/**
	 * Get count of results.
	 *
	 * @since 7.0.0
	 *
	 * @return int Count value.
	 */
	public function count(): int {
		$original_select = $this->select;
		$this->select = [ 'COUNT(*) as count' ];

		$sql = $this->build_select_query();
		$result = $this->db->get_var( $sql );

		$this->select = $original_select;

		return (int) $result;
	}

	/**
	 * Insert data.
	 *
	 * @since 7.0.0
	 *
	 * @param array $data Data to insert.
	 * @return int|false Insert ID or false on failure.
	 */
	public function insert( array $data ) {
		$formats = $this->get_formats( $data );
		$result = $this->db->insert( $this->table, $data, $formats );

		return $result !== false ? $this->db->insert_id : false;
	}

	/**
	 * Update data.
	 *
	 * @since 7.0.0
	 *
	 * @param array $data Data to update.
	 * @return int|false Number of rows updated or false.
	 */
	public function update( array $data ) {
		if ( empty( $this->where ) ) {
			return false; // Prevent updating all rows.
		}

		$where = $this->build_where_array();
		$formats = $this->get_formats( $data );
		$where_formats = $this->get_formats( $where );

		$result = $this->db->update( $this->table, $data, $where, $formats, $where_formats );

		return $result;
	}

	/**
	 * Delete records.
	 *
	 * @since 7.0.0
	 *
	 * @return int|false Number of rows deleted or false.
	 */
	public function delete() {
		if ( empty( $this->where ) ) {
			return false; // Prevent deleting all rows.
		}

		$where = $this->build_where_array();
		$where_formats = $this->get_formats( $where );

		$result = $this->db->delete( $this->table, $where, $where_formats );

		return $result;
	}

	/**
	 * Build SELECT query.
	 *
	 * @since 7.0.0
	 *
	 * @return string SQL query.
	 */
	private function build_select_query(): string {
		$sql = 'SELECT ' . implode( ', ', $this->select ) . ' FROM ' . $this->table;

		// Add joins.
		foreach ( $this->joins as $join ) {
			$sql .= " {$join['type']} JOIN {$join['table']} ON {$join['first']} {$join['operator']} {$join['second']}";
		}

		// Add where conditions.
		if ( ! empty( $this->where ) ) {
			$sql .= ' WHERE ' . $this->build_where_clause();
		}

		// Add group by.
		if ( ! empty( $this->group_by ) ) {
			$sql .= ' GROUP BY ' . implode( ', ', $this->group_by );
		}

		// Add having.
		if ( ! empty( $this->having ) ) {
			$sql .= ' HAVING ' . $this->build_having_clause();
		}

		// Add order by.
		if ( ! empty( $this->order_by ) ) {
			$order_clauses = array_map(
				function( $order ) {
					return "{$order['column']} {$order['direction']}";
				},
				$this->order_by
			);
			$sql .= ' ORDER BY ' . implode( ', ', $order_clauses );
		}

		// Add limit and offset.
		if ( $this->limit !== null ) {
			$sql .= ' LIMIT ' . $this->limit;
			if ( $this->offset !== null ) {
				$sql .= ' OFFSET ' . $this->offset;
			}
		}

		return $sql;
	}

	/**
	 * Build WHERE clause.
	 *
	 * @since 7.0.0
	 *
	 * @return string WHERE clause.
	 */
	private function build_where_clause(): string {
		$clauses = [];

		foreach ( $this->where as $index => $condition ) {
			$clause = '';

			if ( $index > 0 ) {
				$clause .= " {$condition['boolean']} ";
			}

			$clause .= $this->build_condition( $condition );
			$clauses[] = $clause;
		}

		return implode( '', $clauses );
	}

	/**
	 * Build HAVING clause.
	 *
	 * @since 7.0.0
	 *
	 * @return string HAVING clause.
	 */
	private function build_having_clause(): string {
		$clauses = [];

		foreach ( $this->having as $index => $condition ) {
			$clause = '';

			if ( $index > 0 ) {
				$clause .= " {$condition['boolean']} ";
			}

			$clause .= $this->build_condition( $condition );
			$clauses[] = $clause;
		}

		return implode( '', $clauses );
	}

	/**
	 * Build single condition.
	 *
	 * @since 7.0.0
	 *
	 * @param array $condition Condition data.
	 * @return string Condition SQL.
	 */
	private function build_condition( array $condition ): string {
		$column = $condition['column'];
		$operator = $condition['operator'];
		$value = $condition['value'];

		if ( in_array( $operator, [ 'IS NULL', 'IS NOT NULL' ], true ) ) {
			return "{$column} {$operator}";
		}

		if ( in_array( $operator, [ 'IN', 'NOT IN' ], true ) ) {
			$placeholders = array_fill( 0, count( $value ), $this->get_placeholder( $value[0] ) );
			$in_clause = implode( ', ', $placeholders );
			// Use injected db instance, not global.
			return $this->db->prepare( "{$column} {$operator} ({$in_clause})", $value );
		}

		$placeholder = $this->get_placeholder( $value );
		// Use injected db instance, not global.
		return $this->db->prepare( "{$column} {$operator} {$placeholder}", $value );
	}

	/**
	 * Build WHERE array for update/delete.
	 *
	 * @since 7.0.0
	 *
	 * @return array WHERE conditions.
	 */
	private function build_where_array(): array {
		$where = [];

		foreach ( $this->where as $condition ) {
			if ( $condition['operator'] === '=' && $condition['boolean'] === 'AND' ) {
				$where[ $condition['column'] ] = $condition['value'];
			}
		}

		return $where;
	}

	/**
	 * Get placeholder for value.
	 *
	 * @since 7.0.0
	 *
	 * @param mixed $value Value to check.
	 * @return string Placeholder.
	 */
	private function get_placeholder( $value ): string {
		if ( is_int( $value ) ) {
			return '%d';
		} elseif ( is_float( $value ) ) {
			return '%f';
		} else {
			return '%s';
		}
	}

	/**
	 * Get formats array for data.
	 *
	 * @since 7.0.0
	 *
	 * @param array $data Data array.
	 * @return array Formats array.
	 */
	private function get_formats( array $data ): array {
		$formats = [];

		foreach ( $data as $value ) {
			$formats[] = $this->get_placeholder( $value );
		}

		return $formats;
	}

	/**
	 * Execute raw query.
	 *
	 * @since 7.0.0
	 *
	 * @param string $sql    SQL query.
	 * @param array  $params Query parameters.
	 * @return mixed Query result.
	 */
	public function raw( string $sql, array $params = [] ) {
		if ( ! empty( $params ) ) {
			$sql = $this->db->prepare( $sql, $params );
		}

		return $this->db->query( $sql );
	}
}