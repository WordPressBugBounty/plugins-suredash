<?php
/**
 * Portals Query Model Initialize.
 *
 * @package SureDash
 */

namespace SureDashboard\Core\Models;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class Query Model.
 */
class Controller {
	/**
	 * Cache key.
	 */
	public const DB_CACHE_KEY = 'suredash_query_data';

	/**
	 * Base models.
	 */
	public const BASE_MODEL = 'SureDashboard\Core\Models\\';

	/**
	 * Get query data.
	 *
	 * @param string       $query Query.
	 * @param array<mixed> $args  Args.
	 *
	 * @return array<mixed>
	 */
	public static function get_query_data( $query, $args = [] ): array {
		$query_model_instance = self::BASE_MODEL . $query;
		return $query_model_instance::get_instance()::get_query_data( $args );
	}

	/**
	 * Get query data.
	 *
	 * @param string       $query Query.
	 * @param array<mixed> $args  Args.
	 *
	 * @return array<mixed>
	 */
	public static function get_user_query_data( $query, $args = [] ): array {
		$query_model_instance = self::BASE_MODEL . $query;
		return $query_model_instance::get_instance()::get_user_query_data( $args );
	}

	/**
	 * Get query post data.
	 *
	 * @param string       $query Query.
	 * @param array<mixed> $args  Args.
	 *
	 * @return array<mixed>
	 */
	public static function get_query_post_data( $query, $args = [] ): array {
		$query_model_instance = self::BASE_MODEL . $query;
		return $query_model_instance::get_instance()::get_query_post_data( $args );
	}

	/**
	 * Get uncategorized items data.
	 *
	 * @param string       $query Query.
	 * @param array<mixed> $args  Args.
	 *
	 * @return array<mixed>
	 */
	public static function get_query_uncategorized_items( $query, $args = [] ): array {
		$query_model_instance = self::BASE_MODEL . $query;
		return $query_model_instance::get_instance()::get_query_uncategorized_items( $args );
	}

	/**
	 * Update query data.
	 *
	 * @param string       $query Query.
	 * @param array<mixed> $data  Data.
	 *
	 * @return void
	 */
	public static function update_query_data( $query, $data ): void {
		$query_model_instance = self::BASE_MODEL . $query;
		$query_model_instance::update_query_data( $data );
	}

	/**
	 * Update checksum.
	 *
	 * @param string $query Query.
	 *
	 * @return void
	 */
	public static function update_checksum( $query ): void {
		$query_model_instance = self::BASE_MODEL . $query;
		$query_model_instance::update_checksum();
	}
}
