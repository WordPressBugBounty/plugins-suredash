<?php
/**
 * Email dispatcher and batch processing.
 *
 * @package SureDash
 * @since 1.5.0
 */

namespace SureDashboard\Inc\Modules\EmailNotifications;

use SureDashboard\Inc\Traits\Get_Instance;

defined( 'ABSPATH' ) || exit;

/**
 * Email Dispatcher
 *
 * Handles email trigger dispatching and batch processing using Action Scheduler.
 *
 * @since 1.5.0
 */
class Email_Dispatcher {
	use Get_Instance;

	/**
	 * Batch size for email processing.
	 *
	 * @var int
	 */
	private $batch_size = 100;

	/**
	 * Delay between batches in seconds.
	 *
	 * @var int
	 */
	private $batch_delay = 30;

	/**
	 * Constructor
	 *
	 * @since 1.5.0
	 */
	public function __construct() {
		$this->init_hooks();
		$this->init_batch_settings();
	}

	/**
	 * Process a batch of emails.
	 *
	 * @since 1.5.0
	 * @param array<string, mixed> $batch_data The batch data.
	 * @return void
	 */
	public function process_email_batch( array $batch_data ): void {
		$config_id    = $batch_data['config_id'];
		$context_data = $batch_data['context_data'];
		$user_ids     = $batch_data['user_ids'];

		$configured_triggers = Email_Notifications::get_instance()->get_email_triggers_data();

		if ( ! isset( $configured_triggers[ $config_id ] ) ) {
			return;
		}

		$trigger_config = $configured_triggers[ $config_id ];

		// Filter users based on admin email preferences for admin-triggered emails.
		if ( class_exists( '\SureDashboard\Inc\Modules\EmailNotifications\Admin_Updates' ) ) {
			$user_ids = \SureDashboard\Inc\Modules\EmailNotifications\Admin_Updates::filter_admin_email_recipients( $user_ids );
		}

		if ( empty( $user_ids ) ) {
			return;
		}

		// Get user objects for this batch.
		$users = get_users(
			[
				'include' => $user_ids,
				'fields'  => 'all',
			]
		);

		if ( empty( $users ) ) {
			return;
		}

		// Send emails to each user in the batch.
		foreach ( $users as $user ) {
			$this->send_trigger_email( $user, $trigger_config, $context_data );
		}
	}

	/**
	 * Trigger emails for a specific event.
	 *
	 * @since 1.5.0
	 * @param string               $trigger_key The trigger key.
	 * @param array<string, mixed> $context_data Context data for the trigger.
	 * @return void
	 */
	public function trigger_emails_for_event( string $trigger_key, array $context_data ): void {
		$configured_triggers = Email_Notifications::get_instance()->get_email_triggers_data();

		// Find active triggers for this event.
		$active_triggers = array_filter(
			$configured_triggers,
			static function( $trigger ) use ( $trigger_key ) {
				return $trigger['trigger_key'] === $trigger_key && ! empty( $trigger['status'] );
			}
		);

		if ( empty( $active_triggers ) ) {
			return;
		}

		foreach ( $active_triggers as $config_id => $trigger_config ) {
			unset( $trigger_config ); // Suppress unused variable warning.
			$this->schedule_email_trigger( $trigger_key, $config_id, $context_data );
		}
	}

	/**
	 * Initialize batch settings with filters.
	 *
	 * @since 1.5.0
	 * @return void
	 */
	private function init_batch_settings(): void {
		/**
		 * Filter the batch size for email processing.
		 *
		 * @since 1.5.0
		 * @param int $batch_size The batch size. Default 100.
		 */
		$batch_size = apply_filters( 'suredash_email_batch_size', $this->batch_size );
		if ( is_int( $batch_size ) && $batch_size > 0 ) {
			$this->batch_size = $batch_size;
		}

		/**
		 * Filter the delay between batches in seconds.
		 *
		 * @since 1.5.0
		 * @param int $batch_delay The delay in seconds. Default 30.
		 */
		$this->batch_delay = apply_filters( 'suredash_email_batch_delay', $this->batch_delay );
	}

	/**
	 * Initialize email trigger hooks.
	 *
	 * @since 1.5.0
	 * @return void
	 */
	private function init_hooks(): void {
		// Register Action Scheduler hooks.
		add_action( 'suredash_send_email_batch', [ $this, 'process_email_batch' ], 10, 1 );
	}

	/**
	 * Schedule email trigger using Action Scheduler.
	 *
	 * @since 1.5.0
	 * @param string               $trigger_key The trigger key.
	 * @param string               $config_id The configuration ID.
	 * @param array<string, mixed> $context_data Context data for the trigger.
	 * @return void
	 */
	private function schedule_email_trigger( string $trigger_key, string $config_id, array $context_data ): void {
		$configured_triggers = Email_Notifications::get_instance()->get_email_triggers_data();

		if ( ! isset( $configured_triggers[ $config_id ] ) ) {
			return;
		}

		$trigger_config = $configured_triggers[ $config_id ];

		// Get users to email based on trigger type.
		$users = $this->get_users_for_trigger( $trigger_config, $trigger_key, $context_data );

		if ( empty( $users ) ) {
			return;
		}

		// Split users into batches.
		$batch_size = 100;
		if ( $this->batch_size > 0 ) {
			$batch_size = $this->batch_size;
		}
		$user_batches = array_chunk( $users, $batch_size );

		foreach ( $user_batches as $batch_index => $user_batch ) {
			// Extract user IDs for the batch.
			$user_ids = array_map(
				static function( $user ) {
					return $user->ID;
				},
				$user_batch
			);

			$batch_data = [
				'trigger_key'  => $trigger_key,
				'config_id'    => $config_id,
				'context_data' => $context_data,
				'user_ids'     => $user_ids,
				'batch_index'  => $batch_index,
			];

			if ( ! function_exists( 'as_schedule_single_action' ) ) {
				// Fallback if Action Scheduler is not available.
				$this->process_email_batch( $batch_data );
			} else {
				// Schedule the batch processing with delays between batches.
				$delay = $batch_index * $this->batch_delay;
				as_schedule_single_action(
					time() + $delay,
					'suredash_send_email_batch',
					[ $batch_data ],
					'suredash-emails'
				);
			}
		}
	}

	/**
	 * Get users for a trigger based on user roles configuration.
	 *
	 * @since 1.5.0
	 * @param array<string, mixed> $trigger_config The trigger configuration.
	 * @param string               $trigger_key The trigger key.
	 * @param array<string, mixed> $context_data Context data for the trigger.
	 * @return array<\WP_User> Array of WP_User objects.
	 */
	private function get_users_for_trigger( array $trigger_config, string $trigger_key = '', array $context_data = [] ): array {
		// Special handling for user onboarding - only send to the newly registered user.
		if ( $trigger_key === 'user_onboarding' && isset( $context_data['user_id'] ) ) {
			$user = get_user_by( 'ID', $context_data['user_id'] );
			return $user ? [ $user ] : [];
		}

		// Special handling for course completion - only send to the user who completed the course.
		if ( $trigger_key === 'course_completed' && isset( $context_data['user_id'] ) ) {
			$user = get_user_by( 'ID', $context_data['user_id'] );
			return $user ? [ $user ] : [];
		}

		// Default behavior for other triggers - get users by role and/or access groups.
		$user_roles = $trigger_config['user_roles'] ?? [];
		$users      = [];
		$user_ids   = [];

		// Separate user roles and access groups.
		$roles         = [];
		$access_groups = [];

		foreach ( $user_roles as $role ) {
			if ( strpos( $role, 'access_group_' ) === 0 ) {
				// This is an access group.
				$access_groups[] = str_replace( 'access_group_', '', $role );
			} else {
				// This is a regular role.
				$roles[] = $role;
			}
		}

		// Get users by role.
		if ( ! empty( $roles ) ) {
			$role_users = get_users(
				[
					'fields'   => 'all',
					'role__in' => $roles,
				]
			);

			foreach ( $role_users as $user ) {
				if ( ! in_array( $user->ID, $user_ids, true ) ) {
					$users[]    = $user;
					$user_ids[] = $user->ID;
				}
			}
		}

		// Get users by access groups if SureMembers is active.
		if ( ! empty( $access_groups ) && suredash_is_suremembers_active() && class_exists( '\SureMembers\Inc\Access_Groups' ) ) {
			foreach ( $access_groups as $group_id ) {
				$group_user_ids = $this->get_suremembers_group_users( $group_id );

				foreach ( $group_user_ids as $user_id ) {
					if ( ! in_array( $user_id, $user_ids, true ) ) {
						$user = get_user_by( 'ID', $user_id );
						if ( $user ) {
							$users[]    = $user;
							$user_ids[] = $user_id;
						}
					}
				}
			}
		}

		// If no specific roles or groups configured, get all users.
		if ( empty( $user_roles ) ) {
			$users = get_users( [ 'fields' => 'all' ] );
		}

		return $users;
	}

	/**
	 * Get users belonging to a SureMembers access group with active status.
	 *
	 * @since 1.6.0
	 * @param string $group_id The access group ID.
	 * @return array<int> Array of user IDs.
	 */
	private function get_suremembers_group_users( string $group_id ): array {
		if ( ! suredash_is_suremembers_active() || ! class_exists( '\SureMembers\Inc\Access_Groups' ) ) {
			return [];
		}

		// The meta key is suremembers_user_access_group_{group_id}.
		$meta_key = 'suremembers_user_access_group_' . $group_id;

		// Get all users who have this meta key.
		$args = [
			'fields'     => 'ID',
			'meta_query' => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				[
					'key'     => $meta_key,
					'compare' => 'EXISTS',
				],
			],
		];

		$user_ids = get_users( $args );

		// Filter users by checking if their status is 'active'.
		$active_user_ids = [];
		foreach ( $user_ids as $user_id ) {
			$meta_value = get_user_meta( $user_id, $meta_key, true );

			// Unserialize and check status.
			if ( is_array( $meta_value ) && isset( $meta_value['status'] ) && $meta_value['status'] === 'active' ) {
				$active_user_ids[] = $user_id;
			}
		}

		return $active_user_ids;
	}

	/**
	 * Send an individual trigger email.
	 *
	 * @since 1.5.0
	 * @param \WP_User             $user The user object.
	 * @param array<string, mixed> $trigger_config The trigger configuration.
	 * @param array<string, mixed> $context_data Context data for variable replacement.
	 * @return bool Whether the email was sent successfully.
	 */
	private function send_trigger_email( \WP_User $user, array $trigger_config, array $context_data ): bool {
		$template = $trigger_config['template'];

		// Replace variables in subject and body.
		$subject = $this->replace_email_variables( $template['subject'], $user, $context_data );
		$body    = $this->replace_email_variables( $template['body'], $user, $context_data );

		// Convert plain text to HTML.
		$body = wpautop( $body );

		// Set up email headers.
		$headers = [
			'Content-Type: text/html; charset=UTF-8',
			'From: ' . get_bloginfo( 'name' ) . ' <' . get_option( 'admin_email' ) . '>',
		];

		// Send the email.
		return wp_mail( $user->user_email, $subject, $body, $headers );
	}

	/**
	 * Replace variables in email templates.
	 *
	 * @since 1.5.0
	 * @param string               $content The content with variables.
	 * @param \WP_User             $user The user object.
	 * @param array<string, mixed> $context_data Context data for replacement.
	 * @return string The content with variables replaced.
	 */
	private function replace_email_variables( string $content, \WP_User $user, array $context_data ): string {
		// Basic user variables.
		$variables = [
			'{{user_name}}'         => $user->display_name,
			'{{user_email}}'        => $user->user_email,
			'{{user_login}}'        => $user->user_login,
			'{{user_display_name}}' => $user->display_name,
			'{{user_first_name}}'   => sd_get_user_meta( $user->ID, 'first_name', true ),
			'{{user_last_name}}'    => sd_get_user_meta( $user->ID, 'last_name', true ),
			'{{user_registered}}'   => date_i18n( get_option( 'date_format' ), strtotime( $user->user_registered ) ),
			'{{portal_name}}'       => get_bloginfo( 'name' ),
			'{{portal_url}}'        => home_url(),
		];

		// Context-specific variables for basic features.
		if ( isset( $context_data['space_id'] ) ) {
			$space_id   = $context_data['space_id'];
			$space_data = $context_data['space_data'] ?? [];

			$variables['{{space_name}}'] = $space_data['title'] ?? get_the_title( $space_id );
			$variables['{{space_url}}']  = get_permalink( $space_id );
		}

		if ( isset( $context_data['post_id'] ) ) {
			$post_id   = $context_data['post_id'];
			$post_data = $context_data['post_data'] ?? [];

			$variables['{{post_title}}']   = $post_data['title'] ?? get_the_title( $post_id );
			$variables['{{post_url}}']     = get_permalink( $post_id );
			$variables['{{post_excerpt}}'] = $post_data['excerpt'] ?? get_the_excerpt( $post_id );
		}

		/**
		 * Filter email template variables.
		 * External plugins (like suredash-pro) can add their own variable replacements using this filter.
		 *
		 * @since 1.5.0
		 * @param array    $variables The variables array.
		 * @param \WP_User $user The user object.
		 * @param array    $context_data Context data.
		 */
		$variables = apply_filters( 'suredash_email_template_variables', $variables, $user, $context_data );

		// Replace variables in content.
		return str_replace( array_keys( $variables ), array_values( $variables ), $content );
	}
}
