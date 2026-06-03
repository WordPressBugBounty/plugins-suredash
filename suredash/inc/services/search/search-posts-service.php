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
			if ( suredash_is_post_protected( $post_id ) ) {
				continue;
			}
			// Run the same Pro-side visibility filter the items walk runs in
			// query() so badge counts match rendered items — Pro hooks this
			// to apply the lesson→parent-course protection check, which the
			// raw post-level helper above can't see on its own.
			$post_obj = get_post( $post_id );
			if ( $post_obj instanceof \WP_Post
				&& ! (bool) apply_filters( 'suredash_search_post_visible', true, $post_obj )
			) {
				continue;
			}
			$count++;
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
		$per_page    = isset( $args['per_page'] ) ? max( 1, (int) $args['per_page'] ) : 5;
		$page        = isset( $args['page'] ) ? max( 1, (int) $args['page'] ) : 1;
		$target_skip = ( $page - 1 ) * $per_page;

		$query_args = $this->build_query_args( $args );
		// We'll set posts_per_page + offset per batch in the loop below;
		// drop the build_query_args() defaults so they don't pin a single
		// page worth of raw rows.
		unset( $query_args['posts_per_page'], $query_args['paged'], $query_args['offset'] );

		// Admins bypass visibility checks entirely. For everyone else, walk
		// each raw match through the same gate `count()` uses so items and
		// totals stay in lockstep (no fake-pagination, no "ghost" count).
		$enforce_protected = ! current_user_can( 'manage_options' )
			&& function_exists( 'suredash_is_post_protected' );

		// Over-fetch in batches and keep going until we've collected
		// `per_page` visible items, exhausted the raw match set, or hit the
		// iteration safety cap. Solves the "first page is all-hidden so
		// items=[] even though total>0" class of bug across every type that
		// uses this service (spaces / posts / lessons / events / resources).
		$batch_size   = max( $per_page * 4, 20 );
		$max_iter     = 10;
		$raw_offset   = 0;
		$raw_total    = 0;
		$visible_seen = 0;
		$items        = [];

		for ( $iter = 0; $iter < $max_iter; $iter++ ) {
			$query_args['posts_per_page'] = $batch_size;
			$query_args['offset']         = $raw_offset;
			$query_args['no_found_rows']  = false;

			$query     = $this->run_query( $query_args );
			$raw_total = (int) $query->found_posts;
			$batch     = is_array( $query->posts ) ? $query->posts : [];
			if ( empty( $batch ) ) {
				break;
			}

			foreach ( $batch as $post ) {
				if ( ! $post instanceof \WP_Post ) {
					continue;
				}
				if ( $enforce_protected && suredash_is_post_protected( (int) $post->ID ) ) {
					continue;
				}
				if ( ! (bool) apply_filters( 'suredash_search_post_visible', true, $post ) ) {
					continue;
				}

				// Visible item. Either skip past it (we're past its page in
				// the visible-only sequence) or collect it for this page.
				if ( $visible_seen < $target_skip ) {
					$visible_seen++;
					continue;
				}

				$item = $this->format_result( $post, (string) $args['q'] );
				if ( empty( $item ) ) {
					continue;
				}
				$items[] = $item;
				if ( count( $items ) >= $per_page ) {
					break 2;
				}
			}

			$raw_offset += $batch_size;
			if ( $raw_offset >= $raw_total ) {
				break;
			}
		}

		// `total` is the visible-only count (drives the tab badge). Caller
		// in response-builder passes `known_total` to skip recomputation.
		// Pages are derived from the same visible-only total so the API
		// never reports more pages than there are real items.
		$total = isset( $args['known_total'] ) ? (int) $args['known_total'] : $this->count( $args );
		$pages = $per_page > 0 ? (int) max( 1, ceil( $total / $per_page ) ) : 1;

		return [
			'items'   => $items,
			'total'   => $total,
			'pages'   => $pages,
			'current' => min( $page, $pages ),
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
	 * Run the WP_Query for the posts search.
	 *
	 * By default WP's `s` parameter matches post_title + post_content +
	 * post_excerpt — content search is on. Site owners running large
	 * portals (20k+ published posts with long lesson/event bodies) can
	 * opt into the title-only LIKE optimization by returning `true` from
	 * the `suredash_search_titles_only` filter; that swaps in
	 * `Helper::search_only_titles` which restricts the LIKE to post_title.
	 *
	 * @param array<string,mixed> $query_args Query args.
	 * @return \WP_Query
	 */
	private function run_query( array $query_args ) {
		/**
		 * Filter: restrict the search LIKE to post_title only.
		 *
		 * Default `false` — WP searches title + content + excerpt so users
		 * find posts by quoting words from the body. Flip to `true` on
		 * large portals where the full-content scan is too slow; the helper
		 * is 100–1000× faster on indexed title columns but won't match body
		 * text.
		 *
		 * @param bool $titles_only Default false.
		 */
		$titles_only = (bool) apply_filters( 'suredash_search_titles_only', false );

		if ( $titles_only ) {
			add_filter( 'posts_search', [ Helper::class, 'search_only_titles' ], 10, 2 );
		}
		$query = new \WP_Query( $query_args );
		if ( $titles_only ) {
			remove_filter( 'posts_search', [ Helper::class, 'search_only_titles' ], 10 );
		}

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

		// Space-kind metadata drives the leading icon-tile tint, the type
		// word that opens the meta line, and any kind-specific signals
		// (lesson count for courses, file count for resource libraries,
		// link host, etc.). The frontend renderer expects three fields:
		// `space_kind` slug, `space_kind_label` display word, and
		// `space_meta_signals` — pre-formatted extras joined with " · ".
		$space_kind         = '';
		$space_kind_label   = '';
		$space_meta_signals = [];
		$space_icon_html    = '';
		if ( $is_space ) {
			$space_kind_data    = $this->resolve_space_kind( $post );
			$space_kind         = $space_kind_data['kind'];
			$space_kind_label   = $space_kind_data['label'];
			$space_meta_signals = $this->build_space_signals( $post, $space_kind );

			// Leading-tile icon. Prefer the admin-configured space icon
			// (`item_emoji` — emoji / Lucide name / custom-SVG URL) so the
			// tile in search matches the rest of the portal UI. When the
			// admin hasn't set one, fall back to a kind-default Lucide
			// icon resolved via the same `Helper::get_library_icon` path,
			// so all space icons originate from the icon-library on the
			// server side rather than duplicated SVG paths in the JS bundle.
			$item_emoji        = (string) get_post_meta( (int) $post->ID, 'item_emoji', true );
			$default_kind_icon = self::default_space_icon_name( $space_kind );
			// `item_emoji` post-meta defaults to the literal `'Link'` when
			// the admin never picked an icon. Treat the unchanged default
			// as "unset" and substitute the kind-default icon — except for
			// `link` integration spaces where `'Link'` is genuinely the
			// right pick.
			$treat_as_unset = ( $item_emoji === '' )
				|| ( $item_emoji === 'Link' && $space_kind !== 'link' );
			if ( $treat_as_unset ) {
				$item_emoji = $default_kind_icon;
			}
			$rendered = Helper::get_library_icon( $item_emoji, false, 'md' );
			if ( is_string( $rendered ) ) {
				$space_icon_html = $rendered;
			}
		}

		// Link spaces are bookmarks: the portal page is a thin pass-through
		// to an external URL stored in `link_url`. Skip the pass-through and
		// take the user straight to the destination, opening in the configured
		// target (defaults to a new tab for safety).
		$result_url    = (string) get_permalink( $post );
		$result_target = '';
		if ( $space_kind === 'link' ) {
			$external_url = (string) get_post_meta( (int) $post->ID, 'link_url', true );
			if ( $external_url !== '' ) {
				$result_url    = $external_url;
				$target_meta   = (string) get_post_meta( (int) $post->ID, 'link_target', true );
				$result_target = $target_meta !== '' ? $target_meta : '_blank';
			}
		}

		$result = [
			'id'                 => (int) $post->ID,
			'type'               => 'post',
			'subtype'            => (string) $post->post_type,
			'is_space'           => $is_space,
			'title'              => SearchHighlighter::highlight( $post->post_title, $term ),
			'title_raw'          => (string) $post->post_title,
			'excerpt'            => SearchExcerptBuilder::build( $excerpt_source, $term ),
			'url'                => esc_url_raw( $result_url ),
			'url_target'         => $result_target,
			'author'             => [
				'id'     => $author_id,
				'name'   => $author_name,
				'avatar' => $avatar,
			],
			'date'               => mysql_to_rfc3339( $post->post_date ), // phpcs:ignore PHPCompatibility.Extensions.RemovedExtensions.mysql_DeprecatedRemoved -- WP core function, not the mysql_* extension.
			'date_formatted'     => self::format_date( $post->post_date ),
			'portal_id'          => $portal_id,
			'portal_title'       => $is_space ? '' : $portal_title,
			'comments_count'     => $comments_count,
			'likes_count'        => $likes_count,
			'space_kind'         => $space_kind,
			'space_kind_label'   => $space_kind_label,
			'space_meta_signals' => $space_meta_signals,
			'space_icon_html'    => $space_icon_html,
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

	/**
	 * Default icon-library handle for a space kind, used when the admin
	 * hasn't picked an `item_emoji`. Names map to `assets/icon-library/lucide-icons.php`.
	 *
	 * Filterable so Pro / third-party integrations that introduce new kinds
	 * can register a default icon without forking this list.
	 *
	 * @param string $kind Kind slug.
	 * @return string Lucide icon name (or `'Box'` for unknown kinds).
	 */
	private static function default_space_icon_name( $kind ) {
		$map  = [
			'course'     => 'Book',
			'event'      => 'Calendar',
			'resource'   => 'Folder',
			'link'       => 'Link',
			'discussion' => 'MessageSquare',
			'collection' => 'Layers',
			'post'       => 'Newspaper',
			'page'       => 'PanelsTopLeft',
		];
		$name = $map[ $kind ] ?? 'Box';
		return (string) apply_filters( 'suredash_search_space_default_icon', $name, $kind );
	}

	/**
	 * Resolve a space's kind slug + display label from its `integration` meta.
	 *
	 * Drives the type-colored leading icon tile and the type word that opens
	 * the meta line in the Spaces tab. Returned kind is one of:
	 *   course | event | resource | link | discussion | collection | post | page | members
	 * Unknown integration values fall through to a neutral `'space'` kind
	 * (was previously 'discussion', which mislabeled types like `collection`
	 * and `single_post`).
	 *
	 * Filterable so Pro / third-party integrations can introduce new kinds
	 * without forking this list.
	 *
	 * @param \WP_Post $post Space post.
	 * @return array{kind:string,label:string}
	 */
	private function resolve_space_kind( $post ) {
		$integration = (string) get_post_meta( (int) $post->ID, 'integration', true );

		// Canonical integration values come from `create-space.php` enum
		// (single_post, posts_discussion, link, course, resource_library,
		// collection, events) plus `portal_page` and `membership_directory`
		// set by other handlers. Each maps to a search-side kind slug used
		// for icon + badge color, and a translated display label.
		$map = [
			'course'           => [ 'course', __( 'Course', 'suredash' ) ],
			'events'           => [ 'event', __( 'Event', 'suredash' ) ],
			'resource_library' => [ 'resource', __( 'Resource', 'suredash' ) ],
			'link'             => [ 'link', __( 'Link', 'suredash' ) ],
			'posts_discussion' => [ 'discussion', __( 'Discussion', 'suredash' ) ],
			'collection'       => [ 'collection', __( 'Collection', 'suredash' ) ],
			'single_post'      => [ 'post', __( 'Post', 'suredash' ) ],
			'portal_page'      => [ 'page', __( 'Page', 'suredash' ) ],
		];

		if ( isset( $map[ $integration ] ) ) {
			$kind  = $map[ $integration ][0];
			$label = $map[ $integration ][1];
		} else {
			// Unknown / missing integration → render as a generic space,
			// not as "Discussion" (which would mislabel collection / blog /
			// portal-page / members-directory spaces).
			$kind  = 'space';
			$label = __( 'Space', 'suredash' );
		}

		/**
		 * Filter: override the resolved kind + label for a space result.
		 *
		 * @param array{kind:string,label:string} $resolved    Resolved kind data.
		 * @param string                          $integration Raw integration meta value.
		 * @param \WP_Post                        $post        Space post.
		 */
		$resolved = (array) apply_filters(
			'suredash_search_space_kind',
			[
				'kind'  => $kind,
				'label' => $label,
			],
			$integration,
			$post
		);

		return [
			'kind'  => isset( $resolved['kind'] ) ? (string) $resolved['kind'] : $kind,
			'label' => isset( $resolved['label'] ) ? (string) $resolved['label'] : $label,
		];
	}

	/**
	 * Build the kind-specific meta-signal strings that render after the type
	 * word ("Course · 8 lessons", "Resource · 23 files · Updated May 28", etc.).
	 *
	 * Phase 1 ships the cheap signals only — lesson count, file count, link
	 * host. Member counts and "posts this week" need an aggregate helper
	 * (potential N+1 across a result page) and are intentionally deferred.
	 *
	 * Filterable so Pro can append the deferred signals once the helpers
	 * exist without re-touching the base format_result path.
	 *
	 * @param \WP_Post $post Space post.
	 * @param string   $kind Kind slug (course | event | resource | link | discussion).
	 * @return array<int,string>
	 */
	private function build_space_signals( $post, $kind ) {
		$signals = [];

		if ( $kind === 'course' ) {
			$lesson_count = $this->count_published_in_course_loop( (int) $post->ID );
			if ( $lesson_count > 0 ) {
				$signals[] = sprintf(
					/* translators: %d: number of lessons in the course. */
					_n( '%d lesson', '%d lessons', $lesson_count, 'suredash' ),
					(int) $lesson_count
				);
			}
		} elseif ( $kind === 'resource' ) {
			$file_count = $this->count_published_in_id_loop( (int) $post->ID, 'resource_ids' );
			if ( $file_count > 0 ) {
				$signals[] = sprintf(
					/* translators: %d: number of files in the resource library. */
					_n( '%d file', '%d files', $file_count, 'suredash' ),
					(int) $file_count
				);
			}
		} elseif ( $kind === 'event' ) {
			$event_count = $this->count_published_in_id_loop( (int) $post->ID, 'event_ids' );
			if ( $event_count > 0 ) {
				$signals[] = sprintf(
					/* translators: %d: number of events in the event space. */
					_n( '%d event', '%d events', $event_count, 'suredash' ),
					(int) $event_count
				);
			}
		} elseif ( $kind === 'collection' ) {
			// Collections curate other spaces, not sub-content. Member IDs
			// live in `collection_space_ids` (flat array). Cost is one meta
			// read + a publish-status walk over a handful of space IDs —
			// same shape as resource_ids / event_ids.
			$space_count = $this->count_published_in_id_loop( (int) $post->ID, 'collection_space_ids' );
			if ( $space_count > 0 ) {
				$signals[] = sprintf(
					/* translators: %d: number of spaces curated in a collection. */
					_n( '%d space', '%d spaces', $space_count, 'suredash' ),
					(int) $space_count
				);
			}
		} elseif ( $kind === 'link' ) {
			$link_url = (string) get_post_meta( (int) $post->ID, 'link_url', true );
			if ( $link_url !== '' ) {
				$host = wp_parse_url( $link_url, PHP_URL_HOST );
				if ( is_string( $host ) && $host !== '' ) {
					if ( stripos( $host, 'www.' ) === 0 ) {
						$host = substr( $host, 4 );
					}
					$signals[] = $host;
				}
			}
		}

		/**
		 * Filter: append / replace kind-specific meta signals.
		 *
		 * @param array<int,string> $signals Resolved signals.
		 * @param \WP_Post          $post    Space post.
		 * @param string            $kind    Kind slug.
		 */
		return (array) apply_filters( 'suredash_search_space_meta_signals', $signals, $post, $kind );
	}

	/**
	 * Count published lessons in a course as configured in its section loop.
	 *
	 * Reads `pp_course_section_loop` — the same meta the course UI uses to
	 * render sections + lessons — so the search badge reflects what users
	 * actually see when they open the course. Counts unique published
	 * lesson IDs across all sections; drafts / trashed / orphaned lesson
	 * records that the loop no longer references are excluded.
	 *
	 * @param int $space_id Course space ID.
	 * @return int
	 */
	private function count_published_in_course_loop( $space_id ) {
		if ( $space_id <= 0 ) {
			return 0;
		}

		static $cache = [];
		if ( isset( $cache[ $space_id ] ) ) {
			return $cache[ $space_id ];
		}

		$sections_raw = get_post_meta( $space_id, 'pp_course_section_loop', true );
		$sections     = is_array( $sections_raw ) ? $sections_raw : [];

		$lesson_ids = [];
		foreach ( $sections as $section ) {
			$medias = is_array( $section['section_medias'] ?? null ) ? $section['section_medias'] : [];
			foreach ( $medias as $media ) {
				$id = absint( $media['value'] ?? $media['id'] ?? 0 );
				if ( $id > 0 ) {
					$lesson_ids[] = $id;
				}
			}
		}

		$cache[ $space_id ] = $this->count_published_ids( array_unique( $lesson_ids ) );
		return $cache[ $space_id ];
	}

	/**
	 * Count published children referenced by a flat ID-array meta key.
	 *
	 * Resource libraries store their child IDs in `resource_ids`; event
	 * spaces use `event_ids`. Both are flat arrays of sub-content post IDs.
	 * Same rationale as the course-loop counter: counts only what's wired
	 * into the space's configured loop and currently published, so the
	 * search badge matches the visible count inside the space.
	 *
	 * @param int    $space_id Space ID.
	 * @param string $meta_key Loop meta key (`resource_ids` | `event_ids`).
	 * @return int
	 */
	private function count_published_in_id_loop( $space_id, $meta_key ) {
		if ( $space_id <= 0 || $meta_key === '' ) {
			return 0;
		}

		static $cache = [];
		$cache_key    = $space_id . '|' . $meta_key;
		if ( isset( $cache[ $cache_key ] ) ) {
			return $cache[ $cache_key ];
		}

		$raw = get_post_meta( $space_id, $meta_key, true );
		$ids = is_array( $raw ) ? array_map( 'absint', $raw ) : [];
		$ids = array_filter( $ids );

		$cache[ $cache_key ] = $this->count_published_ids( array_unique( $ids ) );
		return $cache[ $cache_key ];
	}

	/**
	 * Count how many of the given post IDs are currently published. One
	 * indexed `post_status` lookup per ID via WP's object cache; per-request
	 * memo above keeps the same space from re-walking its list.
	 *
	 * @param array<int,int> $ids Post IDs.
	 * @return int
	 */
	private function count_published_ids( $ids ) {
		if ( empty( $ids ) ) {
			return 0;
		}
		$count = 0;
		foreach ( $ids as $id ) {
			if ( get_post_status( (int) $id ) === 'publish' ) {
				$count++;
			}
		}
		return $count;
	}
}
