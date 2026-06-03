<?php
/**
 * SearchResponseBuilder — assembles the final REST response envelope for
 * the /suredash/v1/search endpoint.
 *
 * Orchestration:
 *   1. Resolve the list of enabled search types (apply filter).
 *   2. Run cheap count-only queries for every type.
 *   3. Fetch full result pages only for non-zero types that we need
 *      (type=all → every non-zero type; type=posts → just that type).
 *   4. Return the shared envelope: { query, tabs, results }.
 *
 * @package SureDash
 */

namespace SureDashboard\Inc\Services\Search;

use SureDashboard\Inc\Traits\Get_Instance;

defined( 'ABSPATH' ) || exit;

/**
 * Class SearchResponseBuilder.
 *
 * @since 1.3.0
 */
class SearchResponseBuilder {
	use Get_Instance;

	public const PREVIEW_PER_TYPE = 5;  // per type in `type=all` view.
	public const DEFAULT_PER_PAGE = 10; // per page in `type=<specific>` view.

	/**
	 * Build the full response for a search request.
	 *
	 * @param array<string,mixed> $args Normalized args: q, type, page, per_page, orderby, order, portal_id.
	 * @return array<string,mixed>
	 */
	public function build( array $args ) {
		$enabled_types = $this->get_enabled_types();
		$requested     = (string) ( $args['type'] ?? 'all' );
		$is_all        = ( $requested === 'all' );

		$counts = $this->get_counts( $enabled_types, $args );
		$tabs   = $this->build_tabs( $enabled_types, $counts );

		// Determine which types need a full result fetch.
		$fetch_types = $is_all
			? array_keys( array_filter( $counts, static fn( $c ): bool => $c > 0 ) )
			: ( in_array( $requested, $enabled_types, true ) ? [ $requested ] : [] );

		$results  = [];
		$per_page = $is_all
			? self::PREVIEW_PER_TYPE
			: (int) ( $args['per_page'] ?? self::DEFAULT_PER_PAGE );

		foreach ( $fetch_types as $type_key ) {
			$type_args                = $args;
			$type_args['per_page']    = $per_page;
			$type_args['page']        = $is_all ? 1 : max( 1, (int) ( $args['page'] ?? 1 ) );
			$type_args['search_type'] = $type_key;
			// Pass the already-computed count so services can skip a
			// redundant COUNT query on the fetch path.
			$type_args['known_total'] = (int) ( $counts[ $type_key ] ?? 0 );

			$block = $this->fetch_for_type( $type_key, $type_args );
			if ( ! isset( $block['items'] ) ) {
				continue;
			}

			$results[ $type_key ] = [
				'items'      => $block['items'],
				'pagination' => [
					'total'   => $block['total'],
					'pages'   => $block['pages'],
					'current' => $block['current'],
				],
			];
		}

		return [
			'query'   => (string) ( $args['q'] ?? '' ),
			'tabs'    => $tabs,
			'results' => (object) $results,
		];
	}

	/**
	 * Resolve the list of enabled search types.
	 *
	 * Pro addons can hook into `suredash_search_types` to add 'lessons',
	 * 'events', etc.
	 *
	 * @return array<int,string>
	 */
	public function get_enabled_types() {
		// Order determines tab display order in the UI. "All" is prepended
		// client-side; the first server-sent tab appears right after All.
		$default = [ 'spaces', 'posts', 'comments', 'people' ];

		if ( ! SearchCommentsService::is_enabled() ) {
			$default = array_values( array_diff( $default, [ 'comments' ] ) );
		}

		// Tabs hidden from guests by default:
		// - 'people' is privacy-sensitive (member directory exposure).
		// - 'comments' lead to parent posts that typically require login on
		// gated portals — surfacing them just bounces guests to a login
		// screen on click.
		// Site owners who want either exposed publicly can re-enable via
		// the `suredash_search_types` filter below.
		if ( ! is_user_logged_in() ) {
			$default = array_values( array_diff( $default, [ 'people', 'comments' ] ) );
		}

		return array_values( (array) apply_filters( 'suredash_search_types', $default ) );
	}

	/**
	 * Labels shown in the tabs bar. Filterable for i18n overrides.
	 *
	 * @return array<string,string>
	 */
	public function get_tab_labels() {
		$default = [
			'posts'     => __( 'Posts', 'suredash' ),
			'comments'  => __( 'Comments', 'suredash' ),
			'people'    => __( 'Members', 'suredash' ),
			'lessons'   => __( 'Lessons', 'suredash' ),
			'events'    => __( 'Events', 'suredash' ),
			'resources' => __( 'Resources', 'suredash' ),
			'spaces'    => __( 'Spaces', 'suredash' ),
		];

		return (array) apply_filters( 'suredash_search_tab_labels', $default );
	}

	/**
	 * Compute count-only results for every enabled type.
	 *
	 * @param array<int,string>   $enabled_types Enabled type keys.
	 * @param array<string,mixed> $args          Normalized args.
	 * @return array<string,int>
	 */
	private function get_counts( array $enabled_types, array $args ) {
		$counts = [];
		foreach ( $enabled_types as $type ) {
			$type_args                = $args;
			$type_args['search_type'] = $type;
			$counts[ $type ]          = (int) $this->count_for_type( $type, $type_args );
		}
		return $counts;
	}

	/**
	 * Run the count for a given type key by dispatching to the right service.
	 *
	 * @param string              $type Search type key.
	 * @param array<string,mixed> $args Args (must contain search_type).
	 * @return int
	 */
	private function count_for_type( $type, array $args ) {
		switch ( $type ) {
			case 'people':
				return SearchPeopleService::get_instance()->count( $args );
			case 'comments':
				return SearchCommentsService::get_instance()->count( $args );
			case 'posts':
			case 'lessons':
			case 'events':
			case 'resources':
			case 'spaces':
			default:
				return $this->dispatch_posts_count( $type, $args );
		}
	}

	/**
	 * Fetch full results for a given type key.
	 *
	 * @param string              $type Search type key.
	 * @param array<string,mixed> $args Args.
	 * @return array<string,mixed>
	 */
	private function fetch_for_type( $type, array $args ) {
		switch ( $type ) {
			case 'people':
				return SearchPeopleService::get_instance()->query( $args );
			case 'comments':
				return SearchCommentsService::get_instance()->query( $args );
			case 'posts':
			case 'lessons':
			case 'events':
			case 'resources':
			case 'spaces':
			default:
				return $this->dispatch_posts_query( $type, $args );
		}
	}

	/**
	 * Dispatch a post-backed count query (posts / lessons / events).
	 *
	 * Custom types registered by Pro can use the `suredash_search_custom_count`
	 * filter to short-circuit with their own counter, otherwise they fall
	 * through to the shared post service.
	 *
	 * @param string              $type Type key.
	 * @param array<string,mixed> $args Args.
	 * @return int
	 */
	private function dispatch_posts_count( $type, array $args ) {
		$override = apply_filters( 'suredash_search_custom_count', null, $type, $args );
		if ( is_int( $override ) ) {
			return $override;
		}
		return SearchPostsService::get_instance()->count( $args );
	}

	/**
	 * Dispatch a post-backed fetch query (posts / lessons / events).
	 *
	 * @param string              $type Type key.
	 * @param array<string,mixed> $args Args.
	 * @return array<string,mixed>
	 */
	private function dispatch_posts_query( $type, array $args ) {
		$override = apply_filters( 'suredash_search_custom_query', null, $type, $args );
		if ( is_array( $override ) && isset( $override['items'] ) ) {
			return $override;
		}
		return SearchPostsService::get_instance()->query( $args );
	}

	/**
	 * Build the tabs array: [ {type, label, count}, ... ].
	 *
	 * @param array<int,string> $enabled_types Enabled types in display order.
	 * @param array<string,int> $counts        Count per type.
	 * @return array<int,array{type:string,label:string,count:int}>
	 */
	private function build_tabs( array $enabled_types, array $counts ) {
		$labels = $this->get_tab_labels();
		$tabs   = [];

		foreach ( $enabled_types as $type ) {
			$tabs[] = [
				'type'  => $type,
				'label' => $labels[ $type ] ?? ucfirst( $type ),
				'count' => (int) ( $counts[ $type ] ?? 0 ),
			];
		}

		return $tabs;
	}
}
