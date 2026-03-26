<?php
/**
 * Dashboard Core's User Notifier.
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
class User_Notifier extends Base {
	use Get_Instance;

	/**
	 * Current user ID.
	 *
	 * @var int
	 */
	private $current_user_id = 0;

	/**
	 * Option name.
	 *
	 * @var string
	 */
	private $option = 'suredashboard_user_notifications';

	/**
	 * Constructor
	 *
	 * @since 0.0.1
	 */
	public function __construct() {
		$this->current_user_id = get_current_user_id();
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
			'topic_approved' => [
				'icon'                => 'FilePen',
				'description'         => '"{{TOPIC}}" ' . __( 'your topic has been approved.', 'suredash' ),
				'trigger'             => 'suredashboard_topic_approved',
				'callback'            => [ $this, 'topic_approved_callback' ],
				'formatting_callback' => [ $this, 'topic_approved_format' ],
			],
			'user_mentioned' => [
				'icon'                => 'Bell',
				'description'         => '{{CALLER}} ' . __( 'has mentioned you in a ', 'suredash' ) . '{{TOPIC}}.',
				'trigger'             => 'suredashboard_user_mentioned',
				'callback'            => [ $this, 'user_mentioned_callback' ],
				'formatting_callback' => [ $this, 'user_mentioned_format' ],
			],
		];

		return apply_filters( 'suredashboard_user_notifications_dataset', $dataset );
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
		$notifications = sd_get_user_meta( $this->current_user_id, $this->option, true );
		if ( ! is_array( $notifications ) ) {
			$notifications = [];
		}

		// Filter notifications based on user registration date.
		return $this->filter_notifications_by_registration_date( $notifications );
	}

	/**
	 * Topic approved callback.
	 *
	 * @since 0.0.1
	 * @param array<mixed> $args Arguments, if any to perform the callback.
	 * @return void
	 */
	public function topic_approved_callback( $args ): void {
		$notifiable = [];
		$user_id    = 0;

		if ( ! empty( $args['from_user'] ) && ! empty( $args['topic_id'] ) ) {
			$topic_id = absint( $args['topic_id'] );
			$user_id  = absint( $args['from_user'] );

			// Check if portal notifications are enabled for this user (admin type).
			if ( ! Base::is_portal_notification_enabled( $user_id, 'admin' ) ) {
				return;
			}

			$notifiable = [
				'trigger'   => 'topic_approved',
				'topic_id'  => $topic_id,
				'from_user' => $user_id,
			];
		}

		if ( $user_id && ! empty( $notifiable ) ) {
			$timestamp          = ! empty( $args['timestamp'] ) ? $args['timestamp'] : suredash_get_timestamp();
			$notifications_data = sd_get_user_meta( $user_id, $this->option, true );

			if ( ! is_array( $notifications_data ) ) {
				$notifications_data = [];
			}

			$notifications_data[ $timestamp ] = $notifiable;
			sd_update_user_meta( $user_id, $this->option, $notifications_data );
		}
	}

	/**
	 * Format topic approved notification.
	 *
	 * @since 0.0.1
	 * @param array<mixed> $args Arguments, if any to perform the callback.
	 * @param mixed        $value Value.
	 * @return string|false
	 */
	public function topic_approved_format( $args, $value ) {
		$icon = ! empty( $args['icon'] ) ? $args['icon'] : '';

		ob_start();

		if ( is_array( $value ) && ! empty( $value ) ) {
			$description = ! empty( $args['description'] ) ? $args['description'] : '';
			$topic_id    = ! empty( $value['topic_id'] ) ? absint( $value['topic_id'] ) : 0;
			$from_user   = ! empty( $value['from_user'] ) ? absint( $value['from_user'] ) : 0;

			// If topic is not associated with current user then skip.
			if ( get_current_user_id() !== $from_user || ! $topic_id || ! $from_user ) {
				return ob_get_clean();
			}

			// Check if topic is still exists.
			if ( ! get_post_status( $topic_id ) ) {
				return ob_get_clean();
			}

			$topic_title = '<a href="' . get_permalink( $topic_id ) . '"><strong>' . get_the_title( $topic_id ) . '</strong></a>';
			$description = str_replace( '{{TOPIC}}', $topic_title, $description );

			$this->format_notification( $icon, $description, $value );
		}

		return ob_get_clean();
	}

	/**
	 * User Mentioned callback.
	 *
	 * @since 0.0.1
	 * @param array<mixed> $args Arguments, if any to perform the callback.
	 * @return void
	 */
	public function user_mentioned_callback( $args ): void {
		$notifiable     = [];
		$user_id        = 0;
		$user_mentioned = 0;

		if ( ! empty( $args['caller'] ) && ( ! empty( $args['topic_id'] ) || ! empty( $args['comment_id'] ) ) && ! empty( $args['mentioned_user'] ) ) {
			$user_mentioned = 1;
			$topic_id       = absint( $args['topic_id'] ?? 0 );
			$comment_id     = absint( $args['comment_id'] ?? 0 );
			$caller         = absint( $args['caller'] );
			$user_id        = absint( $args['mentioned_user'] );

			// Check if portal notifications are enabled for this user (mention type).
			if ( ! Base::is_portal_notification_enabled( $user_id, 'mention' ) ) {
				return;
			}

			$notifiable = [
				'trigger'        => 'user_mentioned',
				'caller'         => $caller,
				'mentioned_user' => $user_id,
			];

			if ( $topic_id ) {
				$notifiable['topic_id'] = $topic_id;
			}
			if ( $comment_id ) {
				$notifiable['comment_id'] = $comment_id;
			}
		}

		if ( $user_id && ! empty( $notifiable ) ) {
			$timestamp          = ! empty( $args['timestamp'] ) ? $args['timestamp'] : suredash_get_timestamp();
			$notifications_data = sd_get_user_meta( $user_id, $this->option, true );

			if ( ! is_array( $notifications_data ) ) {
				$notifications_data = [];
			}

			$notifications_data[ $timestamp - $user_mentioned ] = $notifiable; // Subtract 1 second to make sure it's unique, because topic submission with user mention can be at the same time.
			sd_update_user_meta( $user_id, $this->option, $notifications_data );
		}
	}

	/**
	 * Format user mentioned notification.
	 *
	 * @since 0.0.1
	 * @param array<mixed> $args Arguments, if any to perform the callback.
	 * @param mixed        $value Value.
	 * @return string|false
	 */
	public function user_mentioned_format( $args, $value ) {
		$icon = ! empty( $args['icon'] ) ? $args['icon'] : '';

		ob_start();

		if ( ! is_array( $value ) || empty( $value ) ) {
			return ob_get_clean();
		}

		$topic_id   = absint( $value['topic_id'] ?? 0 );
		$comment_id = absint( $value['comment_id'] ?? 0 );

		if ( $topic_id && ! get_post_status( $topic_id ) ) {
			return ob_get_clean();
		}
		if ( $comment_id && ! get_comment( $comment_id ) ) {
			return ob_get_clean();
		}

		$description = ! empty( $args['description'] ) ? $args['description'] : '';

		$caller      = absint( $value['caller'] ?? 0 );
		$caller_name = suredash_get_notifier_caller( $caller );

		$topic_title  = $topic_id !== 0 ? __( 'Topic:', 'suredash' ) . '<a href="' . get_permalink( $topic_id ) . '"><strong>' . get_the_title( $topic_id ) . '</strong></a>' : '';
		$comment_link = $comment_id !== 0 ? '<a href="' . get_comment_link( $comment_id ) . '"><strong> Comment </strong></a>' : '';
		$description  = str_replace( '{{TOPIC}}', $topic_title ? $topic_title : $comment_link, $description );
		$description  = str_replace( '{{CALLER}}', $caller_name, $description );

		if ( ! is_string( $description ) ) {
			$description = '';
		}

		$icon = $topic_id ? 'FilePen' : 'AtSign';

		$this->format_notification( $icon, $description, $value );

		return ob_get_clean();
	}
}
