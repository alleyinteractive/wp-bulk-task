<?php
/**
 * Alley\WP_Bulk_Task Tests: Test_User_Bulk_Task class
 *
 * @package alleyinteractive/wp-bulk-task
 */

declare( strict_types=1 );

namespace Alley\WP_Bulk_Task\Tests;

use Alley\WP_Bulk_Task\Bulk_Task;
use Mantle\Testing\Concerns\Refresh_Database;
use Mantle\Testkit\Test_Case;
use WP_User;

/**
 * Tests for the Test_User_Bulk_Task class.
 *
 * @package alleyinteractive/wp-bulk-task
 */
class TestUserBulkTask extends Test_Case {
	use Refresh_Database;

	/**
	 * Stores an array of created user IDs used in tests.
	 *
	 * @var int[]
	 */
	private array $user_ids;

	/**
	 * Actions to be taken before each function in this class is run.
	 */
	public function setUp(): void {
		parent::setUp();
		$this->user_ids = [
			self::factory()->user->create(
				[
					'user_login' => 'john',
					'role'       => 'contributor',
				]
			),
			self::factory()->user->create(
				[
					'user_login' => 'mattrew',
					'role'       => 'contributor',
				]
			),
		];
	}

	/**
	 * Tests the run function on a filtered set of users.
	 */
	public function test_filtered_run(): void {
		( new Bulk_Task( 'test_filtered_user_run' ) )->run(
			[
				'role' => 'contributor',
			],
			function ( $user ): void {
				$user->set_role( 'editor' );
			},
			'wp_user'
		);

		$this->assertEquals( [ 'editor' ], get_user_by( 'ID', $this->user_ids[0] )->roles );
		$this->assertEquals( [ 'editor' ], get_user_by( 'ID', $this->user_ids[1] )->roles );
	}

	/**
	 * Tests the run function on a full set of users.
	 */
	public function test_full_run(): void {
		( new Bulk_Task( 'test_user_run' ) )->run(
			[],
			function ( WP_User $user ): void {
				$user->set_role( 'editor' );
			},
			'wp_user'
		);

		$this->assertEquals( [ 'editor' ], get_user_by( 'ID', $this->user_ids[0] )->roles );
		$this->assertEquals( [ 'editor' ], get_user_by( 'ID', $this->user_ids[1] )->roles );
	}

	/**
	 * Tests the resume function based on the cursor position in the database.
	 */
	public function test_resumed_run(): void {
		$bulk_task = new Bulk_Task( 'test_resumed_run' );
		$bulk_task->cursor->set( $this->user_ids[0] );
		$bulk_task->run(
			[],
			function ( WP_User $user ): void {
				$user->set_role( 'editor' );
			},
			'wp_user'
		);

		$this->assertEquals( [ 'contributor' ], get_user_by( 'ID', $this->user_ids[0] )->roles );
		$this->assertEquals( [ 'editor' ], get_user_by( 'ID', $this->user_ids[1] )->roles );
	}

	/**
	 * Tests getting the current query object.
	 */
	public function test_get_query(): void {
		$query = null;

		( new Bulk_Task( 'test_query_run' ) )->run(
			[],
			function ( $_, $__, $user_query ) use ( &$query ): void {
				$query = $user_query;
			},
			'wp_user'
		);

		$this->assertInstanceOf( 'WP_User_Query', $query );
	}
}
