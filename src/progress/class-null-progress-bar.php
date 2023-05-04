<?php
/**
 * Alley\WP_Bulk_Task\Progress: Null_Progress_Bar Class
 *
 * @package alleyinteractive/wp-bulk-task
 */

namespace Alley\WP_Bulk_Task\Progress;

/**
 * No-op progress bar.
 *
 * @package alleyinteractive/wp-bulk-task
 */
class Null_Progress_Bar implements Progress {
	/**
	 * Sets the current value of the progress tracker.
	 *
	 * @param int $current The new current value for the progress tracker.
	 */
	public function set_current( int $current ): void {
		// Nothing.
	}

	/**
	 * Tells the progress tracker that it is finished.
	 */
	public function set_finished(): void {
		// Nothing.
	}

	/**
	 * Define the finish line for the progress tracker.
	 *
	 * @param int $total The total to set.
	 */
	public function set_total( int $total ): void {
		// Nothing.
	}
}
