<?php
/**
 * Portals Content Header Shortcode Initialize.
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
use SureDashboard\Inc\Utils\Labels;

/**
 * Class Content Header Shortcode.
 */
class Content_Header {
	use Shortcode;
	use Get_Instance;

	/**
	 * Register_shortcode_event.
	 *
	 * @return void
	 */
	public function register_shortcode_event(): void {
		$this->add_shortcode( 'content_header' );
	}

	/**
	 * Display content header section.
	 *
	 * @param array<mixed> $atts Array of attributes.
	 * @since 1.0.0
	 *
	 * @return string|false
	 */
	public function render_content_header( $atts ) {
		$defaults = [
			'emoji' => '',
			'title' => apply_filters( 'suredash_page_heading', Labels::get_label( 'welcome_text' ) . ' ' . suredash_get_user_display_name() ),
		];

		$atts = apply_filters( 'suredash_content_header_attributes', shortcode_atts( $defaults, $atts ) );

		if ( ! empty( $atts['emoji'] ) ) {
			$atts['emoji'] = Helper::get_library_icon( $atts['emoji'], false, 'md' );
		}

		ob_start();
		?>
		<div class="portal-item-title-area portal-content sd-flex sd-justify-between sd-items-center sd-gap-8 sd-py-12 sd-px-20 sd-border-b sd-m-0">
			<?php
			if ( suredash_cpt() ) {
				$current_post_id = get_the_ID();
				$space_id        = $current_post_id ? absint( sd_get_space_id_by_post( $current_post_id ) ) : 0;
				$default_url     = home_url( '/' . suredash_get_community_slug() . '/' );
				$space_link      = $space_id ? get_permalink( $space_id ) . '#portal-post-' . $current_post_id : $default_url;
				$space_title     = $space_id ? get_the_title( $space_id ) : '';

				// Priority 1: Use ?ref= param (passed from quick-view expand).
				// Priority 2: Use HTTP_REFERER (direct navigation).
				// Priority 3: Fall back to space permalink.
				$ref_param     = ! empty( $_GET['ref'] ) ? esc_url_raw( rawurldecode( sanitize_text_field( wp_unslash( $_GET['ref'] ) ) ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$referer       = $ref_param ? $ref_param : ( ! empty( $_SERVER['HTTP_REFERER'] ) ? esc_url_raw( $_SERVER['HTTP_REFERER'] ) : '' );
				$referer_clean = $referer ? strtok( $referer, '?' ) : '';
				$is_same_site  = $referer_clean && strpos( $referer_clean, home_url() ) === 0;
				$is_self       = $is_same_site && $current_post_id && strpos( $referer_clean, (string) $current_post_id ) !== false;
				$use_referer   = $is_same_site && ! $is_self;
				$back_link     = $use_referer ? $referer_clean . '#portal-post-' . $current_post_id : $space_link;

				// Resolve back link title from referer or space.
				$back_title = '';
				if ( $use_referer ) {
					$slug = basename( rtrim( $referer_clean, '/' ) );
					if ( $slug === 'feeds' ) {
						$back_title = __( 'Feeds', 'suredash' );
					} else {
						$ref_post   = suredash_get_referer_post();
						$back_title = ! empty( $ref_post['post_title'] ) ? $ref_post['post_title'] : '';
					}
				}
				if ( empty( $back_title ) ) {
					$back_title = $space_title;
				}
				?>
				<a href="<?php echo esc_url( $back_link ); ?>" class="portal-sub-item-link">
					<?php
						Helper::get_library_icon( 'ChevronLeft', true );

					if ( ! empty( $back_title ) ) {
						Labels::get_label( 'back_to_cpt', true );
						echo esc_html( $back_title );
					} else {
						Labels::get_label( 'back_to_portal', true );
					}
					?>
				</a>
				<?php
			} else {
				?>
				<h1 class="portal-item-title sd-font-lg sd-line-base sd-no-space">
					<?php echo do_shortcode( $atts['emoji'] ); ?>
					<?php echo wp_kses_post( $atts['title'] ); ?>
				</h1>
			<?php } ?>
			<?php if ( is_user_logged_in() ) { ?>
				<div class="portal-user-settings-wrap sd-flex sd-gap-4 sd-items-center">
					<?php echo do_shortcode( '[portal_menu]' ); ?>
					<?php echo do_shortcode( '[portal_notification]' ); ?>
				</div>
			<?php } ?>
		</div>
		<?php

		return ob_get_clean();
	}
}
