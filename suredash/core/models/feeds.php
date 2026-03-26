<?php
/**
 * Portals Query Feeds Initialize.
 *
 * @package SureDash
 */

namespace SureDashboard\Core\Models;

use SureDashboard\Inc\Traits\Get_Instance;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class Feeds Query Model.
 */
class Feeds {
	use Get_Instance;

	/**
	 * Get queried data.
	 *
	 * @param array<mixed> $args Args.
	 *
	 * @return mixed
	 */
	public static function get_query_data( $args ) {
		$category_id = $args['category_id'] ?? 0;
		$post_type   = $args['post_type'] ?? '';
		$taxonomy    = $args['taxonomy'] ?? '';
		$no_of_posts = $args['posts_per_page'] ?? 5;
		$paged       = $args['paged'] ?? 1;
		$offset      = ( $paged - 1 ) * $no_of_posts;
		$order_by    = $args['order_by'] ?? 'post_date';
		$order       = $args['order'] ?? 'DESC';
		$meta_key    = $args['meta_key'] ?? '';

		// Build SELECT with computed columns for sorting.
		$select_fields = 'p.ID, p.post_title, p.post_type, p.post_status, p.post_date, p.post_author, p.comment_count';

		// Special handling for new_activity sort - query comments table directly.
		$is_new_activity_sort = $order_by === 'meta_value' && $meta_key === 'portal_last_comment_date';

		if ( $is_new_activity_sort ) {
			// Get latest comment date directly from comments table.
			$select_fields .= ', COALESCE(MAX(c.comment_date), p.post_date) AS last_comment_date';
		} elseif ( $order_by === 'meta_value_num' && ! empty( $meta_key ) ) {
			// Special handling for portal_post_likes which stores serialized array.
			if ( $meta_key === 'portal_post_likes' ) {
				// Extract array count from serialized format: a:2:{...} -> 2.
				// Use MAX to handle multiple postmeta rows per post when using GROUP BY.
				$select_fields .= ", MAX(CAST(COALESCE(SUBSTRING_INDEX(SUBSTRING_INDEX(pm.meta_value, ':{', 1), 'a:', -1), '0') AS SIGNED)) AS meta_sort_value";
			} else {
				// Add computed column for numeric meta sorting with NULL handling.
				// Use MAX to handle multiple postmeta rows per post when using GROUP BY.
				$select_fields .= ", MAX(CAST(COALESCE(pm.meta_value, '0') AS SIGNED)) AS meta_sort_value";
			}
		} elseif ( $order_by === 'meta_value' && ! empty( $meta_key ) ) {
			// Add computed column for string meta sorting with NULL handling.
			// Use MAX to handle multiple postmeta rows per post when using GROUP BY.
			$select_fields .= ", MAX(COALESCE(pm.meta_value, '1970-01-01 00:00:00')) AS meta_sort_value";
		}

		// Prepend DISTINCT to SELECT to avoid duplicate rows when joining with term_relationships or postmeta tables.
		// This is more reliable than GROUP BY when dealing with complex multi-table JOINs.
		$select_with_distinct = 'DISTINCT ' . $select_fields;
		$query                = sd_query()->select( $select_with_distinct )->from( 'posts AS p' );

		// Join with comments table for new_activity sort.
		if ( $is_new_activity_sort ) {
			$query->leftJoin(
				'comments AS c',
				// @phpstan-ignore-next-line.
				static function( $q ): void {
					$q->where( 'p.ID', '=', 'c.comment_post_ID' )
						->where( 'c.comment_approved', '=', '1' );
				}
			);
		} elseif ( in_array( $order_by, [ 'meta_value', 'meta_value_num' ], true ) && ! empty( $meta_key ) ) {
			// Join with postmeta for other meta-based sorting.
			$query->leftJoin(
				'postmeta AS pm',
				// @phpstan-ignore-next-line.
				static function( $q ) use ( $meta_key ): void {
					$q->where( 'p.ID', '=', 'pm.post_id' )
						->where( 'pm.meta_key', '=', $meta_key );
				}
			);
		}

		if ( $category_id ) {
			$query->join( 'term_relationships AS tr', 'p.ID', '=', 'tr.object_id' )
				->join( 'term_taxonomy AS tt', 'tr.term_taxonomy_id', '=', 'tt.term_taxonomy_id' )
				->join( 'terms AS t', 'tt.term_id', '=', 't.term_id' ) // Ensure the join with 'terms' to filter based on terms.
				->where( 'tt.taxonomy', '=', $taxonomy ) // Match the taxonomy.
				->where( 'tt.term_id', '=', $category_id ); // Match the category ID.
		}

		$query->where( 'p.post_type', '=', $post_type ) // Match the post type.
			->where( 'p.post_status', '=', 'publish' ); // Filter only published posts.

		// Group by post ID only when we have aggregate functions (MAX) in the SELECT.
		// DISTINCT (added above) handles deduplication from JOINs, so GROUP BY is only needed for aggregates.
		if ( $is_new_activity_sort || ( in_array( $order_by, [ 'meta_value', 'meta_value_num' ], true ) && ! empty( $meta_key ) ) ) {
			$query->group_by( 'p.ID' );
		}

		$query->limit( $no_of_posts ) // Limit the number of posts to match posts_per_page in WP_Query.
			->offset( $offset ); // Apply pagination using OFFSET.

		// Apply ordering based on sort type.
		if ( $is_new_activity_sort ) {
			// Sort by latest comment date (or post date if no comments).
			$query->order_by( 'last_comment_date', $order )
				->order_by( 'p.post_date', 'DESC' ); // Secondary sort by date.
		} elseif ( $order_by === 'meta_value_num' || $order_by === 'meta_value' ) {
			// Use computed column for meta sorting (with NULL handling).
			$query->order_by( 'meta_sort_value', $order )
				->order_by( 'p.post_date', 'DESC' ); // Secondary sort by date.
		} elseif ( $order_by === 'comment_count' ) {
			$query->order_by( 'p.comment_count', $order )
				->order_by( 'p.post_date', 'DESC' ); // Secondary sort by date for consistent ordering.
		} elseif ( $order_by === 'title' ) {
			$query->order_by( 'p.post_title', $order );
		} else {
			// Default to post_date.
			$query->order_by( 'p.post_date', $order );
		}

		return $query->get( ARRAY_A );
	}

	/**
	 * Get user query data.
	 *
	 * @param array<mixed> $args Args.
	 *
	 * @return mixed
	 */
	public static function get_user_query_data( $args ) {
		$user_id     = $args['user_id'] ?? 0;
		$post_types  = $args['post_types'] ?? [];
		$no_of_posts = $args['posts_per_page'] ?? 5;
		$paged       = $args['paged'] ?? 1;
		$offset      = ( $paged - 1 ) * $no_of_posts;
		$order_by    = $args['order_by'] ?? 'post_date';
		$order       = $args['order'] ?? 'DESC';

		return sd_query()->select( 'p.ID, p.post_title, p.post_status, p.post_type, p.post_author, p.post_date' )
			->from( 'posts AS p' )
			->order_by( "p.{$order_by}", $order )
			->where( 'p.post_status', '=', 'publish' )  // Filter only published posts.
			->where( 'p.post_author', '=', $user_id )  // Filter by author.
			->whereIn( 'p.post_type', $post_types )  // Filter by post types (this handles multiple post types).
			->limit( $no_of_posts )  // Limit the number of posts as in posts_per_page.
			->offset( $offset ) // Apply pagination using OFFSET.
			->get( ARRAY_A );
	}
}
