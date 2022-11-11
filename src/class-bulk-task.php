<?php
/**
 * Alley_Interactive\WP_CLI_Bulk_Task: Bulk_Task class
 *
 * @package alleyinteractive/wp-cli-bulk-task
 */

namespace Alley_Interactive\WP_CLI_Bulk_Task;

use WP_Query;

/**
 * A class that provides performant bulk task functionality.
 *
 * @package alleyinteractive/wp-cli-bulk-task
 */
class Bulk_Task {

	/**
	 * Store the max post ID from the posts table so we know when we are done.
	 *
	 * @var int
	 */
	protected int $max_id = 0;

	/**
	 * Store the last processed post ID for bulk task pagination.
	 *
	 * @var int
	 */
	protected int $min_id = 0;

	/**
	 * Store the current WP_Query object hash for bulk tasks.
	 *
	 * @var string
	 */
	protected string $object_hash = '';

	/**
	 * Constructor. Accepts a unique key, which is used to keep track of the
	 * cursor within the database.
	 *
	 * @param string $key A unique key for this bulk task, used to manage the cursor.
	 */
	public function __construct( public string $key ) {}

	/**
	 * Actions to be taken after a batch is processed. Calls the WordPress VIP
	 * helper functions if they exist, or falls back to a manual implementation if
	 * not.
	 *
	 * @link https://github.com/Automattic/vip-go-mu-plugins/blob/develop/vip-helpers/vip-caching.php
	 * @link https://github.com/Automattic/vip-go-mu-plugins/blob/develop/vip-helpers/vip-wp-cli.php
	 */
	protected function after_batch(): void {
		// Reset query cache.
		if ( function_exists( 'vip_reset_db_query_log' ) ) {
			vip_reset_db_query_log();
		} else {
			global $wpdb;

			$wpdb->queries = [];
		}

		// Reset object cache.
		if ( function_exists( 'vip_reset_local_object_cache' ) ) {
			vip_reset_local_object_cache();
		} else {
			global $wp_object_cache;

			if ( is_object( $wp_object_cache ) ) {
				$wp_object_cache->group_ops      = [];
				$wp_object_cache->memcache_debug = [];
				$wp_object_cache->cache          = [];

				if ( method_exists( $wp_object_cache, '__remoteset' ) ) {
					$wp_object_cache->__remoteset();
				}
			}
		}

		// TODO: Print status.
	}

	/**
	 * Actions to take after a bulk task is run.
	 */
	protected function after_run(): void {
		wp_defer_term_counting( false );
	}

	/**
	 * Actions to take before a bulk task is run.
	 */
	protected function before_run(): void {
		wp_defer_term_counting( true );
	}

	/**
	 * Manipulate the WHERE clause of a bulk task query to paginate by ID.
	 *
	 * This checks the object hash to ensure that we don't manipulate any other
	 * queries that might run during a bulk task.
	 *
	 * @param  string   $where The WHERE clause of the query.
	 * @param  WP_Query $query The WP_Query instance (passed by reference).
	 *
	 * @return string WHERE clause with our pagination added.
	 */
	public function filter__posts_where( $where, $query ) {
		if ( spl_object_hash( $query ) === $this->bulk_task_object_hash ) {
			// TODO: Put this at the beginning of the WHERE clause and add the max ID.
			return "AND {$GLOBALS['wpdb']->posts}.ID > {$this->bulk_task_min_id} {$where}";
		}

		return $where;
	}

	/**
	 * Loop through any number of posts efficiently with a callback, and output
	 * the progress.
	 *
	 * @param array    $args {
	 *     Optional. WP_Query args. Some have overridden defaults, and some are
	 *     fixed. Anything not mentioned below will operate as normal.
	 *
	 *     @type string $fields              Always 'ids'.
	 *     @type bool   $ignore_sticky_posts Always true.
	 *     @type string $order               Always 'ASC'.
	 *     @type string $orderby             Always 'ID'.
	 *     @type int    $paged               Always 1.
	 *     @type string $post_status         Defaults to 'any'.
	 *     @type string $post_type           Defaults to 'any'.
	 *     @type int    $posts_per_page      Defaults to 100.
	 *     @type bool   $suppress_filters    Always false.
	 * }
	 * @param callable $callable Required. Callback function to invoke for each
	 *                            post. The callable will be passed a post ID.
	 */
	public function run( array $args, callable $callable ): void {
		global $wpdb;

		// Apply default arguments.
		$args = wp_parse_args(
			$args,
			[
				'post_type'      => 'any',
				'post_status'    => 'any',
				'posts_per_page' => 100,
			],
		);

		// Force some arguments and don't let them get overridden.
		$args['fields']              = 'ids';
		$args['ignore_sticky_posts'] = true;
		$args['paged']               = 1;
		$args['order']               = 'ASC';
		$args['orderby']             = 'ID';
		$args['suppress_filters']    = false;

		// Ensure $bulk_task_min_id always starts at 0.
		// TODO: Replace this with the value from the option via the cursor.
		$this->min_id = 0;

		// Set the max ID from the database.
		$this->max_id = $wpdb->get_var( 'SELECT MAX(ID) FROM ' . $wpdb->posts ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		// Handle pagination.
		add_filter( 'posts_where', [ $this, 'filter__posts_where' ], 9999, 2 );

		// Turn off some automatic behavior that would slow down the process.
		$this->before_run();

		// All systems go.
		while ( $this->min_id < $this->max_id ) {
			// Build the query object, but don't run it without the object hash.
			$query = new WP_Query();

			// Store the unique object hash to ensure we only filter this query.
			$this->object_hash = spl_object_hash( $query );

			// Run the query.
			$query->query( $args );

			// Invoke the callable over every post.
			array_walk( $query->posts, $callable );

			// Update our min ID for the next query.
			// TODO: Handle when no match. Boost by 10x pagination.
			$this->min_id = max( $query->posts );

			// Actions to run after each batch of results.
			$this->after_batch();
		}

		// Re-enable automatic behavior turned off earlier.
		$this->after_run();
	}
}
