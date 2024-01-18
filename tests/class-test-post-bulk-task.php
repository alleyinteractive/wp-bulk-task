<?php
/**
 * Alley\WP_Bulk_Task Tests: Test_Post_Bulk_Task class
 *
 * @package alleyinteractive/wp-bulk-task
 */

use Alley\WP_Bulk_Task\Bulk_Task;
use Mantle\Testing\Concerns\Refresh_Database;
use Mantle\Testkit\Test_Case;

/**
 * Tests for the Test_Post_Bulk_Task class.
 *
 * @package alleyinteractive/wp-bulk-task
 */
class Test_Post_Bulk_Task extends Test_Case {
	use Refresh_Database;

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
}
