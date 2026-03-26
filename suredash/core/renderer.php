<?php
/**
 * Frontend Renderer.
 *
 * @package SureDash
 * @since 1.0.0
 */

namespace SureDashboard\Core;

use SureDashboard\Inc\Traits\Get_Instance;
use SureDashboard\Inc\Utils\Activity_Tracker;
use SureDashboard\Inc\Utils\Helper;
use SureDashboard\Inc\Utils\Labels;
use SureDashboard\Inc\Utils\PostMeta;

defined( 'ABSPATH' ) || exit;

/**
 * Frontend Compatibility
 *
 * @package SureDash
 */

/**
 * Renderer setup
 *
 * @since 1.0.0
 */
class Renderer {
	use Get_Instance;

	/**
	 *  Constructor
	 */
	public function __construct() {
		add_action( 'suredash_dequeue_assets', [ $this, 'suredash_dequeue_assets' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'wp_styles_for_portal' ], 999999 ); // must be greater than 999.
		add_action( 'wp_print_styles', [ $this, 'wp_styles_for_portal' ], 101 ); // Bricks compatibility: must be greater than 100.

		add_action( 'suredash_enqueue_scripts', [ $this, 'suredash_enqueue_scripts' ] );
		add_filter( 'suredash_page_heading', [ $this, 'update_queried_heading' ] );
		add_filter( 'suredash_title_block_set', [ $this, 'update_title_block_set' ] );
		add_filter( 'pre_get_document_title', [ $this, 'update_document_title_parts' ], 99 );

		add_action( 'wp', [ $this, 'update_recently_viewed_items' ] );
		add_action( 'wp', [ $this, 'track_space_visit' ] );
		add_action( 'template_redirect', [ $this, 'handle_portal_redirection' ], 9 );
		add_action( 'template_redirect', [ $this, 'redirect_to_login' ] );
		add_filter( 'template_include', [ $this, 'update_templates' ], 999 );
		add_filter( 'body_class', [ $this, 'add_body_class' ] );

		// Hide admin bar as it is not required.
		add_filter( 'show_admin_bar', [ $this, 'adjust_admin_bar' ] );

		// Update hardcoded static things with dynamic within content.
		add_filter( 'the_content', 'suredash_dynamic_content_support' );

		// Add the shortcut for SureDash admin dashboard.
		add_action( 'admin_bar_menu', [ $this, 'add_custom_admin_bar_items' ], 100 );
		add_action( 'wp_head', [ $this, 'admin_bar_styles' ] );

		// Modify edit post link in admin bar to include extra params.
		add_action( 'admin_bar_menu', [ $this, 'modify_edit_post_link' ], 999 );

		// Disable WordPress emoji conversion to show native emojis.
		add_action( 'wp', [ $this, 'disable_wp_emoji_conversion' ] );
	}

	/**
	 * Disable WordPress emoji conversion.
	 * This allows native emojis to be displayed without being converted to SVG images.
	 *
	 * @since 1.6.0
	 * @return void
	 */
	public function disable_wp_emoji_conversion(): void {

		if ( ! suredash_frontend() ) {
			return;
		}

		// Remove emoji-related hooks.
		remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
	}

	/**
	 * Enqueue WordPress Assets.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function wp_styles_for_portal(): void {
		if ( is_admin() ) {
			return;
		}

		if ( ! suredash_frontend() ) {
			return;
		}

		// WordPress Assets.
		wp_enqueue_style( 'global-styles' );
		wp_enqueue_style( 'wp-block-library' );
		wp_enqueue_style( 'wp-block-library-theme' );
		wp_enqueue_style( 'classic-theme-styles' );

		do_action( 'suredash_dequeue_assets' );
	}

	/**
	 * Dequeue external conflicting assets.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function suredash_dequeue_assets(): void {
		if ( is_admin() ) {
			return;
		}

		if ( ! suredash_frontend() ) {
			return;
		}

		if ( method_exists( Assets::get_instance(), 'dequeue_external_assets' ) ) {
			Assets::get_instance()->dequeue_external_assets();
		}
	}

	/**
	 * Enqueue SureDash Assets
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function suredash_enqueue_scripts(): void {
		if ( is_admin() || ! suredash_frontend() ) {
			return;
		}

		// Global necessary assets.
		if ( method_exists( Assets::get_instance(), 'enqueue_global_assets' ) ) {
			Assets::get_instance()->enqueue_global_assets();
		}

		if ( method_exists( Assets::get_instance(), 'enqueue_search_assets' ) ) {
			Assets::get_instance()->enqueue_search_assets();
		}

		// Type wise assets.
		if ( method_exists( Assets::get_instance(), 'enqueue_archive_group_assets' ) ) {
			Assets::get_instance()->enqueue_archive_group_assets();
		}

		if ( method_exists( Assets::get_instance(), 'enqueue_single_item_assets' ) ) {
			Assets::get_instance()->enqueue_single_item_assets();
		}
	}

	/**
	 * Adjust admin bar.
	 *
	 * @param bool $status admin bar status.
	 *
	 * @since 1.0.0
	 * @return bool
	 */
	public function adjust_admin_bar( $status ) {
		if ( suredash_frontend() || suredash_simply_content() ) {
			if ( suredash_show_content_only() || suredash_simply_content() || is_customize_preview() ) {
				return false;
			}

			// Show admin bar only for administrators.
			if ( suredash_is_user_manager() ) {
				return true;
			}

			return false;
		}

		return $status;
	}

	/**
	 * Add shortcut to SureDash admin dashboard.
	 *
	 * @param \WP_Admin_Bar $wp_admin_bar WP Admin Bar object.
	 *
	 * @since 1.0.0
	 */
	public function add_custom_admin_bar_items( $wp_admin_bar ): void {
		if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$post_id     = get_queried_object_id();
		$admin_url   = false;
		$group_terms = wp_get_post_terms( $post_id, SUREDASHBOARD_TAXONOMY );

		if ( ! empty( $group_terms ) && ! is_wp_error( $group_terms ) ) {
			$admin_url = admin_url( 'admin.php?page=' . SUREDASHBOARD_SLUG . '&tab=spaces&section=space&group=' . absint( $group_terms[0]->term_id ) . '&space=' . absint( $post_id ) );
		}

		$logo_url = SUREDASHBOARD_URL . 'assets/icons/admin-icon.svg';

		if ( ! is_callable( [ $wp_admin_bar, 'add_node' ] ) ) {
			return;
		}

		$wp_admin_bar->add_node(
			[
				'id'    => 'suredash_admin_link',
				'title' => '<img src="' . esc_url( $logo_url ) . '" style="height:18px;" alt="SureDash" />',
				'href'  => '',
				'meta'  => [
					'title' => 'SureDash ' . __( 'Menu', 'suredash' ),
				],
			]
		);

		if ( $admin_url ) {
			$wp_admin_bar->add_node(
				[
					'id'     => 'suredash_current_space',
					'parent' => 'suredash_admin_link',
					'title'  => __( 'Edit Space', 'suredash' ),
					'href'   => esc_url( $admin_url ),
					'meta'   => [
						'title' => __( 'Go to Space Editor', 'suredash' ),
					],
				]
			);
		}
		$wp_admin_bar->add_node(
			[
				'id'     => 'suredash_dashboard',
				'parent' => 'suredash_admin_link',
				'title'  => __( 'Dashboard', 'suredash' ),
				'href'   => esc_url( admin_url( 'admin.php?page=' . SUREDASHBOARD_SLUG . '&tab=home' ) ),
				'meta'   => [
					'title' => __( 'Go to Dashboard Page', 'suredash' ),
				],
			]
		);

		$wp_admin_bar->add_node(
			[
				'id'     => 'suredash_spaces',
				'parent' => 'suredash_admin_link',
				'title'  => __( 'All Spaces', 'suredash' ),
				'href'   => esc_url( admin_url( 'admin.php?page=' . SUREDASHBOARD_SLUG . '&tab=spaces' ) ),
				'meta'   => [
					'title' => __( 'Go to All Spaces Page', 'suredash' ),
				],
			]
		);

		$wp_admin_bar->add_node(
			[
				'id'     => 'suredash_posts',
				'parent' => 'suredash_admin_link',
				'title'  => __( 'All Posts', 'suredash' ),
				'href'   => esc_url( admin_url( 'admin.php?page=' . SUREDASHBOARD_SLUG . '&tab=posts' ) ),
				'meta'   => [
					'title' => __( 'Go to All Posts Page', 'suredash' ),
				],
			]
		);

		$wp_admin_bar->add_node(
			[
				'id'     => 'suredash_users',
				'parent' => 'suredash_admin_link',
				'title'  => __( 'Users', 'suredash' ),
				'href'   => esc_url( admin_url( 'admin.php?page=' . SUREDASHBOARD_SLUG . '&tab=users' ) ),
				'meta'   => [
					'title' => __( 'Go to All Users Page', 'suredash' ),
				],
			]
		);

		$wp_admin_bar->add_node(
			[
				'id'     => 'suredash_notifications',
				'parent' => 'suredash_admin_link',
				'title'  => __( 'Notifications', 'suredash' ),
				'href'   => esc_url( admin_url( 'admin.php?page=' . SUREDASHBOARD_SLUG . '&tab=notifications' ) ),
				'meta'   => [
					'title' => __( 'Go to Notifications Page', 'suredash' ),
				],
			]
		);

		$wp_admin_bar->add_node(
			[
				'id'     => 'suredash_settings',
				'parent' => 'suredash_admin_link',
				'title'  => __( 'Settings', 'suredash' ),
				'href'   => esc_url( admin_url( 'admin.php?page=' . SUREDASHBOARD_SLUG . '&tab=settings&section=branding' ) ),
				'meta'   => [
					'title' => __( 'Go to Settings Page', 'suredash' ),
				],
			]
		);
	}

	/**
	 * Add menu styles.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function admin_bar_styles(): void {
		if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<style type="text/css" media="screen">
			#wp-admin-bar-suredash_admin_link .ab-item {
				display: flex !important;
				align-items: center;
			}
			#wp-admin-bar-suredash_admin_link ul li:first-child:after,
			#wp-admin-bar-suredash_admin_link ul li#wp-admin-bar-suredash_users:before,
			#wp-admin-bar-suredash_admin_link ul li:last-child:before {
				display: block;
				margin: 5px 0 5px;
				content: "";
				width: 100%;
			}
			#wp-admin-bar-suredash_admin_link ul li:first-child:after {
				border-bottom: 1px solid hsla(0, 0%, 100%, .2);
			}
			#wp-admin-bar-suredash_admin_link ul li:last-child:before,
			#wp-admin-bar-suredash_admin_link ul li#wp-admin-bar-suredash_users:before {
				border-top: 1px solid hsla(0, 0%, 100%, .2);
			}
		</style>
		<?php
	}

	/**
	 * Modify edit post link in admin bar to include extra parameters.
	 *
	 * @param \WP_Admin_Bar $wp_admin_bar WordPress admin bar object.
	 * @return void
	 * @since 1.4.0
	 */
	public function modify_edit_post_link( \WP_Admin_Bar $wp_admin_bar ): void {
		if ( ! is_user_logged_in() || ! current_user_can( 'edit_posts' ) ) {
			return;
		}

		// Only modify on single content posts.
		if ( ! is_singular( SUREDASHBOARD_SUB_CONTENT_POST_TYPE ) ) {
			return;
		}

		// Get the existing edit node.
		$edit_node = $wp_admin_bar->get_node( 'edit' );
		if ( ! $edit_node || ! is_object( $edit_node ) ) {
			return;
		}

		// Ensure the node has required properties.
		if ( ! property_exists( $edit_node, 'href' ) || ! property_exists( $edit_node, 'title' ) || ! property_exists( $edit_node, 'meta' ) ) {
			return;
		}

		// Determine the space_type based on content_type meta.
		$space_type = $this->get_current_space_type();

		// Build new URL with extra parameters.
		$edit_url = $edit_node->href;
		$edit_url = add_query_arg(
			[
				'context'    => $space_type,
				'space_type' => $space_type,
			],
			$edit_url
		);

		// Update the edit node with modified URL.
		$wp_admin_bar->add_node(
			[
				'id'    => 'edit',
				'title' => $edit_node->title,
				'href'  => $edit_url,
				'meta'  => $edit_node->meta,
			]
		);
	}

	/**
	 * Update Docs Views count.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function update_recently_viewed_items(): void {
		if ( ! ( is_singular( SUREDASHBOARD_POST_TYPE ) || is_singular( SUREDASHBOARD_FEED_POST_TYPE ) ) ) {
			return;
		}

		if ( ! empty( $_SERVER['HTTP_PURPOSE'] ) && $_SERVER['HTTP_PURPOSE'] === 'prefetch' ) {
			return;
		}

		global $post;
		$cookie_duration = get_option( 'portal-items-cookie-duration', 1 );

		$ids = isset( $_COOKIE['portal_recently_viewed'] ) ? explode( 'portal', sanitize_text_field( wp_unslash( $_COOKIE['portal_recently_viewed'] ) ) ) : [];

		$domain_path = ! empty( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : '';
		$domain_path = defined( 'COOKIE_DOMAIN' ) ? COOKIE_DOMAIN : $domain_path;

		if ( ! in_array( strval( $post->ID ), $ids, true ) ) {
			$ids[] = $post->ID;
			$ids   = implode( 'portal', $ids );

			if ( $cookie_duration !== '' ) {
				$item_cookie_time = 60 * 60 * 24 * $cookie_duration;
				setcookie( 'portal_recently_viewed', $ids, time() + $item_cookie_time, '/', $domain_path, suredash_site_is_https() && is_ssl(), true );
			}
		}
	}

	/**
	 * Redirect portal home to the appropriate destination based on settings.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function handle_portal_redirection(): void {
		// Skip redirection for SureMembers download URLs.
		if ( get_query_var( 'suremembers-download-id' ) || get_query_var( 'suremembers-download-token' ) ) {
			return;
		}

		if ( ! suredash_frontend() ) {
			return;
		}

		if ( suredash_show_content_only() || suredash_simply_content() ) {
			return;
		}

		if ( is_singular( SUREDASHBOARD_POST_TYPE ) ) {
			$post_id    = suredash_get_post_id();
			$space_type = sd_get_post_meta( $post_id, 'integration', true );
			if ( $space_type === 'link' ) {
				$link = PostMeta::get_post_meta_value( $post_id, 'link_url' );
				if ( ! empty( $link ) ) {
					wp_redirect( $link ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- Redirection can be a external location.
					exit;
				}

				wp_safe_redirect( home_url( '/' . suredash_get_community_slug() . '/' ) );
				exit;
			}
		}

		$home_page      = Helper::get_option( 'home_page', 'default' );
		$is_portal_home = suredash_is_home() || ( is_front_page() && Helper::get_option( 'portal_as_homepage' ) );

		if ( ! $is_portal_home ) {
			return;
		}

		switch ( $home_page ) {
			case 'feed_page':
				if ( Helper::get_option( 'enable_feeds' ) ) {
					$feed_url = suredash_get_feed_page_url();
					if ( $feed_url ) {
						wp_safe_redirect( $feed_url );
						exit;
					}
				}
				break;

			case 'first_space':
				$space_id = Helper::get_first_space_id();
				if ( $space_id ) {
					wp_safe_redirect( (string) get_permalink( $space_id ) );
					exit;
				}
				break;

			default:
				// No redirect needed for default or unrecognized values.
				break;
		}
	}

	/**
	 * Redirect to login page if not logged in.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function redirect_to_login(): void {
		if ( suredash_frontend() ) {
			$is_public_portal = Helper::get_option( 'hidden_community' );

			// Don't redirect if we're already on the login or register page to prevent redirect loops.
			if ( ! is_user_logged_in() && $is_public_portal && ! suredash_is_auth_page() ) {
				$current_url    = ( is_ssl() ? 'https://' : 'http://' ) . sanitize_text_field( $_SERVER['HTTP_HOST'] ?? '' ) . esc_url_raw( $_SERVER['REQUEST_URI'] ?? '' );
				$login_page_url = suredash_get_login_page_url();
				$redirect_url   = add_query_arg( 'redirect_to', urlencode( $current_url ), $login_page_url );

				wp_safe_redirect( $redirect_url );
				exit;
			}
		}
	}

	/**
	 * Update home page heading as per sub queried item.
	 *
	 * @param string $title Page title.
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function update_queried_heading( $title ) {
		if ( suredash_is_sub_queried_page() ) {
			$title = Labels::get_label( suredash_get_sub_queried_page() );
		}

		return $title;
	}

	/**
	 * Update document title parts for sub-queried pages.
	 *
	 * @param string $title Document title.
	 * @since 1.3.2
	 *
	 * @return string
	 */
	public function update_document_title_parts( $title ) {
		if ( ! suredash_frontend() ) {
			return $title;
		}

		$updated_title = '';

		if ( suredash_is_sub_queried_page() ) {
			$sub_page   = suredash_get_sub_queried_page();
			$page_title = Labels::get_label( $sub_page );

			if ( ! empty( $page_title ) ) {
				$updated_title = $page_title;
			} else {
				$updated_title = ucwords( str_replace( [ '-', '_' ], ' ', $sub_page ) );
			}
		}

		return $updated_title;
	}

	/**
	 * Update space title heading text & emoji as per type.
	 *
	 * @param array<string, string> $emoji_n_title Emoji and title.
	 * @since 0.0.6
	 *
	 * @return array<string, string>
	 */
	public function update_title_block_set( $emoji_n_title ) {
		global $post;
		$caught = false;

		switch ( true ) {
			case is_singular() && ! empty( $post->ID ):
				$caught  = true;
				$post_id = ! empty( $post->ID ) ? absint( $post->ID ) : 0;
				$emoji   = PostMeta::get_post_meta_value( $post_id, 'item_emoji' );
				$title   = get_the_title( $post_id );
				break;
			case suredash_is_sub_queried_page():
				$caught = true;
				$emoji  = '';
				$title  = Labels::get_label( suredash_get_sub_queried_page() );
				break;
			case get_queried_object():
				$caught = true;
				$emoji  = '';
				$title  = single_term_title( '', false );
				break;
		}

		if ( $caught ) {
			return [
				'emoji' => $emoji,
				'title' => $title,
			];
		}

		return $emoji_n_title;
	}

	/**
	 * Check if override template.
	 *
	 * @since 0.0.3
	 * @return bool
	 */
	public function check_if_override_template() {
		$status = true;

		/* Breakdance & Bricks Builders compatibility */
		if ( ! empty( $_GET['breakdance'] ) || ( ! empty( $_GET['bricks'] ) && $_GET['bricks'] === 'run' ) ) { // phpcs:ignore
			$status = false;
		}

		return apply_filters( 'suredash_perform_template_include', $status );
	}

	/**
	 * Callback function for override templates.
	 *
	 * @param string $template override single templates.
	 * @since 1.0.0
	 * @return mixed
	 */
	public function update_templates( $template ) {
		if ( ! $this->check_if_override_template() ) {
			return $template;
		}

		if ( suredash_simply_content() ) {
			return suredash_get_template_part(
				'quick-view',
				suredash_show_content_only() ? 'content' : 'post'
			);
		}

		if ( has_suredash_blocks() || suredash_portal() || suredash_cpt() || suredash_is_portal_container_template() ) {
			do_action( 'suredash_enqueue_scripts' );
		}

		if ( ( is_front_page() && Helper::get_option( 'portal_as_homepage' ) ) || suredash_is_sub_queried_page() || suredash_portal() || suredash_cpt() ) {
			do_action( 'suredash_enqueue_scripts' );
			if ( wp_is_block_theme() ) {
				global $_wp_current_template_id, $_wp_current_template_content;

				$_wp_current_template_id = 'suredash/suredash//portal';
				$block_template          = get_block_template( 'suredash/suredash//portal' );

				if ( ! empty( $block_template ) ) {
					$_wp_current_template_content = $block_template->content;
				}
			} else {
				$template = SUREDASHBOARD_DIR . 'templates/pages/template-suredash-portal.php';
			}

			add_action( 'wp_footer', [ $this, 'render_suredash_footer_compat' ] );
		}

		return $template;
	}

	/**
	 * Execute SureDash Footer.
	 *
	 * @since 0.0.6
	 * @return void
	 */
	public function render_suredash_footer_compat(): void {
		do_action( 'suredash_footer' );
	}

	/**
	 * Add body class.
	 *
	 * @param array<int, string> $classes body classes.
	 * @since 1.0.0
	 * @return array<int, string>
	 */
	public function add_body_class( $classes ) {
		// Assign version class for reference.
		$classes[] = 'suredash-' . SUREDASHBOARD_VER;

		if ( ! is_user_logged_in() ) {
			$classes[] = 'suredash-guest-user';
		}

		if ( Helper::get_option( 'backward_color_options' ) ) {
			$classes[] = 'suredash-legacy-colors-setup';
		}

		if ( suredash_is_dark_mode_active() ) {
			$classes[] = 'palette-dark';
			$classes[] = 'dark-mode';
		}

		if ( suredash_screen() ) {
			$classes[] = 'suredash-screen-view';
		}

		$sub_queried_page = suredash_get_sub_queried_page();
		if ( $sub_queried_page ) {
			$classes[] = 'suredash-' . $sub_queried_page;
		}

		// Add page builder specific body classes.
		if ( suredash_frontend() ) {
			$classes = $this->maybe_add_page_builder_body_classes( $classes );
		}

		// Add class for portal-container template.
		if ( suredash_is_portal_container_template() ) {
			$classes[] = 'suredash-portal-container';
			$classes[] = 'suredash-custom-layout';
		}

		// Add class for block themes to help with CSS targeting.
		if ( function_exists( 'wp_is_block_theme' ) && wp_is_block_theme() ) {
			$classes[] = 'suredash-block-theme';
		}

		return $classes;
	}

	/**
	 * Track when a user visits a space (community forum).
	 * Updates the last visit timestamp for unread count calculation.
	 *
	 * @return void
	 * @since 1.6.0
	 */
	public function track_space_visit(): void {
		// Only track for logged-in users.
		$user_id = get_current_user_id();
		if ( empty( $user_id ) ) {
			return;
		}

		// Only track on frontend.
		if ( ! suredash_frontend() ) {
			return;
		}

		// Skip prefetch/prerender requests - these are browser optimization requests
		// that shouldn't trigger side effects like marking posts as viewed.
		// Check for various prefetch/prerender headers used by different browsers.
		$purpose     = isset( $_SERVER['HTTP_PURPOSE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_PURPOSE'] ) ) : '';
		$sec_purpose = isset( $_SERVER['HTTP_SEC_PURPOSE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_SEC_PURPOSE'] ) ) : '';
		$sec_fetch   = isset( $_SERVER['HTTP_SEC_FETCH_DEST'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_SEC_FETCH_DEST'] ) ) : '';

		if ( $purpose === 'prefetch' || $sec_purpose === 'prefetch' || $sec_fetch === 'prefetch' ) {
			return;
		}

		$forum_id     = 0;
		$post_id      = 0;
		$content_type = '';

		// Check if viewing a forum taxonomy archive (space page).
		if ( is_tax( SUREDASHBOARD_FEED_TAXONOMY ) ) {
			$term     = get_queried_object();
			$forum_id = $term->term_id ?? 0;
		}

		// Check if viewing a single community post.
		if ( is_singular( SUREDASHBOARD_FEED_POST_TYPE ) ) {
			$post_id = get_queried_object_id();
			$terms   = wp_get_post_terms( $post_id, SUREDASHBOARD_FEED_TAXONOMY, [ 'fields' => 'ids' ] );
			if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
				$forum_id = $terms[0];
			}
		}

		// Check if viewing a portal space with posts_discussion or category integration.
		if ( is_singular( SUREDASHBOARD_POST_TYPE ) ) {
			$post_id      = get_queried_object_id();
			$content_type = PostMeta::get_post_meta_value( $post_id, 'integration' );

			if ( in_array( $content_type, [ 'posts_discussion', 'category' ], true ) ) {
				$forum_id = absint( PostMeta::get_post_meta_value( $post_id, 'feed_group_id' ) );
			}
		}

		// If we found a forum, mark posts as viewed.
		if ( ! empty( $forum_id ) ) {
			$tracker = Activity_Tracker::get_instance();
			// Mark ALL posts in this space as viewed when user visits.
			$tracker->mark_posts_as_viewed( $user_id, $forum_id );
		}
	}

	/**
	 * Add page builder specific body classes based on detected builder.
	 *
	 * @param array<int, string> $classes Existing body classes.
	 * @return array<int, string> Updated body classes.
	 * @since 1.5.3
	 */
	private function maybe_add_page_builder_body_classes( $classes ) {
		if ( ! function_exists( 'suredash_detect_page_builder' ) ) {
			return $classes;
		}

		$post_id = suredash_get_post_id();
		if ( ! $post_id ) {
			return $classes;
		}

		// Determine which post ID to check for page builder.
		$target_post_id = $this->get_target_post_id_for_page_builder( $post_id );

		// Detect page builder and add appropriate body class.
		$page_builder = suredash_detect_page_builder( $target_post_id );

		switch ( $page_builder ) {
			case 'breakdance':
				$classes[] = 'breakdance';
				break;

			default:
				break;
		}

		return $classes;
	}

	/**
	 * Get the target post ID to check for page builder detection.
	 *
	 * @param int $post_id The space post ID.
	 * @return int The target post ID.
	 * @since 1.5.3
	 */
	private function get_target_post_id_for_page_builder( $post_id ) {
		$content_type = PostMeta::get_post_meta_value( (int) $post_id, 'integration' );
		$render_type  = PostMeta::get_post_meta_value( (int) $post_id, 'post_render_type' );

		// Default to space ID.
		$target_post_id = $post_id;

		// If using a WordPress post (not Space editor), check the selected post.
		if ( $content_type === 'single_post' && $render_type === 'wordpress' ) {
			$remote_post_data = PostMeta::get_post_meta_value( (int) $post_id, 'wp_post' );
			$remote_post_id   = absint( is_array( $remote_post_data ) && ! empty( $remote_post_data['value'] ) ? $remote_post_data['value'] : 0 );

			if ( $remote_post_id ) {
				$target_post_id = $remote_post_id;
			}
		}

		return $target_post_id;
	}

	/**
	 * Determine current space type based on post content_type meta.
	 *
	 * @return string Space type: 'events', 'resource_library', or 'none'
	 * @since 1.4.0
	 */
	private function get_current_space_type(): string {
		$post_id = get_queried_object_id();
		if ( ! $post_id ) {
			return 'none';
		}

		$content_type = get_post_meta( $post_id, 'content_type', true );

		switch ( $content_type ) {
			case 'event':
				return 'events';
			case 'resource':
				return 'resource_library';
			default:
				return 'lesson';
		}
	}
}
