<?php
/**
 * Alley_Interactive\WP_Bulk_Task: Cursor class
 *
 * @package alleyinteractive/wp-bulk-task
 */

namespace Alley_Interactive\WP_Bulk_Task;

/**
 * A class that handles getting, updating, and resetting a cursor for a task.
 *
 * @package alleyinteractive/wp-bulk-task
 */
class Cursor {
	/**
	 * Stores the name of the option that holds the cursor's value.
	 *
	 * @var string
	 */
	private string $option_name;

	/**
	 * Constructor. Takes the unique key for the cursor and converts it into an
	 * option name and stores it for future use.
	 *
	 * @param string $key A unique key for this cursor.
	 */
	public function __construct( string $key ) {
		$this->option_name = 'bt_' . $key;
	}

	/**
	 * Gets the current value for the cursor. Defaults to 0 if not set.
	 *
	 * @return int The current value for the cursor.
	 */
	public function get(): int {
		return (int) get_option( $this->option_name, 0 );
	}

	/**
	 * Resets the value for the cursor.
	 */
	public function reset(): void {
		delete_option( $this->option_name );
	}

	/**
	 * Sets the value for the cursor.
	 *
	 * @param int $value The new value for the cursor.
	 */
	public function set( int $value ): void {
		update_option( $this->option_name, $value );
	}
}
