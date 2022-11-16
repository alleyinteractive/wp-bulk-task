<?php
/**
 * Alley_Interactive\WP_Bulk_Task Tests: Test_Bulk_Task class
 *
 * @package alleyinteractive/wp-bulk-task
 */

use Alley_Interactive\WP_Bulk_Task\Bulk_Task;
use Mantle\Testkit\Test_Case;

/**
 * Tests for the Bulk_Task class.
 *
 * @package alleyinteractive/wp-bulk-task
 */
class Test_Bulk_Task extends Test_Case {
	/**
	 * Tests the run function on a filtered set of posts.
	 */
	public function test_filtered_run(): void {
		$post_id_1 = self::factory()->post->create(
			[
				'post_content' => 'apple',
				'post_type'    => 'post',
			] 
		);
		$post_id_2 = self::factory()->post->create(
			[
				'post_content' => 'apple',
				'post_type'    => 'page',
			] 
		);
		( new Bulk_Task( 'test_filtered_run' ) )->run(
			[
				'post_type' => 'post',
			],
			function( $post ) {
				$post->post_content = str_replace( 'apple', 'banana', $post->post_content );
				wp_update_post( $post );
			}
		);
		$post_1 = get_post( $post_id_1 );
		$post_2 = get_post( $post_id_2 );
		$this->assertEquals( 'banana', $post_1->post_content );
		$this->assertEquals( 'apple', $post_2->post_content );
	}

	/**
	 * Tests the run function on a full set of posts.
	 */
	public function test_full_run(): void {
		$post_id_1 = self::factory()->post->create( [ 'post_content' => 'apple' ] );
		$post_id_2 = self::factory()->post->create( [ 'post_content' => 'apple' ] );
		( new Bulk_Task( 'test_run' ) )->run(
			[],
			function( $post ) {
				$post->post_content = str_replace( 'apple', 'banana', $post->post_content );
				wp_update_post( $post );
			}
		);
		$post_1 = get_post( $post_id_1 );
		$post_2 = get_post( $post_id_2 );
		$this->assertEquals( 'banana', $post_1->post_content );
		$this->assertEquals( 'banana', $post_2->post_content );
	}

	/**
	 * Tests the resume function based on the cursor position in the database.
	 */
	public function test_resumed_run(): void {
		$post_id_1 = self::factory()->post->create( [ 'post_content' => 'apple' ] );
		$post_id_2 = self::factory()->post->create( [ 'post_content' => 'apple' ] );
		update_option( 'bt_test_resumed_run', $post_id_1 );
		( new Bulk_Task( 'test_resumed_run' ) )->run(
			[],
			function( $post ) {
				$post->post_content = str_replace( 'apple', 'banana', $post->post_content );
				wp_update_post( $post );
			}
		);
		$post_1 = get_post( $post_id_1 );
		$post_2 = get_post( $post_id_2 );
		$this->assertEquals( 'apple', $post_1->post_content );
		$this->assertEquals( 'banana', $post_2->post_content );
	}
}
