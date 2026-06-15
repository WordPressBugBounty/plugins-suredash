<?php
/**
 * Post Router Initialize.
 *
 * @package SureDashboard
 */

namespace SureDashboard\Core\Routers;

use SureDashboard\Inc\Services\AI_Portal_Generator;
use SureDashboard\Inc\Traits\Get_Instance;
use SureDashboard\Inc\Traits\Rest_Errors;
use SureDashboard\Inc\Utils\Helper;
use SureDashboard\Inc\Utils\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class Onboarding.
 */
class Onboarding {
	use Get_Instance;
	use Rest_Errors;

	/**
	 * Save the onboarding settings.
	 *
	 * @param \WP_REST_Request $request  Request object.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function skip_onboarding( $request ): void {
		$nonce = (string) $request->get_header( 'X-WP-Nonce' );
		if ( ! wp_verify_nonce( sanitize_text_field( $nonce ), 'wp_rest' ) ) {
			wp_send_json_error( [ 'message' => $this->get_rest_event_error( 'nonce' ) ] );
		}

		$valid_steps     = [ 'welcome', 'setup_community', 'choose_community_type', 'create_portal', 'integration', 'optin' ];
		$skipped_on_step = sanitize_text_field( (string) $request->get_param( 'skipped_on_step' ) );
		if ( ! empty( $skipped_on_step ) && in_array( $skipped_on_step, $valid_steps, true ) ) {
			update_option( 'suredash_onboarding_skipped_step', $skipped_on_step );
		}

		update_option( 'suredash_onboarding_skipped', 'yes' );

		wp_send_json_success( [ 'message' => __( 'Onboarding skipped successfully.', 'suredash' ) ] );
	}

	/**
	 * Save the onboarding settings.
	 *
	 * @param \WP_REST_Request $request  Request object.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function complete_onboarding( $request ): void {
		$nonce = (string) $request->get_header( 'X-WP-Nonce' );
		if ( ! wp_verify_nonce( sanitize_text_field( $nonce ), 'wp_rest' ) ) {
			wp_send_json_error( [ 'message' => $this->get_rest_event_error( 'nonce' ) ] );
		}

		update_option( 'suredash_onboarding_completed', 'yes' );

		wp_send_json_success( [ 'message' => __( 'Onboarding completed successfully.', 'suredash' ) ] );
	}

	/**
	 * Save the onboarding settings.
	 *
	 * @param \WP_REST_Request $request  Request object.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function process_onboarding( $request ): void {
		$nonce = (string) $request->get_header( 'X-WP-Nonce' );
		if ( ! wp_verify_nonce( sanitize_text_field( $nonce ), 'wp_rest' ) ) {
			wp_send_json_error( [ 'message' => $this->get_rest_event_error( 'nonce' ) ] );
		}

		$action = ! empty( $_POST['action'] ) ? sanitize_text_field( wp_unslash( $_POST['action'] ) ) : '';

		switch ( $action ) {
			case 'setup_community':
				$portal_name              = ! empty( $_POST['portal_name'] ) ? sanitize_text_field( wp_unslash( $_POST['portal_name'] ) ) : '';
				$hidden_community         = ! empty( $_POST['hidden_community'] ) && sanitize_text_field( $_POST['hidden_community'] ) === 'on';
				$portal_as_homepage       = ! empty( $_POST['portal_as_homepage'] ) && sanitize_text_field( $_POST['portal_as_homepage'] ) === 'on';
				$override_wp_registration = ! empty( $_POST['override_wp_registration'] ) && sanitize_text_field( $_POST['override_wp_registration'] ) === 'on';

				$settings                             = Settings::get_suredash_settings();
				$settings['portal_name']              = $portal_name;
				$settings['hidden_community']         = $hidden_community;
				$settings['portal_as_homepage']       = $portal_as_homepage;
				$settings['override_wp_registration'] = $override_wp_registration;
				update_option( SUREDASHBOARD_SETTINGS, $settings );
				break;
			case 'scaffold_portal':
				$this->handle_scaffold_portal();
				break;
			case 'plugin_integrations':
				$required_plugins_list = ! empty( $_POST['required_plugins'] ) ? json_decode( stripslashes( sanitize_text_field($_POST['required_plugins']) ), true ) : []; // phpcs:ignore
				$required_plugins      = $this->get_required_plugins( $required_plugins_list );
				wp_send_json_success( [ 'required_plugins' => $required_plugins ] );
				break; // @phpstan-ignore-line
			case 'optin':
				$this->subscribe_to_suredash();
				update_option( 'suredash_onboarding_completed', 'yes' );
				break;
			default:
				wp_send_json_success();
		}

		wp_send_json_success( [ 'message' => __( 'Onboarding data updated successfully.', 'suredash' ) ] );
	}

	/**
	 * Generate a portal scaffold from a free-text prompt via OpenAI or Anthropic.
	 *
	 * The provider API key is single-use: it's read from the request, passed
	 * to the provider for one call, and discarded. It is never written to the
	 * database, transients, or logs.
	 *
	 * Response shape matches the payload that the existing `scaffold_portal`
	 * action consumes, so the client can hand the result straight back to
	 * `/process-onboarding` once the admin confirms the preview.
	 *
	 * @param \WP_REST_Request $request Request object.
	 *
	 * @since 1.9.2
	 * @return void
	 */
	public function ai_generate_portal( $request ): void {
		$nonce = (string) $request->get_header( 'X-WP-Nonce' );
		if ( ! wp_verify_nonce( sanitize_text_field( $nonce ), 'wp_rest' ) ) {
			wp_send_json_error( [ 'message' => $this->get_rest_event_error( 'nonce' ) ] );
		}

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce verified above.
		$provider = ! empty( $_POST['provider'] ) ? sanitize_key( wp_unslash( $_POST['provider'] ) ) : '';
		// API key is an opaque secret typed verbatim by the admin. Sanitizers
		// like `sanitize_text_field` would mangle characters legitimately
		// present in some provider keys, so we only `wp_unslash` + `trim`
		// here and never persist the value.
		$api_key        = ! empty( $_POST['api_key'] ) ? trim( (string) wp_unslash( $_POST['api_key'] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- raw secret, see comment above.
		$prompt         = ! empty( $_POST['prompt'] ) ? sanitize_textarea_field( wp_unslash( $_POST['prompt'] ) ) : '';
		$community_type = ! empty( $_POST['community_type'] ) ? sanitize_key( wp_unslash( $_POST['community_type'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		// A pasted key wins when present (paste-a-key override for OpenAI /
		// Anthropic only). Otherwise dispatch through the WordPress
		// Connectors API so any registered AI provider — OpenAI, Anthropic,
		// Google, Vercel, or anything a plugin registers — just works.
		if ( $api_key !== '' ) {
			$result = AI_Portal_Generator::get_instance()->generate( $provider, $api_key, $prompt, $community_type );
		} else {
			$result = AI_Portal_Generator::get_instance()->generate_via_connector( $provider, $prompt, $community_type );
		}

		// Help the GC release the key buffer.
		unset( $api_key );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error(
				[
					'code'    => $result->get_error_code(),
					'message' => $result->get_error_message(),
				]
			);
		}

		wp_send_json_success( $result );
	}

	/**
	 * Discover AI providers registered via the WordPress 7 Connectors API
	 * (Settings → Connectors). Returns a client-safe list — connector id,
	 * display name, description, logo, and a flag for whether the key is
	 * configured. Never returns the key value.
	 *
	 * @param \WP_REST_Request $request Request object.
	 *
	 * @since 1.9.2
	 * @return void
	 */
	public function ai_discover_credentials( $request ): void {
		$nonce = (string) $request->get_header( 'X-WP-Nonce' );
		if ( ! wp_verify_nonce( sanitize_text_field( $nonce ), 'wp_rest' ) ) {
			wp_send_json_error( [ 'message' => $this->get_rest_event_error( 'nonce' ) ] );
		}

		$providers = AI_Portal_Generator::get_instance()->discover_providers();

		wp_send_json_success( [ 'providers' => $providers ] );
	}

	/**
	 * Subscribe to SureDash.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function subscribe_to_suredash(): void {

		// phpcs:disable WordPress.Security.NonceVerification
		$user_email   = isset( $_POST['user_email'] ) ? sanitize_email( $_POST['user_email'] ) : '';
		$first_name   = isset( $_POST['first_name'] ) ? sanitize_text_field( $_POST['first_name'] ) : '';
		$last_name    = isset( $_POST['last_name'] ) ? sanitize_text_field( $_POST['last_name'] ) : '';
		$is_subscribe = isset( $_POST['subscribe_to_newsletter'] ) && sanitize_text_field( $_POST['subscribe_to_newsletter'] ) === 'on' ? true : false;
		$share_data   = isset( $_POST['share_non_sensitive_data'] ) && sanitize_text_field( $_POST['share_non_sensitive_data'] ) === 'on' ? true : false;
		// phpcs:enable WordPress.Security.NonceVerification

		// Set BSF analytics optin based on user choice.
		if ( $share_data ) {
			update_option( 'suredash_usage_optin', 'yes' );
		} else {
			update_option( 'suredash_usage_optin', 'no' );
		}

		if ( ! $is_subscribe ) {
			return;
		}

		$domain = wp_parse_url( home_url(), PHP_URL_HOST );
		if ( ! is_string( $domain ) ) {
			$domain = '';
		}

		$url  = 'https://metrics.brainstormforce.com/wp-json/bsf-metrics-server/v1/subscribe/';
		$body = wp_json_encode(
			[
				'email'      => $user_email,
				'first_name' => $first_name,
				'last_name'  => $last_name,
				'domain'     => $domain,
				'source'     => 'suredash',
			]
		);
		if ( $body === false ) {
			wp_send_json_error( [ 'message' => __( 'Failed to encode subscription payload.', 'suredash' ) ] );
		}

		$args = [
			'headers' => [
				'Content-Type' => 'application/json',
			],
			'body'    => $body,
		];

		$response = wp_safe_remote_post( $url, $args );

		if ( ! is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) === 200 ) {
			$response = json_decode( wp_remote_retrieve_body( $response ), true );

			if ( isset( $response['success'] ) && $response['success'] ) {
				update_user_meta( get_current_user_ID(), 'suredash-subscribed', 'yes' );
			}
		}
		wp_send_json_success( $response );
	}

	/**
	 * Get the list of required plugins.
	 *
	 * @param array<array<string, string>> $required_plugins_list List of required plugins.
	 *
	 * @since 1.0.0
	 * @return array<string, array<int<0, max>, array<string, string>>>
	 */
	public function get_required_plugins( $required_plugins_list ): array {

		$required_plugins = [
			'installed'     => [],
			'not_installed' => [],
			'inactive'      => [],
		];

		if ( is_array( $required_plugins_list ) && ! empty( $required_plugins_list ) ) {
			foreach ( $required_plugins_list as $plugin ) {

				if ( $this->is_plugin_installed( $plugin ) ) {
					if ( $this->check_is_plugin_active( $plugin ) ) {
						$required_plugins['installed'][] = $plugin;
					} else {
						$required_plugins['inactive'][] = $plugin;
					}
				} else {
					$required_plugins['not_installed'][] = $plugin;
				}
			}
		}

		return $required_plugins;
	}

	/**
	 * Check if a plugin is installed.
	 *
	 * @param array<string, string> $plugin_path Plugin path.
	 *
	 * @since 1.0.0
	 * @return bool
	 */
	public function is_plugin_installed( $plugin_path ): bool {

		if ( file_exists( WP_PLUGIN_DIR . '/' . $plugin_path['init'] ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Check if a plugin is active.
	 *
	 * @param array<string, string> $plugin_path Plugin path.
	 *
	 * @since 1.0.0
	 * @return bool
	 */
	public function check_is_plugin_active( $plugin_path ): bool {

		if ( ! function_exists( 'is_plugin_active' ) ) {
			include_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		if ( is_plugin_active( $plugin_path['init'] ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Activate plugin.
	 *
	 * @param \WP_REST_Request $request  Request object.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function activate_plugin( $request ): void {

		$nonce = (string) $request->get_header( 'X-WP-Nonce' );
		if ( ! wp_verify_nonce( sanitize_text_field( $nonce ), 'wp_rest' ) ) {
			wp_send_json_error( [ 'message' => $this->get_rest_event_error( 'nonce' ) ] );
		}

		$plugin_path = ! empty( $_POST['plugin_init'] ) ? sanitize_text_field( wp_unslash( $_POST['plugin_init'] ) ) : '';
		$plugin_slug = ! empty( $_POST['plugin_slug'] ) ? sanitize_text_field( wp_unslash( $_POST['plugin_slug'] ) ) : '';

		if ( empty( $plugin_path ) ) {
			wp_send_json_error( [ 'message' => __( 'Invalid plugin.', 'suredash' ) ] );
		}

		// Validate plugin path to prevent path traversal.
		if ( validate_file( $plugin_path ) !== 0 || ! file_exists( WP_PLUGIN_DIR . '/' . $plugin_path ) ) {
			wp_send_json_error( [ 'message' => __( 'Invalid plugin path.', 'suredash' ) ] );
		}

		if ( ! function_exists( 'activate_plugin' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$activate = activate_plugin( $plugin_path );

		do_action( 'suredash_after_plugin_activation', $plugin_path, $plugin_slug );

		if ( is_wp_error( $activate ) ) {
			wp_send_json_error( [ 'message' => $activate->get_error_message() ] );
		}

		wp_send_json_success( [ 'message' => __( 'Plugin activated successfully.', 'suredash' ) ] );
	}

	/**
	 * Scaffold an entire portal from community type and selected spaces.
	 *
	 * Creates a group, then loops through the selected spaces and creates
	 * each one as a published space. For discussion spaces, also creates
	 * the associated forum category.
	 *
	 * @since 1.9.2
	 * @return void
	 */
	private function handle_scaffold_portal(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce verified in process_onboarding().
		$community_type = ! empty( $_POST['community_type'] ) ? sanitize_text_field( wp_unslash( $_POST['community_type'] ) ) : 'custom';
		$spaces_json    = ! empty( $_POST['spaces'] ) ? sanitize_text_field( wp_unslash( $_POST['spaces'] ) ) : '[]';
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		/**
		 * Decoded payload: [{ id, name, type, portal_page_target? }, ...].
		 *
		 * @var array<int, array{id?: string, name: string, type: string, portal_page_target?: string}> $spaces
		 */
		$spaces = json_decode( $spaces_json, true );

		if ( ! is_array( $spaces ) || empty( $spaces ) ) {
			wp_send_json_error( [ 'message' => __( 'No spaces selected.', 'suredash' ) ] );
		}

		$backend = Backend::get_instance();

		// Fallback group used for spaces that don't carry a group field
		// (legacy preset templates, or AI responses that drop the field).
		$default_group_name = $this->get_group_name_for_community_type( $community_type );

		// Lazy group cache — only create groups that actually get used by a
		// space we manage to insert. Keyed by lowercased + trimmed group name
		// so casing/whitespace variations from the AI don't fragment groups.
		$group_ids = [];

		$created_spaces      = [];
		$pro_types           = [ 'course', 'resource_library', 'collection', 'events' ];
		$is_pro              = function_exists( 'suredash_is_pro_active' ) && suredash_is_pro_active();
		$portal_page_targets = Helper::get_portal_page_targets();
		$known_target_keys   = array_keys( $portal_page_targets );

		foreach ( $spaces as $space ) {
			if ( empty( $space['name'] ) || empty( $space['type'] ) ) {
				continue;
			}

			$space_name  = sanitize_text_field( $space['name'] );
			$space_type  = sanitize_text_field( $space['type'] );
			$template_id = ! empty( $space['id'] ) ? sanitize_key( $space['id'] ) : '';

			// Skip Pro space types if Pro is not active.
			if ( in_array( $space_type, $pro_types, true ) && ! $is_pro ) {
				continue;
			}

			// Validate Portal Page target. Skip silently if missing/invalid or if the target is Pro-only and Pro is inactive.
			$portal_page_target = '';
			if ( $space_type === 'portal_page' ) {
				$portal_page_target = ! empty( $space['portal_page_target'] )
					? sanitize_key( $space['portal_page_target'] )
					: '';

				if ( ! in_array( $portal_page_target, $known_target_keys, true ) ) {
					continue;
				}

				if ( ! empty( $portal_page_targets[ $portal_page_target ]['pro'] ) && ! $is_pro ) {
					continue;
				}
			}

			// Resolve the group for this space. Per-space `group` field wins;
			// otherwise fall back to the community-type derived default.
			$space_group_raw = isset( $space['group'] ) && is_string( $space['group'] )
				? sanitize_text_field( $space['group'] )
				: '';
			$space_group     = $space_group_raw !== '' ? $space_group_raw : $default_group_name;
			$group_key       = strtolower( trim( $space_group ) );

			if ( ! isset( $group_ids[ $group_key ] ) ) {
				$created_group_id = $backend->create_portal_group( $space_group );
				if ( ! $created_group_id ) {
					// Group creation failed — skip this space rather than
					// orphaning it. We do not bail on the whole request because
					// other groups may still create successfully.
					continue;
				}
				$group_ids[ $group_key ] = $created_group_id;
			}
			$group_id = $group_ids[ $group_key ];

			$space_post_args = [
				'post_title'  => $space_name,
				'post_name'   => sanitize_title( $space_name ),
				'post_type'   => SUREDASHBOARD_POST_TYPE,
				'post_status' => 'publish',
				'post_author' => get_current_user_id(),
			];

			// For Single Page spaces, seed the space's own post_content so the page
			// is not empty on first visit. The space editor (Gutenberg) reads this field.
			if ( $space_type === 'single_post' ) {
				$space_post_args['post_content'] = $this->get_single_page_seed_content( $template_id, $space_name );
			}

			$post_id = sd_wp_insert_post( $space_post_args );

			if ( is_wp_error( $post_id ) ) {
				continue;
			}

			wp_set_post_terms( $post_id, [ $group_id ], SUREDASHBOARD_TAXONOMY );
			$backend->update_link_order_term( $post_id, $group_id );
			sd_update_post_meta( $post_id, 'integration', $space_type );

			// Portal Page spaces use the target's icon; other types fall back to the type-level mapping.
			if ( $space_type === 'portal_page' && $portal_page_target ) {
				sd_update_post_meta( $post_id, 'portal_page_target', $portal_page_target );
				$icon = $portal_page_targets[ $portal_page_target ]['icon'] ?? $this->get_icon_for_space_type( $space_type );
			} else {
				$icon = $this->get_icon_for_space_type( $space_type );
			}
			sd_update_post_meta( $post_id, 'item_emoji', $icon );

			// For discussion spaces, create the forum category + seed first feed post.
			if ( $space_type === 'posts_discussion' ) {
				$feed_group_id = $backend->create_forum_category( $post_id, $space_name );
				if ( $feed_group_id ) {
					sd_update_post_meta( $post_id, 'feed_group_id', $feed_group_id );
					$this->seed_discussion_post( $feed_group_id, $template_id, $space_name );
				}
			}

			$created_spaces[] = [
				'space_id' => $post_id,
				'name'     => $space_name,
				'type'     => $space_type,
			];
		}

		if ( empty( $created_spaces ) ) {
			wp_send_json_error( [ 'message' => __( 'Could not create any spaces.', 'suredash' ) ] );
		}

		// Store the community type for analytics.
		update_option( 'suredash_community_type', $community_type );

		wp_send_json_success(
			[
				'message'        => sprintf(
					/* translators: %d: number of spaces created */
					__( '%d spaces created successfully.', 'suredash' ),
					count( $created_spaces )
				),
				'created_spaces' => $created_spaces,
			]
		);
	}

	/**
	 * Get the group name based on community type.
	 *
	 * @since 1.9.2
	 * @param string $community_type The selected community type.
	 * @return string Group name.
	 */
	private function get_group_name_for_community_type( string $community_type ): string {
		$names = [
			'course_academy'       => __( 'Academy', 'suredash' ),
			'membership_community' => __( 'Community', 'suredash' ),
			'support_portal'       => __( 'Support', 'suredash' ),
			'team_intranet'        => __( 'Team', 'suredash' ),
			'creator_community'    => __( 'Community', 'suredash' ),
			'nonprofit_club'       => __( 'Organization', 'suredash' ),
			'custom'               => __( 'General', 'suredash' ),
		];

		return $names[ $community_type ] ?? __( 'General', 'suredash' );
	}

	/**
	 * Get the default icon name for a space type.
	 *
	 * Mirrors the icon mapping used by the in-dashboard "Add Space" popup so
	 * spaces created from onboarding and from the popup look identical.
	 *
	 * @since 1.9.2
	 * @param string $space_type The space integration type.
	 * @return string Icon name.
	 */
	private function get_icon_for_space_type( string $space_type ): string {
		$icons = [
			'posts_discussion' => 'MessagesSquare',
			'single_post'      => 'FileText',
			'link'             => 'Link2',
			'portal_page'      => 'LayoutDashboard',
			'course'           => 'LibraryBig',
			'resource_library' => 'Paperclip',
			'collection'       => 'GalleryVerticalEnd',
			'events'           => 'Calendar',
		];

		return $icons[ $space_type ] ?? 'Link';
	}

	/**
	 * Build block-editor-ready starter content for a Single Page space.
	 *
	 * Stored on the space post's own `post_content` so the Gutenberg editor
	 * shows it when the admin opens the space, and the frontend renders it
	 * straight away (avoids an empty space on first visit).
	 *
	 * @since 1.9.2
	 * @param string $template_id Template id from the onboarding payload (e.g. 'welcome', 'knowledge-base').
	 * @param string $space_name  The space name, used as a fallback in generic copy.
	 * @return string Block-comment-wrapped HTML suitable for `post_content`.
	 */
	private function get_single_page_seed_content( string $template_id, string $space_name ): string {
		$presets = [
			'welcome'        => [
				'heading' => __( 'Welcome 👋', 'suredash' ),
				'body'    => __( 'Glad to have you here. This is your community starter page — share what you do, set expectations, and point new members to the most useful spaces.', 'suredash' ),
			],
			'knowledge-base' => [
				'heading' => __( 'Find answers fast', 'suredash' ),
				'body'    => __( 'Browse the most common questions and how-to guides below. If you cannot find what you need, head over to the Support Forum and our team will help.', 'suredash' ),
			],
		];

		// Map other template ids to the closest preset.
		$aliases = [
			'about-us'      => 'welcome',
			'team-handbook' => 'welcome',
		];
		if ( isset( $aliases[ $template_id ] ) ) {
			$template_id = $aliases[ $template_id ];
		}

		$preset = $presets[ $template_id ] ?? [
			'heading' => sprintf( /* translators: %s: space name */ __( 'About %s', 'suredash' ), $space_name ),
			'body'    => __( 'Use this space to introduce members to what you do and how this section works. Replace this text with anything you like — it lives inside the regular block editor.', 'suredash' ),
		];

		$heading = esc_html( $preset['heading'] );
		$body    = esc_html( $preset['body'] );

		return "<!-- wp:heading -->\n<h2 class=\"wp-block-heading\">{$heading}</h2>\n<!-- /wp:heading -->\n\n<!-- wp:paragraph -->\n<p>{$body}</p>\n<!-- /wp:paragraph -->";
	}

	/**
	 * Create one starter feed post inside a discussion space's forum category.
	 *
	 * Without this, a newly-created discussion space shows an empty feed on
	 * first visit. The starter post is published, authored by the current
	 * user, and tagged with `suredash_seed_content = 1` so it can be
	 * identified later (e.g. by cleanup tools).
	 *
	 * @since 1.9.2
	 * @param int    $feed_group_id Forum category term id returned by `Backend::create_forum_category()`.
	 * @param string $template_id   Template id from the onboarding payload.
	 * @param string $space_name    The space name, used as a fallback in generic copy.
	 * @return void
	 */
	private function seed_discussion_post( int $feed_group_id, string $template_id, string $space_name ): void {
		$presets = [
			'announcements' => [
				'title' => __( '👋 First announcement', 'suredash' ),
				'body'  => __( 'Use this space to share product updates, events, and important notices with your community. Edit or delete this post to start fresh.', 'suredash' ),
			],
			'discussions'   => [
				'title' => __( 'Say hello 👋', 'suredash' ),
				'body'  => __( 'Welcome to the discussion! Introduce yourself, share what brought you here, and feel free to start a new conversation any time.', 'suredash' ),
			],
		];

		$preset = $presets[ $template_id ] ?? [
			'title' => sprintf( /* translators: %s: space name */ __( 'Welcome to %s', 'suredash' ), $space_name ),
			'body'  => __( 'This is the first post in this space. Edit or delete it, then invite members to start chatting.', 'suredash' ),
		];

		$post_id = sd_wp_insert_post(
			[
				'post_title'   => $preset['title'],
				'post_content' => wpautop( $preset['body'] ),
				'post_status'  => 'publish',
				'post_type'    => SUREDASHBOARD_FEED_POST_TYPE,
				'post_author'  => get_current_user_id(),
			]
		);

		if ( is_wp_error( $post_id ) || ! $post_id ) {
			return;
		}

		wp_set_post_terms( $post_id, [ $feed_group_id ], SUREDASHBOARD_FEED_TAXONOMY );
		sd_update_post_meta( $post_id, 'suredash_seed_content', 1 );
	}
}
