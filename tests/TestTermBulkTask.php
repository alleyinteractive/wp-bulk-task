<?php
/**
 * Alley\WP_Bulk_Task Tests: Test_Term_Bulk_Task class
 *
 * @package alleyinteractive/wp-bulk-task
 */

namespace Alley\WP_Bulk_Task\Tests;

use Alley\WP_Bulk_Task\Bulk_Task;
use Mantle\Testing\Concerns\Refresh_Database;
use Mantle\Testkit\Test_Case;
use WP_Term;

/**
 * Tests for the Test_Term_Bulk_Task class.
 *
 * @package alleyinteractive/wp-bulk-task
 */
class TestTermBulkTask extends Test_Case {
	use Refresh_Database;

	/**
	 * Stores an array of created term IDs used in tests.
	 *
	 * @var int[]
	 */
	private array $term_ids;

	/**
	 * Actions to be taken before each function in this class is run.
	 */
	public function setUp(): void {
		parent::setUp();
		$this->term_ids = [
			self::factory()->category->create(
				[
					'name' => 'apple',
				]
			),
			self::factory()->tag->create(
				[
					'name' => 'apple',
				]
			),
		];
	}

	/**
	 * Tests the run function on a filtered set of terms.
	 */
	public function test_filtered_run(): void {
		( new Bulk_Task( 'test_filtered_term_run' ) )->run(
			[
				'taxonomy'   => 'category',
				'hide_empty' => false,
			],
			function ( WP_Term $term ): void {
				wp_update_term( $term->term_id, $term->taxonomy, [ 'name' => 'banana' ] );
			},
			'wp_term'
		);

		$this->assertEquals( 'banana', get_term( $this->term_ids[0] )->name );
	}

	/**
	 * Tests the run function on a full set of terms.
	 */
	public function test_full_run(): void {
		( new Bulk_Task( 'test_term_run' ) )->run(
			[
				'taxonomy'   => [ 'category', 'post_tag' ],
				'hide_empty' => false,
			],
			function ( WP_Term $term ): void {
				wp_update_term( $term->term_id, $term->taxonomy, [ 'name' => 'banana' ] );
			},
			'wp_term'
		);

		$this->assertEquals( 'banana', get_term( $this->term_ids[0] )->name );
		$this->assertEquals( 'banana', get_term( $this->term_ids[1] )->name );
	}

	/**
	 * Tests the resume function based on the cursor position in the database.
	 */
	public function test_resumed_run(): void {
		$bulk_task = new Bulk_Task( 'test_resumed_run' );
		$bulk_task->cursor->set( $this->term_ids[0] );
		$bulk_task->run(
			[
				'taxonomy'   => [ 'category', 'post_tag' ],
				'hide_empty' => false,
			],
			function ( WP_Term $term ): void {
				wp_update_term( $term->term_id, $term->taxonomy, [ 'name' => 'banana' ] );
			},
			'wp_term'
		);

		$this->assertEquals( 'apple', get_term( $this->term_ids[0] )->name );
		$this->assertEquals( 'banana', get_term( $this->term_ids[1] )->name );
	}

	/**
	 * Tests getting the current query object.
	 */
	public function test_get_query(): void {
		$query = null;

		( new Bulk_Task( 'test_query_run' ) )->run(
			[
				'taxonomy'   => [ 'category', 'post_tag' ],
				'hide_empty' => false,
			],
			function ( $_, $term_query ) use ( &$query ): void {
				$query = $term_query;
			},
			'wp_term'
		);

		$this->assertInstanceOf( 'WP_Term_Query', $query );
	}

	/**
	 * Test the halt task.
	 */
	public function test_halt_task(): void {
		$bulk_task = new Bulk_Task( 'test_halt_task_run' );
		$bulk_task->run(
			[
				'taxonomy'   => [ 'category', 'post_tag' ],
				'hide_empty' => false,
			],
			function ( WP_Term $term ): bool {
				if ( 'apple' === $term->name ) {
					return false;
				}

				wp_update_term( $term->term_id, $term->taxonomy, [ 'name' => 'banana' ] );

				return true;
			},
			'wp_term'
		);

		// Check the cursor reflects the halted state.
		$this->assertSame( $bulk_task->cursor->get(), $this->term_ids[1] );
		$this->assertEquals( 'apple', get_term( $this->term_ids[1] )->name );

		$this->assertEquals( 'apple', get_term( $this->term_ids[0] )->name );
	}
}
