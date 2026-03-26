<?php
/**
 * Dashboard Core's Admin Notifier.
 *
 * @package SureDash
 * @since 0.0.1
 */

namespace SureDashboard\Core\Notifier;

use SureDashboard\Inc\Traits\Get_Instance;
use SureDashboard\Inc\Utils\Helper;
use SureDashboard\Inc\Utils\Labels;

defined( 'ABSPATH' ) || exit;

/**
 * All the admin related notification mechanism handle here.
 *
 * @since 0.0.1
 */
class Admin_Notifier extends Base {
	use Get_Instance;

	/**
	 * Option name.
	 *
	 * @var string
	 */
	private $option = 'suredashboard_admin_notifications';

	/**
	 * Constructor
	 *
	 * @since 0.0.1
	 */
	public function __construct() {
		$this->init();
	}

	/**
	 * Gather all notification data.
	 *
	 * @since 0.0.1
	 * @return array<mixed>
	 */
	public function get_notification_dataset() {
		$dataset = [
			'users_registered' => [
				'icon'                => 'UserPlus',
				'description'         => '{{CALLER}}' . __( ' and ', 'suredash' ) . '{{COUNT}} ' . __( 'new users have registered on the ', 'suredash' ) . Helper::get_option( 'portal_name' ) . '.',
				'trigger'             => 'suredashboard_user_registered',
				'callback'            => [ $this, 'user_registered_callback' ],
				'formatting_callback' => [ $this, 'user_registered_format' ],
			],
			'submitted_topic'  => [
				'icon'                => 'FilePen',
				'description'         => sprintf( /* translators: %1$s: user, %2$s: topic */ __( 'New post from %1$s "%2$s"', 'suredash' ), '{{USER}}', '{{TOPIC}}' ),
				'trigger'             => 'suredashboard_user_submitted_topic',
				'callback'            => [ $this, 'user_submitted_topic_callback' ],
				'formatting_callback' => [ $this, 'user_submitted_topic_format' ],
			],
		];

		return apply_filters( 'suredashboard_admin_notifications_dataset', $dataset );
	}

	/**
	 * Initialize the notifier.
	 *
	 * @since 0.0.1
	 * @return void
	 */
	public function init(): void {
		$notifications_dataset = $this->get_notification_dataset();
		foreach ( $notifications_dataset as $notification ) {
			$this->add_notification( $notification );
		}
	}

	/**
	 * Add notification.
	 *
	 * @since 0.0.1
	 * @param array<mixed> $notification Notification data.
	 * @return void
	 */
	public function add_notification( $notification ): void {
		add_action( $notification['trigger'], $notification['callback'] );
	}

	/**
	 * Dispatch notification.
	 *
	 * @since 0.0.1
	 * @param string       $key Notification key.
	 * @param array<mixed> $args Arguments, if any to perform the callback.
	 * @return void
	 */
	public function dispatch_notification( $key, $args = [] ): void {
		if ( $key !== '' ) {
			do_action( $key, $args );
		}
	}

	/**
	 * Get all notifications.
	 *
	 * @since 0.0.1
	 * @return array<mixed>
	 */
	public function get_notifications() {
		$notifications = get_option( $this->option, [] );

		// Filter notifications based on user registration date.
		return $this->filter_notifications_by_registration_date( $notifications );
	}

	/**
	 * User registered callback.
	 *
	 * @param array<mixed> $args Arguments, if any to perform the callback.
	 * @since 0.0.1
	 * @return void
	 */
	public function user_registered_callback( $args ): void {
		$notifiable         = [];
		$notifications_data = get_option( $this->option, [] );
		$timestamp          = ! empty( $args['timestamp'] ) ? $args['timestamp'] : suredash_get_timestamp();
		$count              = 1;
		$caller             = absint( $args['caller'] );

		if ( is_array( $notifications_data ) ) {

			// Look for existing users_registered notification for this portal.
			foreach ( $notifications_data as $timestamp => $notification ) {
				if (
					isset( $notification['trigger'] ) &&
					$notification['trigger'] === 'users_registered'
				) {
					// Remove existing notification.
					$existing_count = $notification['count'] ?? 0;
					$count          = $existing_count + $count;
					unset( $notifications_data[ $timestamp ] );
					break;
				}
			}

			// Create new notification with updated count.
			$notifiable = [
				'trigger' => 'users_registered',
				'caller'  => $caller,
				'count'   => $count,
			];

			$notifications_data[ $timestamp ] = $notifiable;
			update_option( $this->option, $notifications_data );
		}
	}

	/**
	 * Format user registered notification.
	 *
	 * @since 0.0.1
	 * @param array<mixed> $args Arguments, if any to perform the callback.
	 * @param mixed        $value Value.
	 * @return string|false
	 */
	public function user_registered_format( $args, $value ) {
		$icon   = ! empty( $args['icon'] ) ? $args['icon'] : '';
		$caller = absint( is_array( $value ) ? $value['caller'] : 0 );
		$count  = absint( is_array( $value ) ? $value['count'] : 1 );

		if ( ! $caller || ! $count ) {
			return '';
		}

		ob_start();

		$caller_name = suredash_get_notifier_caller( $caller );
		$description = ! empty( $args['description'] ) ? $args['description'] : '';

		// Choose between singular and plural messages.
		if ( $count <= 1 ) {
			$description = '{{CALLER}} ' . __( ' has registered on the ', 'suredash' ) . ' ' . Helper::get_option( 'portal_name' ) . '.';
		} else {
			$description = str_replace( '{{COUNT}}', strval( $count - 1 ), $description );
		}

		$description = str_replace( '{{CALLER}}', '<strong>' . $caller_name . '</strong>', $description );

		if ( ! is_string( $description ) ) {
			$description = '';
		}

		$this->format_notification( $icon, $description, $value );

		return ob_get_clean();
	}

	/**
	 * User submitted topic callback.
	 *
	 * @param array<mixed> $args Arguments, if any to perform the callback.
	 * @since 0.0.1
	 * @return void
	 */
	public function user_submitted_topic_callback( $args ): void {
		$notifiable         = [];
		$notifications_data = get_option( $this->option, [] );
		$timestamp          = ! empty( $args['timestamp'] ) ? $args['timestamp'] : suredash_get_timestamp();

		$submitted_post_id = ! empty( $args['topic_id'] ) ? absint( $args['topic_id'] ) : 0;
		$author            = get_post_field( 'post_author', $submitted_post_id );
		if ( $submitted_post_id ) {
			$notifiable = [
				'trigger'   => 'submitted_topic',
				'topic_id'  => $submitted_post_id,
				'from_user' => $author,
			];
		}

		if ( ! empty( $notifiable ) ) {

			if ( ! is_array( $notifications_data ) ) {
				$notifications_data = [];
			}

			$notifications_data[ $timestamp ] = $notifiable;
			update_option( $this->option, $notifications_data );
		}
	}

	/**
	 * Format user submitted topic notification.
	 *
	 * @since 0.0.1
	 * @param array<mixed> $args Arguments, if any to perform the callback.
	 * @param mixed        $value Value.
	 * @return string|false
	 */
	public function user_submitted_topic_format( $args, $value ) {
		$icon = ! empty( $args['icon'] ) ? $args['icon'] : '';

		ob_start();

		if ( is_array( $value ) && ! empty( $value ) ) {
			$topic_id = ! empty( $value['topic_id'] ) ? absint( $value['topic_id'] ) : 0;

			if ( ! $topic_id ) {
				return ob_get_clean();
			}

			$author      = absint( get_post_field( 'post_author', $topic_id ) );
			$author_name = suredash_get_notifier_caller( $author );
			$topic_title = $topic_id !== 0 ? '<a href="' . get_permalink( $topic_id ) . '">' . get_the_title( $topic_id ) . '</a>' : ''; // @phpstan-ignore-line
			$description = ! empty( $args['description'] ) ? $args['description'] : '';

			$description = str_replace( '{{TOPIC}}', '<strong>' . $topic_title . '</strong>', $description );
			$description = str_replace( '{{USER}}', '<strong>' . $author_name . '</strong>', $description );

			// Check if topic is still exists.
			if ( ! get_post_status( $topic_id ) ) {
				return ob_get_clean();
			}

			if ( ! is_string( $description ) ) {
				$description = '';
			}

			$this->format_notification( $icon, $description, $value );
		}

		return ob_get_clean();
	}
}
