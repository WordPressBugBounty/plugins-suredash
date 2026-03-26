<?php
/**
 * SinglePost Integration.
 *
 * @package SureDash
 */

namespace SureDashboard\Core\Integrations;

use SureDashboard\Inc\Traits\Get_Instance;
use SureDashboard\Inc\Utils\PostMeta;
use SureDashboard\Inc\Utils\WpPost;

defined( 'ABSPATH' ) || exit;

/**
 * SinglePost Integration.
 *
 * @since 1.0.0
 */
class SinglePost extends Base {
	use Get_Instance;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->name        = 'SinglePost';
		$this->slug        = 'single-post';
		$this->description = __( 'SinglePost Integration', 'suredash' );
		$this->is_active   = true;

		parent::__construct( $this->name, $this->slug, $this->description, $this->is_active );

		if ( ! $this->is_active ) {
			return;
		}
	}

	/**
	 * Render content for the single post.
	 *
	 * @param int $post_id Remote post ID.
	 * @return string
	 * @since 1.0.2
	 */
	public function render_content( $post_id ) {
		/* Later check if this can be move on wp_enqueue_scripts hook. */
		$post_content = get_post_field( 'post_content', $post_id );
		if ( class_exists( 'UAGB_Post_Assets' ) && is_string( $post_content ) && strpos( $post_content, '<!-- wp:uagb/' ) !== false ) {
			$post_assets_instance = new \UAGB_Post_Assets( $post_id );
			$post_assets_instance->enqueue_scripts();
		}

		ob_start();

		$entry_content_classes = apply_filters( 'suredash_single_post_content_classes', suredash_is_post_by_block_editor( $post_id ) ? 'sd-overflow-hidden suredash-single-content portal-space-post-content' : 'sd-overflow-hidden suredash-single-content' );
		echo '<div class="' . esc_attr( $entry_content_classes ) . '">';

		do_action( 'suredashboard_before_wp_single_content_load', $post_id );

		$remote_wp_post = new WpPost( $post_id );
		$remote_wp_post->enqueue_assets();
		echo do_shortcode( apply_filters( 'the_content', $remote_wp_post->render_content() ) );

		do_action( 'suredashboard_after_wp_single_content_load', $post_id );

		echo '</div>';

		return strval( ob_get_clean() );
	}

	/**
	 * Get item single content.
	 *
	 * @param int  $post_id Post ID.
	 * @param bool $use_passed_post_id Use passed post ID.
	 * @return string|false
	 */
	public function get_integration_content( $post_id, $use_passed_post_id = false ) {
		if ( ! $this->is_active ) {
			return '';
		}

		if ( $use_passed_post_id ) {
			$remote_post_id = $post_id;
		} else {
			$render_type = PostMeta::get_post_meta_value( $post_id, 'post_render_type' );
			if ( $render_type === 'wordpress' ) {
				$remote_post_data = PostMeta::get_post_meta_value( $post_id, 'wp_post' );
				$remote_post_id   = absint( is_array( $remote_post_data ) && ! empty( $remote_post_data['value'] ) ? $remote_post_data['value'] : 0 );
			} else {
				$remote_post_id = $post_id;
			}
		}

		if ( $remote_post_id ) {
			return $this->render_content( $remote_post_id );
		}

		return '';
	}
}
