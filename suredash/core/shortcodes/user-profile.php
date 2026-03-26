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
			$menu_direction_css .= $atts['menuopenverposition'] . ':' . $atts['menuverpositionoffset'] . ';';
			$menu_direction_css .= $atts['menuopenhorposition'] . ':' . $atts['menuhorpositionoffset'] . ';';
		}

		ob_start();
		?>
			<div class="portal-user-profiles-wrap sd-relative portal-content">
				<div class="portal-header-avatar-wrap" data-view="logged-in">
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
						<button class="portal-button button-ghost sd-pointer sd-force-p-0">
							<?php Helper::get_library_icon( 'EllipsisVertical', true, 'sm' ); ?>
						</button>
					<?php } ?>
				</div>
				<div class="portal-avatar-menu" style="<?php echo do_shortcode( $menu_direction_css ); ?>">
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
}
