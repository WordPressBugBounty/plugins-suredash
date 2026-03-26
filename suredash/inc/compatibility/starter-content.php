<?php
/**
 * Initialize Login/Register starter-content setup
 *
 * @package suredashboard
 * @since 1.0.0
 */

namespace SureDashboard\Inc\Compatibility;

use SureDashboard\Inc\Traits\Get_Instance;
use SureDashboard\Inc\Utils\Helper;

defined( 'ABSPATH' ) || exit;

/**
 * This class setup admin init
 *
 * @class Starter_Content
 */
class Starter_Content {
	use Get_Instance;

	/**
	 * Constructor
	 */
	public function __construct() {
	}

	/**
	 * Create Login/Register on plugin activation.
	 *
	 * Consider templates/docs-page.php as a block code editor markup.
	 *
	 * @param string $layout_type Optional. Layout type: 'single-column' or 'two-column'. Default 'single-column'.
	 * @since 1.0.0
	 */
	public function create_pages( $layout_type = 'single-column' ): void {
		$login_page            = Helper::get_option( 'login_page' );
		$login_setting_page_id = is_array( $login_page ) && ! empty( $login_page['value'] ) ? absint( $login_page['value'] ) : 0;
		if ( ! $login_setting_page_id ) {
			$page_title    = 'Portal Login';
			$login_content = $layout_type === 'two-column' ? suredash_login_two_columns_pattern() : suredash_login_single_column_centered_pattern();
			$login_page    = [
				'post_title'   => $page_title,
				'post_content' => $login_content,
				'post_status'  => 'publish',
				'post_type'    => 'page',
			];

			$login_page_id = sd_wp_insert_post( $login_page );

			// Theme settings compatibility.
			if ( $login_page_id ) {
				$login_page_id = absint( $login_page_id ); // @phpstan-ignore-line
				if ( suredash_is_on_astra_theme() ) {
					sd_update_post_meta( $login_page_id, 'ast-site-content-layout', 'full-width-container' );
					sd_update_post_meta( $login_page_id, 'theme-transparent-header-meta', 'disabled' );
					sd_update_post_meta( $login_page_id, 'site-sidebar-layout', 'no-sidebar' );
					sd_update_post_meta( $login_page_id, 'site-post-title', 'disabled' );
					sd_update_post_meta( $login_page_id, 'ast-global-header-display', 'disabled' );
					sd_update_post_meta( $login_page_id, 'footer-sml-layout', 'disabled' );
				}

				Helper::update_option(
					'login_page',
					[
						'label' => $page_title,
						'value' => strval( $login_page_id ),
					]
				);
			}
		}

		$register_page            = Helper::get_option( 'register_page' );
		$register_setting_page_id = is_array( $register_page ) && ! empty( $register_page['value'] ) ? absint( $register_page['value'] ) : 0;
		if ( ! $register_setting_page_id ) {
			$page_title       = 'Portal Register';
			$register_content = $layout_type === 'two-column' ? suredash_register_two_columns_pattern() : suredash_register_single_column_centered_pattern();
			$register_page    = [
				'post_title'   => $page_title,
				'post_content' => $register_content,
				'post_status'  => 'publish',
				'post_type'    => 'page',
			];

			$register_page_id = sd_wp_insert_post( $register_page );

			// Theme settings compatibility.
			if ( $register_page_id ) {
				$register_page_id = absint( $register_page_id ); // @phpstan-ignore-line
				if ( suredash_is_on_astra_theme() ) {
					sd_update_post_meta( $register_page_id, 'ast-site-content-layout', 'full-width-container' );
					sd_update_post_meta( $register_page_id, 'theme-transparent-header-meta', 'disabled' );
					sd_update_post_meta( $register_page_id, 'site-sidebar-layout', 'no-sidebar' );
					sd_update_post_meta( $register_page_id, 'site-post-title', 'disabled' );
					sd_update_post_meta( $register_page_id, 'ast-global-header-display', 'disabled' );
					sd_update_post_meta( $register_page_id, 'footer-sml-layout', 'disabled' );
				}

				Helper::update_option(
					'register_page',
					[
						'label' => $page_title,
						'value' => strval( $register_page_id ),
					]
				);
			}
		}
	}
}
