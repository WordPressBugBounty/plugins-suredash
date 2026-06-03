<?php
/**
 * SearchCommentsService — search comments for the /suredash/v1/search endpoint.
 *
 * Scoped to comments attached to SureDash post types (portal, community-post,
 * community-content) so replies on unrelated posts never leak into results.
 *
 * @package SureDash
 */

namespace SureDashboard\Inc\Services\Search;

use SureDashboard\Inc\Traits\Get_Instance;

defined( 'ABSPATH' ) || exit;

/**
 * Class SearchCommentsService.
 *
 * @since 1.3.0
 */
class SearchCommentsService {
	use Get_Instance;

	/**
	 * Is comment search enabled?
	 *
	 * @return bool
	 */
	public static function is_enabled() {
		return (bool) apply_filters( 'suredash_search_comments_enabled', true );
	}

	/**
	 * Count comments matching the search.
	 *
	 * @param array<string,mixed> $args Normalized search args.
	 * @return int
	 */
	public function count( array $args ) {
		if ( ! self::is_enabled() ) {
			return 0;
		}

		$query_args           = $this->build_query_args( $args );
		$query_args['number'] = 0;
		$query_args['fields'] = 'ids';
		unset( $query_args['paged'], $query_args['offset'], $query_args['count'] );

		$query       = $this->run_query( $query_args );
		$comment_ids = (array) $query->get_comments();

		// Admins and free-only installs (no protection helper) get the raw
		// match count. Otherwise walk IDs and drop comments whose parent
		// post the user can't see — matches the items walk in query() so
		// the tab badge stays in sync with rendered items.
		if ( current_user_can( 'manage_options' ) || ! function_exists( 'suredash_is_post_protected' ) ) {
			return count( $comment_ids );
		}

		$count = 0;
		foreach ( $comment_ids as $cid ) {
			$comment_id = $cid instanceof \WP_Comment ? (int) $cid->comment_ID : (int) $cid;
			$comment    = get_comment( $comment_id );
			if ( ! $comment instanceof \WP_Comment ) {
				continue;
			}
			if ( suredash_is_post_protected( (int) $comment->comment_post_ID ) ) {
				continue;
			}
			if ( ! (bool) apply_filters( 'suredash_search_comment_visible', true, $comment ) ) {
				continue;
			}
			$count++;
		}
		return $count;
	}

	/**
	 * Run the search and return result objects.
	 *
	 * @param array<string,mixed> $args Normalized search args.
	 * @return array{items:array<int,array<string,mixed>>,total:int,pages:int,current:int}
	 */
	public function query( array $args ) {
		if ( ! self::is_enabled() ) {
			return [
				'items'   => [],
				'total'   => 0,
				'pages'   => 1,
				'current' => 1,
			];
		}

		$per_page    = isset( $args['per_page'] ) ? max( 1, (int) $args['per_page'] ) : 5;
		$page        = isset( $args['page'] ) ? max( 1, (int) $args['page'] ) : 1;
		$target_skip = ( $page - 1 ) * $per_page;

		$query_args = $this->build_query_args( $args );
		// We'll set number + offset per batch in the loop. Drop any caller-set
		// pagination so it doesn't pin us to a single-page worth of raw rows.
		unset( $query_args['number'], $query_args['offset'], $query_args['paged'] );

		$enforce_protected = ! current_user_can( 'manage_options' )
			&& function_exists( 'suredash_is_post_protected' );

		// Same over-fetch + loop strategy as SearchPostsService::query() so
		// comments don't silently render an empty page when the first batch
		// happens to be all parent-post-protected. Items and pages track the
		// visible-only set; no fake "raw_total" leaks into pagination.
		$batch_size   = max( $per_page * 4, 20 );
		$max_iter     = 10;
		$raw_offset   = 0;
		$visible_seen = 0;
		$items        = [];

		for ( $iter = 0; $iter < $max_iter; $iter++ ) {
			$query_args['number'] = $batch_size;
			$query_args['offset'] = $raw_offset;

			$query = $this->run_query( $query_args );
			$batch = (array) $query->get_comments();
			if ( empty( $batch ) ) {
				break;
			}

			foreach ( $batch as $comment ) {
				if ( ! $comment instanceof \WP_Comment ) {
					continue;
				}
				if ( $enforce_protected && suredash_is_post_protected( (int) $comment->comment_post_ID ) ) {
					continue;
				}
				if ( ! (bool) apply_filters( 'suredash_search_comment_visible', true, $comment ) ) {
					continue;
				}

				if ( $visible_seen < $target_skip ) {
					$visible_seen++;
					continue;
				}

				$items[] = $this->format_result( $comment, (string) $args['q'] );
				if ( count( $items ) >= $per_page ) {
					break 2;
				}
			}

			// Stop when the last batch was short — WP_Comment_Query has
			// returned everything matching the query.
			if ( count( $batch ) < $batch_size ) {
				break;
			}
			$raw_offset += $batch_size;
		}

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
	 * Post types whose comments are in scope for search.
	 *
	 * @return array<int,string>
	 */
	private static function searchable_parent_post_types() {
		return [
			SUREDASHBOARD_POST_TYPE,
			SUREDASHBOARD_FEED_POST_TYPE,
			SUREDASHBOARD_SUB_CONTENT_POST_TYPE,
		];
	}

	/**
	 * Build the WP_Comment_Query args.
	 *
	 * @param array<string,mixed> $args Search args.
	 * @return array<string,mixed>
	 */
	private function build_query_args( array $args ) {
		$term      = trim( (string) ( $args['q'] ?? '' ) );
		$portal_id = (int) ( $args['portal_id'] ?? 0 );
		$order     = ( $args['order'] ?? 'desc' ) === 'asc' ? 'ASC' : 'DESC';

		$query_args = [
			'search'      => $term,
			'status'      => 'approve',
			'type'        => '',
			'post_type'   => self::searchable_parent_post_types(),
			'post_status' => 'publish',
			'orderby'     => 'comment_date',
			'order'       => $order,
		];

		if ( $portal_id > 0 ) {
			$post_ids = $this->get_post_ids_in_portal( $portal_id );
			if ( ! empty( $post_ids ) ) {
				$query_args['post__in'] = $post_ids;
			} else {
				// No posts in portal → force empty result.
				$query_args['post__in'] = [ 0 ];
			}
		}

		/**
		 * Filter: adjust WP_Comment_Query args used by search.
		 *
		 * @param array<string,mixed> $query_args Query args.
		 * @param array<string,mixed> $args       Full normalized search args.
		 */
		return (array) apply_filters( 'suredash_search_comments_query_args', $query_args, $args );
	}

	/**
	 * Resolve post IDs belonging to a portal (including sub-content children).
	 *
	 * @param int $portal_id Portal post ID.
	 * @return array<int,int>
	 */
	private function get_post_ids_in_portal( $portal_id ) {
		$ids = [ $portal_id ];

		$children = get_posts(
			[
				'post_parent'    => $portal_id,
				'post_type'      => [ SUREDASHBOARD_POST_TYPE, SUREDASHBOARD_SUB_CONTENT_POST_TYPE ],
				'post_status'    => 'publish',
				'fields'         => 'ids',
				'posts_per_page' => -1,
				'no_found_rows'  => true,
			]
		);

		if ( is_array( $children ) ) {
			$ids = array_merge( $ids, array_map( 'intval', $children ) );
		}

		return array_values( array_unique( $ids ) );
	}

	/**
	 * Execute the WP_Comment_Query.
	 *
	 * @param array<string,mixed> $query_args Query args.
	 * @return \WP_Comment_Query
	 */
	private function run_query( array $query_args ) {
		return new \WP_Comment_Query( $query_args );
	}

	/**
	 * Shape a WP_Comment into a Comment Result Object.
	 *
	 * @param \WP_Comment $comment Comment.
	 * @param string      $term    Search term.
	 * @return array<string,mixed>
	 */
	private function format_result( $comment, $term ) {
		$user_id     = (int) $comment->user_id;
		$author_name = $user_id ? suredash_get_user_display_name( $user_id ) : (string) $comment->comment_author;
		$avatar      = $user_id ? suredash_get_user_avatar( $user_id, false, 36 ) : get_avatar( (string) $comment->comment_author_email, 36 );

		$parent_post = get_post( (int) $comment->comment_post_ID );
		$parent_data = [
			'id'    => 0,
			'title' => '',
			'url'   => '',
		];

		if ( $parent_post instanceof \WP_Post ) {
			$parent_data = [
				'id'    => (int) $parent_post->ID,
				'title' => (string) $parent_post->post_title,
				'url'   => esc_url_raw( (string) get_permalink( $parent_post ) ),
			];
		}

		$raw           = (string) $comment->comment_content;
		$media_info    = $this->analyze_media( $raw );
		$is_media_only = ( $media_info['type'] !== '' ) && $this->is_text_empty( $raw );

		if ( $is_media_only ) {
			$excerpt = '';
		} else {
			$excerpt = SearchExcerptBuilder::build( $raw, $term );
			if ( $excerpt === '' ) {
				$excerpt = $this->fallback_excerpt( $raw );
			}
		}

		$result = [
			'id'              => (int) $comment->comment_ID,
			'type'            => 'comment',
			'content_excerpt' => $excerpt,
			// Media classification fields — null type means "regular text".
			// Frontend renders a typed chip when media_type is set.
			'media_type'      => $media_info['type'],
			'media_label'     => $media_info['label'],
			'media_detail'    => $media_info['detail'],
			'url'             => $parent_data['url'] ? $parent_data['url'] . '#comment-' . (int) $comment->comment_ID : '',
			'author'          => [
				'id'     => $user_id,
				'name'   => $author_name,
				'avatar' => $avatar,
			],
			'date'            => mysql_to_rfc3339( $comment->comment_date ), // phpcs:ignore PHPCompatibility.Extensions.RemovedExtensions.mysql_DeprecatedRemoved -- WP core function, not the mysql_* extension.
			'date_formatted'  => SearchPostsService::format_date( $comment->comment_date ),
			'parent_post'     => $parent_data,
		];

		/**
		 * Filter: adjust / extend an individual comment result object.
		 *
		 * @param array<string,mixed> $result  Formatted result.
		 * @param \WP_Comment         $comment Comment.
		 * @param string              $term    Search term.
		 */
		return (array) apply_filters( 'suredash_search_comment_result', $result, $comment, $term );
	}

	/**
	 * Decide if the raw comment is media-only (no human-readable text) and
	 * return a friendly label to use in place of the normal excerpt.
	 *
	 * Matters because an image-only comment can still match the search (the
	 * LIKE hits the <img src> URL or href), and a raw URL makes for a lousy
	 * excerpt. Returning '' means "not media-only — let the excerpt builder
	 * do its thing".
	 *
	 * @param string $raw Raw comment HTML.
	 * @return string
	 */
	/**
	 * Returns true if stripping HTML/shortcodes leaves no real text (URLs
	 * also stripped). Signals that a typed-media chip should replace the
	 * excerpt on the frontend.
	 *
	 * @param string $raw Raw comment HTML.
	 * @return bool
	 */
	private function is_text_empty( $raw ) {
		if ( trim( (string) $raw ) === '' ) {
			return true;
		}
		// First strip any UI-chrome elements (close/remove buttons etc.)
		// so their "×" glyph / label text doesn't leak into the text check.
		$cleaned = $this->strip_ui_chrome( (string) $raw );
		// Strip zero-width chars (BOM U+FEFF, ZWSP U+200B, ZWNJ U+200C,
		// ZWJ U+200D, word-joiner U+2060) — Jodit leaves them around
		// selection markers and they survive wp_strip_all_tags.
		$no_tags        = wp_strip_all_tags( strip_shortcodes( $cleaned ) );
		$no_zw          = preg_replace_callback(
			'/[\x{FEFF}\x{200B}-\x{200D}\x{2060}\x{00D7}\x{2715}\x{2716}\x{2717}]/u',
			static function () {
				return '';
			},
			$no_tags
		);
		$text           = trim(
			(string) preg_replace_callback(
				'/\s+/u',
				static function () {
					return ' ';
				},
				(string) $no_zw
			)
		);
		$text_sans_urls = trim(
			(string) preg_replace_callback(
				'#https?://\S+#i',
				static function () {
					return '';
				},
				$text
			)
		);
		return $text_sans_urls === '';
	}

	/**
	 * Strip UI-chrome elements (remove/delete/close buttons) from raw
	 * comment HTML before text analysis. Jodit + the comment editor wrap
	 * attachments with a "×" remove button whose glyph would otherwise
	 * leak through wp_strip_all_tags and make the comment look non-empty.
	 *
	 * Matches:
	 *   - <button|a|span|div> whose class / aria-label / data-action
	 *     contains remove|delete|close.
	 *   - <input type="button"|"submit"> with the same hints.
	 *
	 * @param string $raw Raw comment HTML.
	 * @return string HTML with UI chrome elements removed.
	 */
	private function strip_ui_chrome( $raw ) {
		$raw = (string) $raw;
		if ( $raw === '' ) {
			return $raw;
		}
		// Only match class values where "close/remove/delete/dismiss" appear
		// as either a standalone word terminator (end of class or followed
		// by whitespace) or with a -btn/-button/-icon suffix. Avoids
		// false-positives like "sd-video-embed-close-attached" (parent
		// wrapper that contains the media) where "close" is part of a
		// compound name, not the close button itself.
		$kw           = '(?:close|remove|delete|dismiss|clear)';
		$attr_pattern = '\b(?:class|aria-label|title|data-action)\s*=\s*["\'][^"\']*(?:' . $kw . ')(?:-(?:btn|button|icon))?(?=["\']|\s)[^"\']*["\']';
		$tags         = [ 'button', 'a', 'span', 'i' ];
		foreach ( $tags as $tag ) {
			$pattern = '#<' . $tag . '\b[^>]*' . $attr_pattern . '[^>]*>.*?</' . $tag . '>#is';
			$raw     = (string) preg_replace_callback(
				$pattern,
				static function () {
					return '';
				},
				$raw
			);
		}
		return $raw;
	}

	/**
	 * Analyze a comment's media content and return a structured payload:
	 *   [ 'type' => 'image'|'video'|'link'|'file'|'', 'label' => '...', 'detail' => '...' ]
	 *
	 * Type is always populated if any media is detected (regardless of
	 * whether the comment also has text). Frontend decides whether to
	 * display the chip based on is_text_empty.
	 *
	 * @param string $raw Raw comment HTML.
	 * @return array{type:string,label:string,detail:string}
	 */
	private function analyze_media( $raw ) {
		$raw     = (string) $raw;
		$cleaned = $this->strip_ui_chrome( $raw );
		$text    = trim(
			(string) preg_replace_callback(
				'/\s+/u',
				static function () {
					return ' ';
				},
				wp_strip_all_tags( strip_shortcodes( $cleaned ) )
			)
		);

		$empty = [
			'type'   => '',
			'label'  => '',
			'detail' => '',
		];

		// Images — count <img> tags as "N attachments". Fallback: bare URL.
		if ( preg_match_all( '/<img\b[^>]*>/i', $cleaned, $img_matches ) ) {
			$count = count( $img_matches[0] );
			return [
				'type'   => 'image',
				'label'  => __( 'Image', 'suredash' ),
				'detail' => $count > 1
					? sprintf(
						/* translators: %d: number of image attachments. */
						_n( '%d attachment', '%d attachments', $count, 'suredash' ),
						$count
					)
					: '',
			];
		}

		// Video — <video>/<source>/<iframe> or known host URLs.
		if (
			preg_match( '/<(video|source|iframe)\b/i', $cleaned ) ||
			preg_match( '#https?://\S+\.(?:mp4|webm|mov|m4v|avi)\b#i', $text . ' ' . $cleaned ) ||
			preg_match( '#https?://(?:www\.)?(?:youtube\.com|youtu\.be|vimeo\.com|loom\.com)/\S+#i', $text . ' ' . $cleaned )
		) {
			return [
				'type'   => 'video',
				'label'  => __( 'Video', 'suredash' ),
				'detail' => '',
			];
		}

		// File — <a> or bare URL pointing to a document extension.
		$file_exts = 'pdf|docx?|xlsx?|pptx?|zip|rar|7z|csv|txt|rtf|odt|ods|odp';
		if ( preg_match( '#href=["\']([^"\']+\.(?:' . $file_exts . '))(?:\?[^"\']*)?["\']#i', $cleaned, $m )
			|| preg_match( '#https?://\S+\.(?:' . $file_exts . ')\b#i', $text . ' ' . $cleaned, $m ) ) {
			$url      = $m[1] ?? $m[0];
			$filename = wp_basename( (string) wp_parse_url( $url, PHP_URL_PATH ) );
			return [
				'type'   => 'file',
				'label'  => __( 'File', 'suredash' ),
				'detail' => $filename,
			];
		}

		// Link — any <a> or bare URL.
		if ( preg_match( '#<a\b[^>]*href=["\']([^"\']+)["\']#i', $cleaned, $m )
			|| preg_match( '#(https?://\S+)#i', $text . ' ' . $cleaned, $m ) ) {
			$host = wp_parse_url( (string) $m[1], PHP_URL_HOST );
			$host = is_string( $host ) ? $host : '';
			if ( strpos( $host, 'www.' ) === 0 ) {
				$host = substr( $host, 4 );
			}
			return [
				'type'   => 'link',
				'label'  => __( 'Link', 'suredash' ),
				'detail' => $host,
			];
		}

		return $empty;
	}

	/**
	 * Classify the dominant media type of a comment body.
	 *
	 * @param string $raw  Raw comment HTML.
	 * @param string $text Plain text version (tags stripped).
	 * @return string One of 'image' | 'video' | 'link' | ''.
	 */
	private function classify_media( $raw, $text ) {
		// HTML tag signals.
		if ( preg_match( '/<img\b/i', $raw ) ) {
			return 'image';
		}
		if ( preg_match( '/<(video|source|iframe)\b/i', $raw ) ) {
			return 'video';
		}

		// Bare-URL signals — check text + raw (raw catches href-only <a>).
		$haystack = $text . ' ' . $raw;
		if ( preg_match( '#https?://\S+\.(?:jpe?g|png|gif|webp|avif|svg|bmp)\b#i', $haystack ) ) {
			return 'image';
		}
		if ( preg_match( '#https?://\S+\.(?:mp4|webm|mov|m4v|avi)\b#i', $haystack ) ) {
			return 'video';
		}
		if ( preg_match( '#https?://(?:www\.)?(?:youtube\.com|youtu\.be|vimeo\.com|loom\.com)/\S+#i', $haystack ) ) {
			return 'video';
		}

		if ( preg_match( '#<a\b#i', $raw ) || preg_match( '#https?://\S+#i', $haystack ) ) {
			return 'link';
		}

		return '';
	}

	/**
	 * Provide a sensible excerpt when both the media pre-check and the
	 * term-aware excerpt came back empty.
	 *
	 * @param string $raw Raw comment HTML.
	 * @return string
	 */
	private function fallback_excerpt( $raw ) {
		if ( trim( $raw ) === '' ) {
			return '';
		}

		$cleaned    = $this->strip_ui_chrome( (string) $raw );
		$text       = trim(
			(string) preg_replace_callback(
				'/\s+/u',
				static function () {
					return ' ';
				},
				wp_strip_all_tags( strip_shortcodes( $cleaned ) )
			)
		);
		$media_type = $this->classify_media( $cleaned, $text );

		if ( $media_type === 'image' ) {
			return esc_html__( 'Shared an image', 'suredash' );
		}
		if ( $media_type === 'video' ) {
			return esc_html__( 'Shared a video', 'suredash' );
		}
		if ( $media_type === 'link' ) {
			return esc_html__( 'Shared a link', 'suredash' );
		}

		return esc_html__( 'View comment', 'suredash' );
	}
}
