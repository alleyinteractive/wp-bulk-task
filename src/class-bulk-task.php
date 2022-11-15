<?php
/**
 * Alley_Interactive\WP_Bulk_Task: Bulk_Task class
 *
 * @package alleyinteractive/wp-bulk-task
 */

namespace Alley_Interactive\WP_Bulk_Task;

use Alley_Interactive\WP_Bulk_Task\Progress\Progress;
use WP_Query;

/**
 * A class that provides performant bulk task functionality.
 *
 * @package alleyinteractive/wp-bulk-task
 */
class Bulk_Task {

	/**
	 * The cursor associated with this bulk task.
	 *
	 * @var Cursor
	 */
	public Cursor $cursor;

	/**
	 * Store the max post ID from the posts table so we know when we are done.
	 *
	 * @var int
	 */
	protected int $max_id;

	/**
	 * Store the last processed post ID for bulk task pagination.
	 *
	 * @var int
	 */
	protected int $min_id;

	/**
	 * Store the current WP_Query object hash for bulk tasks.
	 *
	 * @var string
	 */
	protected string $object_hash;

	/**
	 * The slice of IDs in the database within which to look for matching posts.
	 * By keeping this number higher than the posts_per_page, but smaller than
	 * the total number of results in very large databases, it enables the right
	 * balance of performant lookups with moving through the database quickly when
	 * there are no matches in a particular slice of results.
	 */
	const STEPPING = 10000;

	/**
	 * Constructor. Accepts a unique key, which is used to keep track of the
	 * cursor within the database.
	 *
	 * @param string    $key      A unique key for this bulk task, used to manage the cursor.
	 * @param ?Progress $progress An object to handle progress updates as the task runs.
	 */
	public function __construct( public string $key, private ?Progress $progress = null ) {
		$this->cursor = new Cursor( $key );
	}

	/**
	 * Actions to be taken after a batch is processed. Calls the WordPress VIP
	 * helper functions if they exist, or falls back to a manual implementation if
	 * not.
	 *
	 * @link https://github.com/Automattic/vip-go-mu-plugins/blob/develop/vip-helpers/vip-caching.php
	 * @link https://github.com/Automattic/vip-go-mu-plugins/blob/develop/vip-helpers/vip-wp-cli.php
	 */
	protected function after_batch(): void {
		// Update cursor with the new min ID.
		$this->cursor->set( $this->min_id );

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

		// Update progress.
		$this->progress?->set_current( $this->min_id );
	}

	/**
	 * Actions to take after a bulk task is run.
	 */
	protected function after_run(): void {
		wp_defer_term_counting( false );
		$this->progress?->set_finished();
	}

	/**
	 * Actions to take before a bulk task is run.
	 */
	protected function before_run(): void {
		wp_defer_term_counting( true );
		$this->progress?->set_total( $this->max_id );
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
		if ( spl_object_hash( $query ) === $this->object_hash ) {
			global $wpdb;

			return sprintf(
				'AND %s.ID > %d AND %s.ID <= %d %s',
				$wpdb->posts,
				$this->min_id,
				$wpdb->posts,
				$this->min_id + self::STEPPING,
				$where
			);
		}

		return $where;
	}

	/**
	 * Loop through any number of posts efficiently with a callback, and output
	 * the progress.
	 *
	 * @param array    $args {
	 *     WP_Query args. Some have overridden defaults, and some are fixed.
	 *     Anything not mentioned below will operate as normal.
	 *
	 *     @type bool   $ignore_sticky_posts Always true.
	 *     @type bool   $no_found_rows       Always true.
	 *     @type string $order               Always 'ASC'.
	 *     @type string $orderby             Always 'ID'.
	 *     @type int    $paged               Always 1.
	 *     @type string $post_status         Defaults to 'any'.
	 *     @type string $post_type           Defaults to 'any'.
	 *     @type int    $posts_per_page      Defaults to 100.
	 *     @type bool   $suppress_filters    Always false.
	 * }
	 * @param callable $callable Callback function to invoke for each post.
	 *                           The callable will be passed a post object.
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
		$args['ignore_sticky_posts'] = true;
		$args['no_found_rows']       = true;
		$args['order']               = 'ASC';
		$args['orderby']             = 'ID';
		$args['paged']               = 1;
		$args['suppress_filters']    = false;

		// Set the min ID from the cursor.
		$this->min_id = $this->cursor->get();

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

			// Fork for results vs. not.
			if ( $query->have_posts() ) {
				// Invoke the callable over every post.
				array_walk( $query->posts, $callable );

				// Update our min ID for the next query.
				$this->min_id = max( wp_list_pluck( $query->posts, 'ID' ) );
			} else {
				// No results found in the block of posts, so skip ahead.
				$this->min_id += self::STEPPING;
			}

			// Actions to run after each batch of results.
			$this->after_batch();
		}

		// Re-enable automatic behavior turned off earlier.
		$this->after_run();
	}
}
