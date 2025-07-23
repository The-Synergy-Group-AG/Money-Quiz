<?php
/**
 * QueryBuilder Test
 *
 * @package MoneyQuiz\Tests\Unit\Database
 * @since   7.0.0
 */

namespace MoneyQuiz\Tests\Unit\Database;

use MoneyQuiz\Database\QueryBuilder;
use MoneyQuiz\Tests\TestCase;

/**
 * QueryBuilder test class.
 *
 * @since 7.0.0
 */
class QueryBuilderTest extends TestCase {

	/**
	 * Test table name.
	 *
	 * @var string
	 */
	private string $test_table;

	/**
	 * Set up test.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();
		
		global $wpdb;
		$this->test_table = $wpdb->prefix . 'test_table';
		
		// Create test table.
		$wpdb->query(
			"CREATE TABLE IF NOT EXISTS {$this->test_table} (
				id INT AUTO_INCREMENT PRIMARY KEY,
				name VARCHAR(255),
				email VARCHAR(255),
				age INT,
				status VARCHAR(50),
				created_at DATETIME
			)"
		);
		
		// Insert test data.
		$wpdb->insert(
			$this->test_table,
			[
				'name' => 'John Doe',
				'email' => 'john@example.com',
				'age' => 30,
				'status' => 'active',
				'created_at' => current_time( 'mysql' ),
			]
		);
		
		$wpdb->insert(
			$this->test_table,
			[
				'name' => 'Jane Smith',
				'email' => 'jane@example.com',
				'age' => 25,
				'status' => 'active',
				'created_at' => current_time( 'mysql' ),
			]
		);
		
		$wpdb->insert(
			$this->test_table,
			[
				'name' => 'Bob Johnson',
				'email' => 'bob@example.com',
				'age' => 35,
				'status' => 'inactive',
				'created_at' => current_time( 'mysql' ),
			]
		);
	}

	/**
	 * Tear down test.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		global $wpdb;
		$wpdb->query( "DROP TABLE IF EXISTS {$this->test_table}" );
		
		parent::tearDown();
	}

	/**
	 * Test select all.
	 *
	 * @return void
	 */
	public function test_select_all(): void {
		global $wpdb;
		$query = new QueryBuilder( $wpdb, $this->test_table );
		
		$results = $query->select()->get();
		
		$this->assertCount( 3, $results );
		$this->assertEquals( 'John Doe', $results[0]['name'] );
	}

	/**
	 * Test select specific columns.
	 *
	 * @return void
	 */
	public function test_select_columns(): void {
		global $wpdb;
		$query = new QueryBuilder( $wpdb, $this->test_table );
		
		$results = $query->select( [ 'name', 'email' ] )->get();
		
		$this->assertCount( 3, $results );
		$this->assertArrayHasKey( 'name', $results[0] );
		$this->assertArrayHasKey( 'email', $results[0] );
		$this->assertArrayNotHasKey( 'age', $results[0] );
	}

	/**
	 * Test where condition.
	 *
	 * @return void
	 */
	public function test_where(): void {
		global $wpdb;
		$query = new QueryBuilder( $wpdb, $this->test_table );
		
		$results = $query->where( 'status', 'active' )->get();
		
		$this->assertCount( 2, $results );
	}

	/**
	 * Test where with different operators.
	 *
	 * @return void
	 */
	public function test_where_operators(): void {
		global $wpdb;
		$query = new QueryBuilder( $wpdb, $this->test_table );
		
		// Greater than.
		$results = $query->where( 'age', 30, '>' )->get();
		$this->assertCount( 1, $results );
		$this->assertEquals( 'Bob Johnson', $results[0]['name'] );
		
		// Less than or equal.
		$query2 = new QueryBuilder( $wpdb, $this->test_table );
		$results2 = $query2->where( 'age', 30, '<=' )->get();
		$this->assertCount( 2, $results2 );
	}

	/**
	 * Test or where.
	 *
	 * @return void
	 */
	public function test_or_where(): void {
		global $wpdb;
		$query = new QueryBuilder( $wpdb, $this->test_table );
		
		$results = $query
			->where( 'age', 25 )
			->orWhere( 'age', 35 )
			->get();
		
		$this->assertCount( 2, $results );
	}

	/**
	 * Test where in.
	 *
	 * @return void
	 */
	public function test_where_in(): void {
		global $wpdb;
		$query = new QueryBuilder( $wpdb, $this->test_table );
		
		$results = $query->whereIn( 'age', [ 25, 30 ] )->get();
		
		$this->assertCount( 2, $results );
	}

	/**
	 * Test where null.
	 *
	 * @return void
	 */
	public function test_where_null(): void {
		global $wpdb;
		
		// Insert record with null email.
		$wpdb->insert(
			$this->test_table,
			[
				'name' => 'Test Null',
				'email' => null,
				'age' => 40,
				'status' => 'active',
			]
		);
		
		$query = new QueryBuilder( $wpdb, $this->test_table );
		$results = $query->whereNull( 'email' )->get();
		
		$this->assertCount( 1, $results );
		$this->assertEquals( 'Test Null', $results[0]['name'] );
	}

	/**
	 * Test order by.
	 *
	 * @return void
	 */
	public function test_order_by(): void {
		global $wpdb;
		$query = new QueryBuilder( $wpdb, $this->test_table );
		
		$results = $query->orderBy( 'age', 'DESC' )->get();
		
		$this->assertEquals( 35, $results[0]['age'] );
		$this->assertEquals( 25, $results[2]['age'] );
	}

	/**
	 * Test limit and offset.
	 *
	 * @return void
	 */
	public function test_limit_offset(): void {
		global $wpdb;
		$query = new QueryBuilder( $wpdb, $this->test_table );
		
		$results = $query
			->orderBy( 'id' )
			->limit( 2 )
			->offset( 1 )
			->get();
		
		$this->assertCount( 2, $results );
		$this->assertEquals( 'Jane Smith', $results[0]['name'] );
	}

	/**
	 * Test first.
	 *
	 * @return void
	 */
	public function test_first(): void {
		global $wpdb;
		$query = new QueryBuilder( $wpdb, $this->test_table );
		
		$result = $query->where( 'email', 'jane@example.com' )->first();
		
		$this->assertIsArray( $result );
		$this->assertEquals( 'Jane Smith', $result['name'] );
	}

	/**
	 * Test count.
	 *
	 * @return void
	 */
	public function test_count(): void {
		global $wpdb;
		$query = new QueryBuilder( $wpdb, $this->test_table );
		
		$count = $query->where( 'status', 'active' )->count();
		
		$this->assertEquals( 2, $count );
	}

	/**
	 * Test insert.
	 *
	 * @return void
	 */
	public function test_insert(): void {
		global $wpdb;
		$query = new QueryBuilder( $wpdb, $this->test_table );
		
		$insert_id = $query->insert( [
			'name' => 'New User',
			'email' => 'new@example.com',
			'age' => 28,
			'status' => 'active',
		] );
		
		$this->assertIsInt( $insert_id );
		$this->assertGreaterThan( 0, $insert_id );
		
		// Verify inserted.
		$query2 = new QueryBuilder( $wpdb, $this->test_table );
		$result = $query2->where( 'id', $insert_id )->first();
		$this->assertEquals( 'New User', $result['name'] );
	}

	/**
	 * Test update.
	 *
	 * @return void
	 */
	public function test_update(): void {
		global $wpdb;
		$query = new QueryBuilder( $wpdb, $this->test_table );
		
		$affected = $query
			->where( 'name', 'John Doe' )
			->update( [ 'age' => 31 ] );
		
		$this->assertEquals( 1, $affected );
		
		// Verify updated.
		$query2 = new QueryBuilder( $wpdb, $this->test_table );
		$result = $query2->where( 'name', 'John Doe' )->first();
		$this->assertEquals( 31, $result['age'] );
	}

	/**
	 * Test delete.
	 *
	 * @return void
	 */
	public function test_delete(): void {
		global $wpdb;
		$query = new QueryBuilder( $wpdb, $this->test_table );
		
		$affected = $query
			->where( 'status', 'inactive' )
			->delete();
		
		$this->assertEquals( 1, $affected );
		
		// Verify deleted.
		$query2 = new QueryBuilder( $wpdb, $this->test_table );
		$count = $query2->count();
		$this->assertEquals( 2, $count );
	}

	/**
	 * Test group by.
	 *
	 * @return void
	 */
	public function test_group_by(): void {
		global $wpdb;
		$query = new QueryBuilder( $wpdb, $this->test_table );
		
		$results = $query
			->select( [ 'status', 'COUNT(*) as count' ] )
			->groupBy( 'status' )
			->get();
		
		$this->assertCount( 2, $results );
		
		$active = array_filter( $results, fn( $r ) => $r['status'] === 'active' );
		$this->assertEquals( 2, reset( $active )['count'] );
	}

	/**
	 * Test raw query.
	 *
	 * @return void
	 */
	public function test_raw(): void {
		global $wpdb;
		$query = new QueryBuilder( $wpdb, $this->test_table );
		
		$query->raw(
			"UPDATE {$this->test_table} SET age = age + %d WHERE status = %s",
			[ 1, 'active' ]
		);
		
		// Verify update.
		$results = $query->select()->where( 'name', 'John Doe' )->first();
		$this->assertEquals( 31, $results['age'] );
	}
}