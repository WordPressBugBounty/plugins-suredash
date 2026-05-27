<?php
/**
 * Email triggers and action hooks.
 *
 * @package SureDash
 * @since 1.5.0
 */

namespace SureDashboard\Inc\Modules\EmailNotifications;

use SureDashboard\Inc\Traits\Get_Instance;
use SureDashboard\Inc\Utils\Helper;

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
	 * Email selected admins / portal managers when a member submits a post.
	 *
	 * Gated by the `notify_admins_on_new_post` general setting.
	 *
	 * Recipient resolution:
	 *   - If `admin_notification_recipients` (an array of user IDs) is set,
	 *     restrict to those users — but only if each still holds either
	 *     `manage_options` or `manage_portal_dashboard` at send time, so
	 *     users who've been demoted since being picked stop receiving the
	 *     email.
	 *   - If the list is empty, fall back to every administrator + portal
	 *     manager on the site (sensible default for fresh installs).
	 *
	 * The post author is always removed from the final list (no self-emails),
	 * and the result is passed through `Admin_Updates::filter_admin_email_recipients`
	 * so each recipient's personal `enable_all_email_notifications` /
	 * `enable_admin_email` user-meta opt-outs are still honored.
	 *
	 * @since 1.9.0
	 *
	 * @param array<string, mixed> $args Notifier args; expects `topic_id`.
	 *
	 * @return void
	 */
	public function handle_new_post_admin_notification( $args ): void {
		// Setting must be on.
		if ( ! Helper::get_option( 'notify_admins_on_new_post' ) ) {
			return;
		}

		$post_id = ! empty( $args['topic_id'] ) ? absint( $args['topic_id'] ) : 0;
		if ( ! $post_id ) {
			return;
		}

		$post = get_post( $post_id );
		if ( ! $post || $post->post_type !== SUREDASHBOARD_FEED_POST_TYPE ) {
			return;
		}

		$post_author_id = (int) $post->post_author;

		// Build the candidate user-ID list. Picked recipients win when set,
		// otherwise we fall back to all admins + portal managers.
		$picked     = Helper::get_option( 'admin_notification_recipients', [] );
		$picked_ids = is_array( $picked )
			? array_values( array_unique( array_filter( array_map( 'absint', $picked ) ) ) )
			: [];

		if ( ! empty( $picked_ids ) ) {
			// Validate each picked ID still has admin/portal-manager privileges.
			$candidate_ids = array_values(
				array_filter(
					$picked_ids,
					static function ( int $id ): bool {
						return user_can( $id, 'manage_options' )
							|| user_can( $id, 'manage_portal_dashboard' );
					}
				)
			);
		} else {
			$fallback_users = get_users(
				[
					'role__in' => [ 'administrator', 'portal_manager' ],
					'fields'   => 'ID',
				]
			);
			$candidate_ids  = array_map( 'absint', $fallback_users );
		}

		// Never email the author about their own post, even if they were
		// explicitly picked or happen to be an admin/portal manager.
		$candidate_ids = array_values(
			array_filter(
				$candidate_ids,
				static function ( int $id ) use ( $post_author_id ): bool {
					return $id > 0 && $id !== $post_author_id;
				}
			)
		);
		if ( empty( $candidate_ids ) ) {
			return;
		}

		// Respect each user's personal email-notification preferences.
		$allowed_ids = Admin_Updates::filter_admin_email_recipients( $candidate_ids );
		if ( empty( $allowed_ids ) ) {
			return;
		}

		$portal_name = Helper::get_option( 'portal_name', get_bloginfo( 'name' ) );
		$post_title  = (string) get_the_title( $post );
		// Keep the subject readable in client previews — RFC suggests staying
		// under ~78 chars total, so cap the post title at 60 chars with an
		// ellipsis when needed. The portal-name prefix is short for most sites.
		if ( function_exists( 'mb_strlen' ) && mb_strlen( $post_title ) > 60 ) {
			$post_title = rtrim( mb_substr( $post_title, 0, 57 ) ) . '…';
		} elseif ( strlen( $post_title ) > 60 ) {
			$post_title = rtrim( substr( $post_title, 0, 57 ) ) . '…';
		}
		$subject = sprintf(
			/* translators: %1$s: portal name, %2$s: post title. */
			__( '[%1$s] New community post: %2$s', 'suredash' ),
			$portal_name,
			$post_title
		);
		$body = suredash_new_post_admin_email_body( $post_id );
		if ( $body === '' ) {
			return;
		}

		// Batch-fetch all recipients in one query instead of N `get_userdata`
		// calls, then look up by ID for each send.
		$recipients = get_users(
			[
				'include' => array_map( 'intval', $allowed_ids ),
				'fields'  => [ 'ID', 'user_email' ],
			]
		);

		foreach ( $recipients as $user ) {
			if ( empty( $user->user_email ) ) {
				continue;
			}
			suredash_send_email( (string) $user->user_email, $subject, $body );
		}
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

		// Notify admins by email when a user submits a community post.
		add_action( 'suredashboard_user_submitted_topic', [ $this, 'handle_new_post_admin_notification' ], 10, 1 );
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
