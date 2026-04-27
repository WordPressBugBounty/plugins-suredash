<?php
/**
 * Dashboard Core's Common Notifier.
 *
 * @package SureDash
 * @since 0.0.1
 */

namespace SureDashboard\Core\Notifier;

use SureDashboard\Inc\Traits\Get_Instance;
use SureDashboard\Inc\Utils\Labels;

defined( 'ABSPATH' ) || exit;

/**
 * All the misc & common notification mechanism handle here.
 *
 * @since 0.0.1
 */
class Common_Notifier extends Base {
	use Get_Instance;

	/**
	 * Option name.
	 *
	 * @var string
	 */
	private $option = 'suredashboard_common_notifications';

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
			'new_space'     => [
				'icon'                => 'SquarePlus',
				'description'         => '{{SPACE}} ' . __( 'new space has been introduced.', 'suredash' ),
				'trigger'             => 'suredashboard_new_space',
				'callback'            => [ $this, 'new_space_callback' ],
				'formatting_callback' => [ $this, 'new_space_format' ],
			],
			'comment_reply' => [
				'icon'                => 'Bell',
				'description'         => Labels::get_label( 'comment_reply_message' ),
				'trigger'             => 'suredashboard_comment_reply',
				'callback'            => [ $this, 'comment_reply_callback' ],
				'formatting_callback' => [ $this, 'comment_reply_format' ],
			],
			'topic_comment' => [
				'icon'                => 'Bell',
				'description'         => Labels::get_label( 'plural_post_comment_message' ),
				'trigger'             => 'suredashboard_topic_comment',
				'callback'            => [ $this, 'post_comment_callback' ],
				'formatting_callback' => [ $this, 'topic_comment_format' ],
			],
			'entity_like'   => [
				'icon'                => 'Heart',
				'description'         => Labels::get_label( 'entity_likes' ),
				'trigger'             => 'suredashboard_entity_like',
				'callback'            => [ $this, 'entity_like_callback' ],
				'formatting_callback' => [ $this, 'entity_like_format' ],
			],
		];

		return apply_filters( 'suredashboard_common_notifications_dataset', $dataset );
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
		$options = get_option( $this->option, [] );

		$user_specifics = sd_get_user_meta( get_current_user_id(), $this->option, true );
		$user_specifics = is_array( $user_specifics ) ? $user_specifics : [];

		$all_notifications = $options + $user_specifics;

		// Filter notifications based on user registration date.
		return $this->filter_notifications_by_registration_date( $all_notifications );
	}

	/**
	 * New space callback.
	 *
	 * @since 0.0.1
	 * @param array<mixed> $args Arguments, if any to perform the callback.
	 * @return void
	 */
	public function new_space_callback( $args ): void {
		$notifiable = [];

		if ( ! empty( $args['space_id'] ) ) {
			$space_id = absint( $args['space_id'] );

			// Skip if the space is hidden.
			if ( suredash_is_space_hidden( $space_id ) ) {
				return;
			}

			$notifiable = [
				'trigger'  => 'new_space',
				'space_id' => $space_id,
			];
		}

		if ( ! empty( $notifiable ) ) {
			$timestamp          = ! empty( $args['timestamp'] ) ? $args['timestamp'] : suredash_get_timestamp();
			$notifications_data = get_option( $this->option, [] );

			if ( ! is_array( $notifications_data ) ) {
				$notifications_data = [];
			}

			$notifications_data[ $timestamp ] = $notifiable;
			update_option( $this->option, $notifications_data );
		}
	}

	/**
	 * Comment reply callback.
	 *
	 * @since 0.0.1
	 * @param array<mixed> $args Arguments, if any to perform the callback.
	 * @return void
	 */
	public function comment_reply_callback( $args ): void {
		$notifiable = [];
		$user_id    = 0;

		if ( ! empty( $args['caller'] ) && ! empty( $args['comment_id'] ) && ! empty( $args['mentioned_user'] ) ) {
			$comment_id = absint( $args['comment_id'] );
			$caller     = absint( $args['caller'] );
			$user_id    = absint( $args['mentioned_user'] );

			if ( ! $comment_id || ! $user_id ) {
				return;
			}

			// Skip if the commenter is replying to his own comment.
			if ( $caller === $user_id ) {
				return;
			}

			// Check if portal notifications are enabled for this user (comment_replies type).
			if ( ! Base::is_portal_notification_enabled( $user_id, 'comment_replies' ) ) {
				return;
			}

			$notifiable = [
				'trigger'        => 'comment_reply',
				'caller'         => $caller,
				'mentioned_user' => $user_id,
				'comment_id'     => $comment_id,
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
	 * Topic comment callback.
	 *
	 * @since 0.0.1
	 * @param array<mixed> $args Arguments, if any to perform the callback.
	 * @return void
	 */
	public function post_comment_callback( $args ): void {
		if ( ! empty( $args['caller'] ) && ! empty( $args['comment_id'] ) && ! empty( $args['topic_id'] ) && ! empty( $args['topic_author'] ) ) {
			$caller       = absint( $args['caller'] );
			$comment_id   = absint( $args['comment_id'] );
			$topic_id     = absint( $args['topic_id'] );
			$topic_author = absint( $args['topic_author'] );

			// Skip if the commenter is the topic author.
			if ( $caller === $topic_author ) {
				return;
			}

			// Get comment count excluding author's comments.
			$topic_author_id = get_post_field( 'post_author', $topic_id );
			$comments_args   = [
				'post_id'        => $topic_id,
				'author__not_in' => [ $topic_author_id ], // Exclude post author's comments.
				'count'          => true, // Return only the count.
				'status'         => 'approve', // Only count approved comments.
			];
			$comment_count   = get_comments( $comments_args ); // @phpstan-ignore-line

			// Get existing notifications.
			$notifications_data = sd_get_user_meta( $topic_author, $this->option, true );
			if ( ! is_array( $notifications_data ) ) {
				$notifications_data = [];
			}

			// Look for existing topic_comment notification for this topic.
			foreach ( $notifications_data as $timestamp => $notification ) {
				if (
					isset( $notification['trigger'] ) &&
					$notification['trigger'] === 'topic_comment' &&
					isset( $notification['topic_id'] ) &&
					$notification['topic_id'] === $topic_id
				) {
					unset( $notifications_data[ $timestamp ] );
					break;
				}
			}

			// Create new notification.
			$notifiable = [
				'trigger'      => 'topic_comment',
				'caller'       => $caller,
				'comment_id'   => $comment_id,
				'topic_id'     => $topic_id,
				'topic_author' => $topic_author,
				'count'        => $comment_count,
			];

			// Check if portal notifications are enabled for this user (post_replies type).
			if ( ! Base::is_portal_notification_enabled( $topic_author, 'post_replies' ) ) {
				return;
			}

			$current_timestamp                        = suredash_get_timestamp();
			$notifications_data[ $current_timestamp ] = $notifiable;

			sd_update_user_meta( $topic_author, $this->option, $notifications_data );
		}
	}

	/**
	 * Entity like callback (Topic/Comment).
	 *
	 * @since 0.0.1
	 * @param array<mixed> $args Arguments, if any to perform the callback.
	 * @return void
	 */
	public function entity_like_callback( $args ): void {
		if ( ! empty( $args['caller'] ) && ! empty( $args['entity_id'] ) && ! empty( $args['author'] ) && isset( $args['count'] ) ) {
			$caller    = absint( $args['caller'] );
			$entity    = ! empty( $args['entity'] ) ? $args['entity'] : 'post';
			$entity_id = absint( $args['entity_id'] );
			$author    = absint( $args['author'] );
			$count     = absint( $args['count'] );

			// Skip if the liker is the owner of the entity.
			if ( $caller === $author ) {
				return;
			}

			// Get existing notifications.
			$notifications_data = sd_get_user_meta( $author, $this->option, true );
			if ( ! is_array( $notifications_data ) ) {
				$notifications_data = [];
			}

			// Look for existing topic_like notification for this topic.
			foreach ( $notifications_data as $timestamp => $notification ) {
				if (
					isset( $notification['trigger'] ) &&
					$notification['trigger'] === 'entity_like' &&
					isset( $notification['entity_id'] ) &&
					$notification['entity_id'] === $entity_id
				) {
					// Remove existing notification.
					unset( $notifications_data[ $timestamp ] );
					break;
				}
			}

			// Create new notification with updated count.
			$notifiable = [
				'trigger'   => 'entity_like',
				'caller'    => $caller,
				'entity_id' => $entity_id,
				'author'    => $author,
				'count'     => $count,
				'entity'    => $entity,
			];
			// Check if portal notifications are enabled for this user (general type for likes).
			if ( ! Base::is_portal_notification_enabled( $author, 'general' ) ) {
				return;
			}

			$current_timestamp                        = suredash_get_timestamp();
			$notifications_data[ $current_timestamp ] = $notifiable;

			sd_update_user_meta( $author, $this->option, $notifications_data );
		}
	}

	/**
	 * Format new space notification.
	 *
	 * @since 0.0.1
	 * @param array<mixed> $args Arguments, if any to perform the callback.
	 * @param mixed        $value Value.
	 * @return string|false
	 */
	public function new_space_format( $args, $value ) {
		$icon = ! empty( $args['icon'] ) ? $args['icon'] : '';

		ob_start();

		if ( is_array( $value ) && ! empty( $value ) ) {
			$description = ! empty( $args['description'] ) ? $args['description'] : '';
			$space_id    = ! empty( $value['space_id'] ) ? absint( $value['space_id'] ) : 0;

			// Check if topic is still exists.
			if ( ! get_post_status( $space_id ) ) {
				return ob_get_clean();
			}

			// Skip if the space is hidden.
			if ( suredash_is_space_hidden( $space_id ) ) {
				return ob_get_clean();
			}

			// Skip if the current user does not have access to the space.
			if ( $space_id && suredash_is_post_protected( $space_id ) ) {
				return ob_get_clean();
			}

			$space_title = '<a href="' . get_permalink( $space_id ) . '"><strong>' . get_the_title( $space_id ) . '</strong></a>';

			$description = str_replace( '{{SPACE}}', $space_title, $description );
			$this->format_notification( $icon, $description, $value );
		}

		return ob_get_clean();
	}

	/**
	 * Format comment reply notification.
	 *
	 * @since 0.0.1
	 * @param array<mixed> $args Arguments, if any to perform the callback.
	 * @param mixed        $value Value.
	 * @return string|false
	 */
	public function comment_reply_format( $args, $value ) {
		$icon = ! empty( $args['icon'] ) ? $args['icon'] : '';

		ob_start();

		if ( is_array( $value ) && ! empty( $value ) ) {
			$comment_id     = absint( $value['comment_id'] ?? 0 );
			$caller         = absint( $value['caller'] ?? 0 );
			$mentioned_user = absint( $value['mentioned_user'] ?? 0 );
			$description    = ! empty( $args['description'] ) ? $args['description'] : '';

			if ( ! $comment_id || ! $caller || ! $mentioned_user ) {
				return ob_get_clean();
			}

			// Skip if not the right user (mentioned_user should be current user).
			if ( get_current_user_id() !== $mentioned_user ) {
				return ob_get_clean();
			}

			$comment = get_comment( $comment_id );
			if ( ! $comment || ! $comment->comment_parent ) {
				return ob_get_clean();
			}

			$comment_parent = get_comment( $comment->comment_parent );
			if ( ! $comment_parent ) {
				return ob_get_clean();
			}

			// Skip if the current user does not have access to the post the comment belongs to.
			$comment_post_id = absint( $comment->comment_post_ID );
			if ( $comment_post_id && suredash_is_post_protected( $comment_post_id ) ) {
				return ob_get_clean();
			}

			$caller_name = suredash_get_notifier_caller( $caller );

			$excerpt          = wp_trim_words( $comment_parent->comment_content, 5, '...' );
			$excerpt          = trim( $excerpt );
			$comment_link_url = get_comment_link( $comment_id );
			if ( ! $comment_link_url ) {
				// Fallback to post permalink if comment link fails.
				$post_id          = absint( $comment->comment_post_ID );
				$comment_link_url = get_permalink( $post_id );
			}
			$comment_link_url = $comment_link_url ? $comment_link_url : '#';
			$comment_link     = '<a href="' . esc_url( $comment_link_url ) . '"><strong>' . __( 'Comment:', 'suredash' ) . '</strong> ' . esc_html( $excerpt ) . '</a>';

			$description = str_replace( '{{COMMENT}}', $comment_link, $description );
			$description = str_replace( '{{CALLER}}', $caller_name, $description );
			if ( is_array( $description ) ) {
				$description = implode( ' ', $description );
			}

			$description = (string) $description;
			$this->format_notification( $icon, $description, $value );
		}

		return ob_get_clean();
	}

	/**
	 * Format topic comment notification.
	 *
	 * @since 0.0.1
	 * @param array<mixed> $args Arguments, if any to perform the callback.
	 * @param mixed        $value Value.
	 * @return string|false
	 */
	public function topic_comment_format( $args, $value ) {
		$icon = ! empty( $args['icon'] ) ? $args['icon'] : '';
		if ( ! sd_specific_comment_exists( $value['comment_id'] ) ) {
			return false;
		}

		ob_start();

		if ( is_array( $value ) && ! empty( $value ) ) {
			$caller = absint( $value['caller'] ?? 0 );

			// For topic comments, verify caller and check if current user is topic author.
			$topic_id = absint( $value['topic_id'] ?? 0 );
			if ( ! $caller || ! $topic_id || get_current_user_id() !== $value['topic_author'] ) {
				return ob_get_clean();
			}

			$count    = absint( $value['count'] ?? 1 );
			$topic_id = absint( $value['topic_id'] ?? 0 );

			if ( ! $topic_id || ! get_post_status( $topic_id ) ) {
				return ob_get_clean();
			}

			// Skip if the current user does not have access to the topic.
			if ( suredash_is_post_protected( $topic_id ) ) {
				return ob_get_clean();
			}

			$caller_name = suredash_get_notifier_caller( $caller );
			$description = ! empty( $args['description'] ) ? $args['description'] : '';
			$comment_id  = absint( $value['comment_id'] ?? 0 );
			// Link directly to the comment instead of just the post.
			$comment_link = $comment_id ? get_comment_link( $comment_id ) : get_permalink( $topic_id );
			$comment_link = $comment_link ? $comment_link : '';
			$topic_link   = '<a href="' . esc_url( $comment_link ) . '"><strong>' . get_the_title( $topic_id ) . '</strong></a>';
			if ( $count <= 1 ) {
				$description = Labels::get_label( 'single_post_comment_message' );
			} else {
				$description = str_replace( '{{COUNT}}', strval( $count - 1 ), (string) $description );
			}
			$description = str_replace( '{{COUNT}}', '<strong>' . ( $count - 1 ) . '</strong>', $description );
			$description = str_replace( '{{TOPIC}}', $topic_link, $description );
			$description = str_replace( '{{CALLER}}', $caller_name, $description );

			if ( is_array( $description ) ) {
				$description = implode( ' ', $description );
			}
			$description = (string) $description;
			$this->format_notification( $icon, $description, $value );
		}

		return ob_get_clean();
	}

	/**
	 * Format topic like notification.
	 *
	 * @since 0.0.1
	 * @param array<mixed> $args Arguments, if any to perform the callback.
	 * @param mixed        $value Value.
	 * @return string|false
	 */
	public function entity_like_format( $args, $value ) {
		$icon = ! empty( $args['icon'] ) ? $args['icon'] : '';

		if ( $value['entity'] === 'comment' && ! sd_specific_comment_exists( $value['entity_id'] ) ) {
			return false;
		}

		ob_start();

		if ( is_array( $value ) && ! empty( $value ) ) {
			$caller = absint( $value['caller'] ?? 0 );

			// For topic likes, verify caller and check if current user is topic author.
			if ( ! $caller || ! isset( $value['entity_id'] ) ) {
				return ob_get_clean();
			}

			// Return if liking to own entity.
			$author = absint( $value['author'] ?? 0 );
			if ( $caller === $author ) {
				return ob_get_clean();
			}

			$entity    = ! empty( $value['entity'] ) ? $value['entity'] : 'post';
			$count     = isset( $value['count'] ) ? absint( $value['count'] ) : 1;
			$entity_id = absint( $value['entity_id'] );
			$status    = $entity === 'post' ? get_post_status( $entity_id ) : wp_get_comment_status( $entity_id );

			if ( $entity === 'post' ) {
				$title = get_the_title( $entity_id );
			} else {
				$comment = get_comment( $entity_id );
				$title   = $comment->comment_content ?? '';
			}

			if ( ! $entity_id || ! $status ) {
				return ob_get_clean();
			}

			// Skip if the current user does not have access to the entity's post.
			$entity_post_id = $entity === 'post' ? $entity_id : absint( $comment->comment_post_ID ?? 0 );
			if ( $entity_post_id && suredash_is_post_protected( $entity_post_id ) ) {
				return ob_get_clean();
			}

			$caller_name = suredash_get_notifier_caller( $caller );
			$description = ! empty( $args['description'] ) ? $args['description'] : '';
			$title       = wp_trim_words( suredash_clean_text( $title ), 5, '...' );
			$entity_link = '<a href="' . get_permalink( $entity_id ) . '"><strong>' . esc_html( $title ) . '</strong></a>';

			// Choose between singular and plural messages.
			if ( $count <= 1 ) {
				$description = Labels::get_label( 'entity_like' );
			} else {
				$description = str_replace( '{{COUNT}}', strval( $count - 1 ), (string) $description );
			}

			$description = str_replace( '{{TOPIC}}', $entity_link, $description );
			$description = str_replace( '{{ENTITY}}', $entity, $description );
			$description = str_replace( '{{CALLER}}', '<strong>' . $caller_name . '</strong>', $description );
			if ( is_array( $description ) ) {
				$description = implode( ' ', $description );
			}
			$description = (string) $description;
			$this->format_notification( $icon, $description, $value );
		}

		return ob_get_clean();
	}
}
