<?php
/**
 * Alley\WP_Bulk_Task\Progress: PHP_CLI_Progress_Bar Class
 *
 * @package alleyinteractive/wp-bulk-task
 */

namespace Alley\WP_Bulk_Task\Progress;

/**
 * A class that extends the cli\progress\Bar class used by WP_CLI and implements
 * our custom interface.
 *
 * @package alleyinteractive/wp-bulk-task
 */
class PHP_CLI_Progress_Bar extends \cli\progress\Bar implements Progress {
	/**
	 * Override the constructor to not require a total (this is set later).
	 *
	 * @param string $msg The text to display next to the notifier.
	 */
	public function __construct( string $msg ) { // phpcs:ignore Generic.CodeAnalysis.UselessOverridingMethod.Found
		parent::__construct( $msg, 0 );
	}

	/**
	 * Sets the current value of the progress tracker.
	 *
	 * @param int $current The new current value for the progress tracker.
	 */
	public function set_current( int $current ): void {
		$this->tick( $current - $this->_current );
	}

	/**
	 * Tells the progress tracker that it is finished.
	 */
	public function set_finished(): void {
		$this->finish();
	}

	/**
	 * Define the finish line for the progress tracker.
	 *
	 * @param int $total The total to set.
	 */
	public function set_total( int $total ): void {
		$this->setTotal( $total );
	}
}
