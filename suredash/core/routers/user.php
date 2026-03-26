<?php
/**
 * User Router Initialize.
 *
 * @package SureDash
 */

namespace SureDashboard\Core\Routers;

use SureDashboard\Inc\Traits\Get_Instance;
use SureDashboard\Inc\Traits\Rest_Errors;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class User Router.
 */
class User {
	use Rest_Errors;
	use Get_Instance;

	/**
	 * Updates the read status of a user notification.
	 *
	 * This method handles an AJAX request to mark a notification as read for the current user.
	 * It stores the notification timestamp in the user's meta data to track which notifications
	 * have been read.
	 *
	 * @param \WP_REST_Request $request Request.
	 *
	 * @since 0.0.2
	 * @return void
	 */
	public function update_notifications( $request ): void {
		$nonce = (string) $request->get_header( 'X-WP-Nonce' );
		if ( ! wp_verify_nonce( sanitize_text_field( $nonce ), 'wp_rest' ) ) {
			wp_send_json_error( [ 'message' => $this->get_rest_event_error( 'nonce' ) ] );
		}

		$decoded_timestamps = ! empty( $_POST['notification_timestamps'] ) ? json_decode( wp_unslash( $_POST['notification_timestamps'] ), true ) : []; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Data is sanitized further down the line with array_map.

		if ( empty( $decoded_timestamps ) || $decoded_timestamps === 'undefined' ) {
			wp_send_json_error( [ 'message' => $this->get_rest_event_error( 'default' ) ] );
		}
		$user_id = get_current_user_id();

		// Get notification status and ensure proper structure.
		$notification_status = sd_get_user_meta( $user_id, 'portal_user_notification_status', true );
		if ( ! is_array( $notification_status ) ) {
			$notification_status = [
				'read' => [],
			];
		}

		// Ensure 'read' key exists.
		if ( ! isset( $notification_status['read'] ) ) {
			$notification_status['read'] = [];
		}

		if ( is_array( $decoded_timestamps ) ) {
			$notification_timestamps = array_map(
				static function( $timestamp ) {
					return intval( $timestamp );
				},
				$decoded_timestamps
			);

			// Add timestamp if not already present.
			foreach ( $notification_timestamps as $timestamp ) {
				if ( ! in_array( $timestamp, $notification_status['read'], true ) ) {
					$notification_status['read'][] = $timestamp;
				}
			}
			sd_update_user_meta( $user_id, 'portal_user_notification_status', $notification_status );
			wp_send_json_success( [ 'status' => 'success' ] );
		}

		wp_send_json_error( [ 'message' => $this->get_rest_event_error( 'default' ) ] );
	}

	/**
	 * Updates user data.
	 *
	 * This method handles an AJAX request to update user data for the current user.
	 * It verifies the nonce and updates the user meta data based on the provided data.
	 *
	 * @param \WP_REST_Request $request Request.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function update_user_data( $request ): void {
		$nonce = (string) $request->get_header( 'X-WP-Nonce' );
		if ( ! wp_verify_nonce( sanitize_text_field( $nonce ), 'wp_rest' ) ) {
			wp_send_json_error( [ 'message' => $this->get_rest_event_error( 'nonce' ) ] );
		}

		$user_id = ! empty( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
		if ( ! $user_id ) {
			$user_id = get_current_user_id();
			if ( ! $user_id ) {
				wp_send_json_error( [ 'message' => $this->get_rest_event_error( 'default' ) ] );
			}
		}

		$decoded_data = ! empty( $_POST['user_data'] ) ? json_decode( wp_unslash( $_POST['user_data'] ), true ) : []; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Data is sanitized further down the line with array_map.

		if ( empty( $decoded_data ) || $decoded_data === 'undefined' ) {
			wp_send_json_error( [ 'message' => $this->get_rest_event_error( 'default' ) ] );
		}

		// Denylist of WordPress-internal meta keys to prevent privilege escalation.
		global $wpdb;
		$blocked_prefixes = [ $wpdb->prefix, 'wp_', 'meta_', 'session_tokens', 'use_ssl' ];

		foreach ( $decoded_data as $key => $dataset ) {
			$type  = $dataset['type'] ?? 'text';
			$value = $dataset['value'] ?? '';

			$key = sanitize_text_field( wp_unslash( $key ) );

			// Block writes to WordPress-internal or security-sensitive meta keys.
			$is_blocked = false;
			foreach ( $blocked_prefixes as $prefix ) {
				if ( strpos( $key, $prefix ) === 0 ) {
					$is_blocked = true;
					break;
				}
			}

			if ( $is_blocked ) {
				continue;
			}

			switch ( $type ) {
				case 'array':
					$value = is_array( $value ) ? suredash_clean_data( $value ) : [];
					break;

				case 'boolean':
					$value = filter_var( $value, FILTER_VALIDATE_BOOLEAN );
					break;

				case 'integer':
					$value = absint( $value );
					break;

				default:
				case 'string':
					$value = sanitize_text_field( wp_unslash( $value ) );
					break;
			}

			do_action( 'suredash_update_user_data', $user_id, $key, $value );
			sd_update_user_meta( $user_id, $key, $value );
		}

		wp_send_json_success();
	}
}
