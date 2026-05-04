<?php
/**
 * Analytics — BSF usage tracking and KPI reporting.
 *
 * Integrates with BSF Analytics to send:
 *
 * 1. Plugin data: free_version, site_language, pro_active.
 *
 * 2. One-time events (via BSF_Analytics_Events, deduplicated):
 *    - plugin_activated    — first activation, with install source.
 *    - plugin_updated      — on each version bump, with from_version.
 *    - first_space_published       — first portal space goes live.
 *    - first_community_post_created — first community post published.
 *    - onboarding_completed — yes/no, with skipped_on_step if skipped.
 *    - integration_enabled  — comma-separated list (google_login, facebook_login, surecart, suremembers).
 *    - feeds_enabled        — community feeds turned on.
 *    - global_sidebar_enabled — at least one sidebar widget configured.
 *
 * 3. Daily KPI records (last 2 days, sent with each analytics ping):
 *    - community_posts   — new discussion posts published (DB query).
 *    - community_content — new lessons/resources/events published (DB query).
 *    - comments          — approved comments on SureDash post types (DB query).
 *    - members_joined    — new suredash_user registrations (DB query).
 *    - reactions         — post/comment likes by members (daily counter, increment-on-action).
 *    - logins            — suredash_user role logins (daily counter, increment-on-action).
 *    - bookmarks         — items bookmarked by members (daily counter, increment-on-action).
 *
 *    Daily counters use wp_options with autoload=false (key: suredash_kpi_{metric}_{date}).
 *    Counters are cleaned up after being reported.
 *
 *    Activity thresholds (30-day sum of all KPIs):
 *    - Inactive:     0–10   (abandoned or freshly installed).
 *    - Active:       11–100 (regular community usage).
 *    - Super Active: 101+   (thriving, high-engagement community).
 *
 * @package SureDashboard
 * @since 0.0.6
 */

namespace SureDashboard\Inc\Utils;

use SureDashboard\Inc\Traits\Get_Instance;

defined( 'ABSPATH' ) || exit;

/**
 * Analytics class.
 *
 * @since 0.0.6
 * @package SureDashboard
 */
class Analytics {
	use Get_Instance;

	/**
	 * Shared BSF_Analytics_Events instance.
	 *
	 * @var \BSF_Analytics_Events|null
	 */
	private static $events = null;

	/**
	 *  Constructor
	 */
	public function __construct() {
		$this->set_bsf_analytics_entity();
		add_filter( 'bsf_core_stats', [ $this, 'add_suredash_analytics_data' ] );

		// Hook-based events.
		add_action( 'suredash_update_after', [ $this, 'track_plugin_updated' ] );
		add_action( 'transition_post_status', [ $this, 'track_first_space_published' ], 10, 3 );
		add_action( 'transition_post_status', [ $this, 'track_first_community_post_created' ], 10, 3 );

		// Capture previous version before maintenance overwrites it.
		add_action( 'admin_init', [ $this, 'capture_previous_version' ], 1 );

		// State-based events — run on init at priority 98 (after BSF Analytics library loads
		// at default priority, but before maybe_track_analytics sends at priority 99).
		if ( get_transient( 'suredash_state_events_checked' ) === false ) {
			add_action( 'init', [ $this, 'detect_state_events' ], 98 );
		}

		// KPI daily counters — lightweight hooks for engagement tracking.
		add_action( 'suredash_entity_like_reaction', [ $this, 'track_kpi_reaction' ], 10, 4 );
		add_action( 'suredash_item_bookmark', [ $this, 'track_kpi_bookmark' ], 10, 4 );
		add_action( 'wp_login', [ $this, 'track_kpi_login' ], 10, 2 );
	}

	/**
	 * Get shared BSF_Analytics_Events instance.
	 *
	 * @return \BSF_Analytics_Events|null
	 */
	public static function events() {
		if ( ! class_exists( 'BSF_Analytics_Events' ) ) {
			return null;
		}

		if ( self::$events === null ) {
			self::$events = new \BSF_Analytics_Events( 'suredash' );
		}

		return self::$events;
	}

	/**
	 * Set BSF Analytics Entity.
	 *
	 * @since 0.0.5
	 */
	public function set_bsf_analytics_entity(): void {

		$sd_bsf_analytics = \BSF_Analytics_Loader::get_instance(); // @phpstan-ignore-line

		$sd_bsf_analytics->set_entity(
			[
				'suredash' => [
					'product_name'        => 'SureDash',
					'path'                => SUREDASHBOARD_DIR . 'inc/lib/bsf-analytics',
					'author'              => 'SureDash',
					'time_to_display'     => '+24 hours',
					'deactivation_survey' => apply_filters(
						'suredash_deactivation_survey_data',
						[
							[
								'id'                => 'deactivation-survey-suredash',
								'popup_logo'        => SUREDASHBOARD_URL . 'assets/icons/icon.svg',
								'plugin_slug'       => 'suredash',
								'popup_title'       => __( 'Quick Feedback', 'suredash' ),
								'support_url'       => 'https://suredash.com/contact/',
								'popup_description' => __( 'If you have a moment, please share why you are deactivating SureDash:', 'suredash' ),
								'show_on_screens'   => [ 'plugins' ],
								'plugin_version'    => SUREDASHBOARD_VER,
							],
						]
					),
					'hide_optin_checkbox' => true,
				],
			]
		);
	}

	/**
	 * Capture the currently-saved version before maintenance.php overwrites it.
	 * Stored for use in track_plugin_updated() to populate the from_version property.
	 *
	 * Runs on admin_init at priority 1, before maintenance.php runs.
	 *
	 * @since 1.7.3
	 */
	public function capture_previous_version(): void {
		$saved = get_option( 'suredash_saved_version', '' );
		$prev  = get_option( 'suredash_usage_prev_version', '' );
		if ( $saved && $saved !== $prev && version_compare( (string) $saved, SUREDASHBOARD_VER, '<' ) ) {
			update_option( 'suredash_usage_prev_version', $saved );
		}
	}

	/**
	 * Detect and track one-time state-based events.
	 *
	 * Called on init when the daily transient is absent.
	 * BSF_Analytics_Events dedup prevents duplicate tracking across calls.
	 *
	 * @since 1.7.3
	 */
	public function detect_state_events(): void {
		$events = self::events();
		if ( $events === null ) {
			return;
		}

		set_transient( 'suredash_state_events_checked', true, DAY_IN_SECONDS );

		$this->track_plugin_activated_state( $events );
		$this->track_onboarding_events( $events );
		$this->track_feature_events( $events );
		$this->track_integration_events( $events );

		// pro_license_activated, leaderboard_enabled — handled by suredash-pro via detect_pro_state_events().
	}

	/**
	 * Track plugin_updated event when the plugin version changes.
	 *
	 * Hooked to suredash_update_after (fired by maintenance.php after version bump).
	 * Uses flush_pushed so the event re-tracks on each upgrade.
	 *
	 * @since 1.7.3
	 */
	public function track_plugin_updated(): void {
		if ( self::events() === null ) {
			return;
		}

		$prev_version = (string) get_option( 'suredash_usage_prev_version', '' );

		self::events()->flush_pushed( [ 'plugin_updated' ] );
		self::events()->track(
			'plugin_updated',
			SUREDASHBOARD_VER,
			[
				'from_version' => $prev_version,
			]
		);
	}

	/**
	 * Track first_space_published when the first portal space is published.
	 *
	 * This is the activation event — the moment SureDash delivers its core value.
	 *
	 * @param string   $new_status New post status.
	 * @param string   $old_status Previous post status.
	 * @param \WP_Post $post       Post object.
	 *
	 * @since 1.7.3
	 */
	public function track_first_space_published( $new_status, $old_status, $post ): void {
		if ( $new_status !== 'publish' || $old_status === 'publish' ) {
			return;
		}

		if ( ! ( $post instanceof \WP_Post ) || $post->post_type !== SUREDASHBOARD_POST_TYPE ) {
			return;
		}

		if ( self::events() === null ) {
			return;
		}

		$space_type = (string) get_post_meta( $post->ID, 'integration', true );
		if ( empty( $space_type ) ) {
			$space_type = 'unknown';
		}

		self::events()->track(
			'first_space_published',
			'yes',
			[
				'space_type'         => $space_type,
				'days_since_install' => (string) $this->get_days_since_install(),
			]
		);
	}

	/**
	 * Track first_community_post_created when the first community post is published.
	 *
	 * @param string   $new_status New post status.
	 * @param string   $old_status Previous post status.
	 * @param \WP_Post $post       Post object.
	 *
	 * @since 1.7.3
	 */
	public function track_first_community_post_created( $new_status, $old_status, $post ): void {
		if ( $new_status !== 'publish' || $old_status === 'publish' ) {
			return;
		}

		if ( ! ( $post instanceof \WP_Post ) || $post->post_type !== SUREDASHBOARD_FEED_POST_TYPE ) {
			return;
		}

		if ( self::events() === null ) {
			return;
		}

		self::events()->track(
			'first_community_post_created',
			'yes',
			[
				'days_since_install' => (string) $this->get_days_since_install(),
			]
		);
	}

	/**
	 * Callback function to add SureDash specific analytics data.
	 *
	 * @param array<mixed> $stats_data existing stats_data.
	 * @return array<mixed> $stats_data modified stats_data.
	 * @since 0.0.5
	 */
	public function add_suredash_analytics_data( $stats_data ) {

		$stats_data['plugin_data']['suredash'] = [
			'free_version'  => SUREDASHBOARD_VER,
			'site_language' => get_locale(),
			'pro_active'    => function_exists( 'suredash_is_pro_active' ) && suredash_is_pro_active(),
		];

		// Add events record.
		if ( self::events() !== null ) {
			$events_record = self::events()->flush_pending();
			if ( ! empty( $events_record ) ) {
				$stats_data['plugin_data']['suredash']['events_record'] = $events_record;
			}
		}

		// Add KPI tracking data.
		$kpi_data = $this->get_kpi_tracking_data();
		if ( ! empty( $kpi_data ) ) {
			$stats_data['plugin_data']['suredash']['kpi_records'] = $kpi_data;
		}

		return apply_filters( 'suredash_bsf_analytics_data', $stats_data );
	}

	/**
	 * Track a reaction event for daily KPI counter.
	 *
	 * @since 1.8.1
	 * @param int    $entity_id   Entity ID.
	 * @param string $entity_type Entity type (post or comment).
	 * @param string $like_status Like status (liked or unliked).
	 * @param int    $user_id     User ID who reacted.
	 * @return void
	 */
	public function track_kpi_reaction( $entity_id, $entity_type, $like_status, $user_id ): void {
		if ( $like_status !== 'liked' ) {
			return;
		}

		$this->increment_kpi_counter( 'reactions' );
	}

	/**
	 * Track a bookmark event for daily KPI counter.
	 *
	 * @since 1.8.1
	 * @param int    $item_id   Item ID.
	 * @param string $item_type Item type.
	 * @param string $status    Bookmark status (bookmarked or un-bookmarked).
	 * @param int    $user_id   User ID.
	 * @return void
	 */
	public function track_kpi_bookmark( $item_id, $item_type, $status, $user_id ): void {
		if ( $status !== 'bookmarked' ) {
			return;
		}

		$this->increment_kpi_counter( 'bookmarks' );
	}

	/**
	 * Track a login event for daily KPI counter.
	 *
	 * Only counts users with the suredash_user role.
	 *
	 * @since 1.8.1
	 * @param string   $user_login Username.
	 * @param \WP_User $user       WP_User object.
	 * @return void
	 */
	public function track_kpi_login( $user_login, $user ): void {
		if ( ! in_array( 'suredash_user', (array) $user->roles, true ) ) {
			return;
		}

		$this->increment_kpi_counter( 'logins' );
	}

	/**
	 * Track plugin_activated (once per install).
	 *
	 * @param \BSF_Analytics_Events $events Event tracker.
	 *
	 * @since 1.7.3
	 */
	private function track_plugin_activated_state( \BSF_Analytics_Events $events ): void {
		$installed_time = get_option( 'suredash_usage_installed_time', 0 );
		if ( ! $installed_time ) {
			return;
		}

		$bsf_referrers = get_option( 'bsf_product_referers', [] );
		$source        = ! empty( $bsf_referrers['suredash'] )
			? sanitize_text_field( $bsf_referrers['suredash'] )
			: 'self';

		$events->track( 'plugin_activated', SUREDASHBOARD_VER, [ 'source' => $source ] );
	}

	/**
	 * Track onboarding completed/skipped state events.
	 *
	 * @param \BSF_Analytics_Events $events Event tracker.
	 *
	 * @since 1.7.3
	 */
	private function track_onboarding_events( \BSF_Analytics_Events $events ): void {
		$completed = get_option( 'suredash_onboarding_completed' ) === 'yes';
		$skipped   = get_option( 'suredash_onboarding_skipped' ) === 'yes';

		if ( ! $completed && ! $skipped ) {
			return;
		}

		$properties = [];

		if ( $skipped ) {
			$skipped_on_step = get_option( 'suredash_onboarding_skipped_step', '' );
			if ( ! empty( $skipped_on_step ) ) {
				$properties['skipped_on_step'] = $skipped_on_step;
			}
		}

		$events->track(
			'onboarding_completed',
			$completed ? 'yes' : 'no',
			$properties
		);
	}

	/**
	 * Track integration connected events.
	 *
	 * @param \BSF_Analytics_Events $events Event tracker.
	 *
	 * @since 1.7.3
	 */
	private function track_integration_events( \BSF_Analytics_Events $events ): void {
		$settings = Settings::get_suredash_settings();
		$enabled  = [];

		if ( ! empty( $settings['google_token_id'] ) ) {
			$enabled[] = 'google_login';
		}

		if ( ! empty( $settings['facebook_token_id'] ) ) {
			$enabled[] = 'facebook_login';
		}

		$surecart_space = absint( $settings['surecart_customer_dashboard_space'] ?? 0 );
		if ( defined( 'SURECART_PLUGIN_FILE' ) && $surecart_space > 0 ) {
			$enabled[] = 'surecart';
		}

		if ( function_exists( 'suredash_is_suremembers_active' ) && suredash_is_suremembers_active() ) {
			$enabled[] = 'suremembers';
		}

		if ( ! empty( $enabled ) ) {
			$events->track(
				'integration_enabled',
				implode( ',', $enabled )
			);
		}
	}

	/**
	 * Track feature-level adoption events.
	 *
	 * @param \BSF_Analytics_Events $events Event tracker.
	 *
	 * @since 1.7.3
	 */
	private function track_feature_events( \BSF_Analytics_Events $events ): void {
		$settings = Settings::get_suredash_settings();

		// global_sidebar_enabled — at least one widget configured.
		$sidebar_widgets = $settings['global_sidebar_widgets'] ?? [];
		if ( is_array( $sidebar_widgets ) && ! empty( $sidebar_widgets ) ) {
			$events->track( 'global_sidebar_enabled', 'yes' );
		}

		// feeds_enabled.
		if ( ! empty( $settings['enable_feeds'] ) ) {
			$events->track( 'feeds_enabled', 'yes' );
		}

		// abilities_api_enabled — admin turned on the WordPress Abilities API
		// surface that exposes SureDash actions to AI agents.
		if ( ! empty( $settings['suredash_abilities_api'] ) ) {
			$events->track( 'abilities_api_enabled', 'yes' );
		}

		// mcp_server_enabled — admin turned on the MCP server (gates the
		// abilities through the MCP Adapter for external AI clients).
		if ( ! empty( $settings['suredash_mcp_server'] ) ) {
			$events->track( 'mcp_server_enabled', 'yes' );
		}
	}

	/**
	 * Get days since plugin install.
	 *
	 * @since 1.7.3
	 * @return int
	 */
	private function get_days_since_install(): int {
		$install_time = (int) get_option( 'suredash_usage_installed_time', 0 );
		if ( $install_time <= 0 ) {
			return 0;
		}
		return (int) floor( ( time() - $install_time ) / DAY_IN_SECONDS );
	}

	/**
	 * Get KPI tracking data for the last 2 days (excluding today).
	 *
	 * @since 1.6.1
	 * @return array<string, array<string, array<string, int>>> KPI data organized by date.
	 */
	private function get_kpi_tracking_data(): array {
		$kpi_data = [];
		$today    = (string) wp_date( 'Y-m-d' );

		// Get data for yesterday and day before yesterday.
		for ( $i = 1; $i <= 2; $i++ ) {
			$date = (string) wp_date( 'Y-m-d', (int) strtotime( $today . ' -' . $i . ' days' ) );

			$kpi_data[ $date ] = [
				'numeric_values' => [
					'community_posts'   => $this->get_daily_community_posts_count( $date ),
					'community_content' => $this->get_daily_community_content_count( $date ),
					'comments'          => $this->get_daily_comments_count( $date ),
					'members_joined'    => $this->get_daily_members_joined_count( $date ),
					'reactions'         => $this->get_kpi_counter( 'reactions', $date ),
					'logins'            => $this->get_kpi_counter( 'logins', $date ),
					'bookmarks'         => $this->get_kpi_counter( 'bookmarks', $date ),
				],
			];

			// Clean up counters for reported dates.
			$this->cleanup_kpi_counters( $date );
		}

		return $kpi_data;
	}

	/**
	 * Increment a daily KPI counter.
	 *
	 * Uses a lightweight option per metric per day. Autoload is off
	 * so counters don't affect every page load.
	 *
	 * @since 1.8.1
	 * @param string $metric Metric name (reactions, logins, bookmarks).
	 * @return void
	 */
	private function increment_kpi_counter( string $metric ): void {
		$date = (string) wp_date( 'Y-m-d' );
		$key  = 'suredash_kpi_' . $metric . '_' . $date;

		$current = (int) get_option( $key, 0 );
		update_option( $key, $current + 1, false );
	}

	/**
	 * Get a daily KPI counter value.
	 *
	 * @since 1.8.1
	 * @param string $metric Metric name.
	 * @param string $date   Date in Y-m-d format.
	 * @return int Counter value.
	 */
	private function get_kpi_counter( string $metric, string $date ): int {
		return (int) get_option( 'suredash_kpi_' . $metric . '_' . $date, 0 );
	}

	/**
	 * Clean up KPI counter options for a reported date.
	 *
	 * Called after data is included in analytics payload to prevent
	 * stale options from accumulating in the database.
	 *
	 * @since 1.8.1
	 * @param string $date Date in Y-m-d format.
	 * @return void
	 */
	private function cleanup_kpi_counters( string $date ): void {
		$metrics = [ 'reactions', 'logins', 'bookmarks' ];
		foreach ( $metrics as $metric ) {
			delete_option( 'suredash_kpi_' . $metric . '_' . $date );
		}
	}

	/**
	 * Get daily community posts count for a specific date.
	 *
	 * @since 1.6.1
	 * @param string $date Date in Y-m-d format.
	 * @return int Daily community posts count.
	 */
	private function get_daily_community_posts_count( $date ): int {
		global $wpdb;

		$start_date = $date . ' 00:00:00';
		$end_date   = $date . ' 23:59:59';

		$count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(ID) FROM {$wpdb->posts}
				WHERE post_type = %s
				AND post_status = 'publish'
				AND post_date >= %s
				AND post_date <= %s",
				SUREDASHBOARD_FEED_POST_TYPE,
				$start_date,
				$end_date
			)
		);

		return absint( $count );
	}

	/**
	 * Get daily community content count for a specific date.
	 *
	 * Includes lessons, resources, and events.
	 *
	 * @since 1.6.1
	 * @param string $date Date in Y-m-d format.
	 * @return int Daily community content count.
	 */
	private function get_daily_community_content_count( $date ): int {
		global $wpdb;

		$start_date = $date . ' 00:00:00';
		$end_date   = $date . ' 23:59:59';

		$count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(ID) FROM {$wpdb->posts}
				WHERE post_type = %s
				AND post_status = 'publish'
				AND post_date >= %s
				AND post_date <= %s",
				SUREDASHBOARD_SUB_CONTENT_POST_TYPE,
				$start_date,
				$end_date
			)
		);

		return absint( $count );
	}

	/**
	 * Get daily comments count for a specific date.
	 *
	 * @since 1.6.1
	 * @param string $date Date in Y-m-d format.
	 * @return int Daily comments count.
	 */
	private function get_daily_comments_count( $date ): int {
		global $wpdb;

		$start_date = $date . ' 00:00:00';
		$end_date   = $date . ' 23:59:59';

		// Get comments for SureDash post types.
		$count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(c.comment_ID) FROM {$wpdb->comments} c
				INNER JOIN {$wpdb->posts} p ON c.comment_post_ID = p.ID
				WHERE p.post_type IN (%s, %s)
				AND c.comment_approved = '1'
				AND c.comment_date >= %s
				AND c.comment_date <= %s",
				SUREDASHBOARD_FEED_POST_TYPE,
				SUREDASHBOARD_SUB_CONTENT_POST_TYPE,
				$start_date,
				$end_date
			)
		);

		return absint( $count );
	}

	/**
	 * Get daily members joined count for a specific date.
	 *
	 * Counts users registered on the given date who have the suredash_user role.
	 *
	 * @since 1.7.3
	 * @param string $date Date in Y-m-d format.
	 * @return int Daily members joined count.
	 */
	private function get_daily_members_joined_count( $date ): int {
		$users = get_users(
			[
				'role'        => 'suredash_user',
				'date_query'  => [
					[
						'after'     => $date . ' 00:00:00',
						'before'    => $date . ' 23:59:59',
						'inclusive' => true,
					],
				],
				'fields'      => 'ID',
				'count_total' => true,
			]
		);

		return count( $users );
	}
}
