<?php
/**
 * Dashboard Core's Notifier base.
 *
 * @package SureDash
 * @since 0.0.1
 */

namespace SureDashboard\Core\Notifier;

use SureDashboard\Inc\Traits\Get_Instance;
use SureDashboard\Inc\Utils\Helper;

defined( 'ABSPATH' ) || exit;

/**
 * Notification Base
 *
 * @since 0.0.1
 */
class Base {
	use Get_Instance;

	/**
	 * Notifier.
	 *
	 * @var object
	 */
	private $notifier = null;

	/**
	 * Is current user is portal manager.
	 *
	 * @var bool
	 */
	private $is_manager = false;

	/**
	 * Constructor
	 *
	 * @since 0.0.1
	 */
	public function __construct() {
		$this->init_notifier();
	}

	/**
	 * Initialize Notifier.
	 *
	 * @since 0.0.1
	 * @return void
	 */
	public function init_notifier(): void {
		if ( ! is_null( $this->notifier ) ) {
			return;
		}

		// Check if current user is portal manager.
		if ( suredash_is_user_manager() ) {
			$this->is_manager = true;
			$this->notifier   = Admin_Notifier::get_instance();
			return;
		}

		// Fallback to user notifier.
		$this->notifier = User_Notifier::get_instance();
	}

	/**
	 * Get Default Args.
	 *
	 * @param array<mixed> $dataset Dataset.
	 * @since 0.0.1
	 * @return array<mixed>
	 */
	public function get_parsed_args( $dataset = [] ) {
		$defaults = apply_filters(
			'suredashboard_notification_default_args',
			[
				'timestamp' => suredash_get_timestamp(),
			]
		);

		return wp_parse_args( $dataset, $defaults );
	}

	/**
	 * Get Notifier.
	 *
	 * @since 0.0.1
	 * @return object
	 */
	public function get_notifier() {
		return apply_filters( 'suredashboard_notifier_instance', $this->notifier );
	}

	/**
	 * Get Notifications.
	 *
	 * @since 0.0.1
	 * @return array<mixed>
	 */
	public function get_notifications() {
		$admin_notifications  = [];
		$common_notifications = [];
		$user_notifications   = [];

		if ( method_exists( Common_Notifier::get_instance(), 'get_notifications' ) ) {
			$common_notifications = Common_Notifier::get_instance()->get_notifications();
		}

		if ( method_exists( User_Notifier::get_instance(), 'get_notifications' ) ) {
			$user_notifications = User_Notifier::get_instance()->get_notifications();
		}

		// If current user is portal manager, grant admin notifications.
		if ( $this->is_manager && method_exists( Admin_Notifier::get_instance(), 'get_notifications' ) ) {
			$admin_notifications = Admin_Notifier::get_instance()->get_notifications();
		}

		// Filter common notifications based on user preferences.
		$current_user_id = get_current_user_id();
		if ( $current_user_id && ! empty( $common_notifications ) ) {
			$common_notifications = array_filter(
				$common_notifications,
				static function( $notification ) use ( $current_user_id ) {
					if ( isset( $notification['trigger'] ) ) {
						switch ( $notification['trigger'] ) {
							case 'new_space':
								return self::is_portal_notification_enabled( $current_user_id, 'admin' );
							default:
								return true; // Other notifications are handled in their respective callbacks.
						}
					}
					return true;
				}
			);
		}

		return $common_notifications + $user_notifications + $admin_notifications;
	}

	/**
	 * Get Notifications dataset.
	 *
	 * @since 0.0.1
	 * @return array<mixed>
	 */
	public function get_notifications_dataset() {
		$common_notifications = [];
		$user_notifications   = [];
		$admin_notifications  = [];

		if ( method_exists( Common_Notifier::get_instance(), 'get_notification_dataset' ) ) {
			$common_notifications = Common_Notifier::get_instance()->get_notification_dataset();
		}

		if ( method_exists( User_Notifier::get_instance(), 'get_notification_dataset' ) ) {
			$user_notifications = User_Notifier::get_instance()->get_notification_dataset();
		}

		// If current user is portal manager, grant admin notifications.
		if ( method_exists( Admin_Notifier::get_instance(), 'get_notification_dataset' ) ) {
			$admin_notifications = Admin_Notifier::get_instance()->get_notification_dataset();
		}

		return $common_notifications + $user_notifications + $admin_notifications;
	}

	/**
	 * Format notification.
	 *
	 * @param string              $icon Icon name.
	 * @param string              $description Description.
	 * @param array<mixed>|string $value Notification data or timestamp.
	 * @return void
	 */
	public function format_notification( $icon, $description, $value = '' ): void {
		$timestamp              = '';
		$utc_adjusted_timestamp = '';
		$user_id                = 0;

		if ( is_array( $value ) ) {
			// If notification data is passed, find its timestamp.
			$notifications = $this->get_notifications();
			$timestamp     = array_search( $value, $notifications );
			// Adjusting timestamp based on GMT offset so that if user sets his timezone other than UTC, it still works.
			$gmt_offset             = get_option( 'gmt_offset' );
			$offset_in_epoch        = (int) ( $gmt_offset * 3600 );
			$utc_adjusted_timestamp = (int) $timestamp - $offset_in_epoch;

			// Extract user ID from the value array based on the keys observed.
			if ( isset( $value['caller'] ) ) {
				$user_id = $value['caller'];
			} elseif ( isset( $value['from_user'] ) ) {
				$user_id = $value['from_user'];
			}
		}
		if ( $timestamp === 0 ) {
			return;
		}
		$human_readable_timestamp = sprintf(
			/* translators: %s: Human-readable time difference (e.g., "5 days", "2 hours") */
			__( '%s ago', 'suredash' ),
			human_time_diff( absint( $utc_adjusted_timestamp ), time() )
		);

		$notification_status = sd_get_user_meta( get_current_user_id(), 'portal_user_notification_status', true );
		$read_notifications  = is_array( $notification_status ) && isset( $notification_status['read'] ) ? $notification_status['read'] : [];
		$is_read             = in_array( $timestamp, $read_notifications );

		ob_start();

		?>
		<li class="portal-notification-item sd-flex sd-justify-between sd-items-start sd-p-8 sd-cursor-default"
		id="notification-<?php echo esc_attr( (string) $timestamp ); ?>"
		>
			<div class="portal-notification-item-wrap sd-flex sd-gap-12 sd-w-full sd-font-14 sd-line-20 sd-items-center">
				<span class="notification-avatar-wrap <?php echo $icon === 'Bell' || $icon === 'Heart' ? 'svg-fill' : ''; ?>">
					<?php suredash_get_user_avatar( $user_id, true, 40, false, $value['space_id'] ?? null ); ?>
					<?php Helper::get_library_icon( $icon, true, 'md' ); ?>
				</span>
				<div class="portal-notification-item-description-wrap sd-flex-col sd-gap-4 sd-justify-center sd-w-full">
					<div class="portal-notification-item-description sd-w-full sd-flex sd-justify-between sd-items-baseline">
						<p class="notification-content sd-m-0">
							<?php echo wp_kses_post( $description ); ?>
						</p>
						<button class="notification-read-status portal-button button-ghost <?php echo $is_read ? 'read' : 'unread'; ?>"
						data-notification-timestamp="<?php echo esc_attr( (string) $timestamp ); ?>"
						aria-live="assertive"
						aria-label="<?php echo $is_read ? 'read' : 'unread'; ?>" >
						</button>
					</div>
					<?php if ( ! empty( $human_readable_timestamp ) ) { ?>
						<span class="portal-notification-item-timestamp sd-font-12" aria-label="<?php echo esc_attr( $human_readable_timestamp ); ?>">
							<?php echo esc_html( $human_readable_timestamp ); ?>
						</span>
					<?php } ?>
				</div>
			</div>
					</li>
		<?php

		echo do_shortcode( (string) ob_get_clean() );
	}

	/**
	 * Get Notifications Markup.
	 *
	 * @since 0.0.1
	 * @return string Notifications Markup.
	 */
	public function get_notifications_markup() {
		$markup        = '';
		$dataset       = $this->get_notifications_dataset();
		$notifications = $this->get_notifications();
		krsort( $notifications );

		if ( ! empty( $notifications ) ) {
			$markup .= '<ul class="portal-notification-list sd-no-space">';
			foreach ( $notifications as $notification ) {
				$trigger             = ! empty( $notification['trigger'] ) ? $notification['trigger'] : '';
				$formatting_callback = ! empty( $dataset[ $trigger ]['formatting_callback'] ) ? $dataset[ $trigger ]['formatting_callback'] : '';

				if ( $trigger && $formatting_callback ) {
					$markup .= call_user_func_array( $formatting_callback, [ $dataset[ $trigger ], $notification ] );
				}
			}
			$markup .= '</ul>';
		}

		return $markup;
	}

	/**
	 * Dispatch Admin Notification.
	 *
	 * @since 0.0.1
	 * @param string       $trigger Trigger.
	 * @param array<mixed> $data    Data.
	 * @return void
	 */
	public function dispatch_admin_notification( $trigger, $data ): void {

		if ( method_exists( Admin_Notifier::get_instance(), 'dispatch_notification' ) ) {
			Admin_Notifier::get_instance()->dispatch_notification( $trigger, $this->get_parsed_args( $data ) );
		}
	}

	/**
	 * Dispatch User Notification.
	 *
	 * @since 0.0.1
	 * @param string       $trigger Trigger.
	 * @param array<mixed> $data    Data.
	 * @return void
	 */
	public function dispatch_user_notification( $trigger, $data ): void {
		if ( method_exists( User_Notifier::get_instance(), 'dispatch_notification' ) ) {
			User_Notifier::get_instance()->dispatch_notification( $trigger, $this->get_parsed_args( $data ) );
		}
	}

	/**
	 * Dispatch Common Notification.
	 *
	 * @since 0.0.1
	 * @param string       $trigger Trigger.
	 * @param array<mixed> $data    Data.
	 * @return void
	 */
	public function dispatch_common_notification( $trigger, $data ): void {
		if ( method_exists( Common_Notifier::get_instance(), 'dispatch_notification' ) ) {
			Common_Notifier::get_instance()->dispatch_notification( $trigger, $this->get_parsed_args( $data ) );
		}
	}

	/**
	 * Is Notification Visited.
	 *
	 * @since 0.0.1
	 * @return bool
	 */
	public function suredash_is_notification_visited() {
		$notification_visited = isset( $_COOKIE['portal_notifications_visited'] ) ? true : false;
		return apply_filters( 'suredashboard_user_notification_visited', $notification_visited );
	}

	/**
	 * Filter notifications by user registration date.
	 *
	 * @since 1.3.0
	 * @param array<mixed> $notifications Array of notifications to filter.
	 * @return array<mixed> Filtered notifications.
	 */
	public function filter_notifications_by_registration_date( $notifications ) {
		// Return empty array if no notifications.
		if ( ! is_array( $notifications ) || empty( $notifications ) ) {
			return [];
		}

		// Get current user.
		$current_user = wp_get_current_user();
		if ( ! $current_user->ID ) {
			return $notifications;
		}

		// Get user registration timestamp.
		$user_registered_timestamp = strtotime( $current_user->user_registered );

		// Filter out notifications that occurred before user registration using array_filter.
		return array_filter(
			$notifications,
			static function( $timestamp ) use ( $user_registered_timestamp ) {
				return intval( $timestamp ) >= $user_registered_timestamp;
			},
			ARRAY_FILTER_USE_KEY
		);
	}

	/**
	 * Check if portal notifications are enabled for a specific user and notification type.
	 *
	 * @since 1.5.0
	 * @param int    $user_id          The user ID to check.
	 * @param string $notification_type The type of notification (mention, post_replies, comment_replies, admin).
	 * @return bool True if portal notifications are enabled, false otherwise.
	 */
	public static function is_portal_notification_enabled( $user_id, $notification_type = 'general' ) {
		// Check if "Receive all notifications" is enabled - if so, return true immediately.
		$enable_all_portal_notifications = sd_get_user_meta( $user_id, 'enable_all_portal_notifications', true );

		// Default to enabled if not set (new feature should be ON by default).
		$enable_all_portal_notifications = $enable_all_portal_notifications !== '' ? $enable_all_portal_notifications : '1';

		// If "Receive all notifications" is enabled, return true for all notification types.
		if ( $enable_all_portal_notifications === '1' ) {
			return true;
		}

		// If "Receive all notifications" is disabled, check specific notification type settings.
		switch ( $notification_type ) {
			case 'mention':
				$setting = sd_get_user_meta( $user_id, 'portal_notification_on_mention', true );
				return $setting !== '' ? ( $setting === '1' ) : false;

			case 'post_replies':
				$setting = sd_get_user_meta( $user_id, 'portal_notification_on_post_replies', true );
				return $setting !== '' ? ( $setting === '1' ) : false;

			case 'comment_replies':
				$setting = sd_get_user_meta( $user_id, 'portal_notification_on_comment_replies', true );
				return $setting !== '' ? ( $setting === '1' ) : false;

			case 'admin':
				$setting = sd_get_user_meta( $user_id, 'enable_admin_portal_notification', true );
				return $setting !== '' ? ( $setting === '1' ) : false;

			default:
				// For general notifications, when "Receive all" is disabled, default to false.
				return false;
		}
	}
}
