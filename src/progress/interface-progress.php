<?php
/**
 * Alley\WP_Bulk_Task\Progress: Progress Interface
 *
 * @package alleyinteractive/wp-bulk-task
 */

namespace Alley\WP_Bulk_Task\Progress;

/**
 * An interface that describes required properties for reporting progress.
 *
 * @package alleyinteractive/wp-bulk-task
 */
interface Progress {
	/**
	 * Sets the current value of the progress tracker.
	 *
	 * @param int $current The new current value for the progress tracker.
	 */
	public function set_current( int $current ): void;

	/**
	 * Tells the progress tracker that it is finished.
	 */
	public function set_finished(): void;

	/**
	 * Sets the maximum value of things being counted in the progress tracker.
	 *
	 * @param int $total The total that is being counted up to.
	 */
	public function set_total( int $total ): void;
}
