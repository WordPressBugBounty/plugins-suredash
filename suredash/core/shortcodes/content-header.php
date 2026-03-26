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
				$is_referer = ! empty( $_SERVER['HTTP_REFERER'] ) ? true : false;
				$back_link  = $is_referer ? esc_url_raw( $_SERVER['HTTP_REFERER'] ) : home_url( '/' . suredash_get_community_slug() . '/' );
				?>
				<a href="<?php echo esc_url( $back_link ); ?>" class="portal-sub-item-link">
					<?php
						Helper::get_library_icon( 'ChevronLeft', true );
						$ref_post = suredash_get_referer_post();

					if ( $is_referer && ! empty( $ref_post['post_title'] ) ) {
						Labels::get_label( 'back_to_cpt', true );
						echo esc_html( $ref_post['post_title'] );
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
