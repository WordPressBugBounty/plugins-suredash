<?php
/**
 * Backwards.
 *
 * @package SureDash
 * @since 1.3.0
 */

namespace SureDashboard\Inc\Utils;

defined( 'ABSPATH' ) || exit;

/**
 * Update Compatibility
 *
 * @package SureDash
 */
class Backwards {
	/**
	 * Backward compatibility for color palette.
	 *
	 * Case: We need to keep the old legacy colors as it is on users end, and on basis of flag we have ask user to migrate.
	 *
	 * @param string $saved_version The saved version number.
	 * @since 1.3.0
	 * @return void
	 */
	public static function version_1_3_0( $saved_version ): void {
		if ( version_compare( $saved_version, '1.3.0', '<' ) ) {
			$db_options = get_option( SUREDASHBOARD_SETTINGS, [] );
			if ( ! isset( $db_options['backward_color_options'] ) ) {
				$db_options['backward_color_options'] = true;
				$db_options['default_palette']        = 'custom';
				update_option( SUREDASHBOARD_SETTINGS, $db_options );
			}
		}
	}

	/**
	 * Backward compatibility for separate like and share options.
	 *
	 * Case: Migrate from the combined 'show_like_share' option to separate 'show_like_button' and 'show_share_button' options.
	 *
	 * @param string $saved_version The saved version number.
	 * @since 1.4.0
	 * @return void
	 */
	public static function version_1_4_0( $saved_version ): void {
		if ( version_compare( $saved_version, '1.4.0', '<' ) ) {
			$spaces = sd_get_posts(
				[
					'post_type'      => SUREDASHBOARD_POST_TYPE,
					'post_status'    => 'publish',
					'posts_per_page' => -1,
				]
			);

			if ( ! empty( $spaces ) && is_array( $spaces ) ) {
				foreach ( $spaces as $space ) {
					$space_id = $space['ID'] ?? 0;
					// Check if this space already has the new separate options.
					$has_like_button  = metadata_exists( 'post', $space_id, 'show_like_button' );
					$has_share_button = metadata_exists( 'post', $space_id, 'show_share_button' );

					// If the new separate options don't exist yet, migrate from the old combined option.
					if ( ! $has_like_button || ! $has_share_button ) {
						$old_like_share_value = sd_get_post_meta( $space_id, 'show_like_share', true );

						// Convert to boolean.
						$old_like_share_value = filter_var( $old_like_share_value, FILTER_VALIDATE_BOOLEAN );

						// Set both new options based on the old combined value.
						sd_update_post_meta( $space_id, 'show_like_button', $old_like_share_value );
						sd_update_post_meta( $space_id, 'show_share_button', $old_like_share_value );
					}
				}
			}
		}
	}

	/**
	 * Backward compatibility for preserve excerpt HTML setting.
	 *
	 * Case: Set 'preserve_excerpt_html' to false for existing users to maintain backward compatibility.
	 * New users will have it enabled by default as per settings.php.
	 *
	 * @param string $saved_version The saved version number.
	 * @since 1.5.0
	 * @return void
	 */
	public static function version_1_5_0( $saved_version ): void {
		if ( version_compare( $saved_version, '1.5.0', '<' ) ) {
			$db_options = get_option( SUREDASHBOARD_SETTINGS, [] );
			if ( ! isset( $db_options['preserve_excerpt_html'] ) ) {
				$db_options['preserve_excerpt_html'] = false;
				update_option( SUREDASHBOARD_SETTINGS, $db_options );
			}
		}
	}

	/**
	 * Hot fix update.
	 *
	 * Case: Set 'preserve_excerpt_html' to false for existing users to maintain backward compatibility.
	 * New users will have it enabled by default as per settings.php.
	 *
	 * @param string $saved_version The saved version number.
	 * @since 1.5.1
	 * @return void
	 */
	public static function version_1_5_1( $saved_version ): void {
		if ( version_compare( $saved_version, '1.5.1', '<' ) ) {
			$db_options                          = get_option( SUREDASHBOARD_SETTINGS, [] );
			$db_options['preserve_excerpt_html'] = false;
			update_option( SUREDASHBOARD_SETTINGS, $db_options );
		}
	}
}
