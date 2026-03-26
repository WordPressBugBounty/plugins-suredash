<?php
/**
 * Portals Query Backend_Feeds Initialize.
 *
 * @package SureDash
 */

namespace SureDashboard\Core\Models;

use PhpParser\Node\Expr\Cast\Array_;
use SureDashboard\Inc\Traits\Get_Instance;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class Backend_Feeds Query Model.
 */
class Backend_Feeds {
	use Get_Instance;

	/**
	 * Get queried data.
	 *
	 * @param array<mixed> $args Args.
	 *
	 * @return mixed
	 */
	public static function get_query_post_data( $args ) {
		global $wpdb;

		$search_string = $args['s'] ?? '';
		$post_type     = $args['post_type'] ?? '';
		$is_tax_query  = $args['is_tax_query'] ?? false;
		$post_status   = $args['post_status'] ?? 'publish';
		$no_of_posts   = $args['posts_per_page'] ?? null;
		$taxonomy      = $args['taxonomy'] ?? '';
		$category      = $args['category'] ?? '';

		$escaped_search = $wpdb->esc_like( $search_string );
		$like_clause    = $wpdb->prepare(
			'(p.post_title LIKE %s OR p.post_content LIKE %s)',
			'%' . $escaped_search . '%',
			'%' . $escaped_search . '%'
		);

		$query = sd_query()
			->select( '*' )
			->from( 'posts AS p' )
			->where( 'p.post_type', '=', $post_type )
			->where( 'p.post_status', '=', $post_status )
			->whereRaw( $like_clause )
			->limit( $no_of_posts );

		// Add taxonomy condition if $is_tax_query is true.
		if ( $is_tax_query ) {
			$query->join( 'term_relationships AS tr', 'p.ID', '=', 'tr.object_id' )
				->join( 'term_taxonomy AS tt', 'tr.term_taxonomy_id', '=', 'tt.term_taxonomy_id' )
				->where( 'tt.taxonomy', '=', $taxonomy )
				->where( 'tt.term_id', '=', $category );
		}

		// Handle meta query.
		if ( ! empty( $args['meta_query'] ) && is_array( $args['meta_query'] ) ) {
			foreach ( $args['meta_query'] as $meta_query ) {
				if ( ! empty( $meta_query['key'] ) && ! empty( $meta_query['value'] ) ) {
					$query->join( 'postmeta AS tm', 'p.ID', '=', 'tm.post_id' )
						->where( 'tm.meta_key', '=', $meta_query['key'] )
						->where( 'tm.meta_value', $meta_query['compare'], $meta_query['value'] );
				}
			}
		}

		return $query->get( ARRAY_A );
	}

	/**
	 * Get uncategorized items data
	 *
	 * @param array<mixed> $args Args.
	 *
	 * @return mixed
	 */
	public static function get_query_uncategorized_items( $args = [] ) {

		$term_taxonomy_ids = sd_query()
			->select( 'GROUP_CONCAT(term_taxonomy_id) AS ids' )
			->from( 'term_taxonomy' )
			->where( 'taxonomy', '=', SUREDASHBOARD_TAXONOMY )->get( ARRAY_A );

		$term_taxonomy_ids = is_array( $term_taxonomy_ids ) && isset( $term_taxonomy_ids[0]['ids'] ) ? explode( ',', $term_taxonomy_ids[0]['ids'] ) : [];

		$object_ids = sd_query()
			->select( 'GROUP_CONCAT(object_id) AS ids' )
			->from( 'term_relationships' )
			->whereIn(
				'term_taxonomy_id',
				$term_taxonomy_ids
			)->get( ARRAY_A );

		$object_ids = is_array( $object_ids ) && isset( $object_ids[0]['ids'] ) ? explode( ',', $object_ids[0]['ids'] ) : [];

		$uncategorized_items = sd_query()
			->select( 'GROUP_CONCAT(ID) as ids' )
			->from( 'posts' )
			->where( 'post_type', '=', SUREDASHBOARD_POST_TYPE )
			->where( 'post_status', '!=', 'trash' )
			->whereNotIn(
				'ID',
				$object_ids
			)
			->get( ARRAY_A );

		$uncategorized_items = is_array( $uncategorized_items ) && isset( $uncategorized_items[0]['ids'] ) ? explode( ',', $uncategorized_items[0]['ids'] ) : [];
		return sd_query()
			->select( '*' )
			->from( 'posts' )
			->where( 'post_type', '=', SUREDASHBOARD_POST_TYPE )
			->where( 'post_status', '!=', 'trash' )
			->where( 'ID', 'IN', $uncategorized_items )
			->get( ARRAY_A );
	}
}
