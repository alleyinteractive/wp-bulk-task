<?php
/**
 * Alley_Interactive\WP_Bulk_Task\Progress: Progress Interface
 *
 * @package alleyinteractive/wp-bulk-task
 */

namespace Alley_Interactive\WP_Bulk_Task\Progress;

/**
 * An interface that describes required properties for reporting progress.
 *
 * @package alleyinteractive/wp-bulk-task
 */
interface Progress {
	/**
	 * Indicate that progress has reached 100%.
	 */
	public function finish();

	/**
	 * Define the finish line for the progress tracker.
	 *
	 * @param int $total The total to set.
	 */
	public function set_total( $total );

	/**
	 * Trigger the progress tracker to update. Optionally pass an amount to increment.
	 *
	 * @param int $increment The amount that the tracker should update by.
	 */
	public function tick( $increment );
}
