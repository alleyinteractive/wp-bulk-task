<?php
/**
 * Alley\WP_Bulk_Task: Memory_Cursor class
 *
 * @package alleyinteractive/wp-bulk-task
 */

declare(strict_types=1);

namespace Alley\WP_Bulk_Task\Cursor;

/**
 * A class that handles getting, updating, and resetting a cursor for a task
 * that does not persist in the database.
 *
 * @package alleyinteractive/wp-bulk-task
 */
class Memory_Cursor implements Cursor {
	/**
	 * Cursor value.
	 *
	 * @var int
	 */
	private int $cursor = 0;

	/**
	 * Gets the current value for the cursor. Defaults to 0 if not set.
	 *
	 * @return int The current value for the cursor.
	 */
	public function get(): int {
		return $this->cursor;
	}

	/**
	 * Resets the value for the cursor.
	 *
	 * @return bool
	 */
	public function reset(): bool {
		$this->cursor = 0;

		return true;
	}

	/**
	 * Sets the value for the cursor.
	 *
	 * @param int $value The new value for the cursor.
	 * @return bool
	 */
	public function set( int $value ): bool {
		$this->cursor = $value;

		return true;
	}
}
