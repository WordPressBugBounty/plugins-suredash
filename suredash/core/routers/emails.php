<?php
/**
 * Emails Router Initialize.
 *
 * @package SureDash
 */

namespace SureDashboard\Core\Routers;

use SureDashboard\Inc\Modules\EmailNotifications\Email_Notifications;
use SureDashboard\Inc\Traits\Get_Instance;
use SureDashboard\Inc\Traits\Rest_Errors;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class Emails Router.
 */
class Emails {
	use Rest_Errors;
	use Get_Instance;

	/**
	 * Get email notification triggers.
	 * Returns default data if option doesn't exist, empty array if exists but empty.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return array<string, mixed>
	 */
	public function get_email_triggers( \WP_REST_Request $request ): array {
		// Get configured triggers using the Email_Notifications instance.
		$email_notifications = Email_Notifications::get_instance();
		$triggers_data       = $email_notifications->get_email_triggers_data();

		// Check if option exists in database.
		$option_exists = get_option( 'suredash_emails_triggers_data', null ) !== null;

		// If no data and option doesn't exist, initialize defaults.
		if ( empty( $triggers_data ) && ! $option_exists ) {
			$email_notifications->initialize_default_email_triggers();
			$triggers_data = $email_notifications->get_email_triggers_data();
			$option_exists = true;
		}

		// Get available triggers.
		$available_triggers = $email_notifications->get_available_email_triggers();

		// Get user roles.
		$user_roles = $email_notifications->get_user_roles_for_emails();

		// Get trigger_key from request if provided (for filtering tags).
		$trigger_key = $request->get_param( 'trigger_key' );

		// Get available dynamic tags (filtered by trigger if specified).
		$dynamic_tags = $email_notifications->get_available_dynamic_tags( $trigger_key ? $trigger_key : '' );

		return [
			'success' => true,
			'data'    => [
				'triggers'           => $triggers_data,
				'available_triggers' => $available_triggers,
				'user_roles'         => $user_roles,
				'dynamic_tags'       => $dynamic_tags,
				'option_exists'      => $option_exists,
			],
		];
	}

	/**
	 * Add email trigger.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return void
	 */
	public function add_email_trigger( \WP_REST_Request $request ): void {
		$nonce = (string) $request->get_header( 'X-WP-Nonce' );
		if ( ! wp_verify_nonce( sanitize_text_field( $nonce ), 'wp_rest' ) ) {
			wp_send_json_error( [ 'message' => $this->get_rest_event_error( 'nonce' ) ] );
		}

		$trigger_key  = $request->get_param( 'trigger_key' );
		$user_roles   = $request->get_param( 'user_roles' );
		$template     = $request->get_param( 'template' );
		$custom_title = $request->get_param( 'custom_title' );

		if ( empty( $trigger_key ) || empty( $template ) ) {
			wp_send_json_error( $this->get_rest_event_error( 'missing_key' ) );
		}

		// Validate template structure.
		if ( ! is_array( $template ) || empty( $template['subject'] ) || empty( $template['body'] ) ) {
			wp_send_json_error( __( 'Invalid template structure.', 'suredash' ) );
		}

		$config = [
			'template'     => [
				'subject' => sanitize_text_field( $template['subject'] ),
				'body'    => wp_kses_post( $template['body'] ),
			],
			'user_roles'   => $user_roles,
			'custom_title' => ! empty( $custom_title ) ? sanitize_text_field( $custom_title ) : '',
		];

		$email_notifications = Email_Notifications::get_instance();
		if ( $email_notifications->add_email_trigger( $trigger_key, $config ) ) {
			wp_send_json_success( __( 'Email trigger added successfully.', 'suredash' ) );
		} else {
			wp_send_json_error( __( 'Failed to add email trigger.', 'suredash' ) );
		}
	}

	/**
	 * Update email trigger status.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return void
	 */
	public function update_trigger_status( \WP_REST_Request $request ): void {
		$nonce = (string) $request->get_header( 'X-WP-Nonce' );
		if ( ! wp_verify_nonce( sanitize_text_field( $nonce ), 'wp_rest' ) ) {
			wp_send_json_error( [ 'message' => $this->get_rest_event_error( 'nonce' ) ] );
		}

		$config_id = $request->get_param( 'config_id' );
		$status    = $request->get_param( 'status' );

		// Convert to boolean.
		$status = $status === '1' || $status === 'true' || $status === true;

		if ( empty( $config_id ) ) {
			wp_send_json_error( $this->get_rest_event_error( 'missing_key' ) );
		}

		$email_notifications = Email_Notifications::get_instance();
		$email_notifications->update_email_trigger( $config_id, [ 'status' => $status ] );
		wp_send_json_success( __( 'Trigger status updated successfully.', 'suredash' ) );
	}

	/**
	 * Update email trigger template.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return void
	 */
	public function update_trigger_template( \WP_REST_Request $request ): void {
		$nonce = (string) $request->get_header( 'X-WP-Nonce' );
		if ( ! wp_verify_nonce( sanitize_text_field( $nonce ), 'wp_rest' ) ) {
			wp_send_json_error( [ 'message' => $this->get_rest_event_error( 'nonce' ) ] );
		}

		$config_id    = $request->get_param( 'config_id' );
		$trigger_key  = $request->get_param( 'trigger_key' );
		$template     = $request->get_param( 'template' );
		$user_roles   = $request->get_param( 'user_roles' );
		$custom_title = $request->get_param( 'custom_title' );

		if ( empty( $config_id ) || empty( $trigger_key ) || empty( $template ) ) {
			wp_send_json_error( $this->get_rest_event_error( 'missing_key' ) );
		}

		// Validate template structure.
		if ( ! is_array( $template ) || empty( $template['subject'] ) || empty( $template['body'] ) ) {
			wp_send_json_error( __( 'Invalid template structure. Template must be an object with subject and body.', 'suredash' ) );
		}

		// Get available triggers. to validate trigger_key and get title.
		$email_notifications = Email_Notifications::get_instance();
		$available_triggers  = $email_notifications->get_available_email_triggers();
		if ( ! isset( $available_triggers[ $trigger_key ] ) ) {
			wp_send_json_error( __( 'Invalid trigger selected.', 'suredash' ) );
		}

		$config = [
			'trigger_key'  => $trigger_key,
			'title'        => $available_triggers[ $trigger_key ]['title'],
			'template'     => [
				'subject' => sanitize_text_field( $template['subject'] ),
				'body'    => wp_kses_post( $template['body'] ),
			],
			'user_roles'   => $user_roles,
			'custom_title' => ! empty( $custom_title ) ? sanitize_text_field( $custom_title ) : '',
		];

		$email_notifications->update_email_trigger( $config_id, $config );
		wp_send_json_success( __( 'Email template updated successfully.', 'suredash' ) );
	}

	/**
	 * Delete email trigger.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return void
	 */
	public function delete_email_trigger( \WP_REST_Request $request ): void {
		$nonce = (string) $request->get_header( 'X-WP-Nonce' );
		if ( ! wp_verify_nonce( sanitize_text_field( $nonce ), 'wp_rest' ) ) {
			wp_send_json_error( [ 'message' => $this->get_rest_event_error( 'nonce' ) ] );
		}

		$config_id = $request->get_param( 'config_id' );

		if ( empty( $config_id ) ) {
			wp_send_json_error( $this->get_rest_event_error( 'missing_key' ) );
		}

		$email_notifications = Email_Notifications::get_instance();
		if ( $email_notifications->delete_email_trigger( $config_id ) ) {
			wp_send_json_success( __( 'Email trigger deleted successfully.', 'suredash' ) );
		} else {
			wp_send_json_error( __( 'Failed to delete email trigger.', 'suredash' ) );
		}
	}

	/**
	 * Bulk delete email triggers.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return void
	 */
	public function bulk_delete_email_triggers( \WP_REST_Request $request ): void {
		$nonce = (string) $request->get_header( 'X-WP-Nonce' );
		if ( ! wp_verify_nonce( sanitize_text_field( $nonce ), 'wp_rest' ) ) {
			wp_send_json_error( [ 'message' => $this->get_rest_event_error( 'nonce' ) ] );
		}

		$config_ids = $request->get_param( 'config_ids' );

		if ( empty( $config_ids ) || ! is_array( $config_ids ) ) {
			wp_send_json_error( __( 'Invalid or missing config IDs.', 'suredash' ) );
		}

		$deleted_count       = 0;
		$failed_count        = 0;
		$email_notifications = Email_Notifications::get_instance();

		foreach ( $config_ids as $config_id ) {
			if ( $email_notifications->delete_email_trigger( $config_id ) ) {
				$deleted_count++;
			} else {
				$failed_count++;
			}
		}

		if ( $deleted_count > 0 ) {
			$message = sprintf(
				/* translators: %d: number of deleted triggers */
				_n(
					'%d email trigger deleted successfully.',
					'%d email triggers deleted successfully.',
					$deleted_count,
					'suredash'
				),
				$deleted_count
			);

			if ( $failed_count > 0 ) {
				$message .= ' ' . sprintf(
					/* translators: %d: number of failed deletions */
					_n(
						'%d trigger failed to delete.',
						'%d triggers failed to delete.',
						$failed_count,
						'suredash'
					),
					$failed_count
				);
			}

			wp_send_json_success( $message );
		} else {
			wp_send_json_error( __( 'Failed to delete email triggers.', 'suredash' ) );
		}
	}
}
