<?php
/**
 * Portals TermMeta Initialize.
 *
 * @package SureDash
 */

namespace SureDashboard\Inc\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class TermMeta.
 *
 * @since 1.0.0
 */
class TermMeta {
	/**
	 * Get Post Dataset.
	 *
	 * @since 1.0.0
	 * @return array<string, array<string, mixed>>
	 */
	public static function get_group_dataset() {
		return apply_filters(
			'suredashboard_group_meta_dataset',
			[
				'term_name'        => [
					'default' => '',
					'type'    => 'string',
				],
				'term_description' => [
					'default' => '',
					'type'    => 'string',
				],
				'homegrid_spaces'  => [
					'default' => [],
					'type'    => 'array',
				],
				'hide_label'       => [
					'default' => false,
					'type'    => 'boolean',
				],
			]
		);
	}

	/**
	 * Get Group Meta type.
	 *
	 * @since 1.0.0
	 * @param string $meta_key Meta key.
	 * @return string Meta type.
	 */
	public static function get_group_meta_type( $meta_key ) {
		$dataset = self::get_group_dataset();

		if ( ! empty( $dataset[ $meta_key ]['type'] ) ) {
			return $dataset[ $meta_key ]['type'];
		}

		return 'string';
	}

	/**
	 * Get Group Meta Default Value.
	 *
	 * @since 1.0.0
	 * @param string $meta_key Meta key.
	 * @return mixed
	 */
	public static function get_group_meta_default_value( $meta_key ) {
		$dataset = self::get_group_dataset();

		if ( ! empty( $dataset[ $meta_key ]['default'] ) ) {
			return $dataset[ $meta_key ]['default'];
		}

		return '';
	}

	/**
	 * Get Group Meta Value.
	 *
	 * @since 1.0.0
	 * @param int    $term_id Term ID.
	 * @param string $meta_key Meta key.
	 * @return mixed
	 */
	public static function get_group_meta_value( $term_id, $meta_key ) {
		$term = get_term( absint( $term_id ), SUREDASHBOARD_TAXONOMY );

		if ( is_wp_error( $term ) || ! is_object( $term ) ) {
			return '';
		}

		if ( $meta_key === 'term_name' ) {
			$meta_value = $term->name;
		} elseif ( $meta_key === 'term_description' ) {
			$meta_value = $term->description;
		} else {
			$meta_value = get_term_meta( $term->term_id, $meta_key, true );
		}

		if ( empty( $meta_value ) ) {
			$meta_value = self::get_group_meta_default_value( $meta_key );
		}

		return apply_filters( 'suredashboard_group_meta_value', $meta_value, $term_id, $meta_key );
	}

	/**
	 * Get All Group Meta with values.
	 *
	 * @param int $term_id Term ID.
	 * @since 1.0.0
	 * @return array<string, mixed>
	 */
	public static function get_all_group_meta_values( $term_id ) {
		$dataset = self::get_group_dataset();

		$meta_values = [];

		foreach ( $dataset as $meta_key => $meta_data ) {
			$meta_values[ $meta_key ] = self::get_group_meta_value( $term_id, $meta_key );
		}

		return $meta_values;
	}

	/**
	 * Get All Group Meta with default values.
	 *
	 * @since 1.0.0
	 * @return array<string, mixed>
	 */
	public static function get_all_group_meta_default_values() {
		$dataset = self::get_group_dataset();

		$meta_values = [];

		foreach ( $dataset as $meta_key => $meta_data ) {
			$meta_values[ $meta_key ] = self::get_group_meta_default_value( $meta_key );
		}

		return $meta_values;
	}
}
