<?php
/**
 * Alley\WP_Bulk_Task Tests: Test_Cursor class
 *
 * @package alleyinteractive/wp-bulk-task
 */

use Alley\WP_Bulk_Task\Cursor;
use Mantle\Testkit\Test_Case;

/**
 * Tests for the Cursor class.
 *
 * @package alleyinteractive/wp-bulk-task
 */
class Test_Cursor extends Test_Case {
	/**
	 * Tests the cursor lifecycle (does not exist, created, updated, removed).
	 */
	public function test_lifecycle(): void {
		$this->assertFalse( get_option( 'bt_test-cursor' ) );
		$cursor = new Cursor( 'test-cursor' );
		$cursor->set( 1234 );
		$this->assertEquals( 1234, $cursor->get() );
		$this->assertEquals( 1234, (int) get_option( 'bt_test-cursor' ) );
		$cursor->reset();
		$this->assertFalse( get_option( 'bt_test-cursor' ) );
	}
}
