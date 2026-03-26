<?php
/**
 * Portals Single Content Shortcode Initialize.
 *
 * @package SureDash
 */

namespace SureDashboard\Core\Shortcodes;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use SureDashboard\Inc\Traits\Get_Instance;
use SureDashboard\Inc\Traits\Shortcode;

/**
 * Class SingleEndpointContent Shortcode.
 */
class SingleEndpointContent {
	use Shortcode;
	use Get_Instance;

	/**
	 * Register_shortcode_event.
	 *
	 * @return void
	 */
	public function register_shortcode_event(): void {
		$this->add_shortcode( 'single_endpoint_content' );
	}

	/**
	 * Display Single Post Content.
	 *
	 * @param array<mixed> $atts Array of attributes.
	 * @since 1.0.0
	 *
	 * @return string|null
	 */
	public function render_single_endpoint_content( $atts ) {
		$defaults = [
			'endpoint'         => '',
			'get_only_header'  => 'false',
			'get_only_content' => 'false',
		];

		$atts = shortcode_atts( $defaults, $atts );

		if ( empty( $atts['endpoint'] ) ) {
			return null;
		}

		$endpoint = $atts['endpoint'];

		ob_start();

		$this->process_single_endpoint_content( $endpoint, $atts );

		$content = ob_get_clean();

		return apply_filters( 'suredashboard_single_view_content', $content );
	}

	/**
	 * Process Single Endpoint Content.
	 *
	 * @param string       $endpoint      Endpoint.
	 * @param array<mixed> $atts Array of attributes.
	 * @since 1.0.0
	 * @return void
	 */
	public function process_single_endpoint_content( $endpoint, $atts ): void {
		$content_id = (int) get_the_ID();

		if ( $endpoint === 'resource' ) {
			$base_id = absint( sd_get_post_meta( $content_id, 'space_id', true ) );
		} elseif ( $endpoint === 'event' ) {
			$base_id = absint( sd_get_post_meta( $content_id, 'space_id', true ) );
		} else {
			$base_id = absint( sd_get_post_meta( $content_id, 'belong_to_course', true ) );
		}

		$endpoint_data = suredash_endpoint_data( $endpoint, absint( $content_id ), $base_id );

		if ( empty( $endpoint_data ) ) {
			return;
		}

		switch ( $endpoint ) {
			case 'lesson':
				suredash_lesson_view_content( $endpoint, $endpoint_data, $atts );
				break;
			case 'resource':
				suredash_resource_view_content( $endpoint, $endpoint_data, $atts );
				break;
			case 'event':
				suredash_event_view_content( $endpoint, $endpoint_data, $atts );
				break;
			default:
				break;
		}
	}
}
