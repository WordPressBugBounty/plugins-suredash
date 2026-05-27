<?php
/**
 * Search REST handler — exposes GET /suredash/v1/search.
 *
 * See docs/search-backend-architecture.md for the full contract.
 *
 * @package SureDash
 */

namespace SureDashboard\Core\Routers;

use SureDashboard\Inc\Services\Search\SearchResponseBuilder;
use SureDashboard\Inc\Traits\Get_Instance;

defined( 'ABSPATH' ) || exit;

/**
 * Class Search.
 *
 * @since 1.3.0
 */
class Search {
	use Get_Instance;

	public const MIN_QUERY_LENGTH = 2;
	public const MAX_QUERY_LENGTH = 200;

	/**
	 * Handle the search request.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function handle( $request ) {
		$args = $this->normalize_args( $request );

		if ( is_wp_error( $args ) ) {
			return $args;
		}

		// Flush any prior output so a stray PHP notice / warning (under
		// WP_DEBUG_DISPLAY) from plugin boot can't corrupt our JSON body.
		if ( ob_get_level() > 0 ) {
			ob_clean();
		}

		// Wrap the build in a dedicated buffer so any notice/warning raised
		// *inside* the search services (under WP_DEBUG_DISPLAY) is captured
		// and discarded instead of being prepended to the JSON body.
		ob_start();
		try {
			$response = SearchResponseBuilder::get_instance()->build( $args );
			ob_end_clean();
		} catch ( \Throwable $e ) {
			ob_end_clean();
			return new \WP_Error(
				'suredash_search_failed',
				$e->getMessage(),
				[ 'status' => 500 ]
			);
		}

		return rest_ensure_response( $response );
	}

	/**
	 * Extract and normalize request parameters.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return array<string,mixed>|\WP_Error
	 */
	private function normalize_args( $request ) {
		$q = trim( (string) $request->get_param( 'q' ) );

		if ( $q === '' ) {
			return new \WP_Error(
				'suredash_search_missing_query',
				__( 'A search term is required.', 'suredash' ),
				[ 'status' => 400 ]
			);
		}

		if ( mb_strlen( $q ) < self::MIN_QUERY_LENGTH ) {
			return new \WP_Error(
				'suredash_search_query_too_short',
				sprintf(
					/* translators: %d: minimum number of characters. */
					__( 'Search query must be at least %d characters.', 'suredash' ),
					self::MIN_QUERY_LENGTH
				),
				[ 'status' => 400 ]
			);
		}

		// Hard cap on query length to protect against abuse.
		if ( mb_strlen( $q ) > self::MAX_QUERY_LENGTH ) {
			$q = mb_substr( $q, 0, self::MAX_QUERY_LENGTH );
		}

		$type    = sanitize_key( (string) $request->get_param( 'type' ) );
		$orderby = sanitize_key( (string) $request->get_param( 'orderby' ) );
		$order   = sanitize_key( (string) $request->get_param( 'order' ) );

		if ( $type === '' ) {
			$type = 'all';
		}
		if ( ! in_array( $orderby, [ 'date', 'title' ], true ) ) {
			$orderby = 'date';
		}
		if ( ! in_array( $order, [ 'asc', 'desc' ], true ) ) {
			$order = 'desc';
		}

		return [
			'q'         => $q,
			'type'      => $type,
			'page'      => max( 1, (int) $request->get_param( 'page' ) ),
			'per_page'  => max( 1, min( 50, (int) $request->get_param( 'per_page' ) ) ),
			'orderby'   => $orderby,
			'order'     => $order,
			'portal_id' => max( 0, (int) $request->get_param( 'portal_id' ) ),
		];
	}
}
