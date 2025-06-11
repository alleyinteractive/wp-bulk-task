<?php
/**
 * Alley\WP_Bulk_Task Tests: Test_Post_Bulk_Task class
 *
 * @package alleyinteractive/wp-bulk-task
 */

namespace Alley\WP_Bulk_Task\Tests;

use Alley\WP_Bulk_Task\Bulk_Task;
use Mantle\Testkit\Test_Case;

use WP_Post;

/**
 * Tests for the Test_Post_Bulk_Task class.
 *
 * @package alleyinteractive/wp-bulk-task
 */
class TestPostBulkTask extends Test_Case {

	/**
	 * Stores an array of created post IDs used in tests.
	 *
	 * @var int[]
	 */
	private array $post_ids;

	/**
	 * Actions to be taken before each function in this class is run.
	 */
	public function setUp(): void {
		parent::setUp();

		$this->post_ids = [
			self::factory()->post->create(
				[
					'post_content' => 'apple',
					'post_type'    => 'post',
				]
			),
			self::factory()->post->create(
				[
					'post_content' => 'apple',
					'post_type'    => 'page',
				]
			),
		];
	}

	/**
	 * Tests the run function on a filtered set of posts.
	 */
	public function test_filtered_run(): void {
		( new Bulk_Task( 'test_filtered_run' ) )->run(
			[
				'post_type' => 'post',
			],
			function ( WP_Post $post ): void {
				$post->post_content = str_replace( 'apple', 'banana', $post->post_content );
				wp_update_post( $post );
			}
		);

		$this->assertEquals( 'banana', get_post( $this->post_ids[0] )->post_content );
		$this->assertEquals( 'apple', get_post( $this->post_ids[1] )->post_content );
	}

	/**
	 * Tests the run function on a full set of posts.
	 */
	public function test_full_run(): void {
		( new Bulk_Task( 'test_run' ) )->run(
			[],
			function ( WP_Post $post ): void {
				$post->post_content = str_replace( 'apple', 'banana', $post->post_content );
				wp_update_post( $post );
			}
		);

		$this->assertEquals( 'banana', get_post( $this->post_ids[0] )->post_content );
		$this->assertEquals( 'banana', get_post( $this->post_ids[1] )->post_content );
	}

	/**
	 * Tests the resume function based on the cursor position in the database.
	 */
	public function test_resumed_run(): void {
		$bulk_task = new Bulk_Task( 'test_resumed_run' );
		$bulk_task->cursor->set( $this->post_ids[0] );
		$bulk_task->run(
			[],
			function ( WP_Post $post ): void {
				$post->post_content = str_replace( 'apple', 'banana', $post->post_content );
				wp_update_post( $post );
			}
		);

		$this->assertEquals( 'apple', get_post( $this->post_ids[0] )->post_content );
		$this->assertEquals( 'banana', get_post( $this->post_ids[1] )->post_content );
	}

	/**
	 * Tests getting the current query object.
	 *
	 * @throws \Exception Thrown when invalid callback is used.
	 */
	public function test_get_query(): void {
		$query = null;

		( new Bulk_Task( 'test_query_run' ) )->run(
			[],
			function ( $_, $__, $post_query ) use ( &$query ): void {
				$query = $post_query;
			}
		);

		$this->assertInstanceOf( 'WP_Query', $query );
	}

	/**
	 * Test custom after batch callback.
	 *
	 * @throws \Exception Thrown when invalid callback is used.
	 */
	public function test_custom_after_batch_callback(): void {
		self::factory()->post->create_many(
			200,
			[ 'post_type' => 'post' ]
		);

		$post_ids = [];

		( new Bulk_Task( 'test_custom_after_batch_callback' ) )->run(
			[ 'post_type' => 'post' ],
			function ( WP_Post $post ) use ( &$post_ids ): void {
				$post_ids[] = $post->ID;
			},
			'wp_post',
			function ( array $context ) use ( &$post_ids ): void {
				// Reset the post IDs after the batch is processed.
				if ( 100 < $context['min_id'] ) {
					$post_ids = [];
				}
			},
		);

		$this->assertEmpty( $post_ids, 'Post IDs should be reset with the custom after batch callback.' );
	}
}
