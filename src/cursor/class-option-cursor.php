<?php
/**
 * Alley\WP_Bulk_Task: Option_Cursor class
 *
 * @package alleyinteractive/wp-bulk-task
 */

declare(strict_types=1);

namespace Alley\WP_Bulk_Task\Cursor;

/**
 * A class that handles getting, updating, and resetting a cursor for a task
 * with storage as an option.
 *
 * @package alleyinteractive/wp-bulk-task
 */
class Option_Cursor implements Cursor {
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
		$cursor_value = get_option( $this->option_name, 0 );

		if ( ! is_numeric( $cursor_value ) ) {
			return 0;
		}

		return (int) $cursor_value;
	}

	/**
	 * Resets the value for the cursor.
	 *
	 * @return bool True if the value was deleted, false otherwise.
	 *              Will also return false if the cursor did not have a value saved to the database.
	 */
	public function reset(): bool {
		return delete_option( $this->option_name );
	}

	/**
	 * Sets the value for the cursor.
	 *
	 * @param int $value The new value for the cursor.
	 * @return bool True if the value was successfully set, false otherwise.
	 */
	public function set( int $value ): bool {
		return update_option( $this->option_name, $value, false );
	}
}
