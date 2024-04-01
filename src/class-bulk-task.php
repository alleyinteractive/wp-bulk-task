<?php
/**
 * Alley\WP_Bulk_Task: Bulk_Task class
 *
 * @package alleyinteractive/wp-bulk-task
 */

declare( strict_types=1 );

namespace Alley\WP_Bulk_Task;

use Alley\WP_Bulk_Task\Progress\Progress;
use WP_Term_Query;
use WP_Query;
use SplFileObject;
use Exception;

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
	 * Store the current query object for bulk tasks. Used when filtering the
	 * WHERE clause and the query object is not provided in the filter.
	 *
	 * @var object|WP_Query|WP_Term_Query
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
	 * @global wpdb $wpdb WordPress database abstraction object.
	 * @global WP_Object_Cache $wp_object_cache Object cache global instance.
	 *
	 * @link https://github.com/Automattic/vip-go-mu-plugins/blob/develop/vip-helpers/vip-caching.php
	 * @link https://github.com/Automattic/vip-go-mu-plugins/blob/develop/vip-helpers/vip-wp-cli.php
	 */
	protected function after_batch(): void {
		global $wp_object_cache;

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
		} elseif ( $wp_object_cache instanceof \RedisCachePro\ObjectCaches\ObjectCacheInterface ) {
			$wp_object_cache->flush_runtime();
		} elseif ( is_object( $wp_object_cache ) ) {
			$wp_object_cache->group_ops      = [];
			$wp_object_cache->memcache_debug = [];
			$wp_object_cache->cache          = [];

			if ( method_exists( $wp_object_cache, '__remoteset' ) ) {
				$wp_object_cache->__remoteset();
			}
		}

		// Update progress.
		$this->progress?->set_current( $this->min_id );
	}

	/**
	 * Actions to take after a bulk task is run.
	 */
	protected function after_run(): void {
		if ( function_exists( 'wp_defer_term_counting' ) ) {
			wp_defer_term_counting( false );
		}

		$this->progress?->set_finished();
	}

	/**
	 * Actions to take before a bulk task is run.
	 */
	protected function before_run(): void {
		if ( function_exists( 'wp_defer_term_counting' ) ) {
			wp_defer_term_counting( true );
		}

		$this->progress?->set_total( $this->max_id );
	}

	/**
	 * Manipulate the WHERE clause of a bulk task post query to paginate by ID.
	 *
	 * This checks the object hash to ensure that we don't manipulate any other
	 * queries that might run during a bulk task.
	 *
	 * @global wpdb $wpdb WordPress database abstraction object.
	 *
	 * @param string   $where The WHERE clause of the query.
	 * @param WP_Query $query The WP_Query instance (passed by reference).
	 * @return string WHERE clause with our pagination added.
	 */
	public function filter__posts_where( $where, $query ): string {
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
	 * Manipulate the WHERE clause of a bulk task term query to batch by
	 * term_taxonomy_id. We're using term_taxonomy_id rather than term_id because
	 * they're less likely to span very large ranges.
	 *
	 * This checks the object hash to ensure that we don't manipulate any other
	 * queries that might run during a bulk task.
	 *
	 * @link https://github.com/WordPress/wordpress-develop/blob/6.1/src/wp-includes/class-wp-term-query.php#L731
	 *
	 * @param array $clauses Associative array of the clauses for the query.
	 * @return array Filtered array with our batching added to the WHERE clause.
	 */
	public function filter__terms_where( $clauses ): array {

		// Reset if not an array.
		if ( ! is_array( $clauses ) ) {
			$clauses = [];
		}

		if ( ! empty( $this->query ) && spl_object_hash( $this->query ) === $this->object_hash ) {
			$clauses['where'] .= sprintf(
				' AND tt.term_taxonomy_id > %d AND tt.term_taxonomy_id <= %d',
				$this->min_id,
				$this->min_id + $this->stepping
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
	 * Loop through any number of rows in a CSV file efficiently with a callback, and output progress.
	 *
	 * @throws Exception If the CSV file does not exist or is not readable.
	 *
	 * @param array    $args {
	 *     Args for the CSV query.
	 *     @type string $csv    Path to the CSV file.
	 *     @type int    $number Number of rows to clear cursor in each batch.
	 * }
	 * @param callable $callable Callback function to invoke for each row.
	 *                           The callable will be passed a row array.
	 */
	public function run_csv_query( array $args, callable $callable ): void {

		// Apply default arguments.
		$args = wp_parse_args(
			$args,
			[
				'csv'    => '',
				'number' => 100,
			],
		);

		// Ensure the CSV file exists and is readable.
		if ( empty( $args['csv'] ) || ! is_readable( $args['csv'] ) ) {
			throw new Exception( 'The CSV file does not exist or is not readable.' );
		}

		/**
		 * If the CSV document was created or is read on a Legacy Macintosh computer,
		 * help PHP detect line ending.
		 *
		 * @see https://php.watch/versions/8.1/auto_detect_line_endings-ini-deprecated
		 */
		if ( version_compare( PHP_VERSION, '8.1.0', '<' ) ) {
			if ( ! ini_get( 'auto_detect_line_endings' ) ) {
				ini_set( 'auto_detect_line_endings', '1' );
			}
		}

		// It assumes that the file is encoded in UTF-8.
		try {
			$csv = new SplFileObject( $args['csv'], 'r' );
		} catch ( Exception $e ) {
			throw new Exception( $e->getMessage() ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}

		// Set the CSV flags.
		$csv->setFlags( SplFileObject::READ_CSV | SplFileObject::READ_AHEAD | SplFileObject::SKIP_EMPTY | SplFileObject::DROP_NEW_LINE );

		// Set the min ID from the cursor.
		$this->min_id = $this->cursor->get();

		// Set the max ID from CSV file.
		$csv->seek( PHP_INT_MAX );
		$this->max_id = $csv->key();

		// Turn off some automatic behavior that would slow down the process.
		$this->before_run();

		/**
		 * We do not run rows in batches since the CSV file outputs all rows at once.
		 *
		 * But we need it for the cursor, progress to be updated, and resetting
		 * the object cache.
		 */
		$batch_size = 0;

		// All systems go.
		foreach ( $csv as $row ) {
			$line_number = $csv->key();

			// Skip lines outside of the range.
			if ( $line_number < $this->min_id || $line_number > $this->max_id ) {
				continue;
			}

			if ( $batch_size < $args['number'] ) {
				$batch_size++;
			}

			$callable( $row );

			// Batch size reached, so update the cursor.
			if ( 100 === $batch_size ) {
				$batch_size = 0;

				// Update our min ID for the next batch.
				$this->min_id = $line_number;
			}

			$this->after_batch();
		}

		// Unset the CSV file. Required to close the file stream.
		unset( $csv );

		// Re-enable automatic behavior turned off earlier.
		$this->after_run();
	}

	/**
	 * Loop through any number of terms efficiently with a callback, and output
	 * the progress.
	 *
	 * @global wpdb $wpdb WordPress database abstraction object.
	 *
	 * @param array    $args {
	 *     WP_Term_Query args. Some have overridden defaults, and some are fixed.
	 *     Anything not mentioned below will operate as normal.
	 *
	 *     @type string $order                  Always 'ASC'.
	 *     @type string $orderby                Always 'term_id'.
	 *     @type int    $number                 Defaults to 0 (all).
	 *     @type bool   $update_term_meta_cache Always false.
	 * }
	 * @param callable $callable Callback function to invoke for each post.
	 *                           The callable will be passed a post object.
	 */
	public function run_wp_term_query( array $args, callable $callable ): void {
		global $wpdb;

		// Apply default arguments.
		$args = wp_parse_args( $args, [ 'number' => 0 ] );

		// Force some arguments and don't let them get overridden.
		$args['order']                  = 'ASC';
		$args['orderby']                = 'term_id';
		$args['update_term_meta_cache'] = false;

		// Ensure stepping is the larger of the configured value and number.
		$this->stepping = max( $this->stepping, $args['number'] );

		// Set the min ID from the cursor.
		$this->min_id = $this->cursor->get();

		// Set the max ID from the database.
		$this->max_id = (int) $wpdb->get_var( 'SELECT MAX(term_taxonomy_id) FROM ' . $wpdb->term_taxonomy ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		// Handle batching.
		add_filter( 'terms_clauses', [ $this, 'filter__terms_where' ], 9999 );

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
				$this->min_id = end( $this->query->terms )->term_taxonomy_id;
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
	 * @global wpdb $wpdb WordPress database abstraction object.
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
		$this->max_id = (int) $wpdb->get_var( 'SELECT MAX(ID) FROM ' . $wpdb->posts ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		// Disable ElasticPress or VIP Search integration by default.
		add_filter( 'ep_skip_query_integration', '__return_true', 100 );

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

		// Remove filter after task run.
		remove_filter( 'ep_skip_query_integration', '__return_true', 100 );
	}
}
