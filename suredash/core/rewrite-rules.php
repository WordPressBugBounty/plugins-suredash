<?php
/**
 * Portals RewriteRules Initialize.
 *
 * @package SureDash
 */

namespace SureDashboard\Core;

use SureDashboard\Core\Integrations\SinglePost;
use SureDashboard\Core\Shortcodes\Notification;
use SureDashboard\Core\Shortcodes\SingleComments;
use SureDashboard\Inc\Traits\Get_Instance;
use SureDashboard\Inc\Utils\Helper;
use SureDashboard\Inc\Utils\Labels;
use SureDashboard\Inc\Utils\WpPost;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class RewriteRules.
 */
class RewriteRules {
	use Get_Instance;

	/**
	 * Set status for post reaction modal loaded.
	 *
	 * @var bool
	 */
	private $post_reaction_modal_loaded = false;

	/**
	 * Set status for post quick view loaded.
	 *
	 * @var bool
	 */
	private $quick_view_modal_loaded = false;

	/**
	 * Set status for branding section loaded.
	 *
	 * @var bool
	 */
	private $branding_loaded = false;

	/**
	 * Set status for search modal loaded.
	 *
	 * @var bool
	 */
	private $search_modal_loaded = false;

	/**
	 * Set status for mobile notification popup loaded.
	 *
	 * @var bool
	 */
	private $mobile_notification_loaded = false;

	/**
	 * Constructor
	 *
	 * @since 0.0.1
	 */
	public function __construct() {
		$this->initialize_hooks();
	}

	/**
	 * Init Hooks.
	 *
	 * @since 0.0.1
	 * @return void
	 */
	public function initialize_hooks(): void {
		add_action( 'init', [ $this, 'portal_rewrite_rules' ] );
		add_filter( 'query_vars', [ $this, 'add_query_vars' ] );

		// Add custom rewrite rules for community content.
		add_filter( 'rewrite_rules_array', [ $this, 'extended_rewrite_rules' ] );

		// Load assets for single post.
		add_action( 'suredashboard_single_post_template', [ $this, 'load_post_assets' ] );

		// Quick view post content -- Adding here just to load early.
		add_action( 'suredashboard_quick_view_post_content', [ $this, 'load_quick_view_post_content' ], 10, 3 );

		add_action( 'suredash_footer', [ $this, 'render_search_modal' ] );
		add_action( 'suredash_footer', [ $this, 'render_mobile_notification_popup' ] );
		add_action( 'suredash_footer', [ $this, 'add_post_reaction_modal' ] );
		add_action( 'suredash_footer', [ $this, 'quick_view_popup' ] );
		add_action( 'suredash_footer', [ $this, 'load_branding' ] );
	}

	/**
	 * Add Query Vars.
	 *
	 * @param array<int, string> $vars Query Vars.
	 * @return array<int, string>
	 * @since 0.0.1
	 */
	public function add_query_vars( $vars ) {
		$vars[] = 'portal_subpage';
		$vars[] = 'user_id';
		$vars[] = 'screen_id';
		return $vars;
	}

	/**
	 * Custom Rewrite Rules as per suredash_sub_queries().
	 *
	 * @since 0.0.1
	 * @return void
	 */
	public function portal_rewrite_rules(): void {
		$sub_queries    = suredash_sub_queries();
		$community_slug = suredash_get_community_slug();

		// Add specific rule for user-view with ID parameter. The public-facing
		// slug is admin-overridable via Advanced settings, but the dispatched
		// `portal_subpage` query var is always the canonical `user-view` so
		// downstream code that compares `$queried_page === 'user-view'`
		// continues to work unchanged.
		if ( in_array( 'user-view', $sub_queries ) ) {
			$user_view_public_slug = suredash_get_endpoint_slug( 'user-view' );
			add_rewrite_rule(
				'^' . $community_slug . '/' . esc_attr( $user_view_public_slug ) . '/([0-9]+)/?$',
				'index.php?portal_subpage=user-view&user_id=$matches[1]&post_type=' . SUREDASHBOARD_POST_TYPE,
				'top'
			);
		}

		foreach ( suredash_all_screen_types() as $screen ) {
			add_rewrite_rule(
				'^' . $community_slug . '/screen/' . $screen . '/?$',
				'index.php?portal_subpage=screen&screen_id=' . esc_attr( $screen ) . '&post_type=' . SUREDASHBOARD_POST_TYPE,
				'top'
			);
		}

		// Reverse map of public-facing slug → canonical slug to prevent two
		// endpoints from binding the same URL (last-write-wins).
		$used_slugs = [];

		foreach ( $sub_queries as $query ) {
			// Skip user-view as it has its own rule above.
			if ( $query === 'user-view' ) {
				continue;
			}

			$public_slug = suredash_get_endpoint_slug( $query );

			// On collision (configured override clashes with another endpoint
			// or with itself via filter), drop back to the canonical slug so
			// at least one rule still resolves.
			if ( isset( $used_slugs[ $public_slug ] ) ) {
				$public_slug = $query;
			}
			$used_slugs[ $public_slug ] = $query;

			add_rewrite_rule(
				'^' . $community_slug . '/' . esc_attr( $public_slug ) . '/?$',
				'index.php?portal_subpage=' . esc_attr( $query ) . '&post_type=' . SUREDASHBOARD_POST_TYPE . '&suredash_portal=1',
				'top'
			);
		}

		// Add a catch-all rule for the main portal page.
		add_rewrite_rule(
			'^' . $community_slug . '/?$',
			'index.php?post_type=' . SUREDASHBOARD_POST_TYPE . '&suredash_portal=1',
			'top'
		);
	}

	/**
	 * Add custom rewrite rules for community content.
	 *
	 * @param array<string, string> $rules Existing rewrite rules.
	 * @return array<string, string> Modified rewrite rules.
	 * @since 1.0.0
	 */
	public function extended_rewrite_rules( $rules ) {
		$new_rules = [];

		foreach ( suredash_all_content_types( true ) as $type ) {
			// Match comment-pagination URLs (generated when "Break comments into pages"
			// is enabled) before the catch-all, so /comment-page-N/ resolves via cpage
			// instead of 404ing. cpage is a core public query var, no registration needed.
			$new_rules[ "{$type}/([^/]+)/comment-page-([0-9]+)/?$" ] = 'index.php?post_type=' . SUREDASHBOARD_SUB_CONTENT_POST_TYPE . '&name=$matches[1]&cpage=$matches[2]';
			$new_rules[ "{$type}/([^/]+)/?$" ]                       = 'index.php?post_type=' . SUREDASHBOARD_SUB_CONTENT_POST_TYPE . '&name=$matches[1]';
		}

		return $new_rules + $rules;
	}

	/**
	 * Load quick view post content.
	 *
	 * @param int    $post_id Post ID.
	 * @param bool   $comments Comments.
	 * @param string $integration Space Integration.
	 *
	 * @since 1.0.0
	 */
	public function load_quick_view_post_content( $post_id, $comments, $integration ): void {
		ob_start();

		// If the discussion area is private, we don't show the content.
		// suredash_is_post_protected() now honors SureMembers per-item exceptions centrally.
		if ( suredash_is_post_protected( $post_id ) ) {
			$lock_action = function_exists( 'suredash_sm_item_action' ) ? suredash_sm_item_action( $post_id ) : [];

			// SureMembers restriction → keep the post's title (the author header is already rendered by
			// the quick-view template) and swap the body for SureMembers' own restriction template
			// (message + button + login), exactly as configured in the access group.
			if ( ! empty( $lock_action ) && class_exists( '\SureDashboard\Core\Integrations\SureMembers' ) ) {
				$restriction_settings  = is_array( $lock_action['restrict'] ?? null ) ? $lock_action['restrict'] : [];
				$restriction_html      = (string) \SureDashboard\Core\Integrations\SureMembers::get_instance()->get_restricted_message( '', $restriction_settings );
				$restricted_post_title = get_the_title( $post_id );
				?>
				<div class="sd-post-content sd-post-content-wrapper sd-px-20 sd-pt-4">
					<?php if ( $integration !== 'resource_library' && $restricted_post_title !== '' ) { ?>
						<h1 class="portal-store-post-title" title="<?php echo esc_attr( $restricted_post_title ); ?>">
							<?php echo esc_html( $restricted_post_title ); ?>
						</h1>
					<?php } ?>
					<?php echo do_shortcode( $restriction_html ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- rendered by SureMembers, already escaped. ?>
				</div>
				<?php
				echo do_shortcode( strval( ob_get_clean() ) );
				return;
			}

			// Non-SureMembers restriction (drip, private post, visibility scope) → generic notice.
			suredash_get_restricted_template_part(
				$post_id,
				'parts',
				'restricted',
				[
					'icon'        => 'Lock',
					'label'       => 'restricted_content',
					'description' => 'restricted_content_description',
				]
			);
			echo do_shortcode( strval( ob_get_clean() ) );
			return;
		}

		$wp_post_instance = new WpPost( $post_id );
		$wp_post_instance->enqueue_assets();
		$p_title = get_the_title( $post_id );

		remove_filter( 'the_content', 'wpautop' );

		?>
		<div class="sd-post-content sd-post-content-wrapper sd-px-20 sd-pt-4">
			<?php
			Helper::suredash_featured_cover( $post_id );

			if ( $integration !== 'resource_library' ) {
				?>
				<h1 class="portal-store-post-title" title="<?php echo esc_attr( $p_title ); ?>">
					<?php echo esc_html( $p_title ); ?>
				</h1>
				<?php
			}

			if ( method_exists( SinglePost::get_instance(), 'get_integration_content' ) ) {
				// Removing entry-content class if the page/post built by any supported page builder.
				?>
				<div class="<?php echo esc_attr( suredash_is_post_by_block_editor( $post_id ) ? 'entry-content' : '' ); ?> sd-mb-16">
					<?php echo do_shortcode( apply_filters( 'the_content', SinglePost::get_instance()->get_integration_content( $post_id, true ), $post_id ) ); ?>
				</div>
				<?php
			}

			if ( method_exists( SingleComments::get_instance(), 'get_single_comments_content' ) ) {
				SingleComments::get_instance()->get_single_comments_content(
					[
						'item_id'  => $post_id,
						'echo'     => true,
						'in_qv'    => true,
						'comments' => $comments,
					]
				);
			}
			?>
		</div>
		<?php

		$content = (string) preg_replace_callback(
			'/<p>\s*<\/p>/',
			static function() {
				return '';
			},
			(string) ob_get_clean()
		);
		$content = suredash_dynamic_content_support( $content );

		echo do_shortcode( (string) $content );
		add_filter( 'the_content', 'wpautop' );
	}

	/**
	 * Load required single post assets.
	 *
	 * @param int $post_id Post ID.
	 *
	 * @since 1.0.0
	 */
	public function load_post_assets( $post_id ): void {
		$wp_post_instance = new WpPost( $post_id );
		$wp_post_instance->enqueue_assets();
	}

	/**
	 * Add notification toaster for the portal.
	 *
	 * @return void
	 * @since 0.0.1
	 */
	public function render_notification_toaster(): void {
		?>
			<div id="portal-notification-toaster" class="portal-notification-toaster portal-content" aria-live="assertive"></div>
			<div id="portal-a11y-announcer" class="screen-reader-text" aria-live="polite" aria-atomic="true"></div>
			<?php
			// Shared description for every like-count control in the feed. Referenced
			// by aria-describedby rather than aria-label, so each control keeps its
			// live count text as its accessible name and cannot go stale when JS
			// updates the number.
			?>
			<span id="portal-likes-count-hint" class="screen-reader-text"><?php esc_html_e( 'View who liked this', 'suredash' ); ?></span>
		<?php
	}

	/**
	 * Render the mobile notification popup.
	 *
	 * Rendered once in the portal footer for logged-in users. The bottom-bar
	 * Bell link toggles this popup open/close (see initializeMobileNotificationPopup
	 * in global.js) instead of navigating to the notification screen. Reuses the
	 * same notification list markup as the desktop drawer so the existing
	 * read/unread, tab and unread-count JS binds automatically via the shared
	 * `sd-notification-list` class.
	 *
	 * @return void
	 * @since 1.5.0
	 */
	public function render_mobile_notification_popup(): void {
		if ( $this->mobile_notification_loaded || ! is_user_logged_in() ) {
			return;
		}

		?>
		<div class="portal-mobile-notification-backdrop"></div>
		<div id="portal-mobile-notification" class="portal-mobile-notification-popup portal-content sd-bg-content sd-flex sd-flex-col sd-radius-12 sd-shadow-lg sd-border sd-notification-list" role="dialog" aria-modal="true" aria-label="<?php echo esc_attr( Labels::get_label( 'notifications' ) ); ?>">
			<span class="portal-mobile-notification-arrow" aria-hidden="true"></span>
			<?php Notification::get_instance()->get_user_notification_list( true ); ?>
		</div>
		<?php

		$this->mobile_notification_loaded = true;
	}

	/**
	 * Add Post Reaction Modal - Likes, Comments.
	 *
	 * @return void
	 * @since 0.0.1
	 */
	public function add_post_reaction_modal(): void {
		if ( $this->post_reaction_modal_loaded ) {
			return;
		}

		$this->render_notification_toaster();

		?>
			<div id="portal-post-reaction-modal" class="portal-modal portal-content">
				<div class="portal-modal-content sd-shadow-lg">
					<div class="portal-modal-header">
						<span class="portal-post-reactor-header">
							<h4 class="show-likes sd-no-space"><?php Labels::get_label( 'likes', true ); ?></h4>
							<h4 class="show-visibility sd-no-space"><?php esc_html_e( 'Post Visibility', 'suredash' ); ?></h4>
						</span>
						<span class="portal-modal-close"> <?php Helper::get_library_icon( 'X' ); ?> </span>
					</div>
					<div class="portal-modal-body">
						<div class="portal-pagination-loader active">
							<div class="portal-pagination-loader-1"></div>
							<div class="portal-pagination-loader-2"></div>
							<div class="portal-pagination-loader-3"></div>
						</div>

						<div class="portal-post-reactor-content"></div>
					</div>

				</div>

				<div class="portal-modal-backdrop"></div>
			</div>
		<?php

		$this->post_reaction_modal_loaded = true;
	}

	/**
	 * Load branding.
	 *
	 * @since 0.0.6
	 */
	public function load_branding(): void {
		if ( $this->branding_loaded ) {
			return;
		}

		suredash_get_template_part(
			'parts',
			'footer'
		);

		$this->branding_loaded = true;
	}

	/**
	 * Quick view HTML.
	 *
	 * @since 0.0.1
	 */
	public function quick_view_popup(): void {
		if ( $this->quick_view_modal_loaded ) {
			return;
		}

		suredash_get_template_part( 'quick-view', 'container' );

		$this->quick_view_modal_loaded = true;
	}

	/**
	 * Render Search Modal.
	 *
	 * @since 0.0.1
	 */
	public function render_search_modal(): void {
		if ( $this->search_modal_loaded ) {
			return;
		}

		?>
		<!--Search-term results-->
		<script type="text/html" id="portal-search-result-template">
			<?php // @codingStandardsIgnoreStart?>
			<li id="portal_search_item-<%= post.id %>">
				<a href="<%= post.url %>" class="portal-search-item-link">
					<div class="portal-search-item-title-wrap">
						<span class="portal-search-result-title"><%= post.title %></span>
						<?php // @codingStandardsIgnoreEnd?>
					</div>
				</a>
			</li>
		</script>
		<?php

		echo do_shortcode( '<div class="pfd-header-search"> [portal_search] </div>' );

		$this->search_modal_loaded = true;
	}
}
