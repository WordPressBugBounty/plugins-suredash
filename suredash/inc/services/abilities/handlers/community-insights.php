<?php
/**
 * Community Insights Ability.
 *
 * Single flexible ability for portal analytics — 15 query types covering
 * member activity, content health, space performance, and Pro features
 * (courses, events, resources, leaderboard, badges).
 *
 * @package SureDash
 * @since 1.7.3
 */

namespace SureDashboard\Inc\Services\Abilities\Handlers;

use SureDashboard\Inc\Services\Abilities\Ability;

defined( 'ABSPATH' ) || exit;

/**
 * Community_Insights class.
 *
 * @since 1.7.3
 */
class Community_Insights extends Ability {
	/**
	 * Query types that require SureDash Pro.
	 *
	 * @since 1.7.3
	 */
	private const PRO_QUERIES = [
		'course_progress',
		'event_attendance',
		'resource_popularity',
		'leaderboard_rankings',
		'badge_distribution',
	];

	/**
	 * All supported query types.
	 *
	 * @since 1.7.3
	 */
	private const ALL_QUERIES = [
		'inactive_users',
		'top_contributors',
		'user_activity',
		'new_member_engagement',
		'unanswered_posts',
		'space_analytics',
		'trending_content',
		'content_summary',
		'bookmarked_content',
		'peak_activity_hours',
		'course_progress',
		'event_attendance',
		'resource_popularity',
		'leaderboard_rankings',
		'badge_distribution',
	];

	/**
	 * Get the unique identifier for this ability.
	 *
	 * @since 1.7.3
	 *
	 * @return string
	 */
	public function get_id(): string {
		return 'community-insights';
	}

	/**
	 * Get the human-readable name.
	 *
	 * @since 1.7.3
	 *
	 * @return string
	 */
	public function get_name(): string {
		return __( 'Community Insights', 'suredash' );
	}

	/**
	 * Get the ability description.
	 *
	 * @since 1.7.3
	 *
	 * @return string
	 */
	public function get_description(): string {
		return __( 'Flexible analytics ability for portal managers. Supports 15 query types covering member activity, content health, space performance, courses, events, resources, and gamification. Pass a query type and optional filters to get actionable insights.', 'suredash' );
	}

	/**
	 * Get the ability category.
	 *
	 * @since 1.7.3
	 *
	 * @return string
	 */
	public function get_category(): string {
		return 'analytics';
	}

	/**
	 * Get the parameter schema for this ability.
	 *
	 * @since 1.7.3
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function get_parameters(): array {
		return [
			'query'    => [
				'type'        => 'string',
				'required'    => true,
				'description' => __( 'The insight query type. Base: inactive_users, top_contributors, user_activity, new_member_engagement, unanswered_posts, space_analytics, trending_content, content_summary, bookmarked_content, peak_activity_hours. Pro: course_progress, event_attendance, resource_popularity, leaderboard_rankings, badge_distribution.', 'suredash' ),
				'enum'        => self::ALL_QUERIES,
			],
			'filters'  => [
				'type'        => 'object',
				'required'    => false,
				'description' => __( 'Query-specific filters. Common: days (integer), start_date/end_date (Y-m-d), space_id (integer), user_id (integer), period (7d/30d/90d), metric (likes/comments/all), limit (integer).', 'suredash' ),
				'default'     => [],
			],
			'per_page' => [
				'type'        => 'integer',
				'required'    => false,
				'description' => __( 'Results per page (default 10, max 100).', 'suredash' ),
				'default'     => 10,
			],
			'page'     => [
				'type'        => 'integer',
				'required'    => false,
				'description' => __( 'Page number for pagination (default 1).', 'suredash' ),
				'default'     => 1,
			],
			'order'    => [
				'type'        => 'string',
				'required'    => false,
				'description' => __( 'Sort order (default DESC).', 'suredash' ),
				'enum'        => [ 'ASC', 'DESC' ],
				'default'     => 'DESC',
			],
		];
	}

	/**
	 * Get the MCP annotations for this ability.
	 *
	 * @since 1.7.3
	 *
	 * @return array<string, bool>
	 */
	public function get_annotations(): array {
		return [
			'readOnlyHint'    => true,
			'destructiveHint' => false,
			'idempotentHint'  => true,
		];
	}

	/**
	 * Get the usage instructions for this ability.
	 *
	 * @since 1.7.3
	 *
	 * @return string
	 */
	public function get_instructions(): string {
		return implode(
			' ',
			[
				'QUERY GUIDE:',
				'inactive_users — filters: {days: 30}. Members with no posts/comments in X days.',
				'top_contributors — filters: {period: "30d"} or {start_date, end_date}. Ranked by posts+comments.',
				'user_activity — filters: {user_id: 123}. Full summary for one member.',
				'new_member_engagement — filters: {start_date, end_date}. New signups + their activity.',
				'unanswered_posts — filters: {space_id, days: 7}. Posts needing attention (0 comments).',
				'space_analytics — filters: {space_id} or omit for all spaces compared.',
				'trending_content — filters: {period: "7d", metric: "all"}. Most engaged posts.',
				'content_summary — filters: {start_date, end_date}. Aggregate totals for a period.',
				'bookmarked_content — filters: {limit: 10}. Most saved items.',
				'peak_activity_hours — filters: {days: 30}. Hourly activity distribution (0-23).',
				'PRO: course_progress — filters: {space_id}. Completion rates and drop-off.',
				'PRO: event_attendance — filters: {space_id}. Join counts per event.',
				'PRO: resource_popularity — filters: {space_id}. Access counts per resource.',
				'PRO: leaderboard_rankings — filters: {limit: 10}. Top users by points.',
				'PRO: badge_distribution — No filters. Badge stats across all members.',
			]
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param array<string, mixed> $params Validated parameters.
	 */
	public function execute( array $params ): array {
		$query    = $params['query'];
		$filters  = is_array( $params['filters'] ?? null ) ? $params['filters'] : [];
		$per_page = min( absint( $params['per_page'] ?? 10 ), 100 );
		$page     = max( absint( $params['page'] ?? 1 ), 1 );
		$order    = strtoupper( $params['order'] ?? 'DESC' ) === 'ASC' ? 'ASC' : 'DESC';

		// Gate Pro queries.
		$is_pro_active = function_exists( 'suredash_is_pro_active' ) && suredash_is_pro_active();
		if ( in_array( $query, self::PRO_QUERIES, true ) && ! $is_pro_active ) {
			return [
				'success' => false,
				'data'    => [
					'message' => __( 'This insight requires SureDash Pro.', 'suredash' ),
				],
			];
		}

		$method = 'query_' . $query;
		if ( ! method_exists( $this, $method ) ) {
			return [
				'success' => false,
				'data'    => [
					'message' => __( 'Unknown query type.', 'suredash' ),
				],
			];
		}

		return $this->{$method}( $filters, $per_page, $page, $order );
	}

	// ─── Base Queries ──────────────────────────────────────────────────

	/**
	 * Members with no posts or comments in X days.
	 *
	 * @since 1.7.3
	 * @param array<string, mixed> $filters Query filters.
	 * @param int                  $per_page Results per page.
	 * @param int                  $page Current page.
	 * @param string               $order Sort order.
	 * @return array<string, mixed>
	 */
	private function query_inactive_users( array $filters, int $per_page, int $page, string $order ): array {
		global $wpdb;

		$days      = absint( $filters['days'] ?? 30 );
		$threshold = gmdate( 'Y-m-d H:i:s', (int) strtotime( "-{$days} days" ) );
		$offset    = ( $page - 1 ) * $per_page;

		$cap_key = $wpdb->get_blog_prefix() . 'capabilities';

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$total = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT u.ID)
				FROM {$wpdb->users} u
				INNER JOIN {$wpdb->usermeta} cap ON u.ID = cap.user_id AND cap.meta_key = %s AND cap.meta_value LIKE %s
				LEFT JOIN {$wpdb->posts} p ON u.ID = p.post_author AND p.post_type = %s AND p.post_status = 'publish' AND p.post_date > %s
				LEFT JOIN {$wpdb->comments} c ON u.ID = c.user_id AND c.comment_approved = '1' AND c.comment_date > %s
				WHERE p.ID IS NULL AND c.comment_ID IS NULL",
				$cap_key,
				'%"suredash_user"%',
				SUREDASHBOARD_FEED_POST_TYPE,
				$threshold,
				$threshold
			)
		);

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT u.ID, u.user_email, u.display_name, u.user_registered,
					(SELECT MAX(lp.post_date) FROM {$wpdb->posts} lp WHERE lp.post_author = u.ID AND lp.post_type = %s AND lp.post_status = 'publish') as last_post_date,
					(SELECT MAX(lc.comment_date) FROM {$wpdb->comments} lc WHERE lc.user_id = u.ID AND lc.comment_approved = '1') as last_comment_date
				FROM {$wpdb->users} u
				INNER JOIN {$wpdb->usermeta} cap ON u.ID = cap.user_id AND cap.meta_key = %s AND cap.meta_value LIKE %s
				LEFT JOIN {$wpdb->posts} p ON u.ID = p.post_author AND p.post_type = %s AND p.post_status = 'publish' AND p.post_date > %s
				LEFT JOIN {$wpdb->comments} c ON u.ID = c.user_id AND c.comment_approved = '1' AND c.comment_date > %s
				WHERE p.ID IS NULL AND c.comment_ID IS NULL
				GROUP BY u.ID
				ORDER BY u.user_registered {$order}
				LIMIT %d OFFSET %d",
				SUREDASHBOARD_FEED_POST_TYPE,
				$cap_key,
				'%"suredash_user"%',
				SUREDASHBOARD_FEED_POST_TYPE,
				$threshold,
				$threshold,
				$per_page,
				$offset
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$users = [];
		foreach ( (array) $results as $row ) {
			$last_active = max( (int) strtotime( $row['last_post_date'] ?? '' ), (int) strtotime( $row['last_comment_date'] ?? '' ) );
			$users[]     = [
				'user_id'       => (int) $row['ID'],
				'display_name'  => $row['display_name'],
				'email'         => $row['email'] ?? $row['user_email'],
				'registered'    => $row['user_registered'],
				'last_active'   => $last_active > 0 ? gmdate( 'Y-m-d', $last_active ) : __( 'Never', 'suredash' ),
				'inactive_days' => $last_active > 0 ? (int) floor( ( time() - $last_active ) / DAY_IN_SECONDS ) : __( 'N/A', 'suredash' ),
			];
		}

		return $this->paginated_response( 'inactive_users', $users, $total, $page, $per_page );
	}

	/**
	 * Users ranked by combined activity in a period.
	 *
	 * @since 1.7.3
	 * @param array<string, mixed> $filters Query filters.
	 * @param int                  $per_page Results per page.
	 * @param int                  $page Current page.
	 * @param string               $order Sort order.
	 * @return array<string, mixed>
	 */
	private function query_top_contributors( array $filters, int $per_page, int $page, string $order ): array {
		global $wpdb;

		$dates  = $this->resolve_date_range( $filters );
		$offset = ( $page - 1 ) * $per_page;

		$cap_key = $wpdb->get_blog_prefix() . 'capabilities';

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$total = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT u.ID)
				FROM {$wpdb->users} u
				INNER JOIN {$wpdb->usermeta} cap ON u.ID = cap.user_id AND cap.meta_key = %s AND cap.meta_value LIKE %s
				WHERE (
					EXISTS (SELECT 1 FROM {$wpdb->posts} p WHERE p.post_author = u.ID AND p.post_type = %s AND p.post_status = 'publish' AND p.post_date BETWEEN %s AND %s)
					OR EXISTS (SELECT 1 FROM {$wpdb->comments} c WHERE c.user_id = u.ID AND c.comment_approved = '1' AND c.comment_date BETWEEN %s AND %s)
				)",
				$cap_key,
				'%"suredash_user"%',
				SUREDASHBOARD_FEED_POST_TYPE,
				$dates['start'],
				$dates['end'],
				$dates['start'],
				$dates['end']
			)
		);

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT u.ID, u.display_name, u.user_email,
					(SELECT COUNT(*) FROM {$wpdb->posts} p WHERE p.post_author = u.ID AND p.post_type = %s AND p.post_status = 'publish' AND p.post_date BETWEEN %s AND %s) as post_count,
					(SELECT COUNT(*) FROM {$wpdb->comments} c WHERE c.user_id = u.ID AND c.comment_approved = '1' AND c.comment_date BETWEEN %s AND %s) as comment_count
				FROM {$wpdb->users} u
				INNER JOIN {$wpdb->usermeta} cap ON u.ID = cap.user_id AND cap.meta_key = %s AND cap.meta_value LIKE %s
				HAVING (post_count + comment_count) > 0
				ORDER BY (post_count + comment_count) {$order}
				LIMIT %d OFFSET %d",
				SUREDASHBOARD_FEED_POST_TYPE,
				$dates['start'],
				$dates['end'],
				$dates['start'],
				$dates['end'],
				$cap_key,
				'%"suredash_user"%',
				$per_page,
				$offset
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$users = [];
		foreach ( (array) $results as $row ) {
			$users[] = [
				'user_id'        => (int) $row['ID'],
				'display_name'   => $row['display_name'],
				'email'          => $row['user_email'],
				'post_count'     => (int) $row['post_count'],
				'comment_count'  => (int) $row['comment_count'],
				'total_activity' => (int) $row['post_count'] + (int) $row['comment_count'],
			];
		}

		return $this->paginated_response( 'top_contributors', $users, $total, $page, $per_page );
	}

	/**
	 * Full activity summary for a specific user.
	 *
	 * @since 1.7.3
	 * @param array<string, mixed> $filters Query filters.
	 * @param int                  $per_page Unused.
	 * @param int                  $page Unused.
	 * @param string               $order Unused.
	 * @return array<string, mixed>
	 */
	private function query_user_activity( array $filters, int $per_page, int $page, string $order ): array {
		global $wpdb;

		// Suppress unused parameters — single-result query, no pagination.
		unset( $per_page, $page, $order );

		$user_id = absint( $filters['user_id'] ?? 0 );
		if ( ! $user_id ) {
			return [
				'success' => false,
				'data'    => [ 'message' => __( 'filters.user_id is required for user_activity query.', 'suredash' ) ],
			];
		}

		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return [
				'success' => false,
				'data'    => [ 'message' => __( 'User not found.', 'suredash' ) ],
			];
		}

		$post_count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_author = %d AND post_type = %s AND post_status = 'publish'",
				$user_id,
				SUREDASHBOARD_FEED_POST_TYPE
			)
		);

		$comment_count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->comments} WHERE user_id = %d AND comment_approved = '1'",
				$user_id
			)
		);

		$last_post_date = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT MAX(post_date) FROM {$wpdb->posts} WHERE post_author = %d AND post_type = %s AND post_status = 'publish'",
				$user_id,
				SUREDASHBOARD_FEED_POST_TYPE
			)
		);

		$last_comment_date = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT MAX(comment_date) FROM {$wpdb->comments} WHERE user_id = %d AND comment_approved = '1'",
				$user_id
			)
		);

		$last_active = max( (int) strtotime( (string) $last_post_date ), (int) strtotime( (string) $last_comment_date ) );

		// Spaces the user has posted in.
		$active_spaces = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT pm.meta_value
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = 'feed_group_id'
				WHERE p.post_author = %d AND p.post_type = %s AND p.post_status = 'publish'",
				$user_id,
				SUREDASHBOARD_FEED_POST_TYPE
			)
		);

		// Bookmarked items count.
		$bookmarks      = get_user_meta( $user_id, 'portal_bookmarked_items', true );
		$bookmark_count = is_array( $bookmarks ) ? count( $bookmarks ) : 0;

		$activity = [
			'user_id'        => $user_id,
			'display_name'   => function_exists( 'suredash_get_user_display_name' ) ? suredash_get_user_display_name( $user_id ) : $user->display_name,
			'email'          => $user->user_email,
			'registered'     => $user->user_registered,
			'post_count'     => $post_count,
			'comment_count'  => $comment_count,
			'bookmark_count' => $bookmark_count,
			'last_active'    => $last_active > 0 ? gmdate( 'Y-m-d H:i:s', $last_active ) : __( 'Never', 'suredash' ),
			'active_spaces'  => array_map( 'absint', (array) $active_spaces ),
		];

		// Add Pro data if available.
		if ( function_exists( 'suredash_is_pro_active' ) && suredash_is_pro_active() ) {
			$points          = get_user_meta( $user_id, 'suredash_user_points', true );
			$badges          = get_user_meta( $user_id, 'portal_badges', true );
			$attended_events = get_user_meta( $user_id, 'portal_attended_events', true );

			$activity['leaderboard_points'] = is_numeric( $points ) ? (int) $points : 0;
			$activity['badges']             = is_array( $badges ) ? $badges : [];
			$activity['events_attended']    = is_array( $attended_events ) ? count( $attended_events ) : 0;
		}

		return [
			'success' => true,
			'data'    => [
				'query'   => 'user_activity',
				'results' => $activity,
			],
		];
	}

	/**
	 * New signups + their engagement level.
	 *
	 * @since 1.7.3
	 * @param array<string, mixed> $filters Query filters.
	 * @param int                  $per_page Results per page.
	 * @param int                  $page Current page.
	 * @param string               $order Sort order.
	 * @return array<string, mixed>
	 */
	private function query_new_member_engagement( array $filters, int $per_page, int $page, string $order ): array {
		global $wpdb;

		$dates  = $this->resolve_date_range( $filters );
		$offset = ( $page - 1 ) * $per_page;

		$cap_key = $wpdb->get_blog_prefix() . 'capabilities';

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$total = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT u.ID)
				FROM {$wpdb->users} u
				INNER JOIN {$wpdb->usermeta} cap ON u.ID = cap.user_id AND cap.meta_key = %s AND cap.meta_value LIKE %s
				WHERE u.user_registered BETWEEN %s AND %s",
				$cap_key,
				'%"suredash_user"%',
				$dates['start'],
				$dates['end']
			)
		);

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT u.ID, u.display_name, u.user_email, u.user_registered,
					(SELECT COUNT(*) FROM {$wpdb->posts} p WHERE p.post_author = u.ID AND p.post_type = %s AND p.post_status = 'publish') as post_count,
					(SELECT COUNT(*) FROM {$wpdb->comments} c WHERE c.user_id = u.ID AND c.comment_approved = '1') as comment_count
				FROM {$wpdb->users} u
				INNER JOIN {$wpdb->usermeta} cap ON u.ID = cap.user_id AND cap.meta_key = %s AND cap.meta_value LIKE %s
				WHERE u.user_registered BETWEEN %s AND %s
				ORDER BY u.user_registered {$order}
				LIMIT %d OFFSET %d",
				SUREDASHBOARD_FEED_POST_TYPE,
				$cap_key,
				'%"suredash_user"%',
				$dates['start'],
				$dates['end'],
				$per_page,
				$offset
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$users   = [];
		$engaged = 0;
		foreach ( (array) $results as $row ) {
			$has_activity = (int) $row['post_count'] + (int) $row['comment_count'] > 0;
			if ( $has_activity ) {
				++$engaged;
			}
			$users[] = [
				'user_id'       => (int) $row['ID'],
				'display_name'  => $row['display_name'],
				'email'         => $row['user_email'],
				'registered'    => $row['user_registered'],
				'post_count'    => (int) $row['post_count'],
				'comment_count' => (int) $row['comment_count'],
				'engaged'       => $has_activity,
			];
		}

		$response                            = $this->paginated_response( 'new_member_engagement', $users, $total, $page, $per_page );
		$response['data']['engaged_count']   = $engaged;
		$response['data']['engagement_rate'] = $total > 0 ? round( $engaged / min( $total, $per_page ) * 100, 1 ) : 0;

		return $response;
	}

	/**
	 * Posts with zero comments.
	 *
	 * @since 1.7.3
	 * @param array<string, mixed> $filters Query filters.
	 * @param int                  $per_page Results per page.
	 * @param int                  $page Current page.
	 * @param string               $order Sort order.
	 * @return array<string, mixed>
	 */
	private function query_unanswered_posts( array $filters, int $per_page, int $page, string $order ): array {
		global $wpdb;

		$days      = absint( $filters['days'] ?? 7 );
		$threshold = gmdate( 'Y-m-d H:i:s', (int) strtotime( "-{$days} days" ) );
		$offset    = ( $page - 1 ) * $per_page;
		$space_id  = absint( $filters['space_id'] ?? 0 );

		$space_clause = '';
		if ( $space_id > 0 ) {
			$space_clause = $wpdb->prepare( ' AND pm_space.meta_value = %s', (string) $space_id );
		}

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$total = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT p.ID)
				FROM {$wpdb->posts} p
				LEFT JOIN {$wpdb->comments} c ON p.ID = c.comment_post_ID AND c.comment_approved = '1'
				LEFT JOIN {$wpdb->postmeta} pm_space ON p.ID = pm_space.post_id AND pm_space.meta_key = 'feed_group_id'
				WHERE p.post_type = %s AND p.post_status = 'publish' AND p.post_date > %s
				{$space_clause}
				AND c.comment_ID IS NULL",
				SUREDASHBOARD_FEED_POST_TYPE,
				$threshold
			)
		);

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.ID, p.post_title, p.post_date, p.post_author,
					pm_space.meta_value as space_id
				FROM {$wpdb->posts} p
				LEFT JOIN {$wpdb->comments} c ON p.ID = c.comment_post_ID AND c.comment_approved = '1'
				LEFT JOIN {$wpdb->postmeta} pm_space ON p.ID = pm_space.post_id AND pm_space.meta_key = 'feed_group_id'
				WHERE p.post_type = %s AND p.post_status = 'publish' AND p.post_date > %s
				{$space_clause}
				AND c.comment_ID IS NULL
				GROUP BY p.ID
				ORDER BY p.post_date {$order}
				LIMIT %d OFFSET %d",
				SUREDASHBOARD_FEED_POST_TYPE,
				$threshold,
				$per_page,
				$offset
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$posts = [];
		foreach ( (array) $results as $row ) {
			$posts[] = [
				'post_id'      => (int) $row['ID'],
				'title'        => $row['post_title'],
				'date'         => $row['post_date'],
				'author'       => function_exists( 'suredash_get_user_display_name' ) ? suredash_get_user_display_name( (int) $row['post_author'] ) : get_the_author_meta( 'display_name', (int) $row['post_author'] ),
				'space_id'     => (int) ( $row['space_id'] ?? 0 ),
				'permalink'    => get_permalink( (int) $row['ID'] ),
				'days_waiting' => (int) floor( ( time() - strtotime( $row['post_date'] ) ) / DAY_IN_SECONDS ),
			];
		}

		return $this->paginated_response( 'unanswered_posts', $posts, $total, $page, $per_page );
	}

	/**
	 * Per-space health metrics.
	 *
	 * @since 1.7.3
	 * @param array<string, mixed> $filters Query filters.
	 * @param int                  $per_page Results per page.
	 * @param int                  $page Current page.
	 * @param string               $order Sort order.
	 * @return array<string, mixed>
	 */
	private function query_space_analytics( array $filters, int $per_page, int $page, string $order ): array {
		global $wpdb;

		$space_id = absint( $filters['space_id'] ?? 0 );
		$offset   = ( $page - 1 ) * $per_page;

		// Get spaces.
		$space_args = [
			'post_type'      => SUREDASHBOARD_POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => $space_id > 0 ? 1 : $per_page,
			'offset'         => $space_id > 0 ? 0 : $offset,
			'orderby'        => 'title',
			'order'          => $order,
		];

		if ( $space_id > 0 ) {
			$space_args['p'] = $space_id;
		}

		$space_query = new \WP_Query( $space_args );
		$total       = $space_id > 0 ? ( $space_query->have_posts() ? 1 : 0 ) : (int) $space_query->found_posts;
		$spaces      = [];

		foreach ( $space_query->posts as $space ) {
			$space = get_post( $space );
			if ( ! $space instanceof \WP_Post ) {
				continue;
			}
			$sid = $space->ID;

			$post_count = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$wpdb->posts} p
					INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = 'feed_group_id' AND pm.meta_value = %s
					WHERE p.post_type = %s AND p.post_status = 'publish'",
					(string) $sid,
					SUREDASHBOARD_FEED_POST_TYPE
				)
			);

			$comment_count = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(c.comment_ID) FROM {$wpdb->comments} c
					INNER JOIN {$wpdb->posts} p ON c.comment_post_ID = p.ID
					INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = 'feed_group_id' AND pm.meta_value = %s
					WHERE p.post_type = %s AND c.comment_approved = '1'",
					(string) $sid,
					SUREDASHBOARD_FEED_POST_TYPE
				)
			);

			$unique_contributors = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(DISTINCT p.post_author) FROM {$wpdb->posts} p
					INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = 'feed_group_id' AND pm.meta_value = %s
					WHERE p.post_type = %s AND p.post_status = 'publish'",
					(string) $sid,
					SUREDASHBOARD_FEED_POST_TYPE
				)
			);

			$last_activity = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT MAX(p.post_date) FROM {$wpdb->posts} p
					INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = 'feed_group_id' AND pm.meta_value = %s
					WHERE p.post_type = %s AND p.post_status = 'publish'",
					(string) $sid,
					SUREDASHBOARD_FEED_POST_TYPE
				)
			);

			$integration = get_post_meta( $sid, 'integration', true );

			$spaces[] = [
				'space_id'            => $sid,
				'title'               => $space->post_title,
				'integration'         => is_string( $integration ) ? $integration : 'posts_discussion',
				'post_count'          => $post_count,
				'comment_count'       => $comment_count,
				'unique_contributors' => $unique_contributors,
				'last_activity'       => ! empty( $last_activity ) ? $last_activity : __( 'No activity', 'suredash' ),
			];
		}

		wp_reset_postdata();

		return $this->paginated_response( 'space_analytics', $spaces, $total, $page, $per_page );
	}

	/**
	 * Most engaged posts recently.
	 *
	 * @since 1.7.3
	 * @param array<string, mixed> $filters Query filters.
	 * @param int                  $per_page Results per page.
	 * @param int                  $page Current page.
	 * @param string               $order Sort order.
	 * @return array<string, mixed>
	 */
	private function query_trending_content( array $filters, int $per_page, int $page, string $order ): array {
		global $wpdb;

		$dates  = $this->resolve_date_range( $filters );
		$offset = ( $page - 1 ) * $per_page;

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.ID, p.post_title, p.post_date, p.post_author,
					(SELECT COUNT(*) FROM {$wpdb->comments} c WHERE c.comment_post_ID = p.ID AND c.comment_approved = '1') as comment_count,
					pm_likes.meta_value as likes_data
				FROM {$wpdb->posts} p
				LEFT JOIN {$wpdb->postmeta} pm_likes ON p.ID = pm_likes.post_id AND pm_likes.meta_key = 'portal_post_likes'
				WHERE p.post_type = %s AND p.post_status = 'publish' AND p.post_date BETWEEN %s AND %s
				ORDER BY comment_count {$order}
				LIMIT %d OFFSET %d",
				SUREDASHBOARD_FEED_POST_TYPE,
				$dates['start'],
				$dates['end'],
				$per_page + $offset,
				0
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$posts = [];
		foreach ( (array) $results as $row ) {
			$likes      = maybe_unserialize( $row['likes_data'] ?? '' );
			$like_count = is_array( $likes ) ? count( $likes ) : 0;

			$posts[] = [
				'post_id'       => (int) $row['ID'],
				'title'         => $row['post_title'],
				'date'          => $row['post_date'],
				'author'        => function_exists( 'suredash_get_user_display_name' ) ? suredash_get_user_display_name( (int) $row['post_author'] ) : get_the_author_meta( 'display_name', (int) $row['post_author'] ),
				'like_count'    => $like_count,
				'comment_count' => (int) $row['comment_count'],
				'score'         => $like_count + (int) $row['comment_count'],
				'permalink'     => get_permalink( (int) $row['ID'] ),
			];
		}

		// Sort by combined score.
		usort( $posts, static fn( $a, $b ) => $b['score'] - $a['score'] );
		$posts = array_slice( $posts, $offset, $per_page );

		$total = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_status = 'publish' AND post_date BETWEEN %s AND %s",
				SUREDASHBOARD_FEED_POST_TYPE,
				$dates['start'],
				$dates['end']
			)
		);

		return $this->paginated_response( 'trending_content', $posts, $total, $page, $per_page );
	}

	/**
	 * Aggregate content stats for a date range.
	 *
	 * @since 1.7.3
	 * @param array<string, mixed> $filters Query filters.
	 * @param int                  $per_page Unused.
	 * @param int                  $page Unused.
	 * @param string               $order Unused.
	 * @return array<string, mixed>
	 */
	private function query_content_summary( array $filters, int $per_page, int $page, string $order ): array {
		global $wpdb;

		unset( $per_page, $page, $order );

		$dates   = $this->resolve_date_range( $filters );
		$cap_key = $wpdb->get_blog_prefix() . 'capabilities';

		$new_posts = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_status = 'publish' AND post_date BETWEEN %s AND %s",
				SUREDASHBOARD_FEED_POST_TYPE,
				$dates['start'],
				$dates['end']
			)
		);

		$new_comments = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->comments} c
				INNER JOIN {$wpdb->posts} p ON c.comment_post_ID = p.ID
				WHERE c.comment_approved = '1' AND p.post_type IN (%s, %s, %s) AND c.comment_date BETWEEN %s AND %s",
				SUREDASHBOARD_FEED_POST_TYPE,
				SUREDASHBOARD_SUB_CONTENT_POST_TYPE,
				SUREDASHBOARD_POST_TYPE,
				$dates['start'],
				$dates['end']
			)
		);

		$new_members = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(u.ID) FROM {$wpdb->users} u
				INNER JOIN {$wpdb->usermeta} cap ON u.ID = cap.user_id AND cap.meta_key = %s AND cap.meta_value LIKE %s
				WHERE u.user_registered BETWEEN %s AND %s",
				$cap_key,
				'%"suredash_user"%',
				$dates['start'],
				$dates['end']
			)
		);

		$total_members = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(u.ID) FROM {$wpdb->users} u
				INNER JOIN {$wpdb->usermeta} cap ON u.ID = cap.user_id AND cap.meta_key = %s AND cap.meta_value LIKE %s",
				$cap_key,
				'%"suredash_user"%'
			)
		);

		return [
			'success' => true,
			'data'    => [
				'query'   => 'content_summary',
				'results' => [
					'period'        => $dates['start'] . ' to ' . $dates['end'],
					'new_posts'     => $new_posts,
					'new_comments'  => $new_comments,
					'new_members'   => $new_members,
					'total_members' => $total_members,
				],
			],
		];
	}

	/**
	 * Most bookmarked content items.
	 *
	 * @since 1.7.3
	 * @param array<string, mixed> $filters Query filters.
	 * @param int                  $per_page Results per page.
	 * @param int                  $page Unused for this query.
	 * @param string               $order Sort order.
	 * @return array<string, mixed>
	 */
	private function query_bookmarked_content( array $filters, int $per_page, int $page, string $order ): array {
		global $wpdb;

		unset( $page, $order );

		$limit = absint( $filters['limit'] ?? $per_page );
		$limit = min( $limit, 100 );

		// Get all users' bookmarked items.
		$bookmark_rows = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT meta_value FROM {$wpdb->usermeta} WHERE meta_key = %s AND meta_value != ''",
				'portal_bookmarked_items'
			)
		);

		$counts = [];
		foreach ( (array) $bookmark_rows as $raw ) {
			$items = maybe_unserialize( $raw );
			if ( ! is_array( $items ) ) {
				continue;
			}
			foreach ( array_keys( $items ) as $item_id ) {
				$id = absint( $item_id );
				if ( $id > 0 ) {
					$counts[ $id ] = ( $counts[ $id ] ?? 0 ) + 1;
				}
			}
		}

		arsort( $counts );
		$top = array_slice( $counts, 0, $limit, true );

		$results = [];
		foreach ( $top as $post_id => $count ) {
			$post = get_post( $post_id );
			if ( ! $post ) {
				continue;
			}
			$results[] = [
				'post_id'        => $post_id,
				'title'          => $post->post_title,
				'post_type'      => $post->post_type,
				'bookmark_count' => $count,
				'permalink'      => get_permalink( $post_id ),
			];
		}

		return [
			'success' => true,
			'data'    => [
				'query'   => 'bookmarked_content',
				'results' => $results,
				'total'   => count( $counts ),
			],
		];
	}

	/**
	 * Hourly activity distribution.
	 *
	 * @since 1.7.3
	 * @param array<string, mixed> $filters Query filters.
	 * @param int                  $per_page Unused.
	 * @param int                  $page Unused.
	 * @param string               $order Unused.
	 * @return array<string, mixed>
	 */
	private function query_peak_activity_hours( array $filters, int $per_page, int $page, string $order ): array {
		global $wpdb;

		unset( $per_page, $page, $order );

		$days      = absint( $filters['days'] ?? 30 );
		$threshold = gmdate( 'Y-m-d H:i:s', (int) strtotime( "-{$days} days" ) );

		$post_hours = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT HOUR(post_date) as hour, COUNT(*) as count
				FROM {$wpdb->posts}
				WHERE post_type = %s AND post_status = 'publish' AND post_date > %s
				GROUP BY HOUR(post_date)",
				SUREDASHBOARD_FEED_POST_TYPE,
				$threshold
			),
			ARRAY_A
		);

		$comment_hours = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT HOUR(comment_date) as hour, COUNT(*) as count
				FROM {$wpdb->comments}
				WHERE comment_approved = '1' AND comment_date > %s
				GROUP BY HOUR(comment_date)",
				$threshold
			),
			ARRAY_A
		);

		// Merge into 0-23 hour buckets.
		$distribution = array_fill(
			0,
			24,
			[
				'posts'    => 0,
				'comments' => 0,
				'total'    => 0,
			]
		);

		foreach ( (array) $post_hours as $row ) {
			$h                           = (int) $row['hour'];
			$distribution[ $h ]['posts'] = (int) $row['count'];
		}
		foreach ( (array) $comment_hours as $row ) {
			$h                              = (int) $row['hour'];
			$distribution[ $h ]['comments'] = (int) $row['count'];
		}
		foreach ( $distribution as $h => &$data ) {
			$data['hour']  = $h;
			$data['total'] = $data['posts'] + $data['comments'];
		}
		unset( $data );

		// Find peak hour.
		$peak = 0;
		$max  = 0;
		foreach ( $distribution as $h => $data ) {
			if ( $data['total'] > $max ) {
				$max  = $data['total'];
				$peak = $h;
			}
		}

		return [
			'success' => true,
			'data'    => [
				'query'       => 'peak_activity_hours',
				'results'     => array_values( $distribution ),
				'peak_hour'   => $peak,
				'period_days' => $days,
			],
		];
	}

	// ─── Pro Queries ───────────────────────────────────────────────────

	/**
	 * Course completion stats.
	 *
	 * @since 1.7.3
	 * @param array<string, mixed> $filters Query filters.
	 * @param int                  $per_page Unused.
	 * @param int                  $page Unused.
	 * @param string               $order Unused.
	 * @return array<string, mixed>
	 */
	private function query_course_progress( array $filters, int $per_page, int $page, string $order ): array {
		global $wpdb;

		unset( $per_page, $page, $order );

		$space_id = absint( $filters['space_id'] ?? 0 );
		if ( ! $space_id ) {
			return [
				'success' => false,
				'data'    => [ 'message' => __( 'filters.space_id is required for course_progress query.', 'suredash' ) ],
			];
		}

		// Get course sections and lessons.
		$sections_raw = get_post_meta( $space_id, 'pp_course_section_loop', true );
		$sections     = is_array( $sections_raw ) ? $sections_raw : [];

		$all_lesson_ids = [];
		$section_data   = [];
		foreach ( $sections as $idx => $section ) {
			$lessons = is_array( $section['section_medias'] ?? null ) ? $section['section_medias'] : [];
			$ids     = array_map( static fn( $l ) => absint( $l['value'] ?? $l['id'] ?? 0 ), $lessons );
			$ids     = array_filter( $ids );

			$all_lesson_ids = array_merge( $all_lesson_ids, $ids );
			$section_data[] = [
				'section_index' => $idx,
				'section_title' => $section['section_title'] ?? '',
				'lesson_ids'    => $ids,
				'lesson_count'  => count( $ids ),
			];
		}

		$total_lessons = count( $all_lesson_ids );

		// Count enrolled users (anyone with the progress meta key).
		$meta_key = 'portal_course_' . $space_id . '_completed_lessons';
		$enrolled = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT user_id, meta_value FROM {$wpdb->usermeta} WHERE meta_key = %s",
				$meta_key
			),
			ARRAY_A
		);

		$enrolled_count  = count( (array) $enrolled );
		$completed_count = 0;
		$lesson_counts   = array_fill_keys( $all_lesson_ids, 0 );

		foreach ( (array) $enrolled as $row ) {
			$completed = maybe_unserialize( $row['meta_value'] );
			$completed = is_array( $completed ) ? $completed : [];

			if ( $total_lessons > 0 && count( $completed ) >= $total_lessons ) {
				++$completed_count;
			}

			foreach ( $completed as $lesson_id ) {
				$lid = absint( $lesson_id );
				if ( isset( $lesson_counts[ $lid ] ) ) {
					++$lesson_counts[ $lid ];
				}
			}
		}

		// Build per-section completion data.
		foreach ( $section_data as &$section ) {
			$section_completion = [];
			foreach ( $section['lesson_ids'] as $lid ) {
				$section_completion[] = [
					'lesson_id'       => $lid,
					'title'           => get_the_title( $lid ),
					'completions'     => $lesson_counts[ $lid ] ?? 0,
					'completion_rate' => $enrolled_count > 0 ? round( ( $lesson_counts[ $lid ] ?? 0 ) / $enrolled_count * 100, 1 ) : 0,
				];
			}
			$section['lessons'] = $section_completion;
			unset( $section['lesson_ids'] );
		}
		unset( $section );

		return [
			'success' => true,
			'data'    => [
				'query'   => 'course_progress',
				'results' => [
					'space_id'        => $space_id,
					'title'           => get_the_title( $space_id ),
					'total_lessons'   => $total_lessons,
					'enrolled'        => $enrolled_count,
					'completed'       => $completed_count,
					'completion_rate' => $enrolled_count > 0 ? round( $completed_count / $enrolled_count * 100, 1 ) : 0,
					'sections'        => $section_data,
				],
			],
		];
	}

	/**
	 * Event attendance stats.
	 *
	 * @since 1.7.3
	 * @param array<string, mixed> $filters Query filters.
	 * @param int                  $per_page Results per page.
	 * @param int                  $page Current page.
	 * @param string               $order Sort order.
	 * @return array<string, mixed>
	 */
	private function query_event_attendance( array $filters, int $per_page, int $page, string $order ): array {
		global $wpdb;

		$space_id = absint( $filters['space_id'] ?? 0 );
		if ( ! $space_id ) {
			return [
				'success' => false,
				'data'    => [ 'message' => __( 'filters.space_id is required for event_attendance query.', 'suredash' ) ],
			];
		}

		$offset = ( $page - 1 ) * $per_page;

		// Get events in this space.
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$events = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.ID, p.post_title, p.post_date,
					pm_date.meta_value as event_date,
					pm_time.meta_value as event_start_time
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} pm_space ON p.ID = pm_space.post_id AND pm_space.meta_key = 'feed_group_id' AND pm_space.meta_value = %s
				LEFT JOIN {$wpdb->postmeta} pm_date ON p.ID = pm_date.post_id AND pm_date.meta_key = 'event_date'
				LEFT JOIN {$wpdb->postmeta} pm_time ON p.ID = pm_time.post_id AND pm_time.meta_key = 'event_start_time'
				WHERE p.post_type = %s AND p.post_status = 'publish'
				ORDER BY pm_date.meta_value {$order}
				LIMIT %d OFFSET %d",
				(string) $space_id,
				SUREDASHBOARD_SUB_CONTENT_POST_TYPE,
				$per_page,
				$offset
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$total = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = 'feed_group_id' AND pm.meta_value = %s
				WHERE p.post_type = %s AND p.post_status = 'publish'",
				(string) $space_id,
				SUREDASHBOARD_SUB_CONTENT_POST_TYPE
			)
		);

		// Count attendees per event from user meta.
		$all_attendance = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT meta_value FROM {$wpdb->usermeta} WHERE meta_key = %s AND meta_value != ''",
				'portal_attended_events'
			)
		);

		$attendance_map = [];
		foreach ( (array) $all_attendance as $raw ) {
			$attended = maybe_unserialize( $raw );
			if ( ! is_array( $attended ) ) {
				continue;
			}
			foreach ( $attended as $event_id ) {
				$eid                    = absint( $event_id );
				$attendance_map[ $eid ] = ( $attendance_map[ $eid ] ?? 0 ) + 1;
			}
		}

		$results = [];
		foreach ( (array) $events as $event ) {
			$eid       = (int) $event['ID'];
			$results[] = [
				'event_id'         => $eid,
				'title'            => $event['post_title'],
				'event_date'       => $event['event_date'] ?? '',
				'event_start_time' => $event['event_start_time'] ?? '',
				'attendees'        => $attendance_map[ $eid ] ?? 0,
			];
		}

		return $this->paginated_response( 'event_attendance', $results, $total, $page, $per_page );
	}

	/**
	 * Most accessed resources.
	 *
	 * @since 1.7.3
	 * @param array<string, mixed> $filters Query filters.
	 * @param int                  $per_page Results per page.
	 * @param int                  $page Unused for this query.
	 * @param string               $order Unused.
	 * @return array<string, mixed>
	 */
	private function query_resource_popularity( array $filters, int $per_page, int $page, string $order ): array {
		global $wpdb;

		unset( $page, $order );

		$space_id = absint( $filters['space_id'] ?? 0 );
		if ( ! $space_id ) {
			return [
				'success' => false,
				'data'    => [ 'message' => __( 'filters.space_id is required for resource_popularity query.', 'suredash' ) ],
			];
		}

		// Get resources in this space.
		$resources = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.ID, p.post_title FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = 'feed_group_id' AND pm.meta_value = %s
				WHERE p.post_type = %s AND p.post_status = 'publish'",
				(string) $space_id,
				SUREDASHBOARD_SUB_CONTENT_POST_TYPE
			),
			ARRAY_A
		);

		$resource_ids = array_map( static fn( $r ) => (int) $r['ID'], (array) $resources );
		$resource_map = [];
		foreach ( (array) $resources as $r ) {
			$resource_map[ (int) $r['ID'] ] = $r['post_title'];
		}

		// Count accesses from user meta.
		$access_rows = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT meta_value FROM {$wpdb->usermeta} WHERE meta_key = %s AND meta_value != ''",
				'suredash_accessed_resources'
			)
		);

		$access_counts = array_fill_keys( $resource_ids, 0 );
		foreach ( (array) $access_rows as $raw ) {
			$records = maybe_unserialize( $raw );
			if ( ! is_array( $records ) ) {
				continue;
			}
			foreach ( $records as $record ) {
				$aid = absint( $record['attachment_id'] ?? 0 );
				if ( isset( $access_counts[ $aid ] ) ) {
					++$access_counts[ $aid ];
				}
			}
		}

		arsort( $access_counts );
		$top = array_slice( $access_counts, 0, $per_page, true );

		$results = [];
		foreach ( $top as $rid => $count ) {
			$results[] = [
				'resource_id'  => $rid,
				'title'        => $resource_map[ $rid ] ?? get_the_title( $rid ),
				'access_count' => $count,
				'permalink'    => get_permalink( $rid ),
			];
		}

		return [
			'success' => true,
			'data'    => [
				'query'   => 'resource_popularity',
				'results' => $results,
				'total'   => count( $resource_ids ),
			],
		];
	}

	/**
	 * Top users by leaderboard points.
	 *
	 * @since 1.7.3
	 * @param array<string, mixed> $filters Query filters.
	 * @param int                  $per_page Results per page.
	 * @param int                  $page Current page.
	 * @param string               $order Sort order.
	 * @return array<string, mixed>
	 */
	private function query_leaderboard_rankings( array $filters, int $per_page, int $page, string $order ): array {
		global $wpdb;

		$limit  = min( absint( $filters['limit'] ?? $per_page ), 100 );
		$offset = ( $page - 1 ) * $limit;

		$total = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE meta_key = %s AND meta_value > 0",
				'suredash_user_points'
			)
		);

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT u.ID, u.display_name, u.user_email,
					CAST(um.meta_value AS UNSIGNED) as points,
					ul.meta_value as user_level
				FROM {$wpdb->users} u
				INNER JOIN {$wpdb->usermeta} um ON u.ID = um.user_id AND um.meta_key = %s
				LEFT JOIN {$wpdb->usermeta} ul ON u.ID = ul.user_id AND ul.meta_key = %s
				WHERE CAST(um.meta_value AS UNSIGNED) > 0
				ORDER BY points {$order}
				LIMIT %d OFFSET %d",
				'suredash_user_points',
				'suredash_user_level',
				$limit,
				$offset
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$users = [];
		$rank  = $offset;
		foreach ( (array) $results as $row ) {
			++$rank;
			$users[] = [
				'rank'         => $rank,
				'user_id'      => (int) $row['ID'],
				'display_name' => $row['display_name'],
				'email'        => $row['user_email'],
				'points'       => (int) $row['points'],
				'level'        => $row['user_level'] ?? '',
			];
		}

		return $this->paginated_response( 'leaderboard_rankings', $users, $total, $page, $limit );
	}

	/**
	 * Badge distribution across members.
	 *
	 * @since 1.7.3
	 * @param array<string, mixed> $filters Unused.
	 * @param int                  $per_page Unused.
	 * @param int                  $page Unused.
	 * @param string               $order Unused.
	 * @return array<string, mixed>
	 */
	private function query_badge_distribution( array $filters, int $per_page, int $page, string $order ): array {
		global $wpdb;

		unset( $filters, $per_page, $page, $order );

		$badge_rows = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT meta_value FROM {$wpdb->usermeta} WHERE meta_key = %s AND meta_value != ''",
				'portal_badges'
			)
		);

		$badge_counts = [];
		$badge_names  = [];
		$total_users  = 0;

		foreach ( (array) $badge_rows as $raw ) {
			$badges = maybe_unserialize( $raw );
			if ( ! is_array( $badges ) || empty( $badges ) ) {
				continue;
			}
			++$total_users;
			foreach ( $badges as $badge ) {
				$bid = $badge['id'] ?? '';
				if ( $bid === '' ) {
					continue;
				}
				$badge_counts[ $bid ] = ( $badge_counts[ $bid ] ?? 0 ) + 1;
				if ( ! isset( $badge_names[ $bid ] ) ) {
					$badge_names[ $bid ] = $badge['name'] ?? $bid;
				}
			}
		}

		arsort( $badge_counts );

		$results = [];
		foreach ( $badge_counts as $bid => $count ) {
			$results[] = [
				'badge_id'   => $bid,
				'badge_name' => $badge_names[ $bid ],
				'user_count' => $count,
				'percentage' => $total_users > 0 ? round( $count / $total_users * 100, 1 ) : 0,
			];
		}

		return [
			'success' => true,
			'data'    => [
				'query'               => 'badge_distribution',
				'results'             => $results,
				'total_badge_holders' => $total_users,
			],
		];
	}

	// ─── Helpers ───────────────────────────────────────────────────────

	/**
	 * Resolve date range from filters.
	 *
	 * Supports: period (7d/30d/90d) or explicit start_date/end_date.
	 * Defaults to last 30 days.
	 *
	 * @since 1.7.3
	 * @param array<string, mixed> $filters Query filters.
	 * @return array{start: string, end: string}
	 */
	private function resolve_date_range( array $filters ): array {
		$end   = gmdate( 'Y-m-d 23:59:59' );
		$start = gmdate( 'Y-m-d 00:00:00', strtotime( '-30 days' ) );

		if ( ! empty( $filters['period'] ) ) {
			$period_map = [
				'7d'  => 7,
				'30d' => 30,
				'90d' => 90,
			];
			$days       = $period_map[ $filters['period'] ] ?? 30;
			$start      = gmdate( 'Y-m-d 00:00:00', strtotime( "-{$days} days" ) );
		}

		if ( ! empty( $filters['start_date'] ) ) {
			$start = sanitize_text_field( $filters['start_date'] ) . ' 00:00:00';
		}
		if ( ! empty( $filters['end_date'] ) ) {
			$end = sanitize_text_field( $filters['end_date'] ) . ' 23:59:59';
		}

		return [
			'start' => $start,
			'end'   => $end,
		];
	}

	/**
	 * Build a standardized paginated response.
	 *
	 * @since 1.7.3
	 * @param string            $query Query type name.
	 * @param array<int, mixed> $results Result items.
	 * @param int               $total Total matching items.
	 * @param int               $page Current page.
	 * @param int               $per_page Items per page.
	 * @return array<string, mixed>
	 */
	private function paginated_response( string $query, array $results, int $total, int $page, int $per_page ): array {
		return [
			'success' => true,
			'data'    => [
				'query'       => $query,
				'results'     => $results,
				'total'       => $total,
				'page'        => $page,
				'per_page'    => $per_page,
				'total_pages' => $per_page > 0 ? (int) ceil( $total / $per_page ) : 1,
			],
		];
	}
}
