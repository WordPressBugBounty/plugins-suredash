<?php
/**
 * Post Router Initialize.
 *
 * @package SureDashboard
 */

namespace SureDashboard\Core\Routers;

use SureDashboard\Inc\Traits\Get_Instance;
use SureDashboard\Inc\Traits\Rest_Errors;
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

		$valid_steps     = [ 'welcome', 'setup_community', 'integration', 'optin' ];
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
				$portal_name      = ! empty( $_POST['portal_name'] ) ? sanitize_text_field( wp_unslash( $_POST['portal_name'] ) ) : '';
				$hidden_community = ! empty( $_POST['hidden_community'] ) && sanitize_text_field( $_POST['hidden_community'] ) === 'on' ? true : false;
				$enable_feeds     = ! empty( $_POST['enable_feeds'] ) && sanitize_text_field( $_POST['enable_feeds'] ) === 'on' ? true : false;

				$settings                     = Settings::get_suredash_settings();
				$settings['portal_name']      = $portal_name;
				$settings['hidden_community'] = $hidden_community;
				$settings['enable_feeds']     = $enable_feeds;
				update_option( SUREDASHBOARD_SETTINGS, $settings );
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

		$url  = 'https://websitedemos.net/wp-json/suredash/v1/subscribe/';
		$args = [
			'body' => [
				'EMAIL'      => $user_email,
				'FIRST_NAME' => $first_name,
				'LAST_NAME'  => $last_name,
			],
		];

		$args = [
			'body' => $args,
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

}
