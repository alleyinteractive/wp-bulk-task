<?php
/**
 * Alley_Interactive\WP_Bulk_Task\Progress: PHP_CLI_Progress_Bar Class
 *
 * @package alleyinteractive/wp-bulk-task
 */

namespace Alley_Interactive\WP_Bulk_Task\Progress;

/**
 * A class that extends the cli\progress\Bar class used by WP_CLI and implements
 * our custom interface.
 *
 * @package alleyinteractive/wp-bulk-task
 */
class PHP_CLI_Progress_Bar extends \cli\progress\Bar implements Progress {
	/**
	 * Define the finish line for the progress tracker.
	 *
	 * @param int $total The total to set.
	 */
	public function set_total( $total ) {
		$this->setTotal( $total );
	}
}
