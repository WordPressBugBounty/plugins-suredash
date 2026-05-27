<?php
/**
 * SearchPostsService — search posts for the custom /suredash/v1/search endpoint.
 *
 * Provides count() and query() methods. query() returns result objects
 * ready for the REST response (pre-highlighted title/excerpt, avatar
 * markup, formatted date).
 *
 * @package SureDash
 */

namespace SureDashboard\Inc\Services\Search;

use SureDashboard\Inc\Traits\Get_Instance;
use SureDashboard\Inc\Utils\Helper;

defined( 'ABSPATH' ) || exit;

/**
 * Class SearchPostsService.
 *
 * @since 1.3.0
 */
class SearchPostsService {
	use Get_Instance;

	/**
	 * Resolve the post types included in this search "type" bucket.
	 *
	 * Callers can override via `suredash_search_type_post_types` —
	 * e.g., the Pro addon filters the bucket for 'lessons' to only include
	 * `community-content` posts with `content_type = lesson`.
	 *
	 * @param string $search_type Search type key (e.g. 'posts', 'lessons', 'events').
	 * @return array<int,string> Post type slugs.
	 */
	public static function get_post_types_for( $search_type ) {
		// The 'spaces' tab is dedicated to portal spaces themselves —
		// return only the portal post type. All other types (posts /
		// lessons / events / resources) operate on content within spaces
		// and explicitly exclude SUREDASHBOARD_POST_TYPE.
		if ( $search_type === 'spaces' ) {
			return array_values(
				(array) apply_filters(
					'suredash_search_type_post_types',
					[ SUREDASHBOARD_POST_TYPE ],
					$search_type
				)
			);
		}

		// Default content-search map omits SUREDASHBOARD_POST_TYPE — spaces
		// have their own 'spaces' search type above. Sites that want
		// mixed results can re-add via `suredash_searchable_post_types`.
		$searchable_post_types = apply_filters(
			'suredash_searchable_post_types',
			[
				SUREDASHBOARD_FEED_POST_TYPE        => __( 'Portal Posts', 'suredash' ),
				SUREDASHBOARD_SUB_CONTENT_POST_TYPE => __( 'Portal Contents', 'suredash' ),
			]
		);

		$default = array_keys( $searchable_post_types );

		return array_values( (array) apply_filters( 'suredash_search_type_post_types', $default, $search_type ) );
	}

	/**
	 * Count posts matching the search, after dropping protected ones.
	 *
	 * Pulls all matching IDs (no per_page cap) and runs each through
	 * `suredash_is_post_protected()` so the count matches what the user
	 * will actually see on screen. Skipped for admins (they see all).
	 *
	 * @param array<string,mixed> $args Normalized args — must contain 'q', 'portal_id', 'search_type'.
	 * @return int
	 */
	public function count( array $args ) {
		$query_args = $this->build_query_args( $args );

		$query_args['fields']                 = 'ids';
		$query_args['no_found_rows']          = true;
		$query_args['posts_per_page']         = -1;
		$query_args['update_post_meta_cache'] = false;
		$query_args['update_post_term_cache'] = false;

		$query = $this->run_query( $query_args );
		$ids   = is_array( $query->posts ) ? $query->posts : [];

		if ( current_user_can( 'manage_options' ) || ! function_exists( 'suredash_is_post_protected' ) ) {
			return count( $ids );
		}

		$count = 0;
		foreach ( $ids as $id ) {
			$post_id = $id instanceof \WP_Post ? (int) $id->ID : (int) $id;
			if ( ! suredash_is_post_protected( $post_id ) ) {
				$count++;
			}
		}
		return $count;
	}

	/**
	 * Run the search and return result objects.
	 *
	 * @param array<string,mixed> $args Normalized args.
	 * @return array{items:array<int,array<string,mixed>>,total:int,pages:int,current:int}
	 */
	public function query( array $args ) {
		$per_page = isset( $args['per_page'] ) ? max( 1, (int) $args['per_page'] ) : 5;
		$page     = isset( $args['page'] ) ? max( 1, (int) $args['page'] ) : 1;

		$query_args                   = $this->build_query_args( $args );
		$query_args['posts_per_page'] = $per_page;
		$query_args['paged']          = $page;
		// Need raw match count so pagination keeps progressing even when
		// many items are dropped by the suredash_search_post_visible filter.
		$query_args['no_found_rows'] = false;

		$query     = $this->run_query( $query_args );
		$raw_total = (int) $query->found_posts;

		// Admins bypass visibility checks. For everyone else, when the
		// canonical `suredash_is_post_protected()` helper is available
		// (free + Pro installs), call it directly so visibility logic stays
		// consistent with `count()` even if no filter handler is registered
		// against `suredash_search_post_visible` (free-only installs).
		// Bypass condition mirrors `count()` so totals and items stay in sync.
		$enforce_protected = ! current_user_can( 'manage_options' )
			&& function_exists( 'suredash_is_post_protected' );

		$items = [];
		foreach ( $query->posts as $post ) {
			if ( ! $post instanceof \WP_Post ) {
				continue;
			}
			// Visibility check — runs before format_result so we skip the
			// excerpt-building / image-fetch / meta-line work entirely for
			// posts the user can't see. Pro hooks this with
			// `suredash_is_post_protected()` to honor visibility_scope and
			// SureMembers rules.
			if ( $enforce_protected && suredash_is_post_protected( (int) $post->ID ) ) {
				continue;
			}
			if ( ! (bool) apply_filters( 'suredash_search_post_visible', true, $post ) ) {
				continue;
			}
			$item = $this->format_result( $post, (string) $args['q'] );
			if ( empty( $item ) ) {
				continue;
			}
			$items[] = $item;
		}

		// `total` is the post-filter count (drives the tab badge "Posts (12)").
		// `pages` is computed from the raw match count so infinite scroll
		// keeps fetching pages even when protected items cluster early in
		// the result set and a page returns fewer (or zero) items.
		$total = isset( $args['known_total'] ) ? (int) $args['known_total'] : $this->count( $args );
		$pages = $per_page > 0 ? (int) max( 1, ceil( $raw_total / $per_page ) ) : 1;

		return [
			'items'   => $items,
			'total'   => $total,
			'pages'   => $pages,
			'current' => $page,
		];
	}

	/**
	 * Format a MySQL date into a short display string.
	 *
	 * @param string $mysql_date MySQL datetime.
	 * @return string E.g., "Oct 19 2025".
	 */
	public static function format_date( $mysql_date ) {
		$timestamp = strtotime( (string) $mysql_date );
		if ( ! $timestamp ) {
			return '';
		}
		$formatted = wp_date( 'M j Y', $timestamp );
		return is_string( $formatted ) ? $formatted : '';
	}

	/**
	 * Build the shared WP_Query args (used by both count() and query()).
	 *
	 * @param array<string,mixed> $args Normalized args.
	 * @return array<string,mixed>
	 */
	private function build_query_args( array $args ) {
		$term        = (string) ( $args['q'] ?? '' );
		$portal_id   = (int) ( $args['portal_id'] ?? 0 );
		$search_type = (string) ( $args['search_type'] ?? 'posts' );

		$orderby_map = [
			'date'  => 'date',
			'title' => 'title',
		];
		$orderby     = $orderby_map[ $args['orderby'] ?? 'date' ] ?? 'date';
		$order       = ( $args['order'] ?? 'desc' ) === 'asc' ? 'ASC' : 'DESC';

		$query_args = [
			's'                => $term,
			'post_type'        => self::get_post_types_for( $search_type ),
			'post_status'      => 'publish',
			'orderby'          => $orderby,
			'order'            => $order,
			'has_password'     => false,
			'posts_per_page'   => 10,
			'suppress_filters' => false,
		];

		// Scope results to a specific portal when requested.
		if ( $portal_id > 0 ) {
			$query_args['post_parent'] = $portal_id;
		}

		/**
		 * Filter: allow Pro / third-party integrations to adjust the
		 * WP_Query args for a given search type (e.g., lessons → add
		 * meta_query for content_type, exclude link-type spaces, etc.).
		 *
		 * @param array  $query_args  WP_Query args.
		 * @param string $search_type Search type key.
		 * @param array  $args        Full normalized search args.
		 */
		return (array) apply_filters( 'suredash_search_posts_query_args', $query_args, $search_type, $args );
	}

	/**
	 * Run the WP_Query with title-only LIKE filter for speed.
	 *
	 * `Helper::search_only_titles` restricts the `s` parameter's LIKE to
	 * post_title which dodges a full post_content scan. Pro / third-party
	 * plugins can short-circuit the whole thing via `suredash_search_posts_override`.
	 *
	 * @param array<string,mixed> $query_args Query args.
	 * @return \WP_Query
	 */
	private function run_query( array $query_args ) {
		/**
		 * Filter: full override of the posts search query.
		 *
		 * Return a WP_Query (or any object with ->posts, ->found_posts, ->max_num_pages)
		 * to bypass the default title-LIKE logic entirely. Useful for
		 * ElasticPress, SearchWP, Meilisearch, etc.
		 *
		 * @param \WP_Query|null       $override   Overridden query (null to run default).
		 * @param array<string,mixed>  $query_args WP_Query args.
		 */
		$override = apply_filters( 'suredash_search_posts_override', null, $query_args );
		if ( $override instanceof \WP_Query ) {
			return $override;
		}

		add_filter( 'posts_search', [ Helper::class, 'search_only_titles' ], 10, 2 );
		$query = new \WP_Query( $query_args );
		remove_filter( 'posts_search', [ Helper::class, 'search_only_titles' ], 10 );

		return $query;
	}

	/**
	 * Shape a WP_Post into a Post Result Object.
	 *
	 * @param \WP_Post $post Post.
	 * @param string   $term Search term (for highlighting).
	 * @return array<string,mixed>
	 */
	private function format_result( $post, $term ) {
		$author_id   = (int) $post->post_author;
		$author_name = $author_id ? suredash_get_user_display_name( $author_id ) : '';
		$avatar      = $author_id ? suredash_get_user_avatar( $author_id, false, 36 ) : '';

		// Password-protected posts expose title but NOT content (see UI checklist).
		$content_source = $post->post_password === '' ? $post->post_content : '';
		$excerpt_source = $post->post_excerpt !== '' ? $post->post_excerpt : $content_source;

		$portal_id    = $this->resolve_portal_id( $post );
		$portal_title = $portal_id ? (string) get_the_title( $portal_id ) : '';

		$is_space = ( $post->post_type === SUREDASHBOARD_POST_TYPE );

		// Engagement counts for the Posts card row right edge.
		$comments_count = (int) $post->comment_count;
		$likes_raw      = get_post_meta( (int) $post->ID, 'portal_post_likes', true );
		$likes_count    = is_array( $likes_raw ) ? count( $likes_raw ) : 0;

		$result = [
			'id'             => (int) $post->ID,
			'type'           => 'post',
			'subtype'        => (string) $post->post_type,
			'is_space'       => $is_space,
			'title'          => SearchHighlighter::highlight( $post->post_title, $term ),
			'title_raw'      => (string) $post->post_title,
			'excerpt'        => SearchExcerptBuilder::build( $excerpt_source, $term ),
			'url'            => esc_url_raw( (string) get_permalink( $post ) ),
			'author'         => [
				'id'     => $author_id,
				'name'   => $author_name,
				'avatar' => $avatar,
			],
			'date'           => mysql_to_rfc3339( $post->post_date ), // phpcs:ignore PHPCompatibility.Extensions.RemovedExtensions.mysql_DeprecatedRemoved -- WP core function, not the mysql_* extension.
			'date_formatted' => self::format_date( $post->post_date ),
			'portal_id'      => $portal_id,
			'portal_title'   => $is_space ? '' : $portal_title,
			'comments_count' => $comments_count,
			'likes_count'    => $likes_count,
		];

		/**
		 * Filter: adjust / extend an individual post result object.
		 *
		 * @param array<string,mixed> $result The formatted result.
		 * @param \WP_Post            $post   Post.
		 * @param string              $term   Search term.
		 */
		return (array) apply_filters( 'suredash_search_post_result', $result, $post, $term );
	}

	/**
	 * Best-effort portal ID resolution for a given post.
	 *
	 * Portal spaces are their own portal; feed posts store the portal via
	 * the forum taxonomy; sub-content posts live under a portal space via
	 * post_parent.
	 *
	 * @param \WP_Post $post Post.
	 * @return int Portal ID (0 when unknown).
	 */
	private function resolve_portal_id( $post ) {
		if ( $post->post_type === SUREDASHBOARD_POST_TYPE ) {
			return (int) $post->ID;
		}

		if ( $post->post_type === SUREDASHBOARD_FEED_POST_TYPE && function_exists( 'sd_get_space_id_by_post' ) ) {
			return (int) sd_get_space_id_by_post( $post->ID );
		}

		return (int) $post->post_parent;
	}
}
