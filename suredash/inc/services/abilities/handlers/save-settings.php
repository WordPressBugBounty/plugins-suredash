<?php
/**
 * Save Settings Ability.
 *
 * @package SureDash
 * @since 1.6.3
 */

namespace SureDashboard\Inc\Services\Abilities\Handlers;

use SureDashboard\Core\Routers\Backend as BackendRoute;
use SureDashboard\Inc\Services\Abilities\Ability;

defined( 'ABSPATH' ) || exit;

/**
 * Save_Settings class.
 *
 * @since 1.6.3
 */
class Save_Settings extends Ability {
	/**
	 * Option gate key for ability permission control.
	 *
	 * @since 1.7.3
	 * @var string
	 */
	protected string $gated = 'suredash_abilities_api_edit';

	/**
	 * Get the unique identifier for this ability.
	 *
	 * @since 1.6.3
	 *
	 * @return string
	 */
	public function get_id(): string {
		return 'save-settings';
	}

	/**
	 * Get the human-readable name.
	 *
	 * @since 1.6.3
	 *
	 * @return string
	 */
	public function get_name(): string {
		return __( 'Save Settings', 'suredash' );
	}

	/**
	 * Get the ability description.
	 *
	 * @since 1.6.3
	 *
	 * @return string
	 */
	public function get_description(): string {
		return __( 'Updates global portal settings. Only provided keys are updated — existing settings are preserved. Supported setting groups: BRANDING — portal_name (string), logo_url (string), hide_branding (boolean); LAYOUT — global_layout (string, "normal"|"wide"), global_layout_style (string, "boxed"), font_family (string), narrow_container_width (number, px), normal_container_width (number, px), container_padding (number, px), aside_navigation_width (number, px); COMMUNITY — hidden_community (boolean, true=login required), enable_lightbox (boolean), preserve_excerpt_html (boolean), feeds_per_page (number), user_upload_limit (number, MB), profile_upload_limit (number, MB); PAGES — portal_as_homepage (boolean), home_page (string), login_page (array), register_page (array), override_wp_registration (boolean), first_space (number, post ID); COLORS — default_palette (string, "light"|"dark"|"custom"), accent_color (string, hex), link_color (string, hex), heading_color (string, hex), text_color (string, hex), primary_color (string, hex), secondary_color (string, hex), content_bg_color (string, hex), border_color (string, hex), primary_button_color (string, hex), primary_button_background_color (string, hex), secondary_button_color (string, hex), secondary_button_background_color (string, hex); LABELS — home_text, welcome_text, feeds_label, your_bookmarks_text, pinned_post_text, no_posts_found, start_writing_post_text, write_a_post_text, login_or_join, restricted_content_heading_text, restricted_content_notice_text (all strings); EMAIL — email_from_mail_id (email), forgot_password_mail_body (html); GAMIFICATION — user_badges (array of badge objects with id, name, icon, color, background); CUSTOM CSS — custom_css (string). Use get-settings ability first to see current values.', 'suredash' );
	}

	/**
	 * Get the ability category.
	 *
	 * @since 1.6.3
	 *
	 * @return string
	 */
	public function get_category(): string {
		return 'settings';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_parameters(): array {
		return [
			'settings' => [
				'type'        => 'object',
				'required'    => true,
				'description' => __( 'Object of setting key-value pairs to update. Branding: portal_name (string), logo_url (string URL), hide_branding (boolean). Layout: global_layout ("normal"|"wide"), global_layout_style ("boxed"), font_family (string), narrow_container_width (number px), normal_container_width (number px), container_padding (number px), aside_navigation_width (number px). Community: hidden_community (boolean), enable_lightbox (boolean), feeds_per_page (number), user_upload_limit (number MB), profile_upload_limit (number MB). Pages: portal_as_homepage (boolean), home_page (string), first_space (number post ID). Colors: default_palette ("light"|"dark"|"custom"), accent_color (hex), link_color (hex), heading_color (hex), text_color (hex), primary_color (hex), secondary_color (hex), content_bg_color (hex), border_color (hex), primary_button_color (hex), primary_button_background_color (hex). Labels: home_text, welcome_text, feeds_label, pinned_post_text, no_posts_found, write_a_post_text, login_or_join (all strings). Email: email_from_mail_id (email address). Other: custom_css (string), user_badges (array of badge objects). Example: {"portal_name": "My Community", "accent_color": "#4F46E5", "feeds_per_page": 10}', 'suredash' ),
			],
		];
	}

	/**
	 * Override input schema to document all supported settings keys.
	 *
	 * @return array<string, mixed>
	 */
	public function get_input_schema(): array {
		return [
			'type'       => 'object',
			'required'   => [ 'settings' ],
			'properties' => [
				'settings' => [
					'type'        => 'object',
					'description' => __( 'Object of setting key-value pairs. Only provided keys are updated; existing settings are preserved.', 'suredash' ),
					'properties'  => [
						// Branding.
						'portal_name'                     => [
							'type'        => 'string',
							'description' => __( 'Display name of the portal.', 'suredash' ),
						],
						'logo_url'                        => [
							'type'        => 'string',
							'description' => __( 'URL to the portal logo image.', 'suredash' ),
						],
						'hide_branding'                   => [
							'type'        => 'boolean',
							'description' => __( 'Hide SureDash branding from the portal.', 'suredash' ),
						],

						// Layout.
						'global_layout'                   => [
							'type'        => 'string',
							'enum'        => [ 'normal', 'wide' ],
							'description' => __( 'Portal layout mode.', 'suredash' ),
						],
						'global_layout_style'             => [
							'type'        => 'string',
							'enum'        => [ 'boxed' ],
							'description' => __( 'Layout style.', 'suredash' ),
						],
						'font_family'                     => [
							'type'        => 'string',
							'description' => __( 'Global font family for the portal (e.g., "Figtree", "Inter").', 'suredash' ),
						],
						'narrow_container_width'          => [
							'type'        => 'integer',
							'description' => __( 'Narrow container width in pixels. Default: 600.', 'suredash' ),
						],
						'normal_container_width'          => [
							'type'        => 'integer',
							'description' => __( 'Normal container width in pixels. Default: 800.', 'suredash' ),
						],
						'container_padding'               => [
							'type'        => 'integer',
							'description' => __( 'Container padding in pixels. Default: 20.', 'suredash' ),
						],
						'aside_navigation_width'          => [
							'type'        => 'integer',
							'description' => __( 'Sidebar navigation width in pixels. Default: 300.', 'suredash' ),
						],

						// Community.
						'hidden_community'                => [
							'type'        => 'boolean',
							'description' => __( 'If true, community requires login to access. If false, publicly accessible.', 'suredash' ),
						],
						'enable_lightbox'                 => [
							'type'        => 'boolean',
							'description' => __( 'Enable image lightbox in community posts.', 'suredash' ),
						],
						'preserve_excerpt_html'           => [
							'type'        => 'boolean',
							'description' => __( 'Preserve HTML formatting in post excerpts.', 'suredash' ),
						],
						'feeds_per_page'                  => [
							'type'        => 'integer',
							'description' => __( 'Number of posts per page in feeds. Default: 5.', 'suredash' ),
						],
						'user_upload_limit'               => [
							'type'        => 'integer',
							'description' => __( 'Max file upload size in MB for community posts. Default: 2.', 'suredash' ),
						],
						'profile_upload_limit'            => [
							'type'        => 'integer',
							'description' => __( 'Max file upload size in MB for profile avatar. Default: 2.', 'suredash' ),
						],

						// Pages.
						'portal_as_homepage'              => [
							'type'        => 'boolean',
							'description' => __( 'Use the portal as the site homepage.', 'suredash' ),
						],
						'home_page'                       => [
							'type'        => 'string',
							'description' => __( 'Which space or page is the portal home. "default" for auto.', 'suredash' ),
						],
						'first_space'                     => [
							'type'        => 'integer',
							'description' => __( 'Post ID of the default portal space.', 'suredash' ),
						],
						'override_wp_registration'        => [
							'type'        => 'boolean',
							'description' => __( 'Allow user registration even when WordPress registration is disabled.', 'suredash' ),
						],

						// Colors.
						'default_palette'                 => [
							'type'        => 'string',
							'enum'        => [ 'light', 'dark', 'custom' ],
							'description' => __( 'Active color palette name.', 'suredash' ),
						],
						'accent_color'                    => [
							'type'        => 'string',
							'description' => __( 'Accent color as hex (e.g., "#2563EB").', 'suredash' ),
						],
						'link_color'                      => [
							'type'        => 'string',
							'description' => __( 'Link color as hex.', 'suredash' ),
						],
						'heading_color'                   => [
							'type'        => 'string',
							'description' => __( 'Heading text color as hex.', 'suredash' ),
						],
						'text_color'                      => [
							'type'        => 'string',
							'description' => __( 'Body text color as hex.', 'suredash' ),
						],
						'primary_color'                   => [
							'type'        => 'string',
							'description' => __( 'Primary background color as hex.', 'suredash' ),
						],
						'secondary_color'                 => [
							'type'        => 'string',
							'description' => __( 'Secondary background color as hex.', 'suredash' ),
						],
						'content_bg_color'                => [
							'type'        => 'string',
							'description' => __( 'Content area background color as hex.', 'suredash' ),
						],
						'border_color'                    => [
							'type'        => 'string',
							'description' => __( 'Border color as hex.', 'suredash' ),
						],
						'primary_button_color'            => [
							'type'        => 'string',
							'description' => __( 'Primary button text color as hex.', 'suredash' ),
						],
						'primary_button_background_color' => [
							'type'        => 'string',
							'description' => __( 'Primary button background color as hex.', 'suredash' ),
						],
						'secondary_button_color'          => [
							'type'        => 'string',
							'description' => __( 'Secondary button text color as hex.', 'suredash' ),
						],
						'secondary_button_background_color' => [
							'type'        => 'string',
							'description' => __( 'Secondary button background color as hex.', 'suredash' ),
						],

						// Labels.
						'home_text'                       => [
							'type'        => 'string',
							'description' => __( 'Label for Home navigation. Default: "Home".', 'suredash' ),
						],
						'welcome_text'                    => [
							'type'        => 'string',
							'description' => __( 'Welcome greeting text. Default: "Howdy,".', 'suredash' ),
						],
						'feeds_label'                     => [
							'type'        => 'string',
							'description' => __( 'Label for the feeds section. Default: "Latest Posts".', 'suredash' ),
						],
						'pinned_post_text'                => [
							'type'        => 'string',
							'description' => __( 'Label for pinned post indicator. Default: "Pinned Post".', 'suredash' ),
						],
						'no_posts_found'                  => [
							'type'        => 'string',
							'description' => __( 'Empty state message for posts.', 'suredash' ),
						],
						'write_a_post_text'               => [
							'type'        => 'string',
							'description' => __( 'Button label to create a post. Default: "Create New Post".', 'suredash' ),
						],
						'start_writing_post_text'         => [
							'type'        => 'string',
							'description' => __( 'Placeholder text for new post composer.', 'suredash' ),
						],
						'login_or_join'                   => [
							'type'        => 'string',
							'description' => __( 'CTA text for unauthenticated users.', 'suredash' ),
						],
						'restricted_content_heading_text' => [
							'type'        => 'string',
							'description' => __( 'Heading for restricted content block.', 'suredash' ),
						],
						'restricted_content_notice_text'  => [
							'type'        => 'string',
							'description' => __( 'Message shown for restricted content.', 'suredash' ),
						],

						// Email.
						'email_from_mail_id'              => [
							'type'        => 'string',
							'description' => __( 'From email address for portal emails.', 'suredash' ),
						],

						// Gamification.
						'user_badges'                     => [
							'type'        => 'array',
							'description' => __( 'Community badges. Each item: {id, name, icon, color, background, icon_visibility, sm_access_rule}.', 'suredash' ),
						],

						// Custom CSS.
						'custom_css'                      => [
							'type'        => 'string',
							'description' => __( 'Custom CSS injected into the portal.', 'suredash' ),
						],

						// Usage tracking.
						'usage_tracking'                  => [
							'type'        => 'string',
							'description' => __( 'Usage analytics opt-in. "1" to enable, "" to disable.', 'suredash' ),
						],
					],
					'examples'    => [
						[
							'portal_name'    => 'My Community',
							'accent_color'   => '#4F46E5',
							'feeds_per_page' => 10,
							'welcome_text'   => 'Welcome,',
						],
						[
							'hidden_community' => false,
							'enable_lightbox'  => true,
							'font_family'      => 'Inter',
							'global_layout'    => 'wide',
						],
					],
				],
			],
		];
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_returns(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'message' => [
					'type'        => 'string',
					'description' => __( 'Success or error message.', 'suredash' ),
				],
			],
		];
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_annotations(): array {
		return [
			'readOnlyHint'    => false,
			'destructiveHint' => false,
			'idempotentHint'  => true,
		];
	}

	/**
	 * Get usage instructions for AI agents.
	 *
	 * @since 1.6.3
	 *
	 * @return string
	 */
	public function get_instructions(): string {
		return 'Merges with existing settings — only provided keys are changed, others are preserved. Use get-settings first to see current values. Pass settings as a flat object of key-value pairs.';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param array<string, mixed> $params Validated parameters.
	 */
	public function execute( array $params ): array {
		$settings = $params['settings'];

		if ( ! is_array( $settings ) ) {
			return [
				'success' => false,
				'data'    => [ 'message' => __( 'Settings must be an object.', 'suredash' ) ],
			];
		}

		$this->setup_post_data(
			[
				'settings' => wp_json_encode( $settings ),
			]
		);

		$request = $this->build_request();
		$result  = $this->call_json_handler(
			[ BackendRoute::get_instance(), 'save_settings' ],
			$request
		);

		$this->cleanup_post_data( [ 'settings' ] );

		return $result;
	}
}
