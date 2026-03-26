<?php
/**
 * Portals WpPost Initialize.
 *
 * @package SureDash
 */

namespace SureDashboard\Inc\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use SureDashboard\Inc\Compatibility\PageBuilder;

/**
 * Class WpPost.
 */
class WpPost {
	/**
	 * Remote post to load.
	 *
	 * @var mixed
	 */
	private $post_id = '';

	/**
	 * Constructor.
	 *
	 * @param int $post_id Post ID.
	 */
	public function __construct( $post_id ) {
		$this->post_id = $post_id;
	}

	/**
	 * Display WP Post content.
	 *
	 * @since 1.0.0
	 * @return mixed HTML content.
	 */
	public function render_content() {
		$remote_post = $this->post_id;

		if ( ! $remote_post ) {
			return;
		}

		ob_start();

		if ( method_exists( PageBuilder::get_instance(), 'get_page_content' ) ) {
			ob_start();
			PageBuilder::get_instance()->get_page_content( $remote_post );
			$content = ob_get_clean();

			if ( $content !== null && $content !== '' ) {
				echo do_shortcode( apply_filters( 'the_content', $content ) );
			}
		}

		return ob_get_clean();
	}

	/**
	 * Enqueue assets.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function enqueue_assets(): void {
		$remote_post = $this->post_id;

		if ( ! $remote_post ) {
			return;
		}

		if ( ! method_exists( PageBuilder::get_instance(), 'enqueue_page_assets' ) ) {
			return;
		}

		PageBuilder::get_instance()->enqueue_page_assets( $remote_post );
	}
}
