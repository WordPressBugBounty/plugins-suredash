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
	 * Rows shown per page in the notification list. More load on demand.
	 *
	 * @since 1.13.0
	 */
	public const PER_PAGE = 10;

	/**
	 * Most notification records kept per store (option or user meta row).
	 *
	 * Older records are dropped when a new one is saved. Change it with the
	 * `suredash_notifications_max_stored` filter.
	 *
	 * @since 1.13.0
	 */
	public const MAX_STORED = 200;

	/**
	 * Option keys that hold site-wide notifications.
	 *
	 * @since 1.13.0
	 */
	private const NOTIFICATION_OPTIONS = [ 'suredashboard_admin_notifications', 'suredashboard_common_notifications' ];

	/**
	 * User meta keys that hold per-user notifications.
	 *
	 * @since 1.13.0
	 */
	private const NOTIFICATION_USER_META = [ 'suredashboard_user_notifications', 'suredashboard_common_notifications' ];

	/**
	 * Most targets cleaned up in one request by the self-heal pass.
	 *
	 * @since 1.13.0
	 */
	private const HEAL_LIMIT = 20;

	/**
	 * How many lists were printed on this request.
	 *
	 * The first page renders into the desktop drawer, the mobile popup and the
	 * notification screen. Each copy after the first gets an id suffix so ids
	 * stay unique in the document.
	 *
	 * @var int
	 * @since 1.13.0
	 */
	private static $render_pass = 0;

	/**
	 * First page of rows, built once per request and shared by every list.
	 *
	 * @var array{rows: array<int|string, string>, next_offset: int, has_more: bool, unread_total: int, unread_after: bool}|null
	 * @since 1.13.0
	 */
	private static $first_page = null;

	/**
	 * Timestamps the current user has read, as keys for constant-time lookup.
	 *
	 * @var array<int, bool>|null
	 * @since 1.13.0
	 */
	private static $read_lookup = null;

	/**
	 * Deleted posts and comments found while rendering, removed at shutdown.
	 *
	 * @var array{posts: array<int, int>, comments: array<int, int>, users: array<int, int>}
	 * @since 1.13.0
	 */
	private static $dead_targets = [
		'posts'    => [],
		'comments' => [],
		'users'    => [],
	];

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

		// Drop notifications that point at content which no longer exists.
		add_action( 'deleted_post', [ $this, 'remove_notifications_for_post' ], 10, 2 );
		add_action( 'deleted_comment', [ $this, 'remove_notifications_for_comment' ], 10, 2 );
		add_action( 'deleted_user', [ $this, 'remove_notifications_for_user' ] );
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
			// The render loop passes the timestamp (the notification's key). The
			// search is only a fallback for callers that do not.
			$timestamp = $value['_sd_timestamp'] ?? array_search( $value, $this->get_notifications() );
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

		$is_read = $this->is_notification_read( $timestamp );

		// Copies of the list after the first get an id suffix in get_notifications_markup().
		$notification_element_id = 'notification-' . $timestamp;

		ob_start();

		?>
		<li class="portal-notification-item sd-flex sd-justify-between sd-items-start sd-p-8 sd-cursor-default"
		id="<?php echo esc_attr( $notification_element_id ); ?>"
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

		// Names, titles and excerpts in this row come from members: escape every
		// bracket (user ID 0 always escapes) so do_shortcode() runs nothing.
		echo do_shortcode( suredash_escape_shortcodes( (string) ob_get_clean(), 0 ) );
	}

	/**
	 * Get Notifications Markup.
	 *
	 * Prints the first page of rows plus a "Load more" button when more exist.
	 * The rows are built once per request, so the desktop drawer, the mobile
	 * popup and the notification screen all reuse the same work.
	 *
	 * @since 0.0.1
	 * @return string Notifications Markup.
	 */
	public function get_notifications_markup() {
		$page = $this->get_notifications_page( 0 );

		if ( empty( $page['rows'] ) ) {
			return '';
		}

		++self::$render_pass;

		$markup  = sprintf(
			'<ul class="portal-notification-list sd-no-space" data-status="all" data-pass="%1$d" data-unread-total="%2$d" data-next-offset="%3$d" data-unread-after="%4$d" data-load-more-label="%5$s" data-load-more-hint="%6$s" data-loading-label="%7$s" data-loading-hint="%8$s">',
			self::$render_pass,
			absint( $page['unread_total'] ),
			absint( $page['next_offset'] ),
			$page['unread_after'] ? 1 : 0,
			esc_attr__( 'Load more', 'suredash' ),
			esc_attr__( 'See older notifications', 'suredash' ),
			esc_attr__( 'Loading…', 'suredash' ),
			esc_attr__( 'Fetching older notifications', 'suredash' )
		);
		$markup .= $this->get_rows_markup( $page['rows'], self::$render_pass );
		$markup .= '</ul>';

		// Same markup as portalBuildNotificationLoadMore() in common.js.
		if ( $page['has_more'] ) {
			$markup .= sprintf(
				'<button type="button" class="portal-notification-load-more">
					<span class="portal-notification-load-more-icon" aria-hidden="true"></span>
					<span class="portal-notification-load-more-text">
						<span class="portal-notification-load-more-label">%1$s</span>
						<span class="portal-notification-load-more-hint">%2$s</span>
					</span>
				</button>',
				esc_html__( 'Load more', 'suredash' ),
				esc_html__( 'See older notifications', 'suredash' )
			);
		}

		return $markup;
	}

	/**
	 * Join rendered rows, giving element ids a suffix on repeat copies.
	 *
	 * @param array<int|string, string> $rows Row markup keyed by timestamp.
	 * @param int                       $pass Which copy of the list this is, starting at 1.
	 * @return string
	 * @since 1.13.0
	 */
	public function get_rows_markup( $rows, $pass = 1 ) {
		$markup = '';
		foreach ( $rows as $timestamp => $row ) {
			if ( $pass > 1 ) {
				$row = str_replace( 'id="notification-' . $timestamp . '"', 'id="notification-' . $timestamp . '-' . $pass . '"', $row );
			}
			$markup .= $row;
		}
		return $markup;
	}

	/**
	 * Build one page of notification rows.
	 *
	 * Reads every stored record (they live in a few option and user meta rows),
	 * drops the ones whose post or comment was deleted, then renders the newest
	 * valid records from `$offset` until `$limit` rows have markup. Posts,
	 * comments and users are loaded in batches per chunk, so a row reads them
	 * from the cache.
	 *
	 * @param int    $offset Position in the valid, newest-first record list.
	 * @param int    $limit  Rows to return.
	 * @param string $status `all`, or `unread` to page through unread records only.
	 * @return array{rows: array<int|string, string>, next_offset: int, has_more: bool, unread_total: int, unread_after: bool}
	 * @since 1.13.0
	 */
	public function get_notifications_page( $offset = 0, $limit = self::PER_PAGE, $status = 'all' ) {
		$is_first_page = $offset === 0 && $status === 'all';
		if ( $is_first_page && self::$first_page !== null ) {
			return self::$first_page;
		}

		$dataset       = $this->get_notifications_dataset();
		$notifications = $this->get_notifications();
		krsort( $notifications );

		$valid        = $this->filter_existing_notifications( $notifications );
		$unread_total = 0;
		foreach ( array_keys( $valid ) as $timestamp ) {
			if ( ! $this->is_notification_read( $timestamp ) ) {
				++$unread_total;
			}
		}

		if ( $status === 'unread' ) {
			$valid = array_filter(
				$valid,
				function( $timestamp ) {
					return ! $this->is_notification_read( $timestamp );
				},
				ARRAY_FILTER_USE_KEY
			);
		}

		$timestamps = array_keys( $valid );
		$total      = count( $timestamps );

		$rows      = [];
		$row_count = 0;
		$position  = $offset;
		while ( $row_count < $limit && $position < $total ) {
			$chunk = array_slice( $timestamps, $position, $limit );
			$this->prime_chunk( array_intersect_key( $valid, array_flip( $chunk ) ) );

			foreach ( $chunk as $timestamp ) {
				++$position;
				$notification        = $valid[ $timestamp ];
				$trigger             = $notification['trigger'] ?? '';
				$formatting_callback = $dataset[ $trigger ]['formatting_callback'] ?? '';
				if ( ! $trigger || ! $formatting_callback ) {
					continue;
				}

				$notification['_sd_timestamp'] = $timestamp;
				$row                           = (string) call_user_func_array( $formatting_callback, [ $dataset[ $trigger ], $notification ] );
				if ( trim( $row ) === '' ) {
					continue;
				}

				$rows[ $timestamp ] = $row;
				++$row_count;
				if ( $row_count >= $limit ) {
					break;
				}
			}
		}

		// Whether unread records exist past this page. The Unread tab uses it to
		// decide between filtering the loaded rows and asking for more.
		$unread_after = false;
		foreach ( array_slice( $timestamps, $position ) as $timestamp ) {
			if ( ! $this->is_notification_read( $timestamp ) ) {
				$unread_after = true;
				break;
			}
		}

		$page = [
			'rows'         => $rows,
			'next_offset'  => $position,
			'has_more'     => $position < $total,
			'unread_total' => $unread_total,
			'unread_after' => $unread_after,
		];

		if ( $is_first_page ) {
			self::$first_page = $page;
		}

		return $page;
	}

	/**
	 * Timestamps of every record stored for the current user, before any filter.
	 *
	 * Used to prune the read list. Read straight from the stores, so a record
	 * hidden by a user setting (e.g. new space alerts turned off) keeps its
	 * read mark.
	 *
	 * @return array<int, int>
	 * @since 1.13.0
	 */
	public function get_stored_notification_keys() {
		$keys = [];

		foreach ( self::NOTIFICATION_OPTIONS as $option ) {
			$stored = get_option( $option, [] );
			if ( is_array( $stored ) ) {
				$keys = array_merge( $keys, array_keys( $stored ) );
			}
		}

		foreach ( self::NOTIFICATION_USER_META as $meta_key ) {
			$stored = sd_get_user_meta( get_current_user_id(), $meta_key, true );
			if ( is_array( $stored ) ) {
				$keys = array_merge( $keys, array_keys( $stored ) );
			}
		}

		return array_values( array_unique( array_map( 'intval', $keys ) ) );
	}

	/**
	 * Remove notifications that point at a deleted post.
	 *
	 * Only SureDash content can be a notification target, so deleting other
	 * post types (revisions, attachments, menu items, orders) costs nothing.
	 *
	 * @param int           $post_id Post ID.
	 * @param \WP_Post|null $post    The deleted post.
	 * @return void
	 * @since 1.13.0
	 */
	public function remove_notifications_for_post( $post_id, $post = null ): void {
		if ( ! self::is_notification_post_type( $post instanceof \WP_Post ? $post->post_type : get_post_type( $post_id ) ) ) {
			return;
		}
		$this->remove_notifications_for_target( 'posts', absint( $post_id ) );
	}

	/**
	 * Remove notifications that point at a deleted comment.
	 *
	 * Skipped for comments on non-SureDash posts, which never get notifications.
	 *
	 * @param int              $comment_id Comment ID.
	 * @param \WP_Comment|null $comment    The deleted comment.
	 * @return void
	 * @since 1.13.0
	 */
	public function remove_notifications_for_comment( $comment_id, $comment = null ): void {
		if ( $comment instanceof \WP_Comment && ! self::is_notification_post_type( get_post_type( (int) $comment->comment_post_ID ) ) ) {
			return;
		}
		$this->remove_notifications_for_target( 'comments', absint( $comment_id ) );
	}

	/**
	 * Remove notifications whose actor was a deleted user.
	 *
	 * @param int $user_id User ID.
	 * @return void
	 * @since 1.13.0
	 */
	public function remove_notifications_for_user( $user_id ): void {
		$this->remove_notifications_for_target( 'users', absint( $user_id ) );
	}

	/**
	 * Remove dead notifications found while rendering this request.
	 *
	 * Covers content deleted before the delete hooks existed. Each target is
	 * checked against the database again before anything is removed.
	 *
	 * @return void
	 * @since 1.13.0
	 */
	public function heal_dead_notifications(): void {
		$budget = self::HEAL_LIMIT;
		foreach ( [ 'posts', 'comments', 'users' ] as $type ) {
			$dead = array_slice( self::$dead_targets[ $type ], 0, $budget );
			if ( empty( $dead ) ) {
				continue;
			}

			$still_there = $this->get_existing_ids( $type, $dead );
			foreach ( $dead as $id ) {
				if ( ! isset( $still_there[ $id ] ) ) {
					$this->remove_notifications_for_target( $type, $id );
				}
			}

			$budget -= count( $dead );
			if ( $budget <= 0 ) {
				break;
			}
		}

		self::$dead_targets = [
			'posts'    => [],
			'comments' => [],
			'users'    => [],
		];
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

	/**
	 * Whether a comment exists and is not in the trash.
	 *
	 * Same result as sd_specific_comment_exists(), but reads the comment the
	 * render loop already primed instead of running its own query.
	 *
	 * @param int|string $comment_id Comment ID.
	 * @return bool
	 * @since 1.13.0
	 */
	protected function is_live_comment( $comment_id ) {
		$comment = get_comment( absint( $comment_id ) );
		return $comment instanceof \WP_Comment && $comment->comment_approved !== 'trash';
	}

	/**
	 * Keep only the newest records before a notification store is saved.
	 *
	 * @param mixed $notifications Records keyed by timestamp.
	 * @return mixed
	 * @since 1.13.0
	 */
	protected function trim_stored_notifications( $notifications ) {
		$max = absint( apply_filters( 'suredash_notifications_max_stored', self::MAX_STORED ) );
		if ( ! is_array( $notifications ) || ! $max || count( $notifications ) <= $max ) {
			return $notifications;
		}

		krsort( $notifications );
		return array_slice( $notifications, 0, $max, true );
	}

	/**
	 * Whether posts of a type can be the target of a notification.
	 *
	 * @param string|false $post_type Post type.
	 * @return bool
	 * @since 1.13.0
	 */
	private static function is_notification_post_type( $post_type ) {
		return in_array( $post_type, [ SUREDASHBOARD_POST_TYPE, SUREDASHBOARD_FEED_POST_TYPE, SUREDASHBOARD_SUB_CONTENT_POST_TYPE ], true );
	}

	/**
	 * Keep only notifications whose posts, comments and actors still exist.
	 *
	 * WordPress does not cache a missing post or comment, so each lookup for a
	 * deleted one goes to the database again, several times per row. One ID
	 * query per type finds them up front. Deleted targets are queued for the
	 * shutdown cleanup.
	 *
	 * @param array<int|string, mixed> $notifications Notifications keyed by timestamp.
	 * @return array<int|string, mixed>
	 * @since 1.13.0
	 */
	private function filter_existing_notifications( $notifications ) {
		$wanted = [
			'posts'    => [],
			'comments' => [],
			'users'    => [],
		];
		foreach ( $notifications as $notification ) {
			$targets = $this->get_notification_targets( $notification );
			foreach ( $targets as $type => $ids ) {
				foreach ( $ids as $id ) {
					$wanted[ $type ][ $id ] = $id;
				}
			}
		}

		$existing = [
			'posts'    => $this->get_existing_ids( 'posts', $wanted['posts'] ),
			'comments' => $this->get_existing_ids( 'comments', $wanted['comments'] ),
			'users'    => $this->get_existing_ids( 'users', $wanted['users'] ),
		];

		$dead_found = false;
		foreach ( [ 'posts', 'comments', 'users' ] as $type ) {
			foreach ( array_diff_key( $wanted[ $type ], $existing[ $type ] ) as $id ) {
				self::$dead_targets[ $type ][ $id ] = $id;
				$dead_found                         = true;
			}
		}
		if ( $dead_found && ! has_action( 'shutdown', [ $this, 'heal_dead_notifications' ] ) ) {
			add_action( 'shutdown', [ $this, 'heal_dead_notifications' ] );
		}

		return array_filter(
			$notifications,
			function( $notification ) use ( $existing ) {
				foreach ( $this->get_notification_targets( $notification ) as $type => $ids ) {
					foreach ( $ids as $id ) {
						if ( ! isset( $existing[ $type ][ $id ] ) ) {
							return false;
						}
						// A trashed comment's row is hidden, so it must not count as unread either.
						// It is not treated as deleted: the comment can be restored.
						if ( $type === 'comments' && $existing['comments'][ $id ] === 'trash' ) {
							return false;
						}
					}
				}
				return true;
			}
		);
	}

	/**
	 * Return which of the given post, comment or user IDs exist, as `id => id`.
	 * Comments map to their `comment_approved` status instead, so trashed ones
	 * can be told apart.
	 *
	 * @param string     $type One of `posts`, `comments` or `users`.
	 * @param array<int> $ids  IDs to check.
	 * @return array<int, int|string>
	 * @since 1.13.0
	 */
	private function get_existing_ids( $type, $ids ) {
		global $wpdb;

		$ids = array_values( array_filter( array_map( 'absint', $ids ) ) );
		if ( empty( $ids ) ) {
			return [];
		}

		$found = [];
		foreach ( array_chunk( $ids, 500 ) as $chunk ) {
			if ( $type === 'comments' ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One indexed lookup per page build.
				$comments = $wpdb->get_results( $wpdb->prepare( "SELECT comment_ID, comment_approved FROM {$wpdb->comments} WHERE comment_ID IN (" . implode( ',', array_fill( 0, count( $chunk ), '%d' ) ) . ')', $chunk ) );
				foreach ( (array) $comments as $comment ) {
					$found[ absint( $comment->comment_ID ) ] = (string) $comment->comment_approved;
				}
				continue;
			}
			if ( $type === 'users' ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One indexed lookup per page build.
				$rows = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->users} WHERE ID IN (" . implode( ',', array_fill( 0, count( $chunk ), '%d' ) ) . ')', $chunk ) );
			} else {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One indexed lookup per page build.
				$rows = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE ID IN (" . implode( ',', array_fill( 0, count( $chunk ), '%d' ) ) . ')', $chunk ) );
			}
			foreach ( $rows as $id ) {
				$found[ absint( $id ) ] = absint( $id );
			}
		}

		return $found;
	}

	/**
	 * Load the posts, comments and users a chunk of rows will read, in batches.
	 *
	 * @param array<int|string, mixed> $notifications Notifications in the chunk.
	 * @return void
	 * @since 1.13.0
	 */
	private function prime_chunk( $notifications ): void {
		$post_ids    = [];
		$comment_ids = [];
		$user_ids    = [];
		foreach ( $notifications as $notification ) {
			$targets     = $this->get_notification_targets( $notification );
			$post_ids    = array_merge( $post_ids, $targets['posts'] );
			$comment_ids = array_merge( $comment_ids, $targets['comments'] );
			$user_ids    = array_merge( $user_ids, $targets['users'] );
			// The avatar falls back to the space title.
			$post_ids[] = absint( $notification['space_id'] ?? 0 );
			foreach ( [ 'caller', 'from_user', 'mentioned_user' ] as $user_key ) {
				$user_ids[] = absint( $notification[ $user_key ] ?? 0 );
			}
		}

		$post_ids    = array_values( array_unique( array_filter( $post_ids ) ) );
		$comment_ids = array_values( array_unique( array_filter( $comment_ids ) ) );
		$user_ids    = array_values( array_unique( array_filter( $user_ids ) ) );

		if ( ! empty( $post_ids ) ) {
			_prime_post_caches( $post_ids, true, true );
		}
		if ( ! empty( $comment_ids ) ) {
			_prime_comment_caches( $comment_ids, false );

			// get_comment_link() loads the post each comment belongs to.
			$comment_post_ids = [];
			foreach ( $comment_ids as $comment_id ) {
				$comment = get_comment( $comment_id );
				if ( $comment instanceof \WP_Comment ) {
					$comment_post_ids[] = absint( $comment->comment_post_ID );
				}
			}
			$comment_post_ids = array_diff( array_unique( array_filter( $comment_post_ids ) ), $post_ids );
			if ( ! empty( $comment_post_ids ) ) {
				_prime_post_caches( array_values( $comment_post_ids ), true, true );
			}
		}
		if ( ! empty( $user_ids ) ) {
			cache_users( $user_ids );
		}
	}

	/**
	 * Whether the current user has read a notification.
	 *
	 * @param int|string $timestamp Notification timestamp.
	 * @return bool
	 * @since 1.13.0
	 */
	private function is_notification_read( $timestamp ) {
		if ( self::$read_lookup === null ) {
			$status            = sd_get_user_meta( get_current_user_id(), 'portal_user_notification_status', true );
			$read              = is_array( $status ) && isset( $status['read'] ) && is_array( $status['read'] ) ? $status['read'] : [];
			self::$read_lookup = array_fill_keys( array_map( 'intval', $read ), true );
		}

		return isset( self::$read_lookup[ (int) $timestamp ] );
	}

	/**
	 * Remove every stored notification that points at one post, comment or user.
	 *
	 * Site-wide options are filtered directly. Per-user meta is found with one
	 * LIKE query on the serialized value, then each match is filtered exactly.
	 *
	 * @param string $type One of `posts`, `comments` or `users`.
	 * @param int    $id   Post, comment or user ID.
	 * @return void
	 * @since 1.13.0
	 */
	private function remove_notifications_for_target( $type, $id ): void {
		global $wpdb;

		if ( ! $id ) {
			return;
		}

		$keep = function( $notification ) use ( $type, $id ) {
			return ! in_array( $id, $this->get_notification_targets( $notification )[ $type ], true );
		};

		foreach ( self::NOTIFICATION_OPTIONS as $option ) {
			$stored = get_option( $option, [] );
			if ( ! is_array( $stored ) || empty( $stored ) ) {
				continue;
			}
			$kept = array_filter( $stored, $keep );
			if ( count( $kept ) !== count( $stored ) ) {
				update_option( $option, $kept );
			}
		}

		$args = array_merge(
			self::NOTIFICATION_USER_META,
			[
				'%' . $wpdb->esc_like( 'i:' . $id . ';' ) . '%',
				'%' . $wpdb->esc_like( '"' . $id . '"' ) . '%',
			]
		);
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Runs only when content is deleted. The LIKE is a pre-filter, matches are checked exactly below.
		$matches = $wpdb->get_results( $wpdb->prepare( "SELECT user_id, meta_key FROM {$wpdb->usermeta} WHERE meta_key IN (" . implode( ',', array_fill( 0, count( self::NOTIFICATION_USER_META ), '%s' ) ) . ') AND ( meta_value LIKE %s OR meta_value LIKE %s )', $args ) );

		foreach ( (array) $matches as $match ) {
			$stored = sd_get_user_meta( absint( $match->user_id ), $match->meta_key, true );
			if ( ! is_array( $stored ) || empty( $stored ) ) {
				continue;
			}
			$kept = array_filter( $stored, $keep );
			if ( count( $kept ) !== count( $stored ) ) {
				sd_update_user_meta( absint( $match->user_id ), $match->meta_key, $kept );
			}
		}
	}

	/**
	 * Get the post, comment and user IDs a notification must point to.
	 *
	 * Mirrors the existence checks in each trigger's format callback, so a
	 * notification is only skipped when its callback would render nothing.
	 *
	 * @param array<mixed> $notification Notification data.
	 * @return array{posts: array<int>, comments: array<int>, users: array<int>}
	 * @since 1.13.0
	 */
	private function get_notification_targets( $notification ) {
		$targets = [
			'posts'    => [],
			'comments' => [],
			'users'    => [],
		];

		if ( ! is_array( $notification ) ) {
			return $targets;
		}

		// The actor. WordPress does not cache a missing user either, so a row
		// from a deleted user would look them up several times.
		$targets['users'][] = absint( $notification['caller'] ?? ( $notification['from_user'] ?? 0 ) );

		$trigger    = $notification['trigger'] ?? '';
		$topic_id   = absint( $notification['topic_id'] ?? 0 );
		$comment_id = absint( $notification['comment_id'] ?? 0 );

		switch ( $trigger ) {
			case 'new_space':
				$targets['posts'][] = absint( $notification['space_id'] ?? 0 );
				break;
			case 'submitted_topic':
			case 'topic_approved':
				$targets['posts'][] = $topic_id;
				break;
			case 'topic_comment':
				$targets['posts'][]    = $topic_id;
				$targets['comments'][] = $comment_id;
				break;
			case 'comment_reply':
				$targets['comments'][] = $comment_id;
				break;
			case 'entity_like':
				$entity_key               = ( $notification['entity'] ?? 'post' ) === 'comment' ? 'comments' : 'posts';
				$targets[ $entity_key ][] = absint( $notification['entity_id'] ?? 0 );
				break;
			case 'user_mentioned':
				$targets['posts'][]    = $topic_id;
				$targets['comments'][] = $comment_id;
				break;
		}

		$targets['posts']    = array_values( array_filter( $targets['posts'] ) );
		$targets['comments'] = array_values( array_filter( $targets['comments'] ) );
		$targets['users']    = array_values( array_filter( $targets['users'] ) );

		return $targets;
	}
}
