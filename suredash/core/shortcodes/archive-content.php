<?php
/**
 * Portals Archive Content Shortcode Initialize.
 *
 * @package SureDash
 */

namespace SureDashboard\Core\Shortcodes;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use SureDashboard\Core\Integrations\Feeds;
use SureDashboard\Inc\Traits\Get_Instance;
use SureDashboard\Inc\Traits\Shortcode;

/**
 * Class ArchiveContent Shortcode.
 */
class ArchiveContent {
	use Shortcode;
	use Get_Instance;

	/**
	 * Register_shortcode_event.
	 *
	 * @return void
	 */
	public function register_shortcode_event(): void {
		$this->add_shortcode( 'archive_content' );
	}

	/**
	 * Load integration type wise content.
	 *
	 * @since 1.0.0
	 */
	public function render_archive_content_markup(): void {
		remove_filter( 'the_content', 'wpautop' );

		echo '<div class="portal-content-type-blog">';
		if ( is_tax( SUREDASHBOARD_FEED_TAXONOMY ) || is_post_type_archive( SUREDASHBOARD_FEED_POST_TYPE ) || is_post_type_archive( SUREDASHBOARD_SUB_CONTENT_POST_TYPE ) ) {
			if ( method_exists( Feeds::get_instance(), 'get_archive_content' ) ) {
				echo do_shortcode( apply_filters( 'the_content', Feeds::get_instance()->get_archive_content() ) );
			}
		} else {
			esc_html_e( 'No content found.', 'suredash' );
		}

		echo '</div>'; // End of portal-content-area.

		add_filter( 'the_content', 'wpautop' );
	}

	/**
	 * Display Archive Post Content.
	 *
	 * @param array<mixed> $atts Array of attributes.
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function render_archive_content( $atts ) {
		$atts = apply_filters(
			'suredash_archive_content_attributes',
			shortcode_atts(
				[
					'skip_header' => false,
					'title'       => get_the_archive_title(),
				],
				$atts
			)
		);

		ob_start();

		if ( ! boolval( $atts['skip_header'] ) ) {
			echo do_shortcode( '[portal_content_header title="' . esc_attr( wp_strip_all_tags( $atts['title'] ) ) . '"]' );
		}

		do_action( 'suredashboard_before_archive_content_load' );

		$this->render_archive_content_markup();

		$content = apply_filters( 'suredashboard_single_view_content', ob_get_clean() );

		do_action( 'suredashboard_after_archive_content_load' );

		return $content;
	}
}
