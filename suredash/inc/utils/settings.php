<?php
/**
 * Settings.
 *
 * @package SureDash
 * @since 0.0.1
 */

namespace SureDashboard\Inc\Utils;

defined( 'ABSPATH' ) || exit;

/**
 * This class will holds the code related to the managing of settings of the plugin.
 *
 * @class Settings
 */
class Settings {
	/**
	 * Cache the DB options
	 *
	 * @since 0.0.1
	 * @access public
	 * @var array<string, mixed>
	 */
	public static $dashboard_options = [];

	/**
	 * Returns all default portal settings.
	 *
	 * @return array<string, array<string, mixed>>
	 * @since 0.0.1
	 */
	public static function get_settings_dataset() {

		return apply_filters(
			'suredashboard_settings_dataset',
			[
				// Branding settings.
				'portal_name'                          => [
					'default' => get_bloginfo( 'name' ),
					'type'    => 'string',
				],
				'logo_url'                             => [
					'default' => '',
					'type'    => 'string',
				],
				'hide_branding'                        => [
					'default' => false,
					'type'    => 'boolean',
				],

				// Performance settings.
				'feeds_per_page'                       => [
					'default' => 5,
					'type'    => 'number',
				],
				'user_upload_limit'                    => [
					'default' => 2,
					'type'    => 'number',
				],
				'profile_upload_limit'                 => [
					'default' => 2,
					'type'    => 'number',
				],
				'bypass_wp_interactions'               => [
					'default' => true,
					'type'    => 'boolean',
				],

				// Community settings.
				'hidden_community'                     => [
					'default' => true,
					'type'    => 'boolean',
				],
				'enable_lightbox'                      => [
					'default' => true,
					'type'    => 'boolean',
				],
				'preserve_excerpt_html'                => [
					'default' => true,
					'type'    => 'boolean',
				],
				// Social settings.
				'google_token_id'                      => [
					'default' => '',
					'type'    => 'string',
				],
				'google_token_secret'                  => [
					'default' => '',
					'type'    => 'string',
				],
				'facebook_token_id'                    => [
					'default' => '',
					'type'    => 'string',
				],
				'facebook_token_secret'                => [
					'default' => '',
					'type'    => 'string',
				],
				'recaptcha_site_key_v2'                => [
					'default' => '',
					'type'    => 'string',
				],
				'recaptcha_secret_key_v2'              => [
					'default' => '',
					'type'    => 'string',
				],
				'recaptcha_site_key_v3'                => [
					'default' => '',
					'type'    => 'string',
				],
				'recaptcha_secret_key_v3'              => [
					'default' => '',
					'type'    => 'string',
				],
				'turnstile_site_key'                   => [
					'default' => '',
					'type'    => 'string',
				],
				'turnstile_secret_key'                 => [
					'default' => '',
					'type'    => 'string',
				],
				'giphy_api_key'                        => [
					'default' => '',
					'type'    => 'string',
				],

				// Layout settings.
				'global_layout'                        => [
					'default' => 'normal',
					'type'    => 'string',
				],
				'global_layout_style'                  => [
					'default' => 'boxed',
					'type'    => 'string',
				],
				'narrow_container_width'               => [
					'default' => 600,
					'type'    => 'number',
				],
				'normal_container_width'               => [
					'default' => 800,
					'type'    => 'number',
				],
				'container_padding'                    => [
					'default' => 20,
					'type'    => 'number',
				],
				'aside_navigation_width'               => [
					'default' => 300,
					'type'    => 'number',
				],

				// Miscellaneous settings.
				'portal_as_homepage'                   => [
					'default' => false,
					'type'    => 'boolean',
				],
				'home_page'                            => [
					'default' => 'default',
					'type'    => 'string',
				],
				'login_page'                           => [
					'default' => [],
					'type'    => 'array',
				],
				'register_page'                        => [
					'default' => [],
					'type'    => 'array',
				],
				'override_wp_registration'             => [
					'default' => false,
					'type'    => 'boolean',
				],
				'first_space'                          => [
					'default' => 0,
					'type'    => 'number',
				],

				// Color & Palette settings.
				'default_palette'                      => [
					'default' => 'light',
					'type'    => 'string',
				],
				'color_palette'                        => [
					'default' => suredash_get_color_palette_defaults(),
					'type'    => 'array',
				],
				'accent_color'                         => [
					'default' => '#2563EB',
					'type'    => 'string',
				],
				'link_color'                           => [
					'default' => '#2563EB',
					'type'    => 'string',
				],
				'link_active_color'                    => [
					'default' => '#2563EB',
					'type'    => 'string',
				],
				'heading_color'                        => [
					'default' => '#191b1F',
					'type'    => 'string',
				],
				'text_color'                           => [
					'default' => '#19283A',
					'type'    => 'string',
				],
				'primary_color'                        => [
					'default' => '#FFFFFF',
					'type'    => 'string',
				],
				'secondary_color'                      => [
					'default' => '#F7F9FA',
					'type'    => 'string',
				],
				'content_bg_color'                     => [
					'default' => '#FFFFFF',
					'type'    => 'string',
				],
				'border_color'                         => [
					'default' => '#E4E7EB',
					'type'    => 'string',
				],
				'selection_color'                      => [
					'default' => '#2563EB',
					'type'    => 'string',
				],
				'primary_button_color'                 => [
					'default' => '#FFFFFF',
					'type'    => 'string',
				],
				'primary_button_background_color'      => [
					'default' => '#4338CA',
					'type'    => 'string',
				],
				'secondary_button_color'               => [
					'default' => '#020617',
					'type'    => 'string',
				],
				'secondary_button_background_color'    => [
					'default' => '#FFFFFF',
					'type'    => 'string',
				],

				// Typography settings.
				'font_family'                          => [
					'default' => 'Figtree',
					'type'    => 'string',
				],

				// Label texts settings.
				'home_text'                            => [
					'default' => __( 'Home', 'suredash' ),
					'type'    => 'string',
				],
				'welcome_text'                         => [
					'default' => __( 'Howdy,', 'suredash' ),
					'type'    => 'string',
				],
				'feeds_label'                          => [
					'default' => __( 'Latest Posts', 'suredash' ),
					'type'    => 'string',
				],
				'your_bookmarks_text'                  => [
					'default' => __( 'Your Bookmarks', 'suredash' ),
					'type'    => 'string',
				],
				'profile_information_text'             => [
					'default' => __( 'Profile Information', 'suredash' ),
					'type'    => 'string',
				],
				'pinned_post_text'                     => [
					'default' => __( 'Pinned Post', 'suredash' ),
					'type'    => 'string',
				],
				'no_posts_found'                       => [
					'default' => __( 'No posts found yet. Stay tuned — new content will be shared here soon!', 'suredash' ),
					'type'    => 'string',
				],
				'login_or_join'                        => [
					'default' => __( 'To add to the discussion, join the mastermind.', 'suredash' ),
					'type'    => 'string',
				],
				'start_writing_post_text'              => [
					'default' => __( 'What would you like to share today?', 'suredash' ),
					'type'    => 'string',
				],
				'write_a_post_text'                    => [
					'default' => __( 'Create New Post', 'suredash' ),
					'type'    => 'string',
				],
				'no_discussion_found'                  => [
					'default' => __( 'No post found, create a new one!', 'suredash' ),
					'type'    => 'string',
				],
				'restricted_content_heading_text'      => [
					'default' => __( 'Restricted Content', 'suredash' ),
					'type'    => 'string',
				],
				'restricted_content_notice_text'       => [
					'default' => __( 'This content is not available with your current membership.', 'suredash' ),
					'type'    => 'string',
				],

				// Roles & Access settings.
				'profile_links'                        => [
					'default' => [
						[
							'title' => __( 'View Profile', 'suredash' ),
							'icon'  => 'Contact',
							'slug'  => 'user-view',
							'link'  => '/{portal_slug}/{portal_view_profile}/',
						],
						[
							'title' => __( 'Edit Profile', 'suredash' ),
							'icon'  => 'UserPen',
							'slug'  => 'user-profile',
							'link'  => '/{portal_slug}/user-profile/',
						],
						[
							'title' => __( 'Bookmarks', 'suredash' ),
							'icon'  => 'Bookmark',
							'slug'  => 'bookmarks',
							'link'  => '/{portal_slug}/bookmarks/',
						],
					],
					'type'    => 'array',
				],
				'profile_logout_link'                  => [
					'default' => [
						'title'     => __( 'Log out', 'suredash' ),
						'icon'      => 'LogOut',
						'slug'      => 'logout',
						'link'      => '{portal_logout_url}',
						'fixed_url' => true,
					],
					'type'    => 'array',
				],
				'user_capability'                      => [
					'default'   => [
						[
							'id'   => 'suredash_user',
							'name' => __( 'SureDash User', 'suredash' ),
						],
					],
					'type'      => 'array',
					'mandatory' => true,
				],

				// Email settings.
				'email_from_mail_id'                   => [
					'default' => get_bloginfo( 'admin_email' ),
					'type'    => 'email',
				],
				'forgot_password_mail_body'            => [
					'default' => suredash_forgot_password_mail_body(),
					'type'    => 'html',
				],

				'usage_tracking'                       => [
					'default' => get_option( 'suredash_usage_optin', 'no' ),
					'type'    => 'string',
				],

				// Custom CSS settings.
				'custom_css'                           => [
					'default' => '',
					'type'    => 'css',
				],

				// Integration settings.
				'surecart_customer_dashboard_space'    => [
					'default' => 0,
					'type'    => 'number',
				],
				'suredash_register_user_access_groups' => [
					'default' => [],
					'type'    => 'array',
				],

				// Gamification settings.
				'user_badges'                          => [
					'default' => [
						[
							'id'              => suredash_get_unique_id(),
							'name'            => __( 'Newbie', 'suredash' ),
							'icon'            => 'CircleStar',
							'color'           => '#111827',
							'background'      => '#D8B4FE',
							'icon_visibility' => '1',
							'sm_access_rule'  => [],
						],
						[
							'id'              => suredash_get_unique_id(),
							'name'            => __( 'Manager', 'suredash' ),
							'icon'            => 'UserStar',
							'color'           => '#FFFFFF',
							'background'      => '#4338CA',
							'icon_visibility' => '1',
							'sm_access_rule'  => [],
						],
						[
							'id'              => suredash_get_unique_id(),
							'name'            => __( 'Active Member', 'suredash' ),
							'icon'            => 'Gem',
							'color'           => '#111827',
							'background'      => '#FEF08A',
							'icon_visibility' => '1',
							'sm_access_rule'  => [],
						],
					],
					'type'    => 'array',
				],

				// Global Sidebar.
				'global_sidebar_widgets'               => [
					'default' => [],
					'type'    => 'array',
				],
			]
		);
	}

	/**
	 * Returns an option from the default options.
	 *
	 * @param  string $key     The option key.
	 * @param  mixed  $default Option default value if option is not available.
	 * @return mixed   Returns the option value
	 *
	 * @since 0.0.1
	 */
	public static function get_default_option( $key, $default = false ) {
		$default_settings = self::get_default_settings();

		if ( ! is_array( $default_settings ) || ! array_key_exists( $key, $default_settings ) || empty( $default_settings ) ) {
			return $default;
		}

		return $default_settings[ $key ];
	}

	/**
	 * As per the settings dataset, return the default settings.
	 *
	 * @return array<string, mixed>
	 * @since 0.0.1
	 */
	public static function get_default_settings() {
		$settings_dataset = self::get_settings_dataset();

		$default_settings = [];

		foreach ( $settings_dataset as $key => $value ) {
			$default_settings[ $key ] = $value['default'];
		}

		return $default_settings;
	}

	/**
	 * Returns all portal settings.
	 *
	 * @param bool $use_cache Whether to use cached settings.
	 *
	 * @return array<string, mixed>
	 * @since 0.0.1
	 */
	public static function get_suredash_settings( $use_cache = true ) {
		if ( $use_cache && ! empty( self::$dashboard_options ) ) {
			return self::$dashboard_options;
		}

		$db_option = self::get_settings();
		$db_option = self::sync_bsf_analytics_setting( $db_option );

		$defaults = apply_filters( 'suredashboard_dashboard_rest_options', self::get_default_settings() );

		self::$dashboard_options = wp_parse_args( $db_option, $defaults );

		return self::$dashboard_options;
	}

	/**
	 * Sync BSF Analytics Setting.
	 *
	 * @param array<string, mixed> $options options.
	 * @since 0.0.5
	 * @return array<string, mixed>
	 */
	public static function sync_bsf_analytics_setting( $options ) {

		$usage_tracking            = get_option( 'suredash_usage_optin', 'no' );
		$options['usage_tracking'] = $usage_tracking === 'yes' ? '1' : '';

		return $options;
	}

	/**
	 * Returns all portal settings.
	 * Note: Fallback function as get_portal_settings() is deprecated.
	 *
	 * @return array<string, mixed>
	 * @since 0.0.1
	 */
	public static function get_portal_settings() {
		return self::get_suredash_settings();
	}

	/**
	 * Update portal all settings.
	 *
	 * @param array<string, mixed> $settings The settings to update.
	 * @return void
	 * @since 0.0.1
	 */
	public static function update_suredash_settings( $settings ): void {

		// Get old settings before updating (for comparison).
		$old_settings = self::get_settings();

		// Merge new settings with existing settings to preserve unmodified settings.
		$settings = array_merge( $old_settings, $settings );

		$settings = self::encrypt_keys( $settings );
		update_option( SUREDASHBOARD_SETTINGS, $settings );

		// Run Font Manager if the font has changed.
		do_action( 'suredash_process_fonts', $settings['font_family'] ?? '' ); // Pass the new font family.

		// Fire action hook with old and new settings for comparison.
		do_action( 'suredash_settings_updated', $old_settings, $settings );

		// Flush the rewrite rules.
		flush_rewrite_rules();
	}

	/**
	 * Encrypt the keys of the settings array.
	 *
	 * @param array<string, mixed> $settings The settings to encrypt.
	 * @return array<string, mixed>
	 * @since 0.0.1
	 */
	public static function encrypt_keys( $settings ) {

		$keys_to_encrypt = [
			'google_token_id',
			'google_token_secret',
			'facebook_token_id',
			'facebook_token_secret',
			'recaptcha_site_key_v2',
			'recaptcha_secret_key_v2',
			'recaptcha_site_key_v3',
			'recaptcha_secret_key_v3',
			'turnstile_site_key',
			'turnstile_secret_key',
			'giphy_api_key',
		];

		if ( ! is_array( $settings ) || empty( $settings ) ) {
			return $settings;
		}

		foreach ( $keys_to_encrypt as $key ) {
			if ( array_key_exists( $key, $settings ) ) {
				$settings[ $key ] = base64_encode( $settings[ $key ] );
			}
		}

		return $settings;
	}

	/**
	 * Decrypt the keys of the settings array.
	 *
	 * @param array<string, mixed> $settings The settings to decrypt.
	 * @return array<string, mixed>
	 * @since 0.0.1
	 */
	public static function decrypt_keys( $settings ) {
		$keys_to_decrypt = [
			'google_token_id',
			'google_token_secret',
			'facebook_token_id',
			'facebook_token_secret',
			'recaptcha_site_key_v2',
			'recaptcha_secret_key_v2',
			'recaptcha_site_key_v3',
			'recaptcha_secret_key_v3',
			'turnstile_site_key',
			'turnstile_secret_key',
			'giphy_api_key',
		];

		if ( ! is_array( $settings ) || empty( $settings ) ) {
			return $settings;
		}

		foreach ( $keys_to_decrypt as $key ) {
			if ( array_key_exists( $key, $settings ) && ! empty( $settings[ $key ] ) ) {
				$decoded = base64_decode( $settings[ $key ], true );
				// Decode only if valid base64.
				if ( $decoded !== false ) {
					$settings[ $key ] = $decoded;
				}
			}
		}

		return $settings;
	}

	/**
	 * Decrypt the keys of the settings array.
	 *
	 * @return array<string, mixed>
	 * @since 0.0.1
	 */
	public static function get_settings() {
		// Adjust this option key to match your plugin's saved settings.
		$settings = get_option( SUREDASHBOARD_SETTINGS, [] );

		// Decrypt sensitive keys.
		return self::decrypt_keys( $settings );
	}

	/**
	 * Get the type of the setting.
	 *
	 * @param string $key The setting key.
	 * @return string
	 * @since 0.0.1
	 */
	public static function get_setting_type( $key ) {
		$settings_dataset = self::get_settings_dataset();

		if ( ! is_array( $settings_dataset ) || ! array_key_exists( $key, $settings_dataset ) ) {
			return 'string';
		}

		return $settings_dataset[ $key ]['type'];
	}
}
