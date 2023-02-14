<?php
/**
 * Alley\WP_Bulk_Task: Bulk_Task class
 *
 * @package alleyinteractive/wp-bulk-task
 */

namespace Alley\WP_Bulk_Task;

use Alley\WP_Bulk_Task\Progress\Progress;
use WP_Term_Query;
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
	 * Store the current query object for bulk tasks.
	 *
	 * @var object
	 */
	protected object $query;

	/**
	 * Store the current query object hash for bulk tasks.
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
	 *
	 * This property is public and can be overridden, and the logic for
	 * determining the max ID in the range will take the larger of this property
	 * and posts_per_page.
	 *
	 * @var int
	 */
	public int $stepping = 10000;

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
	 * Manipulate the WHERE clause of a bulk task post query to paginate by ID.
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
				$this->min_id + $this->stepping,
				$where
			);
		}

		return $where;
	}

	/**
	 * Manipulate the WHERE clause of a bulk task term query to batch by ID.
	 *
	 * This checks the object hash to ensure that we don't manipulate any other
	 * queries that might run during a bulk task.
	 *
	 * @link https://github.com/WordPress/wordpress-develop/blob/6.1/src/wp-includes/class-wp-term-query.php#L731
	 *
	 * @param array $clauses Associative array of the clauses for the query.
	 * @return string Filtered array with our batching added to the WHERE clause.
	 */
	public function filter__terms_where( $clauses ) {
		if ( ! empty( $this->query ) && spl_object_hash( $this->query ) === $this->object_hash ) {
			global $wpdb;

			$clauses['where'] .= sprintf(
				' AND %s.term_id > %d AND %s.term_id <= %d %s',
				$wpdb->terms,
				$this->min_id,
				$wpdb->terms,
				$this->min_id + $this->stepping,
				$where
			);
		}

		return $clauses;
	}

	/**
	 * Loop through any number of objects efficiently with a callback, and output
	 * the progress.
	 *
	 * @param array    $args Array of args to pass to query.
	 * @param callable $callable Callback function to invoke for each object.
	 *                           The callable will be passed an object of the
	 *                           specified type.
	 * @param string   $object_type Type of object to query.
	 */
	public function run( array $args, callable $callable, string $object_type = 'wp_post' ): void {
		if ( ! method_exists( $this, "run_${object_type}_query" ) ) {
			return;
		}

		call_user_func( [ $this, "run_${object_type}_query" ], $args, $callable );
	}

	/**
	 * Loop through any number of terms efficiently with a callback, and output
	 * the progress.
	 *
	 * @param array    $args {
	 *     WP_Term_Query args. Some have overridden defaults, and some are fixed.
	 *     Anything not mentioned below will operate as normal.
	 *
	 *     @type bool   $count                  Always false.
	 *     @type string $order                  Always 'ASC'.
	 *     @type string $orderby                Always 'term_id'.
	 *     @type int    $number                 Defaults to 100.
	 *     @type bool   $update_term_meta_cache Always false.
	 * }
	 * @param callable $callable Callback function to invoke for each post.
	 *                           The callable will be passed a post object.
	 */
	public function run_wp_term_query( array $args, callable $callable ): void {
		global $wpdb;

		// Apply default arguments.
		$args = wp_parse_args(
			$args,
			[
				'number' => 100,
			],
		);

		// Force some arguments and don't let them get overridden.
		$args['count']                  = false;
		$args['order']                  = 'ASC';
		$args['orderby']                = 'term_id';
		$args['update_term_meta_cache'] = false;

		// Ensure stepping is the larger of the configured value and number.
		$this->stepping = max( $this->stepping, $args['number'] );

		// Set the min ID from the cursor.
		$this->min_id = $this->cursor->get();

		// Set the max ID from the database.
		$this->max_id = $wpdb->get_var( 'SELECT MAX(term_id) FROM ' . $wpdb->terms ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		// Handle batching.
		add_filter( 'terms_clauses', [ $this, 'filter__terms_where' ], 9999, 1 );

		// Turn off some automatic behavior that would slow down the process.
		$this->before_run();

		// All systems go.
		while ( $this->min_id < $this->max_id ) {
			// Build the query object, but don't run it without the object hash.
			$this->query = new WP_Term_Query();

			// Store the unique object hash to ensure we only filter this query.
			$this->object_hash = spl_object_hash( $this->query );

			// Run the query.
			$this->query->query( $args );

			// Fork for results vs. not.
			if ( ! empty( $this->query->terms ) ) {
				// Invoke the callable over every term.
				array_walk( $this->query->terms, $callable );

				// Update our min ID for the next query.
				$this->min_id = end( $this->query->terms )->term_id;
			} else {
				// No results found in the block of terms, so skip ahead.
				$this->min_id += $this->stepping;
			}

			// Actions to run after each batch of results.
			$this->after_batch();
		}

		// Re-enable automatic behavior turned off earlier.
		$this->after_run();

		// Remove filter after task run. Prevents double filtering the query if you're instantiating the class multiple times.
		remove_filter( 'terms_clauses', [ $this, 'filter__terms_where' ], 9999 );
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
	public function run_wp_post_query( array $args, callable $callable ): void {
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

		// Ensure stepping is the larger of the configured value and posts_per_page.
		$this->stepping = max( $this->stepping, $args['posts_per_page'] );

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
				$this->min_id = end( $query->posts )->ID;
			} else {
				// No results found in the block of posts, so skip ahead.
				$this->min_id += $this->stepping;
			}

			// Actions to run after each batch of results.
			$this->after_batch();
		}

		// Re-enable automatic behavior turned off earlier.
		$this->after_run();

		// Remove filter after task run. Prevents double filtering the query if you're instantiating the class multiple times.
		remove_filter( 'posts_where', [ $this, 'filter__posts_where' ], 9999 );
	}
}
