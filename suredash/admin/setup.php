<?php
/**
 * Portal Admin Setup
 *
 * This class will holds the code related to the admin area modification.
 *
 * @package SureDash
 *
 * @since 1.0.0
 */

namespace SureDashboard\Admin;

use SureDashboard\Inc\Templator\Utility as TemplatorUtility;
use SureDashboard\Inc\Traits\Get_Instance;
use SureDashboard\Inc\Utils\Helper;

defined( 'ABSPATH' ) || exit;

/**
 * Setup
 *
 * @since 1.0.0
 */
class Setup {
	use Get_Instance;

	/**
	 * Constructor
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function __construct() {
		$this->initialize_hooks();
	}

	/**
	 * Function to load the admin area actions.
	 *
	 * @since 1.0.0
	 */
	public function initialize_hooks(): void {
		add_filter( 'plugin_action_links_' . SUREDASHBOARD_BASE, [ $this, 'add_action_links' ] );
		add_action( 'admin_bar_menu', [ $this, 'dashboard_toolbar_menu' ], 32 );
		add_filter( 'display_post_states', [ $this, 'show_custom_post_statuses' ] );
		add_filter( 'wp_dropdown_pages', [ $this, 'exclude_portal_pages_from_homepage_dropdown' ], 15, 3 );
		/** Reverted Default template set to Portal layout
		 * add_action( 'wp_insert_post', [ $this, 'set_default_portal_template' ], 10, 3 );
		 */
	}

	/**
	 * Add Settings Link in Plugin page.
	 *
	 * @param array<int, string> $links - Array of links.
	 *
	 * @return array<int, string>
	 */
	public function add_action_links( array $links ): array {
		$url = admin_url() . 'admin.php?page=portal';
		return array_merge(
			[
				'<a href="' . esc_url( $url ) . '">' . __( 'Settings', 'suredash' ) . '</a>',
			],
			$links
		);
	}

	/**
	 * Function to show custom post statuses.
	 *
	 * @since 1.0.0
	 *
	 * @param array<int, string> $post_states Array of post states.
	 *
	 * @return array<int|string, string>
	 */
	public function show_custom_post_statuses( array $post_states ): array {
		global $post;

		if ( ! is_object( $post ) || ! isset( $post->post_content ) ) {
			return $post_states;
		}

		$login_page    = Helper::get_option( 'login_page' );
		$login_page_id = is_array( $login_page ) && ! empty( $login_page['value'] ) ? absint( $login_page['value'] ) : 0;

		$register_page    = Helper::get_option( 'register_page' );
		$register_page_id = is_array( $register_page ) && ! empty( $register_page['value'] ) ? absint( $register_page['value'] ) : 0;

		if ( ! isset( $post->ID ) ) {
			return $post_states;
		}

		// Check if the current post is the login page.
		if ( $post->ID === $login_page_id ) {
			$post_states['portal_login'] = __( 'Portal Login', 'suredash' );
		}

		// Check if the current post is the register page.
		if ( $post->ID === $register_page_id ) {
			$post_states['portal_register'] = __( 'Portal Register', 'suredash' );
		}

		return $post_states;
	}

	/**
	 * Add Docs link in admin bar.
	 *
	 * @param  object $admin_bar WP Admin Bar.
	 *
	 * @since 1.0.0
	 */
	public function dashboard_toolbar_menu( object $admin_bar ): void {
		if ( ! is_admin() || ! is_admin_bar_showing() ) {
			return;
		}

		// Showcase for site user or super admin.
		if ( ! is_user_member_of_blog() && ! is_super_admin() ) {
			return;
		}

		if ( ! is_a( $admin_bar, 'WP_Admin_Bar' ) ) {
			return;
		}

		$admin_bar->add_node(
			[
				'parent' => 'site-name',
				'id'     => 'view-portal',
				'title'  => __( 'Visit Portal', 'suredash' ),
				'href'   => '/' . suredash_get_community_slug() . '/',
			]
		);
	}

	/**
	 * Exclude Portal Login and Register pages from homepage dropdown.
	 *
	 * @since 1.3.0
	 *
	 * @param string                $output HTML output of the dropdown.
	 * @param array<string, string> $parsed_args Array of arguments.
	 * @param array<string, string> $_pages Array of pages (unused).
	 *
	 * @return string Modified HTML output.
	 */
	public function exclude_portal_pages_from_homepage_dropdown( $output, $parsed_args, $_pages ) {
		// Only filter on the Reading Settings page for homepage dropdown.
		if ( ! isset( $parsed_args['name'] ) || ( $parsed_args['name'] !== 'page_on_front' && $parsed_args['name'] !== 'page_for_posts' ) ) {
			return $output;
		}

		// Get portal page IDs.
		$login_page    = Helper::get_option( 'login_page' );
		$login_page_id = is_array( $login_page ) && ! empty( $login_page['value'] ) ? absint( $login_page['value'] ) : 0;

		$register_page    = Helper::get_option( 'register_page' );
		$register_page_id = is_array( $register_page ) && ! empty( $register_page['value'] ) ? absint( $register_page['value'] ) : 0;

		// Remove the options from the dropdown HTML.
		if ( $login_page_id ) {
			$pattern = '/<option[^>]*value=["\']' . $login_page_id . '["\'][^>]*>.*?<\/option>/i';
			$output  = (string) preg_replace_callback(
				$pattern,
				static function( $matches ) {
					return '';
				},
				$output
			);
		}

		if ( $register_page_id ) {
			$pattern = '/<option[^>]*value=["\']' . $register_page_id . '["\'][^>]*>.*?<\/option>/i';
			$output  = (string) preg_replace_callback(
				$pattern,
				static function( $matches ) {
					return '';
				},
				$output
			);
		}

		return $output;
	}

	/**
	 * Set default Portal Layout template for new posts and pages.
	 *
	 * @since 1.6.0
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 * @param bool     $update  Whether this is an existing post being updated.
	 *
	 * @return void
	 */
	public function set_default_portal_template( int $post_id, \WP_Post $post, bool $update ): void {
		// Only apply to new posts/pages (not updates).
		if ( $update ) {
			return;
		}

		// Skip auto-drafts and revisions.
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		// Get eligible post types for portal-container template.
		$eligible_post_types = TemplatorUtility::get_portal_container_post_types();

		// Only apply to eligible post types.
		if ( ! in_array( $post->post_type, $eligible_post_types, true ) ) {
			return;
		}

		// Check if template is already set.
		$existing_template = get_post_meta( $post_id, '_wp_page_template', true );
		if ( ! empty( $existing_template ) && $existing_template !== 'default' ) {
			return;
		}

		// Set the Portal Layout template based on theme type.
		if ( function_exists( 'wp_is_block_theme' ) && wp_is_block_theme() ) {
			// For block themes, store just the template slug.
			update_post_meta( $post_id, '_wp_page_template', 'portal-container' );
		} else {
			// For classic themes, store the full file path.
			update_post_meta( $post_id, '_wp_page_template', 'templates/pages/template-portal-container.php' );
		}
	}
}
