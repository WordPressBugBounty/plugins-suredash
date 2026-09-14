<?php
/**
 * Portals User Profile Shortcode Initialize.
 *
 * @package SureDash
 */

namespace SureDashboard\Core\Shortcodes;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use SureDashboard\Inc\Traits\Get_Instance;
use SureDashboard\Inc\Traits\Shortcode;
use SureDashboard\Inc\Utils\Helper;

/**
 * Class User Profile Shortcode.
 */
class User_Profile {
	use Shortcode;
	use Get_Instance;

	/**
	 * Register_shortcode_event.
	 *
	 * @return void
	 */
	public function register_shortcode_event(): void {
		$this->add_shortcode( 'user_profile' );
	}

	/**
	 * Add custom attributes to the link.
	 *
	 * Case: Elementor's page-transition feature causes suredash_sub_queries visits auto logout.
	 *
	 * @return void
	 * @since 1.3.0
	 */
	public function add_custom_attributes(): void {
		$attributes = [];

		if ( defined( 'ELEMENTOR_PRO_VERSION' ) ) {
			$attributes['data-e-disable-page-transition'] = 'true';
		}

		if ( ! empty( $attributes ) ) {
			foreach ( $attributes as $attr_name => $attr_value ) {
				printf( ' %s="%s"', esc_attr( $attr_name ), esc_attr( $attr_value ) );
			}
		}
	}

	/**
	 * Display user profile section.
	 *
	 * @param array<mixed> $atts Array of attributes.
	 * @since 1.0.0
	 * @return string|false
	 */
	public function render_user_profile( $atts ) {
		$atts = apply_filters(
			'suredash_user_profile_attributes',
			shortcode_atts(
				[],
				$atts
			)
		);

		if ( ! is_user_logged_in() ) {
			return $this->get_non_logged_in_user_view( $atts );
		}

		$portal_user_menu_links = Helper::get_option( 'profile_links' );
		$logout_link_data       = Helper::get_option( 'profile_logout_link' );

		if ( ! is_array( $logout_link_data ) ) {
			$logout_link_data = [];
		}

		$menu_direction_css = '';
		if ( isset( $atts['menuopenverposition'] ) ) {
			$ver_position = in_array( $atts['menuopenverposition'], [ 'top', 'bottom' ], true ) ? $atts['menuopenverposition'] : 'top';
			$hor_position = in_array( $atts['menuopenhorposition'] ?? '', [ 'left', 'right' ], true ) ? $atts['menuopenhorposition'] : ( is_rtl() ? 'left' : 'right' );
			$ver_offset   = $this->sanitize_css_length( $atts['menuverpositionoffset'] ?? '' );
			$hor_offset   = $this->sanitize_css_length( $atts['menuhorpositionoffset'] ?? '' );

			$menu_direction_css .= $ver_position . ':' . $ver_offset . ';';
			$menu_direction_css .= $hor_position . ':' . $hor_offset . ';';
		}

		ob_start();
		?>
			<div class="portal-user-profiles-wrap sd-relative portal-content">
				<div
					class="portal-header-avatar-wrap"
					data-view="logged-in"
					role="button"
					tabindex="0"
					aria-haspopup="true"
					aria-expanded="false"
					aria-label="<?php echo esc_attr__( 'Account menu', 'suredash' ); ?>">
					<?php
						// Get the current user.
						$current_user = wp_get_current_user();
						suredash_get_user_avatar( $current_user->ID );
					?>

					<?php if ( ! isset( $atts['onlyavatar'] ) || ( isset( $atts['onlyavatar'] ) && ! $atts['onlyavatar'] ) ) { ?>
						<div class="portal-user-settings">
							<div class="portal-user-details">
								<span class="portal-user-name"><?php echo esc_html( suredash_get_user_display_name( $current_user->ID ) ); ?></span>
								<span class="portal-user-email"><?php echo esc_html( $current_user->user_email ); ?></span>
							</div>
						</div>
						<?php
						// Decorative affordance only: the whole avatar wrap is already the
						// account menu button, and this has no handler of its own. Left in
						// the tab order it was an unnamed second stop that did the same
						// thing, and a real button nested inside a role="button" element.
						// Hidden from assistive tech and taken out of the tab order, the
						// same way the duplicate responsive footer nav already is.
						?>
						<span class="portal-button button-ghost sd-pointer sd-force-p-0" aria-hidden="true">
							<?php Helper::get_library_icon( 'EllipsisVertical', true, 'sm' ); ?>
						</span>
					<?php } ?>
				</div>
				<div class="portal-avatar-menu" style="<?php echo esc_attr( $menu_direction_css ); ?>">
					<div class="portal-user-menu-links">
						<?php
						if ( is_array( $portal_user_menu_links ) ) {
							foreach ( $portal_user_menu_links as $link_data ) {
								if ( ! is_array( $link_data ) ) {
									continue;
								}

								$link = suredash_dynamic_content_support( $link_data['link'] );
								?>
								<a href="<?php echo esc_url( $link ); ?>" class="portal-user-menu-link" <?php $this->add_custom_attributes(); ?>>
									<?php Helper::get_library_icon( $link_data['icon'], true, 'xs' ); ?>
									<span class="portal-user-menu-link-title"><?php echo esc_html( __( $link_data['title'], 'suredash' ) ); // phpcs:ignore?></span>
								</a>
								<?php
							}
						}
						?>
					</div>

					<a href="<?php echo esc_url( wp_logout_url( home_url( suredash_get_community_slug() ) ) ); ?>" class="portal-user-menu-link portal-logout-url" <?php $this->add_custom_attributes(); ?>>
						<?php Helper::get_library_icon( $logout_link_data['icon'] ?? '', true, 'xs' ); ?>
						<span class="portal-user-menu-link-title"><?php echo esc_html( __( $logout_link_data['title'] ?? '', 'suredash' ) ); // phpcs:ignore?></span>
					</a>
				</div>
			</div>
		<?php

		return ob_get_clean();
	}

	/**
	 * Get the non logged in user view.
	 *
	 * @param array<mixed> $atts Array of attributes.
	 *
	 * @since 0.0.1
	 * @return string HTML content for the non-logged-in user view.
	 */
	public function get_non_logged_in_user_view( $atts ) {
		ob_start();

		?>
			<div class="portal-user-profiles-wrap sd-relative portal-content">
				<div class="portal-header-avatar-wrap" data-view="not-logged-in">

					<a href="<?php echo esc_url( suredash_get_login_page_url() ); ?>" class="portal-user-menu-link portal-logout-url sd-text-color" title="<?php echo esc_attr__( 'Login', 'suredash' ); ?>">
						<?php suredash_get_user_avatar( get_current_user_id() ); ?>

						<?php if ( ! isset( $atts['onlyavatar'] ) || ( isset( $atts['onlyavatar'] ) && ! $atts['onlyavatar'] ) ) { ?>
							<div class="portal-user-settings">
								<div class="portal-user-details">
									<span class="portal-user-name"><?php echo esc_html__( 'Guest User', 'suredash' ); ?></span>
								</div>
							</div>
							<?php Helper::get_library_icon( 'logIn', true, 'sm' ); ?>
						<?php } ?>
					</a>
				</div>
			</div>
		<?php

		return apply_filters( 'suredashboard_non_logged_in_user_header', ob_get_clean() );
	}

	/**
	 * Sanitize a CSS length value used for menu position offsets.
	 *
	 * Only an empty string or a numeric value with an optional CSS unit is
	 * allowed; anything else (e.g. attribute-breakout payloads) is rejected
	 * and returns an empty string.
	 *
	 * @param mixed $value The raw offset value.
	 * @since 1.10.1
	 * @return string A safe CSS length, or an empty string.
	 */
	private function sanitize_css_length( $value ): string {
		$value = trim( (string) $value );

		if ( $value === '' ) {
			return '';
		}

		return preg_match( '/^-?\d+(\.\d+)?(px|em|rem|%|vh|vw|vmin|vmax)?$/', $value ) ? $value : '';
	}
}
