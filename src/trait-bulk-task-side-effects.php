<?php
/**
 * Alley\Bulk_Task: Bulk_Task_Side_Effects trait
 *
 * @package alleyinteractive/wp-bulk-task
 */

declare(strict_types=1);

namespace Alley\WP_Bulk_Task;

/**
 * A trait that disables side effects for bulk tasks.
 *
 * @package alleyinteractive/wp-bulk-task
 */
trait Bulk_Task_Side_Effects {

	/**
	 * Halt integrations and date changes when updating a post.
	 */
	protected function pause_side_effects(): void {
		add_filter( 'apple_news_skip_push', '__return_true', 100 );
		add_filter( 'apple_news_should_post_autopublish', '__return_false', 100 );
		add_filter( 'pp_notification_status_change', '__return_false', 100 );
		add_filter( 'pp_notification_editorial_comment', '__return_false', 100 );
		add_filter( 'wp_insert_post_data', [ $this, 'revert_post_modified_date' ], 10, 2 );
	}

	/**
	 * Resume integrations and date changes when updating a post.
	 */
	protected function resume_side_effects(): void {
		remove_filter( 'apple_news_skip_push', '__return_true', 100 );
		remove_filter( 'apple_news_should_post_autopublish', '__return_false', 100 );
		remove_filter( 'pp_notification_status_change', '__return_false', 100 );
		remove_filter( 'pp_notification_editorial_comment', '__return_false', 100 );
		remove_filter( 'wp_insert_post_data', [ $this, 'revert_post_modified_date' ], 10, 2 );
	}

	/**
	 * Revert post modified date to date before post update.
	 *
	 * @param array $data    An array of slashed, sanitized, and processed post data.
	 * @param array $postarr An array of sanitized (and slashed) but otherwise unmodified post data.
	 * @return array Array of filtered post data.
	 */
	public function revert_post_modified_date( $data, $postarr ): array {
		if ( empty( $data['post_modified'] ) || empty( $data['post_modified_gmt'] ) || empty( $postarr['post_modified'] ) || empty( $postarr['post_modified_gmt'] ) ) {
			return $data;
		}

		$data['post_modified']     = $postarr['post_modified'];
		$data['post_modified_gmt'] = $postarr['post_modified_gmt'];

		return $data;
	}
}
