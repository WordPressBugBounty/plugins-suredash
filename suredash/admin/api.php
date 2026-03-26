<?php
/**
 * Portal
 *
 * This class will holds the code related to the managing of
 * posts of portals
 *
 * @package SureDash
 *
 * @since 1.0.0
 */

namespace SureDashboard\Admin;

use SureDashboard\Inc\Traits\API_Base;
use SureDashboard\Inc\Traits\Get_Instance;
use SureDashboard\Inc\Utils\Settings;

defined( 'ABSPATH' ) || exit;
/**
 * API
 *
 * @since 1.0.0
 */
class API {
	use Get_Instance;
	use API_Base;

	/**
	 * Route base.
	 *
	 * @var string $rest_base REST base.
	 */
	protected string $rest_base = '/dataset/';

	/**
	 * Option name
	 *
	 * @access private
	 *
	 * @var string $option_name DB option name.
	 *
	 * @since 1.0.0
	 */
	private static string $option_name = 'portal_admin_settings';

	/**
	 * Constructor
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function __construct() {
		add_action( 'init', [ $this, 'register_meta_settings' ] );
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	/**
	 * Register meta settings.
	 *
	 * @since 1.0.0
	 */
	public function register_meta_settings(): void {

		$auth_callback = static function ( $allowed, $meta_key, $post_id ) {
			return current_user_can( 'edit_post', $post_id );
		};

		register_post_meta(
			SUREDASHBOARD_FEED_POST_TYPE,
			'custom_post_embed_media',
			[
				'show_in_rest'  => true,
				'single'        => true,
				'type'          => 'string',
				'auth_callback' => $auth_callback,
			]
		);

		register_post_meta(
			SUREDASHBOARD_FEED_POST_TYPE,
			'custom_post_cover_image',
			[
				'show_in_rest'  => true,
				'single'        => true,
				'type'          => 'string',
				'auth_callback' => $auth_callback,
			]
		);

		// Register content_type meta field for proper URL routing.
		register_post_meta(
			SUREDASHBOARD_SUB_CONTENT_POST_TYPE,
			'content_type',
			[
				'show_in_rest'  => true,
				'single'        => true,
				'default'       => 'lesson',
				'type'          => 'string',
				'auth_callback' => $auth_callback,
			]
		);
	}

	/**
	 * Register API routes.
	 *
	 * @since 1.0.0
	 */
	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			$this->rest_base,
			[
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_admin_settings' ],
					'permission_callback' => [ $this, 'get_permissions_check' ],
					'args'                => [],
				],
			]
		);
	}

	/**
	 * Get common settings.
	 *
	 * @return array<string, mixed> $updated_option defaults + set DB option data.
	 *
	 * @since 1.0.0
	 */
	public function get_admin_settings(): array {
		return Settings::get_suredash_settings();
	}

	/**
	 * Check whether a given request has permission to read notes.
	 *
	 * @since 1.0.0
	 *
	 * @return bool|\WP_Error
	 */
	public function get_permissions_check() {

		// Check if user is a Portal Manager first.
		if ( function_exists( 'suredash_is_user_manager' ) && suredash_is_user_manager() ) {
			return true;
		}

		return false;
	}

	/**
	 * Update an value of a key,
	 * from the settings database option for the admin settings page.
	 *
	 * @param string $key       The option key.
	 * @param mixed  $value     The value to update.
	 *
	 * @return void             Return the option value based on provided key
	 *
	 * @since 1.0.0
	 */
	public static function update_admin_settings_option( string $key, $value ): void {
		$updated_settings = get_option( self::$option_name, [] );

		if ( ! is_array( $updated_settings ) ) {
			$updated_settings = [];
		}

		$updated_settings[ $key ] = $value;
		update_option( self::$option_name, $updated_settings );
	}
}
