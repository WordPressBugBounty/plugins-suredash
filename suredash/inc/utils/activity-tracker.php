<?php
/**
 * Activity Tracker Utility
 *
 * Handles user activity tracking for spaces and unread post counts.
 *
 * @package SureDash
 * @since 1.6.0
 */

namespace SureDashboard\Inc\Utils;

use SureDashboard\Inc\Traits\Get_Instance;

defined( 'ABSPATH' ) || exit;

/**
 * Class Activity_Tracker
 *
 * @since 1.6.0
 */
class Activity_Tracker {
	use Get_Instance;

	/**
	 * Meta key prefix for last visit timestamps.
	 */
	private const META_KEY_PREFIX = 'suredash_space_last_visit_';

	/**
	 * Cache group for transients.
	 */
	private const CACHE_GROUP = 'suredash_unread_counts';

	/**
	 * Cache expiration time in seconds (2 minutes).
	 */
	private const CACHE_EXPIRATION = 120;

	/**
	 * Update the last visit timestamp for a user in a specific space.
	 *
	 * @param int $user_id User ID.
	 * @param int $space_id Space ID (term_id from portal_group taxonomy).
	 * @return bool True on success, false on failure.
	 * @since 1.6.0
	 */
	public function update_space_last_visit( $user_id, $space_id ) {
		if ( empty( $user_id ) || empty( $space_id ) ) {
			return false;
		}

		$meta_key = self::META_KEY_PREFIX . $space_id;
		$result   = sd_update_user_meta( $user_id, $meta_key, time() );

		// Clear cached unread count for this space.
		$this->clear_cache( $user_id, $space_id );

		return $result !== false;
	}

	/**
	 * Get unread posts count for a user in a specific space.
	 * Uses counter-based calculation for scalability: total posts - viewed count = unread count.
	 *
	 * @param int $user_id User ID.
	 * @param int $space_id Space ID (term_id from community-forum taxonomy).
	 * @return int Number of unread posts.
	 * @since 1.6.0
	 */
	public function get_unread_posts_count( $user_id, $space_id ) {
		if ( empty( $user_id ) || empty( $space_id ) ) {
			return 0;
		}

		// Try to get from cache first.
		$cache_key = $this->get_cache_key( $user_id, $space_id );
		$cached    = get_transient( $cache_key );

		if ( $cached !== false ) {
			return absint( $cached );
		}

		// Get total posts in this space.
		$total_posts = $this->get_total_posts_in_space( $space_id );

		if ( $total_posts === 0 ) {
			set_transient( $cache_key, 0, self::CACHE_EXPIRATION );
			return 0;
		}

		// Get count of posts this user has already viewed.
		$viewed_count = $this->get_viewed_count( $user_id, $space_id );

		// Calculate unread = total posts - viewed count.
		// Use max() to prevent negative numbers if posts were deleted.
		$unread_count = max( 0, $total_posts - $viewed_count );

		// Cache the result.
		set_transient( $cache_key, $unread_count, self::CACHE_EXPIRATION );

		return $unread_count;
	}

	/**
	 * Mark posts as viewed when user visits a space.
	 * Uses a counter-based approach for scalability with large post counts.
	 *
	 * @param int $user_id User ID.
	 * @param int $space_id Space ID.
	 * @param int $post_id Optional specific post ID to mark as viewed.
	 * @return bool True on success.
	 * @since 1.6.0
	 */
	public function mark_posts_as_viewed( $user_id, $space_id, $post_id = 0 ) {
		if ( empty( $user_id ) || empty( $space_id ) ) {
			return false;
		}

		$meta_key     = 'suredash_viewed_count_' . $space_id;
		$viewed_count = absint( sd_get_user_meta( $user_id, $meta_key, true ) );

		if ( ! empty( $post_id ) ) {
			// Increment counter by 1 for single post view.
			$viewed_count++;
		} else {
			// Mark ALL current posts in space as viewed - set counter to total post count.
			$total_posts  = $this->get_total_posts_in_space( $space_id );
			$viewed_count = $total_posts;
		}

		// Update user meta with new count.
		$result = sd_update_user_meta( $user_id, $meta_key, $viewed_count );

		// Clear cache.
		$this->clear_cache( $user_id, $space_id );

		return $result !== false;
	}

	/**
	 * Get unread counts for all spaces for a specific user.
	 *
	 * @param int $user_id User ID.
	 * @return array<int,int> Array of space_id => unread_count pairs.
	 * @since 1.6.0
	 */
	public function get_all_unread_counts( $user_id ) {
		if ( empty( $user_id ) ) {
			return [];
		}

		// Get all forum terms (spaces).
		$terms = get_terms(
			[
				'taxonomy'   => SUREDASHBOARD_FEED_TAXONOMY,
				'hide_empty' => false,
			]
		);

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return [];
		}

		$unread_counts = [];

		foreach ( $terms as $term ) {
			$count = $this->get_unread_posts_count( $user_id, $term->term_id );
			if ( $count > 0 ) {
				$unread_counts[ $term->term_id ] = $count;
			}
		}

		return $unread_counts;
	}

	/**
	 * Mark a space as read for a user (set last visit to now).
	 *
	 * @param int $user_id User ID.
	 * @param int $space_id Space ID.
	 * @return bool True on success, false on failure.
	 * @since 1.6.0
	 */
	public function mark_space_as_read( $user_id, $space_id ) {
		return $this->update_space_last_visit( $user_id, $space_id );
	}

	/**
	 * Invalidate cache when a new post is created.
	 * Called via hook when a community post is published.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 * @since 1.6.0
	 */
	public function invalidate_cache_on_new_post( $post_id ): void {
		// Get the space/forum this post belongs to.
		$terms = wp_get_post_terms( $post_id, SUREDASHBOARD_FEED_TAXONOMY, [ 'fields' => 'ids' ] );

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return;
		}

		// Clear cache for all users (since we don't know who has visited this space).
		// This is acceptable since transients will regenerate on next request.
		foreach ( $terms as $term_id ) {
			$this->clear_cache_for_space( $term_id );
		}
	}

	/**
	 * Get count of posts that user has viewed in a space.
	 * For first-time access (no counter exists), initialize counter to current total
	 * to prevent showing all existing posts as unread.
	 *
	 * @param int $user_id User ID.
	 * @param int $space_id Space ID.
	 * @return int Count of viewed posts.
	 * @since 1.6.0
	 */
	private function get_viewed_count( $user_id, $space_id ) {
		$meta_key = 'suredash_viewed_count_' . $space_id;
		$count    = sd_get_user_meta( $user_id, $meta_key, true );

		// First time - no counter exists yet.
		// Initialize to current total so existing posts aren't shown as "unread".
		if ( $count === '' || $count === false ) {
			$total_posts = $this->get_total_posts_in_space( $space_id );
			sd_update_user_meta( $user_id, $meta_key, $total_posts );
			return $total_posts;
		}

		return absint( $count );
	}

	/**
	 * Get total count of published posts in a space.
	 *
	 * @param int $space_id Space ID (term_id from community-forum taxonomy).
	 * @return int Total post count.
	 * @since 1.6.0
	 */
	private function get_total_posts_in_space( $space_id ) {
		$args = [
			'post_type'      => SUREDASHBOARD_FEED_POST_TYPE,
			'post_status'    => 'publish',
			'tax_query'      => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
				[
					'taxonomy' => SUREDASHBOARD_FEED_TAXONOMY,
					'field'    => 'term_id',
					'terms'    => $space_id,
				],
			],
			'fields'         => 'ids',
			'posts_per_page' => -1,
			'no_found_rows'  => false,
		];

		$query = new \WP_Query( $args );

		return absint( $query->found_posts );
	}

	/**
	 * Clear cached unread count for a specific space.
	 *
	 * @param int $user_id User ID.
	 * @param int $space_id Space ID.
	 * @return bool True on success.
	 * @since 1.6.0
	 */
	private function clear_cache( $user_id, $space_id ) {
		$cache_key = $this->get_cache_key( $user_id, $space_id );
		$result    = delete_transient( $cache_key );

		// Also clear from object cache if available (handles Redis/Memcached).
		if ( function_exists( 'wp_cache_delete' ) ) {
			wp_cache_delete( $cache_key, 'transient' );
		}

		return $result;
	}

	/**
	 * Get cache key for a user-space pair.
	 *
	 * @param int $user_id User ID.
	 * @param int $space_id Space ID.
	 * @return string Cache key.
	 * @since 1.6.0
	 */
	private function get_cache_key( $user_id, $space_id ) {
		return self::CACHE_GROUP . '_' . $user_id . '_' . $space_id;
	}

	/**
	 * Clear cached unread counts for all users for a specific space.
	 *
	 * @param int $space_id Space ID.
	 * @return void
	 * @since 1.6.0
	 */
	private function clear_cache_for_space( $space_id ): void {
		global $wpdb;

		// Delete all transients for this space.
		// Pattern: _transient_suredash_unread_counts_{user_id}_{space_id}
		// Use % wildcard for user_id part.
		$pattern = '_transient_' . self::CACHE_GROUP . '_%_' . $space_id;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $pattern ) );

		// Also delete the timeout transients.
		$timeout_pattern = '_transient_timeout_' . self::CACHE_GROUP . '_%_' . $space_id;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $timeout_pattern ) );

		// Flush object cache group if available.
		if ( function_exists( 'wp_cache_flush_group' ) ) {
			wp_cache_flush_group( 'transient' );
		}
	}
}
