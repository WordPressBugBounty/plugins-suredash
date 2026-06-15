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
		$this->init_etch_compatibility();
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
			case 'presto-player/presto-player.php':
				delete_transient( 'presto_player_activation_redirect' );
				break;
			case 'suremembers-core/suremembers-core.php':
				// SureMembers gates its onboarding redirect on an option rather than a transient.
				update_option( '__suremembers_do_redirect', false );
				update_option( 'suremembers_onboarding_skipped', 'yes' );
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
	 * Strip Etch styles, scripts, and inline output on portal pages.
	 *
	 * Etch loads its CSS through three different paths — inline `<style>`
	 * blocks in `wp_head`, enqueued stylesheets/scripts via `wp_enqueue_scripts`,
	 * and Vite app divs in `wp_footer`. We unhook every Etch-namespaced
	 * callback on those tags and dequeue any leftover handles as a safety net.
	 *
	 * Limitation: callbacks registered as closures cannot be identified by
	 * namespace and will not be removed by this routine.
	 *
	 * Pass `false` to the `suredash_dequeue_etch_on_portal` filter to opt out.
	 *
	 * @since 1.8.3
	 * @return void
	 */
	public function dequeue_etch_on_portal(): void {
		if ( ! suredash_frontend() ) {
			return;
		}

		/**
		 * Allow integrations to opt out of stripping Etch's frontend output on
		 * portal pages.
		 *
		 * @since 1.8.3
		 *
		 * @param bool $enabled Whether to strip Etch assets on portal pages.
		 */
		if ( ! apply_filters( 'suredash_dequeue_etch_on_portal', true ) ) {
			return;
		}

		foreach ( [ 'wp_head', 'wp_footer', 'wp_enqueue_scripts', 'wp_print_styles', 'enqueue_block_assets' ] as $tag ) {
			$this->remove_hooks_by_namespace( $tag, 'Etch\\' );
		}

		add_action( 'wp_enqueue_scripts', [ $this, 'dequeue_etch_handles' ], 999 );
		add_action( 'wp_print_styles', [ $this, 'dequeue_etch_handles' ], 999 );
	}

	/**
	 * Dequeue any registered styles/scripts whose handle starts with an Etch
	 * prefix. Acts as a safety net in case Etch handles are pulled in via
	 * dependency chains rather than direct enqueue calls.
	 *
	 * @since 1.8.3
	 * @return void
	 */
	public function dequeue_etch_handles(): void {
		global $wp_styles, $wp_scripts;

		$prefixes = [ 'etch-', 'svelte-app-' ];

		if ( $wp_styles instanceof \WP_Styles && is_array( $wp_styles->registered ) ) {
			foreach ( array_keys( $wp_styles->registered ) as $handle ) {
				$handle = (string) $handle;
				foreach ( $prefixes as $prefix ) {
					if ( strpos( $handle, $prefix ) === 0 ) {
						wp_dequeue_style( $handle );
						break;
					}
				}
			}
		}

		if ( $wp_scripts instanceof \WP_Scripts && is_array( $wp_scripts->registered ) ) {
			foreach ( array_keys( $wp_scripts->registered ) as $handle ) {
				$handle = (string) $handle;
				foreach ( $prefixes as $prefix ) {
					if ( strpos( $handle, $prefix ) === 0 ) {
						wp_dequeue_script( $handle );
						break;
					}
				}
			}
		}
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

		// Quick-view iframes load the post's permalink with `?simply_content=1`.
		// That URL usually lives outside the portal slug (e.g. /community-post/foo/),
		// so the slug check below would miss it. Renderer::update_templates() handles
		// the entire response for these requests — we must keep Breakdance's
		// template_include from clobbering it, otherwise both templates render
		// inside the iframe (see the "Disable Theme" mode failure).
		if ( function_exists( 'suredash_simply_content' ) && suredash_simply_content() ) {
			return true;
		}

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

	/**
	 * Initialize Etch page builder compatibility if Etch is active.
	 *
	 * Etch ships its own CSS reset and defaults that load on every front-end
	 * page, which conflict with portal styles. We hook into template_redirect
	 * so we can detect a portal page and strip Etch's assets before they reach
	 * the browser.
	 *
	 * @since 1.8.3
	 * @return void
	 */
	private function init_etch_compatibility(): void {
		if ( ! class_exists( '\Etch\Plugin' ) ) {
			return;
		}
		add_action( 'template_redirect', [ $this, 'dequeue_etch_on_portal' ] );
	}

	/**
	 * Walk `$wp_filter` for a tag and unhook every callback whose class lives
	 * under the given namespace prefix. Closures are skipped — they don't
	 * carry a class identifier and could belong to anyone.
	 *
	 * @param string $tag              Hook name.
	 * @param string $namespace_prefix Class namespace prefix to match (e.g. `Etch\\`).
	 *
	 * @since 1.8.3
	 * @return void
	 */
	private function remove_hooks_by_namespace( string $tag, string $namespace_prefix ): void {
		global $wp_filter;

		if ( ! isset( $wp_filter[ $tag ] ) || ! is_object( $wp_filter[ $tag ] ) || ! property_exists( $wp_filter[ $tag ], 'callbacks' ) ) {
			return;
		}

		$callbacks = $wp_filter[ $tag ]->callbacks;
		if ( ! is_array( $callbacks ) ) {
			return;
		}

		foreach ( $callbacks as $priority => $hooks ) {
			if ( ! is_array( $hooks ) ) {
				continue;
			}
			foreach ( $hooks as $hook_data ) {
				if ( ! is_array( $hook_data ) || ! isset( $hook_data['function'] ) ) {
					continue;
				}
				$function = $hook_data['function'];
				if ( ! is_array( $function ) || ! isset( $function[0] ) ) {
					continue;
				}
				$class_name = is_object( $function[0] ) ? get_class( $function[0] ) : (string) $function[0];
				if ( strncmp( $class_name, $namespace_prefix, strlen( $namespace_prefix ) ) === 0 ) {
					remove_action( $tag, $function, (int) $priority );
				}
			}
		}
	}
}
