<?php
/**
 * Sanitizer.
 *
 * @package SureDash
 * @since 0.0.1
 */

namespace SureDashboard\Inc\Utils;

defined( 'ABSPATH' ) || exit;

/**
 * This class setup all sanitization methods
 *
 * @class Sanitizer
 */
class Sanitizer {
	/**
	 * Sanitize JSON data with support for various data structures.
	 *
	 * @access public
	 *
	 * @param string               $json_data JSON string to sanitize.
	 * @param array<string,string> $field_types Optional array mapping field names to their data types.
	 * @param bool                 $preserve_structure Whether to preserve the original structure or extract specific fields.
	 * @param array<string>        $extract_fields Fields to extract if not preserving structure.
	 *
	 * @since 1.0.0
	 * @return array|mixed Sanitized data.
	 */
	public static function sanitize_json_data( $json_data, $field_types = [], $preserve_structure = true, $extract_fields = [] ) {
		if ( empty( $json_data ) ) {
			return [];
		}

		// First, unslash the data to handle WordPress's automatic escaping.
		$raw_json = wp_unslash( $json_data );

		// Decode the JSON string into a PHP array.
		$decoded_data = json_decode( $raw_json, true );

		// If JSON is invalid, return empty array.
		if ( ! is_array( $decoded_data ) ) {
			return [];
		}

		$sanitized_data = [];

		// Handle array of objects.
		if ( isset( $decoded_data[0] ) && is_array( $decoded_data[0] ) ) {
			foreach ( $decoded_data as $item ) {
				if ( ! is_array( $item ) ) {
					continue;
				}

				if ( $preserve_structure ) {
					$sanitized_item = [];

					foreach ( $item as $key => $value ) {
						$type                   = $field_types[ $key ] ?? 'string';
						$sanitized_item[ $key ] = self::sanitize_by_type( $value, $type );
					}

					$sanitized_data[] = $sanitized_item;
				} else {
					// Extract only specific fields.
					$extracted_item = [];

					foreach ( $extract_fields as $field ) {
						if ( isset( $item[ $field ] ) ) {
							$type                     = $field_types[ $field ] ?? 'string';
							$extracted_item[ $field ] = self::sanitize_by_type( $item[ $field ], $type );
						}
					}

					if ( ! empty( $extracted_item ) ) {
						$sanitized_data[] = $extracted_item;
					}
				}
			}
		} else {
			// Handle simple array of values.
			foreach ( $decoded_data as $key => $value ) {
				$type                   = is_numeric( $key ) ? 'integer' : ( $field_types[ $key ] ?? 'string' );
				$sanitized_data[ $key ] = self::sanitize_by_type( $value, $type );
			}
		}

		return $sanitized_data;
	}
	/**
	 * Settings sanitizer for portal settings.
	 *
	 * @access public
	 *
	 * @param mixed $dataset from AJAX.
	 * @since 1.0.0
	 * @return mixed Sanitized data.
	 */
	public static function sanitize_settings_data( $dataset ) {
		$output = '';

		if ( is_array( $dataset ) ) {
			$output = [];

			foreach ( $dataset as $key => $value ) {
				$datatype = Settings::get_setting_type( $key );

				switch ( $datatype ) {
					case 'html':
						$output[ $key ] = wp_kses_post( $value );
						break;

					case 'css':
						$output[ $key ] = self::sanitize_css( strval( $value ) );
						break;

					case 'array':
						$output[ $key ] = is_array( $value ) ? suredash_clean_data( $value ) : [];
						break;

					case 'boolean':
						$output[ $key ] = filter_var( $value, FILTER_VALIDATE_BOOLEAN );
						break;

					case 'integer':
					case 'number':
						$output[ $key ] = absint( $value );
						break;

					case 'email':
						$output[ $key ] = sanitize_email( $value );
						break;

					default:
					case 'string':
						$output[ $key ] = sanitize_text_field( $value );
						break;
				}

				do_action( "portal_sanitize_setting_{$key}", $output[ $key ], $key );
			}
		} else {
			$output = sanitize_text_field( $dataset );
		}

		return $output;
	}

	/**
	 * Data sanitizer for AJAX.
	 *
	 * @since 0.0.1
	 * @access public
	 *
	 * @param mixed  $value from AJAX.
	 * @param string $data_type to sanitize further.
	 *
	 * @return mixed Sanitized data.
	 */
	public static function sanitize_meta_data( $value, $data_type = 'default' ) {
		$output = '';

		switch ( $data_type ) {
			case 'bool':
			case 'boolean':
				$output = isset( $value ) && sanitize_text_field( $value ) === 'true' ? true : false;
				break;

			case 'email':
				$output = isset( $value ) ? sanitize_email( wp_unslash( $value ) ) : '';
				break;

			case 'int':
			case 'integer':
				$output = ! empty( $value ) ? absint( $value ) : '';
				break;

			case 'url':
				$output = ! empty( $value ) ? esc_url( $value ) : '';
				break;

			case 'html':
				$output = ! empty( $value ) ? wp_kses_post( wp_unslash( $value ) ) : '';
				break;

			case 'metadata':
				$output = ! empty( $value ) ? PostMeta::sanitize_data( $value ) : '';
				break;

			case 'array':
			case 'default':
			default:
				$output = ! empty( $value ) ? suredash_clean_data( wp_unslash( $value ) ) : '';
				break;
		}

		return $output;
	}

	/**
	 * Data sanitizer for AJAX group meta.
	 *
	 * @access public
	 *
	 * @param mixed $dataset from AJAX.
	 * @since 1.0.0
	 * @return array<string, mixed> Sanitized data.
	 */
	public static function sanitize_term_data( $dataset ) {
		$output = '';

		if ( is_array( $dataset ) ) {
			$output = [];

			foreach ( $dataset as $key => $value ) {
				$datatype = TermMeta::get_group_meta_type( $key );

				switch ( $datatype ) {
					case 'html':
						$output[ $key ] = wp_kses_post( $value );
						break;

					case 'array':
						$output[ $key ] = is_array( $value ) ? suredash_clean_data( $value ) : [];
						break;

					case 'boolean':
						$output[ $key ] = filter_var( $value, FILTER_VALIDATE_BOOLEAN );
						break;

					case 'integer':
						$output[ $key ] = absint( $value );
						break;

					default:
					case 'string':
						$output[ $key ] = sanitize_text_field( $value );
						break;
				}
			}
		} else {
			$output = sanitize_text_field( $dataset );
		}

		return $output; // @phpstan-ignore-line
	}

	/**
	 * Sanitize a value based on its type.
	 *
	 * @access private
	 *
	 * @param mixed  $value Value to sanitize.
	 * @param string $type Type of data.
	 *
	 * @since 1.0.0
	 * @return mixed Sanitized value.
	 */
	private static function sanitize_by_type( $value, $type ) {
		switch ( $type ) {
			case 'html':
				return wp_kses_post( $value );

			case 'array':
				return is_array( $value ) ? suredash_clean_data( $value ) : [];

			case 'boolean':
				return filter_var( $value, FILTER_VALIDATE_BOOLEAN );

			case 'integer':
			case 'number':
				return absint( $value );

			case 'email':
				return sanitize_email( $value );

			case 'url':
				return esc_url_raw( $value );

			default:
			case 'string':
				return sanitize_text_field( $value );
		}
	}

	/**
	 * Sanitize CSS data.
	 *
	 * @access private
	 * @param string $css CSS code to sanitize.
	 * @return string Sanitized CSS.
	 * @since 1.3.0
	 */
	private static function sanitize_css( $css ) {
		// Ensure we have a string to work with.
		$css = strval( $css );

		// Remove null bytes and other control characters.
		$css = str_replace( [ chr( 0 ), chr( 1 ), chr( 2 ), chr( 3 ), chr( 4 ), chr( 5 ) ], '', $css );

		// Combine all dangerous patterns into a single regex for better performance.
		$combined_pattern = '/(<\?(?:php)?.*?\?>)|(<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>)|(expression\s*\()|(javascript\s*:)|(vbscript\s*:)|(-moz-binding)|(behavior\s*:)|(import\s*["\'].*?["\'];?)|(data\s*:\s*[^;,)]*[;,)])|(@import\s+.*?;)/is';

		$css = preg_replace_callback(
			$combined_pattern,
			static function( $matches ) {
				// All patterns should be removed, so return empty string.
				return '';
			},
			$css
		);

		// Strip HTML tags but preserve CSS content.
		$css = wp_strip_all_tags( strval( $css ), false );

		// Additional safety: remove any remaining script-like content.
		$css = str_ireplace( [ '<script', '</script>', 'javascript:', 'vbscript:' ], '', $css );

		return trim( $css );
	}
}
