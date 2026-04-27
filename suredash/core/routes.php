<?php
/**
 * Define the REST API routes.
 *
 * @package SureDash
 */

namespace SureDashboard\Core;

use SureDashboard\Core\Routers\Backend as BackendRoute;
use SureDashboard\Core\Routers\Emails;
use SureDashboard\Core\Routers\Misc as MiscRoute;
use SureDashboard\Core\Routers\Onboarding;
use SureDashboard\Core\Routers\Social_Logins;
use SureDashboard\Core\Routers\User as UserRoute;
use SureDashboard\Inc\Services\Router;
use SureDashboard\Inc\Traits\Get_Instance;
use SureDashboard\Inc\Utils\Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class CPTs.
 */
class Routes {
	use Get_Instance;

	/**
	 * Constructor
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->initialize_actions();

		add_action(
			'rest_api_init',
			static function (): void {
				if ( method_exists( Router::get_instance(), 'registerRoutes' ) ) {
					Router::get_instance()->registerRoutes();
				}
			}
		);

		// Filter restricted content from frontend REST search results.
		add_filter( 'rest_post_dispatch', [ $this, 'filter_search_results' ], 10, 3 );
	}

	/**
	 * Init Hooks.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function initialize_actions(): void {
		$this->register_rest_routes();
	}

	/**
	 * Return the rest response.
	 *
	 * @param mixed $response The response.
	 * @param int   $status The status code.
	 * @return \WP_Error|\WP_REST_Response
	 */
	public static function rest_response( $response, $status = 200 ) {
		if ( empty( $response ) ) {
			return new \WP_Error( 'no_space_found', __( 'Oops! Something wrong here...', 'suredash' ), [ 'status' => 404 ] );
		}

		$response = rest_ensure_response( $response );

		// Only call set_status if the response is a WP_REST_Response instance.
		if ( $response instanceof \WP_REST_Response ) {
			$response->set_status( $status );
		}

		return $response;
	}

	/**
	 * Get SureDash routes.
	 *
	 * @return array<string, array<string, array<int, callable>>>
	 */
	public function get_suredash_routes(): array {
		return apply_filters(
			'suredash_rest_routes',
			[
				'/submit-topic/'                     => [
					'method'              => 'POST',
					'callback'            => [ MiscRoute::get_instance(), 'submit_post' ],
					'permission_callback' => 'user',
				],
				'/load-more-posts/'                  => [
					'method'              => 'POST',
					'callback'            => [ MiscRoute::get_instance(), 'load_more_posts' ],
					'permission_callback' => ! Helper::get_option( 'hidden_community' ) ? false : 'user',
				],
				'/load-more-comments/'               => [
					'method'              => 'POST',
					'callback'            => [ MiscRoute::get_instance(), 'load_more_comments' ],
					'permission_callback' => ! Helper::get_option( 'hidden_community' ) ? false : 'user',
				],
				'bookmark-item'                      => [
					'method'              => 'POST',
					'callback'            => [ MiscRoute::get_instance(), 'bookmark_item' ],
					'permission_callback' => 'user',
				],
				'update-user-profile'                => [
					'method'              => 'POST',
					'callback'            => [ MiscRoute::get_instance(), 'update_user_profile' ],
					'permission_callback' => 'user',
				],
				'send-test-email'                    => [
					'method'              => 'POST',
					'callback'            => [ MiscRoute::get_instance(), 'send_test_email' ],
					'permission_callback' => 'admin',
				],
				'post-quick-view'                    => [
					'method'              => 'POST',
					'callback'            => [ MiscRoute::get_instance(), 'post_quick_view' ],
					'permission_callback' => ! Helper::get_option( 'hidden_community' ) ? false : 'user',
				],
				'entity-reaction'                    => [
					'method'              => 'POST',
					'callback'            => [ MiscRoute::get_instance(), 'entity_reaction' ],
					'permission_callback' => 'user',
				],
				'search-user'                        => [
					'method'              => 'POST',
					'callback'            => [ MiscRoute::get_instance(), 'search_user' ],
					'permission_callback' => 'user',
				],
				'post-reactor-data'                  => [
					'method'              => 'POST',
					'callback'            => [ MiscRoute::get_instance(), 'post_reactor_data' ],
					'permission_callback' => 'user',
				],
				'submit-comment'                     => [
					'method'              => 'POST',
					'callback'            => [ MiscRoute::get_instance(), 'submit_comment' ],
					'permission_callback' => 'user',
				],
				'edit-comment'                       => [
					'method'              => 'POST',
					'callback'            => [ MiscRoute::get_instance(), 'edit_comment' ],
					'permission_callback' => 'user',
				],
				'delete-comment/(?P<id>\d+)'         => [
					'method'              => 'POST',
					'callback'            => [ MiscRoute::get_instance(), 'delete_comment' ],
					'permission_callback' => 'user',
				],
				'edit-post'                          => [
					'method'              => 'POST',
					'callback'            => [ MiscRoute::get_instance(), 'edit_post' ],
					'permission_callback' => 'user',
				],
				'get-post/(?P<id>\d+)'               => [
					'method'              => 'GET',
					'callback'            => [ MiscRoute::get_instance(), 'get_post' ],
					'permission_callback' => 'user',
				],
				'delete-post/(?P<id>\d+)'            => [
					'method'              => 'POST',
					'callback'            => [ MiscRoute::get_instance(), 'delete_post' ],
					'permission_callback' => 'user',
				],
				'get-unread-counts'                  => [
					'method'              => 'POST',
					'callback'            => [ MiscRoute::get_instance(), 'get_unread_counts' ],
					'permission_callback' => 'user',
				],
				'mark-space-read'                    => [
					'method'              => 'POST',
					'callback'            => [ MiscRoute::get_instance(), 'mark_space_read' ],
					'permission_callback' => 'user',
				],
				'oembed'                             => [
					'method'              => 'GET',
					'callback'            => [ MiscRoute::get_instance(), 'get_oembed' ],
					'permission_callback' => 'user',
				],

				// Calls which does not required callback permission_callback.
				'block-login'                        => [
					'method'              => 'POST',
					'callback'            => [ Social_Logins::get_instance(), 'block_login' ],
					'permission_callback' => '',
				],
				'block-login-forgot-password'        => [
					'method'              => 'POST',
					'callback'            => [ Social_Logins::get_instance(), 'block_login_forgot_password' ],
					'permission_callback' => '',
				],
				'block-register'                     => [
					'method'              => 'POST',
					'callback'            => [ Social_Logins::get_instance(), 'block_register' ],
					'permission_callback' => '',
				],
				'register-unique-username-and-email' => [
					'method'              => 'POST',
					'callback'            => [ Social_Logins::get_instance(), 'register_unique_username_and_email' ],
					'permission_callback' => '',
				],
				'login-form-facebook'                => [
					'method'              => 'POST',
					'callback'            => [ Social_Logins::get_instance(), 'login_form_facebook' ],
					'permission_callback' => '',
				],
				'login-form-google'                  => [
					'method'              => 'POST',
					'callback'            => [ Social_Logins::get_instance(), 'login_form_google' ],
					'permission_callback' => '',
				],
				'register-form-google'               => [
					'method'              => 'POST',
					'callback'            => [ Social_Logins::get_instance(), 'register_form_google' ],
					'permission_callback' => '',
				],
				// Backend Rest API's.
				'create-space'                       => [
					'method'              => 'POST',
					'callback'            => [ BackendRoute::get_instance(), 'create_space' ],
					'permission_callback' => 'admin',
				],
				'check-duplicate-space'              => [
					'method'              => 'POST',
					'callback'            => [ BackendRoute::get_instance(), 'check_duplicate_space_endpoint' ],
					'permission_callback' => 'admin',
				],
				'delete-space'                       => [
					'method'              => 'POST',
					'callback'            => [ BackendRoute::get_instance(), 'delete_space' ],
					'permission_callback' => 'admin',
				],
				'delete-sub-content'                 => [
					'method'              => 'POST',
					'callback'            => [ BackendRoute::get_instance(), 'delete_community_content' ],
					'permission_callback' => 'admin',
				],
				'create-space-group'                 => [
					'method'              => 'POST',
					'callback'            => [ BackendRoute::get_instance(), 'create_space_group' ],
					'permission_callback' => 'admin',
				],
				'update-space-group'                 => [
					'method'              => 'POST',
					'callback'            => [ BackendRoute::get_instance(), 'update_space_group' ],
					'permission_callback' => 'admin',
				],
				'delete-space-group'                 => [
					'method'              => 'POST',
					'callback'            => [ BackendRoute::get_instance(), 'delete_space_group' ],
					'permission_callback' => 'admin',
				],
				'update-item-order-by-group'         => [
					'method'              => 'POST',
					'callback'            => [ BackendRoute::get_instance(), 'update_item_order_by_group' ],
					'permission_callback' => 'admin',
				],
				'update-group-order'                 => [
					'method'              => 'POST',
					'callback'            => [ BackendRoute::get_instance(), 'update_group_order' ],
					'permission_callback' => 'admin',
				],
				'update-group-term'                  => [
					'method'              => 'POST',
					'callback'            => [ BackendRoute::get_instance(), 'update_group_term' ],
					'permission_callback' => 'admin',
				],
				'get-posts-list'                     => [
					'method'              => 'POST',
					'callback'            => [ BackendRoute::get_instance(), 'get_posts_list' ],
					'permission_callback' => 'admin',
				],
				'get-community-posts-list'           => [
					'method'              => 'POST',
					'callback'            => [ BackendRoute::get_instance(), 'get_community_posts_list' ],
					'permission_callback' => 'admin',
				],
				'get-post-meta'                      => [
					'method'              => 'POST',
					'callback'            => [ BackendRoute::get_instance(), 'get_post_meta' ],
					'permission_callback' => 'admin',
				],
				'get-post-content'                   => [
					'method'              => 'POST',
					'callback'            => [ BackendRoute::get_instance(), 'get_post_content' ],
					'permission_callback' => 'admin',
				],
				'get-group-meta'                     => [
					'method'              => 'POST',
					'callback'            => [ BackendRoute::get_instance(), 'get_group_meta' ],
					'permission_callback' => 'admin',
				],
				'get-internal-categories-list'       => [
					'method'              => 'POST',
					'callback'            => [ BackendRoute::get_instance(), 'get_internal_categories_list' ],
					'permission_callback' => 'admin',
				],
				'get-internal-category-posts'        => [
					'method'              => 'POST',
					'callback'            => [ BackendRoute::get_instance(), 'get_internal_category_posts' ],
					'permission_callback' => 'admin',
				],
				'save-settings'                      => [
					'method'              => 'POST',
					'callback'            => [ BackendRoute::get_instance(), 'save_settings' ],
					'permission_callback' => 'admin',
				],
				'update-user-notifications'          => [
					'method'              => 'POST',
					'callback'            => [ UserRoute::get_instance(), 'update_notifications' ],
					'permission_callback' => 'user',
				],
				'update-user-data'                   => [
					'method'              => 'POST',
					'callback'            => [ UserRoute::get_instance(), 'update_user_data' ],
					'permission_callback' => 'admin',
				],
				'skip-onboarding'                    => [
					'method'              => 'POST',
					'callback'            => [ Onboarding::get_instance(), 'skip_onboarding' ],
					'permission_callback' => 'admin',
				],
				'complete-onboarding'                => [
					'method'              => 'POST',
					'callback'            => [ Onboarding::get_instance(), 'complete_onboarding' ],
					'permission_callback' => 'admin',
				],
				'process-onboarding'                 => [
					'method'              => 'POST',
					'callback'            => [ Onboarding::get_instance(), 'process_onboarding' ],
					'permission_callback' => 'admin',
				],
				'activate-plugin'                    => [
					'method'              => 'POST',
					'callback'            => [ Onboarding::get_instance(), 'activate_plugin' ],
					'permission_callback' => 'admin',
				],
				'content-action'                     => [
					'method'              => 'POST',
					'callback'            => [ BackendRoute::get_instance(), 'content_action' ],
					'permission_callback' => 'admin',
				],
				'content-bulk-action'                => [
					'method'              => 'POST',
					'callback'            => [ BackendRoute::get_instance(), 'content_bulk_action' ],
					'permission_callback' => 'admin',
				],
				'dashboard-data'                     => [
					'method'              => 'POST',
					'callback'            => [ BackendRoute::get_instance(), 'get_dashboard_data' ],
					'permission_callback' => 'admin',
				],
				'member-stats'                       => [
					'method'              => 'POST',
					'callback'            => [ BackendRoute::get_instance(), 'get_member_stats' ],
					'permission_callback' => 'admin',
				],
				'posts-stats'                        => [
					'method'              => 'POST',
					'callback'            => [ BackendRoute::get_instance(), 'get_posts_stats' ],
					'permission_callback' => 'admin',
				],
				'comments-stats'                     => [
					'method'              => 'POST',
					'callback'            => [ BackendRoute::get_instance(), 'get_comments_stats' ],
					'permission_callback' => 'admin',
				],
				'update-comment-status'              => [
					'method'              => 'POST',
					'callback'            => [ BackendRoute::get_instance(), 'update_comment_status' ],
					'permission_callback' => 'admin',
				],
				'update-comment-status-bulk'         => [
					'method'              => 'POST',
					'callback'            => [ BackendRoute::get_instance(), 'update_comment_status_bulk' ],
					'permission_callback' => 'admin',
				],
				'/users'                             => [
					'method'              => 'GET',
					'callback'            => [ BackendRoute::get_instance(), 'get_users' ],
					'permission_callback' => 'admin',
				],
				'/access-groups'                     => [
					'method'              => 'GET',
					'callback'            => [ BackendRoute::get_instance(), 'get_access_groups' ],
					'permission_callback' => 'user',
				],
				'/users/(?P<id>\d+)'                 => [
					'method'              => 'GET',
					'callback'            => [ BackendRoute::get_instance(), 'get_user' ],
					'permission_callback' => 'admin',
				],
				'/users/(?P<id>\d+)/posts'           => [
					'method'              => 'GET',
					'callback'            => [ BackendRoute::get_instance(), 'get_user_posts' ],
					'permission_callback' => 'admin',
				],
				'/users/(?P<id>\d+)/comments'        => [
					'method'              => 'GET',
					'callback'            => [ BackendRoute::get_instance(), 'get_user_comments' ],
					'permission_callback' => 'admin',
				],
				'/icons/'                            => [
					'method'              => 'POST',
					'callback'            => [ BackendRoute::get_instance(), 'get_icon_library' ],
					'permission_callback' => 'admin',
				],
				// Email notification routes.
				'email-triggers'                     => [
					'method'              => 'GET',
					'callback'            => [ Emails::get_instance(), 'get_email_triggers' ],
					'permission_callback' => 'admin',
				],
				'email-triggers/add'                 => [
					'method'              => 'POST',
					'callback'            => [ Emails::get_instance(), 'add_email_trigger' ],
					'permission_callback' => 'admin',
				],
				'email-triggers/status'              => [
					'method'              => 'POST',
					'callback'            => [ Emails::get_instance(), 'update_trigger_status' ],
					'permission_callback' => 'admin',
				],
				'email-triggers/template'            => [
					'method'              => 'POST',
					'callback'            => [ Emails::get_instance(), 'update_trigger_template' ],
					'permission_callback' => 'admin',
				],
				'email-triggers/delete'              => [
					'method'              => 'POST',
					'callback'            => [ Emails::get_instance(), 'delete_email_trigger' ],
					'permission_callback' => 'admin',
				],
				'email-triggers/bulk-delete'         => [
					'method'              => 'POST',
					'callback'            => [ Emails::get_instance(), 'bulk_delete_email_triggers' ],
					'permission_callback' => 'admin',
				],
				'/emojis/'                           => [
					'method'              => 'POST',
					'callback'            => [ BackendRoute::get_instance(), 'get_emoji_library' ],
					'permission_callback' => 'admin',
				],
				'create-post-for-space'              => [
					'method'              => 'POST',
					'callback'            => [ BackendRoute::get_instance(), 'create_post_for_space' ],
					'permission_callback' => 'admin',
				],
				'update-content-settings'            => [
					'method'              => 'POST',
					'callback'            => [ BackendRoute::get_instance(), 'update_content_settings' ],
					'permission_callback' => 'admin',
				],
			]
		);
	}

	/**
	 * Register REST API routes.
	 *
	 * @return void
	 */
	public function register_rest_routes(): void {
		$sd_routes = $this->get_suredash_routes();

		foreach ( $sd_routes as $route => $route_data ) {
			$method              = $route_data['method'] ?? 'POST';
			$callback            = $route_data['callback'] ?? null;
			$permission_callback = $route_data['permission_callback'] ?? '';
			$args                = $route_data['args'] ?? [];
			$this->register_route( $method, $route, $callback, $permission_callback, $args ); // @phpstan-ignore-line
		}
	}

	/**
	 * Register route.
	 *
	 * @param string       $method Method.
	 * @param string       $route Route.
	 * @param array        $callback Callback.
	 * @param bool         $permission_callback Permission callback.
	 * @param array<mixed> $args Route arguments.
	 * @return void
	 * @since 0.0.2
	 * @phpstan-ignore-next-line
	 */
	public function register_route( $method, $route, $callback, $permission_callback = '', $args = [] ): void {
		sd_route()->addRoute(
			$method,
			$route,
			static function ( $request ) use ( $callback ) {
				// @phpstan-ignore-next-line
				return self::rest_response( call_user_func_array( $callback, [ $request ] ) );
			},
			$permission_callback, // @phpstan-ignore-line
			$args
		);
	}

	/**
	 * Filter restricted content from WordPress REST search results.
	 *
	 * Removes SureDash posts that the current user cannot access (protected
	 * by visibility_scope or SureMembers access groups) from the core
	 * /wp/v2/search endpoint response. Admins see all results.
	 *
	 * @param \WP_REST_Response $result  Response object.
	 * @param \WP_REST_Server   $server  Server instance.
	 * @param \WP_REST_Request  $request Request used to generate the response.
	 * @return \WP_REST_Response Filtered response.
	 * @since x.x.x
	 */
	public function filter_search_results( $result, $server, $request ) {
		// Only filter the core search endpoint.
		$route = $request->get_route();
		if ( strpos( $route, '/wp/v2/search' ) === false ) {
			return $result;
		}

		// Admins see everything.
		if ( function_exists( 'suredash_is_user_manager' ) && suredash_is_user_manager() ) {
			return $result;
		}

		$suredash_types = [
			SUREDASHBOARD_POST_TYPE,
			SUREDASHBOARD_FEED_POST_TYPE,
			SUREDASHBOARD_SUB_CONTENT_POST_TYPE,
		];

		$data     = $result->get_data();
		$filtered = [];

		foreach ( $data as $item ) {
			// Only filter SureDash post types; pass through everything else.
			$subtype = $item['subtype'] ?? '';
			if ( ! in_array( $subtype, $suredash_types, true ) ) {
				$filtered[] = $item;
				continue;
			}

			$post_id = absint( $item['id'] ?? 0 );
			if ( ! $post_id ) {
				$filtered[] = $item;
				continue;
			}

			// Skip protected posts (SureMembers, visibility_scope, etc.).
			if ( function_exists( 'suredash_is_post_protected' ) && suredash_is_post_protected( $post_id ) ) {
				continue;
			}

			$filtered[] = $item;
		}

		$result->set_data( $filtered );

		// Update total count in headers.
		$result->header( 'X-WP-Total', (string) count( $filtered ) );

		return $result;
	}
}
