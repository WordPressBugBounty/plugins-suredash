<?php
/**
 * Plugin.
 *
 * @package SureDash
 * @since 0.0.1
 */

namespace SureDashboard\Inc\Compatibility;

defined( 'ABSPATH' ) || exit;

use SureDashboard\Inc\Traits\Get_Instance;
use SureDashboard\Inc\Utils\PostMeta;

/**
 * Have compatibility with active plugins and themes.
 *
 * @since 0.0.1
 */
class Plugin {
	use Get_Instance;

	/**
	 * Constructor
	 *
	 * @since 0.0.1
	 */
	public function __construct() {
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_page_assets' ] );
		add_action( 'suredash_after_plugin_activation', [ $this, 'prevent_other_plugin_redirection' ], 10, 2 );
		add_action( 'wp', [ $this, 'hide_surecart_icon_on_portal' ], 5 );

		$this->init_breakdance_compatibility();
	}

	/**
	 * Hide SureCart cart icon on portal pages.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function hide_surecart_icon_on_portal(): void {

		if ( ! suredash_frontend() || ! class_exists( '\SureCart' ) ) {
			return;
		}

		// Remove the template_include filter that adds the cart to wp_footer or use 'sc_cart_disabled' filter to disable it.
		if ( is_callable( '\SureCart::cart' ) ) {
			$cart_service = \SureCart::cart();
			remove_action( 'template_include', [ $cart_service, 'includeCartTemplate' ] );
		}
	}

	/**
	 * Prevent other plugin redirection.
	 *
	 * @param string $plugin_init Plugin init.
	 * @param string $plugin_slug Plugin slug.
	 *
	 * @since 1.0.0
	 */
	public function prevent_other_plugin_redirection( $plugin_init, $plugin_slug ): void {

		switch ( $plugin_init ) {
			case 'suretriggers/suretriggers.php':
				delete_transient( 'st-redirect-after-activation' );
				break;
			default:
				break;
		}

		// Tracking BSF Analytics UTM.
		if ( class_exists( 'BSF_UTM_Analytics\Inc\Utils' ) && is_callable( '\BSF_UTM_Analytics\Inc\Utils::update_referer' ) ) {
			\BSF_UTM_Analytics\Inc\Utils::update_referer( 'suredash', $plugin_slug );
		}
	}

	/**
	 * Get WP Content assets.
	 *
	 * @since 1.0.0
	 */
	public function enqueue_page_assets(): void {
		$post_id = suredash_get_post_id();

		if ( ! $post_id ) {
			return;
		}

		$content_type = PostMeta::get_post_meta_value( (int) $post_id, 'integration' );
		if ( $content_type !== 'single_post' ) {
			return;
		}

		$remote_post_data = PostMeta::get_post_meta_value( (int) $post_id, 'wp_post' );
		$remote_post_id   = absint( is_array( $remote_post_data ) && ! empty( $remote_post_data['value'] ) ? $remote_post_data['value'] : 0 );

		if ( ! $remote_post_id ) {
			return;
		}

		if ( ! method_exists( PageBuilder::get_instance(), 'enqueue_page_assets' ) ) {
			return;
		}

		PageBuilder::get_instance()->enqueue_page_assets( $remote_post_id );
	}

	/**
	 * Render PrestoPlayer block.
	 *
	 * @param array<string, mixed> $block Block data.
	 * @return string HTML content.
	 * @since 1.0.0
	 */
	public static function render_presto_player_block( $block ) {
		$html = '';

		if ( empty( $block['blockName'] ) ) {
			return $html;
		}

		if ( $block['blockName'] !== 'presto-player/playlist' ) {
			$media_id = $block['attrs']['id'] ?? 0;
			$media_id = absint( $media_id );
			$html    .= do_shortcode( '[presto_player id="' . $media_id . '"]' );
		} else {
			$html .= render_block( $block );
		}

		return $html;
	}

	/**
	 * Prevent Breakdance from processing SureDash portal routes.
	 *
	 * @param string $template Current template.
	 * @return string Template path.
	 * @since 1.3.2
	 */
	public function breakdance_template_compatibility( $template ) {
		if ( $this->is_suredash_portal_request() ) {
			// Remove Breakdance's template_include filter for portal routes.
			remove_filter( 'template_include', 'Breakdance\ActionsFilters\template_include', 1000000 );
		}
		return $template;
	}

	/**
	 * Initialize Breakdance compatibility if Breakdance is active.
	 *
	 * @since 1.3.2
	 */
	private function init_breakdance_compatibility(): void {
		if ( class_exists( '\Breakdance\ActionsFilters\template_include' ) || defined( '__BREAKDANCE_DIR__' ) ) {
			add_filter( 'template_include', [ $this, 'breakdance_template_compatibility' ], 1 );
		}
	}

	/**
	 * Check if current request is for SureDash portal.
	 *
	 * @return bool True if this is a portal request, false otherwise.
	 * @since 1.3.2
	 */
	private function is_suredash_portal_request() {
		global $wp;

		$portal_slug  = defined( 'SUREDASHBOARD_SLUG' ) ? SUREDASHBOARD_SLUG : 'portal';
		$current_path = trim( $wp->request, '/' );

		// Check if URL starts with portal slug.
		$is_portal_url = strpos( $current_path, $portal_slug ) === 0;

		// But exclude auth pages - they need Breakdance's template system when theme is disabled.
		if ( $is_portal_url && function_exists( 'suredash_is_auth_page' ) && suredash_is_auth_page() ) {
			return false;
		}

		return $is_portal_url;
	}
}
