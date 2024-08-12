<?php
/**
 * Alley\WP_Bulk_Task Tests: Test_Cursor class
 *
 * @package alleyinteractive/wp-bulk-task
 */

declare( strict_types=1 );

namespace Alley\WP_Bulk_Task\Tests;

use Alley\WP_Bulk_Task\Cursor;
use Mantle\Testkit\Test_Case;

/**
 * Tests for the Cursor class.
 *
 * @package alleyinteractive/wp-bulk-task
 */
class TestCursor extends Test_Case {
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

	/**
	 * Tests that the cursor option is not autoloaded.
	 *
	 * @global wpdb $wpdb WordPress database abstraction object.
	 */
	public function test_cursor_option_not_autoload(): void {
		global $wpdb;

		$option_name     = 'test-cursor';
		$option_name_key = "bt_{$option_name}";

		// Ensure the value is indeed empty.
		$this->assertEmpty( get_option( $option_name_key ) );

		$cursor = new Cursor( $option_name );
		$this->assertEquals( 0, $cursor->get() );
		$cursor->set( 1234 );

		// Ensure the option is not autoloaded.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$option_autoloaded = $wpdb->get_var( $wpdb->prepare( "SELECT autoload FROM $wpdb->options WHERE option_name = %s", $option_name_key ) );
		$this->assertNotEmpty( $option_autoloaded );
		$this->assertSame( 'off', $option_autoloaded );

		$this->assertEquals( 1234, $cursor->get() );
		$cursor->reset();
		$this->assertEquals( 0, $cursor->get() );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$option_autoloaded = $wpdb->get_var( $wpdb->prepare( "SELECT autoload FROM $wpdb->options WHERE option_name = %s", $option_name_key ) );

		$this->assertNull( $option_autoloaded );
	}
}
