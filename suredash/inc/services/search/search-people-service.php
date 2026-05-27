<?php
/**
 * SearchPeopleService — search users (portal members) for the
 * /suredash/v1/search endpoint.
 *
 * @package SureDash
 */

namespace SureDashboard\Inc\Services\Search;

use SureDashboard\Inc\Traits\Get_Instance;

defined( 'ABSPATH' ) || exit;

/**
 * Class SearchPeopleService.
 *
 * @since 1.3.0
 */
class SearchPeopleService {
	use Get_Instance;

	/**
	 * Count users matching the search.
	 *
	 * @param array<string,mixed> $args Normalized search args.
	 * @return int
	 */
	public function count( array $args ) {
		$query_args                = $this->build_query_args( $args );
		$query_args['fields']      = 'ID';
		$query_args['number']      = -1;
		$query_args['count_total'] = true;

		$query = $this->run_query( $query_args );

		return (int) $query->get_total();
	}

	/**
	 * Run the search and return result objects.
	 *
	 * @param array<string,mixed> $args Normalized search args.
	 * @return array{items:array<int,array<string,mixed>>,total:int,pages:int,current:int}
	 */
	public function query( array $args ) {
		$per_page = isset( $args['per_page'] ) ? max( 1, (int) $args['per_page'] ) : 5;
		$page     = isset( $args['page'] ) ? max( 1, (int) $args['page'] ) : 1;

		// Skip WP_User_Query's SQL_CALC_FOUND_ROWS when the caller already
		// knows the total — saves MySQL from counting the same matching
		// set twice (once for the tabs badge, once for this fetch).
		$known_total               = isset( $args['known_total'] ) ? (int) $args['known_total'] : null;
		$query_args                = $this->build_query_args( $args );
		$query_args['number']      = $per_page;
		$query_args['paged']       = $page;
		$query_args['count_total'] = $known_total === null;
		// Return full WP_User objects so $user->roles and $user->user_registered
		// are populated for format_result() / get_role_label(). Restricting
		// 'fields' to scalars returns stdClass and triggers undefined-property
		// warnings that corrupt the JSON response under WP_DEBUG_DISPLAY.
		$query_args['fields'] = 'all';

		$query = $this->run_query( $query_args );

		$items = [];
		foreach ( $query->get_results() as $user ) {
			$items[] = $this->format_result( $user, (string) $args['q'] );
		}

		$total = $known_total === null ? (int) $query->get_total() : $known_total;
		$pages = $per_page > 0 ? (int) ceil( $total / $per_page ) : 1;

		return [
			'items'   => $items,
			'total'   => $total,
			'pages'   => max( 1, $pages ),
			'current' => $page,
		];
	}

	/**
	 * Build the shared WP_User_Query args.
	 *
	 * @param array<string,mixed> $args Search args.
	 * @return array<string,mixed>
	 */
	private function build_query_args( array $args ) {
		$term = trim( (string) ( $args['q'] ?? '' ) );

		$query_args = [
			'search'              => '*' . esc_attr( $term ) . '*',
			'search_columns'      => [ 'display_name', 'user_email', 'user_login', 'user_nicename' ],
			'orderby'             => 'display_name',
			'order'               => 'ASC',
			'has_published_posts' => false,
		];

		/**
		 * Filter: restrict the user search to specific user IDs (e.g., only
		 * portal members). Pro can hook in here to scope people search.
		 *
		 * @param array<string,mixed> $query_args WP_User_Query args.
		 * @param array<string,mixed> $args       Full normalized search args.
		 */
		return (array) apply_filters( 'suredash_search_people_query_args', $query_args, $args );
	}

	/**
	 * Execute the WP_User_Query (filterable for overrides).
	 *
	 * @param array<string,mixed> $query_args Query args.
	 * @return \WP_User_Query
	 */
	private function run_query( array $query_args ) {
		/**
		 * Filter: full override of the people search query.
		 *
		 * @param \WP_User_Query|null   $override   Overridden query (null to run default).
		 * @param array<string,mixed>   $query_args WP_User_Query args.
		 */
		$override = apply_filters( 'suredash_search_people_override', null, $query_args );
		if ( $override instanceof \WP_User_Query ) {
			return $override;
		}

		return new \WP_User_Query( $query_args );
	}

	/**
	 * Shape a WP_User into a People Result Object.
	 *
	 * @param \WP_User $user User.
	 * @param string   $term Search term.
	 * @return array<string,mixed>
	 */
	private function format_result( $user, $term ) {
		$user_id      = (int) $user->ID;
		$display_name = suredash_get_user_display_name( $user_id );
		$role_label   = $this->get_role_label( $user );

		$bio         = (string) get_user_meta( $user_id, 'description', true );
		$bio_excerpt = $bio !== '' ? SearchExcerptBuilder::build( $bio, $term ) : '';

		// Default to the portal's own user-view permalink
		// (`/{community_slug}/user-view/{id}/`) since that's where members
		// actually live on the frontend. Pro / site owners can still
		// override via the filter below (e.g., to route to a custom
		// members directory).
		$default_profile_url = function_exists( 'suredash_get_user_view_link' )
			? suredash_get_user_view_link( $user_id )
			: get_author_posts_url( $user_id );

		/**
		 * Filter: profile URL for a user. Pro overrides this with a
		 * portal-aware permalink if needed.
		 *
		 * @param string   $profile_url Default user-view link.
		 * @param int      $user_id     User ID.
		 * @param \WP_User $user        User object.
		 */
		$profile_url = apply_filters(
			'suredash_search_user_profile_url',
			$default_profile_url,
			$user_id,
			$user
		);

		$joined_ts      = strtotime( (string) $user->user_registered );
		$joined_label   = $joined_ts ? wp_date( 'M Y', $joined_ts ) : '';
		$joined_display = $joined_label
			? sprintf(
				/* translators: %s: month and year the user registered (e.g., "Feb 2025"). */
				__( 'joined %s', 'suredash' ),
				$joined_label
			)
			: '';

		// Default meta line: "Member · joined Feb 2025". Pro can filter this
		// to render group-specific strings like "Group · 128 members".
		$meta_line = implode( ' · ', array_filter( [ $role_label, $joined_display ] ) );

		$result = [
			'id'          => $user_id,
			'type'        => 'user',
			'name'        => SearchHighlighter::highlight( $display_name, $term ),
			'name_raw'    => $display_name,
			'username'    => isset( $user->user_login ) ? (string) $user->user_login : '',
			'avatar'      => suredash_get_user_avatar( $user_id, false, 36 ),
			'role_label'  => $role_label,
			'joined'      => $joined_label,
			'meta_line'   => $meta_line,
			/**
			 * Gamification / leaderboard / portal-badge markup rendered
			 * inline beside the name. Populated here with the user's
			 * portal badges; Pro prepends the leaderboard level badge via
			 * the `suredash_search_user_result` filter below.
			 */
			'badges_html' => $this->get_portal_badges_html( $user_id ),
			'profile_url' => esc_url_raw( (string) $profile_url ),
			'bio_excerpt' => $bio_excerpt,
		];

		/**
		 * Filter: adjust / extend an individual people result object.
		 *
		 * @param array<string,mixed> $result Formatted result.
		 * @param \WP_User            $user   User.
		 * @param string              $term   Search term.
		 */
		return (array) apply_filters( 'suredash_search_user_result', $result, $user, $term );
	}

	/**
	 * Capture the portal-badge markup for a user as an HTML string.
	 *
	 * `suredash_get_user_badges()` echoes its output; we buffer it so we
	 * can attach the HTML to the REST payload instead of printing it
	 * inline. Pro prepends the leaderboard level badge on top of this.
	 *
	 * @param int $user_id User ID.
	 * @return string
	 */
	private function get_portal_badges_html( $user_id ) {
		if ( ! function_exists( 'suredash_get_user_badges' ) ) {
			return '';
		}

		ob_start();
		// Limit set to keep the card compact — overflow is handled by the
		// helper itself with a "+N" tooltip chip.
		suredash_get_user_badges( $user_id, 3 );
		return (string) ob_get_clean();
	}

	/**
	 * Best-effort human role label for a user.
	 *
	 * @param \WP_User $user User.
	 * @return string
	 */
	private function get_role_label( $user ) {
		global $wp_roles;

		$roles = is_array( $user->roles ) ? $user->roles : [];

		if ( empty( $roles ) || ! $wp_roles instanceof \WP_Roles ) {
			return __( 'Member', 'suredash' );
		}

		$role  = (string) $roles[0];
		$names = $wp_roles->get_names();

		return isset( $names[ $role ] ) ? (string) translate_user_role( $names[ $role ] ) : __( 'Member', 'suredash' );
	}
}
