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
		$cursor = new Cursor( 'test-cursor' );
		$this->assertEquals( 0, $cursor->get() );
		$cursor->set( 1234 );
		$this->assertEquals( 1234, $cursor->get() );
		$cursor->reset();
		$this->assertEquals( 0, $cursor->get() );
	}
}
