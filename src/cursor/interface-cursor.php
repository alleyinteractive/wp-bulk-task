<?php
/**
 * Alley\WP_Bulk_Task\Cursor: Cursor interface
 *
 * @package alleyinteractive/wp-bulk-task
 */

declare(strict_types=1);

namespace Alley\WP_Bulk_Task\Cursor;

/**
 * An interface that describes required methods for a cursor.
 *
 * @package alleyinteractive/wp-bulk-task
 */
interface Cursor {
	/**
	 * Gets the current value for the cursor.
	 *
	 * @return int
	 */
	public function get(): int;

	/**
	 * Resets the cursor to its initial state.
	 *
	 * @return bool
	 */
	public function reset(): bool;

	/**
	 * Sets the cursor to a specific value.
	 *
	 * @param int $value The value to set the cursor to.
	 * @return bool
	 */
	public function set( int $value ): bool;
}
