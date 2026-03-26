<?php
/**
 * Email triggers and action hooks.
 *
 * @package SureDash
 * @since 1.5.0
 */

namespace SureDashboard\Inc\Modules\EmailNotifications;

use SureDashboard\Inc\Traits\Get_Instance;

defined( 'ABSPATH' ) || exit;

/**
 * Email Triggers
 *
 * Handles email trigger actions for various events.
 *
 * @since 1.5.0
 */
class Email_Triggers {
	use Get_Instance;

	/**
	 * Constructor
	 *
	 * @since 1.5.0
	 */
	public function __construct() {
		$this->init_hooks();
	}

	/**
	 * Handle user registration email triggers.
	 *
	 * @since 1.5.0
	 * @param int $user_id The user ID.
	 * @return void
	 */
	public function handle_user_registered( $user_id ): void {
		// Early return if user onboarding trigger is not enabled.
		if ( ! $this->is_trigger_enabled( 'user_onboarding' ) ) {
			return;
		}

		// Trigger the onboarding email.
		$this->trigger_emails_for_event(
			'user_onboarding',
			[
				'user_id' => $user_id,
			]
		);
	}

	/**
	 * Check if a specific email trigger is enabled.
	 *
	 * @since 1.5.0
	 * @param string $trigger_key The trigger key to check.
	 * @return bool True if trigger is enabled, false otherwise.
	 */
	public function is_trigger_enabled( $trigger_key ): bool {
		$email_notifications = Email_Notifications::get_instance();
		$configured_triggers = $email_notifications->get_email_triggers_data();

		// Check if any active trigger exists for this trigger key.
		foreach ( $configured_triggers as $trigger ) {
			if ( $trigger['trigger_key'] === $trigger_key && ! empty( $trigger['status'] ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Handle space creation email triggers.
	 *
	 * @since 1.5.0
	 * @param string   $new_status New post status.
	 * @param string   $old_status Old post status.
	 * @param \WP_Post $post Post object.
	 * @return void
	 */
	public function handle_space_created( $new_status, $old_status, $post ): void {
		// Early return if space creation trigger is not enabled.
		if ( ! $this->is_trigger_enabled( 'admin_space_created' ) ) {
			return;
		}

		// Check if this is a portal (space) post type.
		if ( $post->post_type !== 'portal' ) {
			return;
		}

		// Check if space is being published (either new or status change).
		if ( $new_status !== 'publish' ) {
			return;
		}

		// Skip if it was already published (avoid duplicate triggers).
		if ( $old_status === 'publish' ) {
			return;
		}

		// Check if space is not hidden.
		$is_hidden = get_post_meta( $post->ID, 'hidden_space', true );
		if ( ! empty( $is_hidden ) ) {
			return;
		}

		// Trigger the email.
		$this->trigger_emails_for_event(
			'admin_space_created',
			[
				'space_id'   => $post->ID,
				'space_data' => [
					'title' => $post->post_title,
				],
			]
		);
	}

	/**
	 * Handle post creation email triggers.
	 *
	 * @since 1.5.0
	 * @param string   $new_status New post status.
	 * @param string   $old_status Old post status.
	 * @param \WP_Post $post Post object.
	 * @return void
	 */
	public function handle_post_created( $new_status, $old_status, $post ): void {
		// Early return if post creation trigger is not enabled.
		if ( ! $this->is_trigger_enabled( 'admin_post_published' ) ) {
			return;
		}

		// Check if this is a community post.
		if ( $post->post_type !== 'community-post' ) {
			return;
		}

		// Check if post is being published.
		if ( $new_status !== 'publish' ) {
			return;
		}

		// Skip if it was already published (avoid duplicate triggers).
		if ( $old_status === 'publish' ) {
			return;
		}

		// Check if post author is admin.
		$post_author = get_user_by( 'ID', $post->post_author );
		if ( ! $post_author || ! user_can( $post_author, 'manage_options' ) ) {
			return;
		}

		// Trigger the email.
		$this->trigger_emails_for_event(
			'admin_post_published',
			[
				'post_id'   => $post->ID,
				'post_data' => [
					'title'   => $post->post_title,
					'excerpt' => ! empty( $post->post_excerpt ) ? $post->post_excerpt : wp_trim_words( $post->post_content, 55 ),
				],
			]
		);
	}

	/**
	 * Initialize email trigger hooks.
	 *
	 * @since 1.5.0
	 * @return void
	 */
	private function init_hooks(): void {
		// Onboarding email trigger.
		add_action( 'user_register', [ $this, 'handle_user_registered' ], 10, 1 );

		// Space is created trigger.
		add_action( 'transition_post_status', [ $this, 'handle_space_created' ], 10, 3 );

		// Post is created trigger.
		add_action( 'transition_post_status', [ $this, 'handle_post_created' ], 10, 3 );
	}

	/**
	 * Trigger emails for a specific event.
	 *
	 * @since 1.5.0
	 * @param string               $trigger_key The trigger key.
	 * @param array<string, mixed> $context_data Context data for the trigger.
	 * @return void
	 */
	private function trigger_emails_for_event( string $trigger_key, array $context_data ): void {
		Email_Dispatcher::get_instance()->trigger_emails_for_event( $trigger_key, $context_data );
	}
}
