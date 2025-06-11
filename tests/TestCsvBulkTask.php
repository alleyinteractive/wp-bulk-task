<?php
/**
 * Alley\WP_Bulk_Task Tests: Test_CSV_Bulk_Task class
 *
 * @package alleyinteractive/wp-bulk-task
 */

declare(strict_types=1);

namespace Alley\WP_Bulk_Task\Tests;

use Alley\WP_Bulk_Task\Bulk_Task;
use Mantle\Testkit\Test_Case;

/**
 * Tests for the Test_CSV_Bulk_Task class.
 *
 * @package alleyinteractive/wp-bulk-task
 */
class TestCsvBulkTask extends Test_Case {

	/**
	 * The CSV file path.
	 *
	 * @var string
	 */
	private string $csv;

	/**
	 * Actions to be taken before each function in this class is run.
	 */
	public function setUp(): void {
		parent::setUp();

		$this->csv = __DIR__ . '/csv-test.csv';
	}

	/**
	 * Test the run function on a invalid CSV file.
	 */
	public function test_invalid_csv(): void {
		$this->expectException( \Exception::class );
		$this->expectExceptionMessage( 'The CSV file does not exist or is not readable.' );

		( new Bulk_Task( 'test_invalid_csv' ) )->run(
			[ 'csv' => 'invalid.csv' ],
			function (): void {},
			'csv'
		);
	}

	/**
	 * Tests the run function on a valid CSV file.
	 */
	public function test_full_run(): void {
		( new Bulk_Task( 'test_run' ) )->run(
			[ 'csv' => $this->csv ],
			function ( $row ): void {
				self::factory()->post->create( [ 'post_title' => $row[1] ] );
			},
			'csv'
		);

		$posts = get_posts(); // @phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.get_posts_get_posts
		$this->assertCount( 5, $posts );
		$this->assertEquals( 'Hello', $posts[0]->post_title );
		$this->assertEquals( 'Hiii', $posts[1]->post_title );
		$this->assertEquals( 'Hi', $posts[2]->post_title );
	}

	/**
	 * Tests the resume function based on the cursor position on the CSV file.
	 */
	public function test_resumed_run(): void {
		$rows = [];

		$bulk_task = new Bulk_Task( 'test_resumed_run' );
		$bulk_task->cursor->set( 4 );
		$bulk_task->run(
			[ 'csv' => $this->csv ],
			function ( $row ) use ( &$rows ): void {
				$rows[] = $row;
				self::factory()->post->create( [ 'post_title' => $row[1] ] );
			},
			'csv'
		);

		$this->assertCount( 1, $rows );
		$this->assertEquals( 05, $rows[0][0] );
		$this->assertEquals( 'Hello', $rows[0][1] );

		$posts = get_posts(); // @phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.get_posts_get_posts
		$this->assertCount( 1, $posts );
		$this->assertEquals( 'Hello', $posts[0]->post_title );
	}

	/**
	 * Test custom after batch callback.
	 *
	 * @throws \Exception Thrown when invalid callback is used.
	 */
	public function test_custom_after_batch_callback(): void {
		( new Bulk_Task( 'test_run' ) )->run(
			[ 'csv' => $this->csv ],
			function ( $row ): void {
				self::factory()->post->create( [ 'post_title' => $row[1] ] );
			},
			'csv',
			function (): void {
				self::factory()->post->create( [ 'post_title' => 'after-callback-post' ] );
			}
		);

		$posts = get_posts(); // @phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.get_posts_get_posts
		$this->assertCount( 5, $posts );
		$this->assertEquals( 'after-callback-post', $posts[0]->post_title );
		$this->assertEquals( 'Hello', $posts[1]->post_title );
	}
}
