<?php
/**
 * Announcements module.
 *
 * Resolves the "What's New" pages the current user has not yet dismissed, for
 * display on the dashboard. Content lives in {@see Releases}.
 *
 * Dismissal state is stored as a single per-user meta value
 * (`suredash_announcements_seen`), holding a flat list of dismissed release
 * ids: `[ 'dismissed' => [ 'suredash-1.9.2', … ] ]`. Every release not yet in
 * that list is shown, and its pages are merged (in registry order) into one
 * payload.
 *
 * @package SureDash
 * @since 1.9.0
 */

namespace SureDashboard\Inc\Modules\Announcements;

use SureDashboard\Inc\Traits\Get_Instance;
use SureDashboard\Inc\Traits\Rest_Errors;

defined( 'ABSPATH' ) || exit;

/**
 * Announcements resolver.
 *
 * @since 1.9.0
 */
class Announcements {
	use Get_Instance;
	use Rest_Errors;

	/*
	 * Portability config — the ONLY plugin-specific identifiers in this module.
	 * To reuse in another plugin: copy the folder, rename the namespace,
	 * change these constants (and the localized payload key in your admin loader).
	 */

	/**
	 * Filter other plugins use to append their own announcement entries.
	 *
	 * @since 1.9.2
	 */
	public const FILTER = 'suredash_announcements';

	/**
	 * User-meta key holding the list of dismissed announcement ids.
	 *
	 * @since 1.9.0
	 */
	public const SEEN_META_KEY = 'suredash_announcements_seen';

	/**
	 * Key the announcement payload is localized under for the dashboard.
	 *
	 * @since 1.9.2
	 */
	public const PAYLOAD_KEY = 'announcement';

	/**
	 * Return the merged announcement payload for the pages the user has not
	 * dismissed, sanitized for the dashboard — or null when there is nothing
	 * to show.
	 *
	 * @since 1.9.0
	 * @param int $user_id User ID. Defaults to the current user.
	 * @return array{release_ids: ?array<string>, changelog_url: string, pages: array<int, array<string, mixed>>}|null
	 */
	public static function get_for_dashboard( int $user_id = 0 ): ?array {
		if ( $user_id === 0 ) {
			$user_id = get_current_user_id();
		}

		$dismissed = self::get_dismissed_ids( $user_id );
		$payload   = [
			'release_ids'   => [],
			'changelog_url' => '',
			'pages'         => [],
		];

		foreach ( Releases::get_structure() as $entry ) {
			if ( ! is_array( $entry ) ) {
				continue;
			}

			$id = (string) ( $entry['id'] ?? '' );
			if ( $id === '' || in_array( $id, $dismissed, true ) ) {
				continue;
			}

			$pages = self::prepare_pages( (array) ( $entry['pages'] ?? [] ) );
			if ( $pages === [] ) {
				continue;
			}

			$payload['release_ids'][] = $id;
			$payload['pages']         = array_merge( $payload['pages'], $pages );

			// Latest entry with a URL wins — registry entries are appended
			// chronologically, so the last undismissed release is the newest.
			if ( ! empty( $entry['changelog_url'] ) ) {
				$payload['changelog_url'] = esc_url_raw( (string) $entry['changelog_url'] );
			}
		}

		return $payload['pages'] === [] ? null : $payload;
	}

	/**
	 * Persist dismissal of one or more announcements for a user.
	 *
	 * Ids are validated against the registry before being stored, so the
	 * saved list cannot be forged from the front end.
	 *
	 * @since 1.9.2
	 * @param int           $user_id User ID.
	 * @param array<string> $ids     Release ids being dismissed.
	 * @return bool True when at least one valid id was recorded.
	 */
	public static function record_dismissals( int $user_id, array $ids ): bool {
		$known = array_map(
			static function ( $entry ) {
				return is_array( $entry ) ? (string) ( $entry['id'] ?? '' ) : '';
			},
			Releases::get_structure()
		);

		$valid = array_values( array_intersect( array_map( 'strval', $ids ), array_filter( $known ) ) );

		if ( $valid === [] || ! $user_id ) {
			return false;
		}

		$seen              = self::get_seen_map( $user_id );
		$seen['dismissed'] = array_values( array_unique( array_merge( self::get_dismissed_ids( $user_id ), $valid ) ) );

		sd_update_user_meta( $user_id, self::SEEN_META_KEY, $seen );

		return true;
	}

	/**
	 * REST handler: dismiss one or more announcements for the current user.
	 *
	 * @since 1.9.0
	 * @param \WP_REST_Request $request Request object.
	 * @return void
	 */
	public function mark_seen( $request ): void {
		$nonce = (string) $request->get_header( 'X-WP-Nonce' );
		if ( ! wp_verify_nonce( sanitize_text_field( $nonce ), 'wp_rest' ) ) {
			wp_send_json_error( [ 'message' => $this->get_rest_event_error( 'nonce' ) ] );
		}

		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			wp_send_json_error( [ 'message' => $this->get_rest_event_error( 'default' ) ] );
		}

		$ids = array_map( 'sanitize_text_field', (array) $request->get_param( 'ids' ) );

		if ( ! self::record_dismissals( $user_id, $ids ) ) {
			wp_send_json_error( [ 'message' => $this->get_rest_event_error( 'default' ) ] );
		}

		wp_send_json_success();
	}

	/**
	 * Return the user's raw dismissal meta map.
	 *
	 * @since 1.9.2
	 * @param int $user_id User ID.
	 * @return array<string, mixed>
	 */
	private static function get_seen_map( int $user_id ): array {
		$seen = sd_get_user_meta( $user_id, self::SEEN_META_KEY, true );

		return is_array( $seen ) ? $seen : [];
	}

	/**
	 * Return the list of release ids the user has already dismissed.
	 *
	 * @since 1.9.2
	 * @param int $user_id User ID.
	 * @return array<string>
	 */
	private static function get_dismissed_ids( int $user_id ): array {
		$map = self::get_seen_map( $user_id );

		if ( empty( $map['dismissed'] ) || ! is_array( $map['dismissed'] ) ) {
			return [];
		}

		return array_values( array_filter( (array) $map['dismissed'], 'is_string' ) );
	}

	/**
	 * Sanitize a release's raw pages into the shape returned to the dashboard,
	 * dropping any malformed page.
	 *
	 * @since 1.9.2
	 * @param array<int, mixed> $pages Raw pages.
	 * @return array<int, array<string, mixed>>
	 */
	private static function prepare_pages( array $pages ): array {
		$prepared = array_map( [ self::class, 'prepare_page' ], $pages );

		return array_values( array_filter( $prepared ) );
	}

	/**
	 * Sanitize a single raw page, or null when it is malformed.
	 *
	 * @since 1.9.2
	 * @param mixed $page Raw page.
	 * @return array<string, mixed>|null
	 */
	private static function prepare_page( $page ): ?array {
		if ( ! is_array( $page ) || empty( $page['title'] ) ) {
			return null;
		}

		$points = [];

		foreach ( (array) ( $page['points'] ?? [] ) as $point ) {
			if ( ! is_array( $point ) || empty( $point['label'] ) ) {
				continue;
			}

			$points[] = [
				'icon'  => ! empty( $point['icon'] ) ? sanitize_key( (string) $point['icon'] ) : '',
				'label' => (string) $point['label'],
			];
		}

		$media     = $page['media'] ?? null;
		$media_src = is_array( $media ) ? (string) ( $media['src'] ?? '' ) : '';
		$media_out = null;

		if ( $media_src !== '' ) {
			$media_type = is_array( $media ) ? (string) ( $media['type'] ?? 'image' ) : 'image';
			$media_out  = [
				'src'  => esc_url_raw( $media_src ),
				'type' => in_array( $media_type, [ 'image', 'video' ], true ) ? $media_type : 'image',
			];
		}

		return [
			'eyebrow'  => (string) ( $page['eyebrow'] ?? '' ),
			'title'    => (string) $page['title'],
			'body'     => (string) ( $page['body'] ?? '' ),
			'points'   => $points,
			'media'    => $media_out,
			'docs_url' => ! empty( $page['docs_url'] ) ? esc_url_raw( (string) $page['docs_url'] ) : '',
			'target'   => self::prepare_target( $page['target'] ?? null ),
		];
	}

	/**
	 * Sanitize an optional dashboard target, or null when not provided/malformed.
	 *
	 * Static targets provide a `route` directly. Dynamic targets provide a
	 * `resolve` strategy instead and are resolved against this site's data at
	 * payload time — when resolution fails (e.g. no matching space exists),
	 * null is returned and the "Take me there" CTA is not rendered.
	 *
	 * @since 1.9.2
	 * @param mixed $target Raw target config.
	 * @return array<string, mixed>|null
	 */
	private static function prepare_target( $target ): ?array {
		if ( ! is_array( $target ) || empty( $target['anchor_id'] ) || empty( $target['label'] ) ) {
			return null;
		}

		$route  = (string) ( $target['route'] ?? '' );
		$params = [];

		if ( ! empty( $target['resolve'] ) ) {
			$resolved = self::resolve_target( (string) $target['resolve'], $target );

			if ( $resolved === null ) {
				return null;
			}

			[ $route, $params ] = $resolved;
		}

		if ( $route === '' ) {
			return null;
		}

		return [
			'route'     => $route,
			'params'    => $params,
			'anchor_id' => sanitize_key( (string) $target['anchor_id'] ),
			'label'     => (string) $target['label'],
			'hint'      => (string) ( $target['hint'] ?? '' ),
		];
	}

	/**
	 * Resolve a dynamic target strategy into a concrete route + query params
	 * for this site, or null when nothing on the site matches.
	 *
	 * Strategies:
	 * - `first_space`: the oldest published space whose `integration` meta
	 *   matches the target's `integration` — routes to that space's settings.
	 *
	 * @since 1.9.2
	 * @param string               $strategy Resolve strategy name.
	 * @param array<string, mixed> $target   Raw target config.
	 * @return array{0: string, 1: array<string, string>}|null
	 */
	private static function resolve_target( string $strategy, array $target ): ?array {
		if ( $strategy !== 'first_space' ) {
			return null;
		}

		$space_ids = get_posts(
			[
				'post_type'      => SUREDASHBOARD_POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'orderby'        => 'ID',
				'order'          => 'ASC',
				'fields'         => 'ids',
				'meta_key'       => 'integration', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'     => sanitize_key( (string) ( $target['integration'] ?? '' ) ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			]
		);

		if ( empty( $space_ids ) ) {
			return null;
		}

		return [ '/spaces/space', [ 'space' => (string) $space_ids[0] ] ];
	}
}
