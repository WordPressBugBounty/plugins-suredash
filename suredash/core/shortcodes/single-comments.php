<?php
/**
 * Portals Single Comments Shortcode Initialize.
 *
 * @package SureDash
 */

namespace SureDashboard\Core\Shortcodes;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use SureDashboard\Inc\Traits\Get_Instance;
use SureDashboard\Inc\Traits\Shortcode;
use SureDashboard\Inc\Utils\PostMeta;

/**
 * Class SingleComments Shortcode.
 */
class SingleComments {
	use Shortcode;
	use Get_Instance;

	/**
	 * Post ID for Comments Form post
	 *
	 * @var int
	 * @since 0.0.1
	 */
	protected $post_id;

	/**
	 * Register_shortcode_event.
	 *
	 * @return void
	 */
	public function register_shortcode_event(): void {
		$this->add_shortcode( 'single_comments' );
	}

	/**
	 * Display Single Post Comments.
	 *
	 * @param array<mixed> $atts Array of attributes.
	 * @return mixed
	 * @since 1.0.0
	 */
	public function render_single_comments( $atts ) {
		if ( ! is_user_logged_in() ) {
			return;
		}

		$item_id = get_the_ID();

		if ( post_password_required() || apply_filters( 'suredashboard_restrict_item_comments', false, $item_id ) === true ) {
			return;
		}

		// Other WP-Contents compatibilities.
		if ( suredash_is_on_astra_theme() ) {
			add_filter( 'astra_get_option_enable-comments-area', '__return_false' );
		}

		$defaults = [
			'item_id'  => $item_id,
			'comments' => PostMeta::get_post_meta_value( absint( $item_id ), 'comments' ),
			'echo'     => false,
		];

		$atts = shortcode_atts( $defaults, $atts );

		if ( ! absint( $atts['item_id'] ) ) {
			return;
		}

		return $this->get_single_comments_content( $atts );
	}

	/**
	 * Get Single Comments Content.
	 *
	 * @param array<mixed> $atts Array of attributes.
	 * @return string
	 * @since 1.0.0
	 */
	public function get_single_comments_content( $atts ) {
		$this->post_id = absint( $atts['item_id'] );
		$echo          = $atts['echo'];
		$in_qv         = boolval( $atts['in_qv'] ?? false );
		$comments      = boolval( $atts['comments'] ?? true );

		ob_start();

		do_action( 'suredashboard_before_single_comments_load', $this->post_id );

			suredash_get_template_part(
				'comments',
				'',
				[
					'post_id'  => $this->post_id,
					'in_qv'    => $in_qv,
					'comments' => $comments,
				]
			);

		do_action( 'suredashboard_after_single_comments_load', $this->post_id );

		$content = apply_filters( 'suredashboard_single_view_comments', ob_get_clean(), $this->post_id );

		if ( $echo ) {
			echo do_shortcode( $content );
			return '';
		}

		return $content;
	}
}
