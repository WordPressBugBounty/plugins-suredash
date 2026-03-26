<?php
/**
 * Analytics.
 *
 * @package SureDashboard
 * @since 0.0.6
 */

namespace SureDashboard\Inc\Utils;

use SureDashboard\Inc\Traits\Get_Instance;

defined( 'ABSPATH' ) || exit;

/**
 * Update Compatibility
 *
 * @package SureDashboard
 */
class Analytics {
	use Get_Instance;

	/**
	 *  Constructor
	 */
	public function __construct() {
		$this->set_bsf_analytics_entity();
		add_filter( 'bsf_core_stats', [ $this, 'add_suredash_analytics_data' ] );
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
	 * Callback function to add SureDash specific analytics data.
	 *
	 * @param array<mixed> $stats_data existing stats_data.
	 * @return array<mixed> $stats_data modified stats_data.
	 * @since 0.0.5
	 */
	public function add_suredash_analytics_data( $stats_data ) {

		$settings = Settings::get_suredash_settings();

		// Get total published spaces count.
		$total_spaces           = wp_count_posts( SUREDASHBOARD_POST_TYPE );
		$published_spaces_count = isset( $total_spaces->publish ) ? (int) $total_spaces->publish : 0;

		// Get space counts by type for free version.
		$single_post_count = suredash_get_space_count_by_integration( 'single_post' );
		$discussion_count  = suredash_get_space_count_by_integration( 'posts_discussion' );
		$link_count        = suredash_get_space_count_by_integration( 'link' );

		// Get activity from last 30 days.
		$recent_activity = $this->get_recent_activity_counts();

		$stats_data['plugin_data']['suredash'] = [
			'free_version'                       => SUREDASHBOARD_VER,
			'site_language'                      => get_locale(),
			'bypass_wp_interactions'             => $settings['bypass_wp_interactions'] ?? '',
			'hidden_community'                   => $settings['hidden_community'] ?? '',
			'total_spaces'                       => $published_spaces_count,
			'single_post_spaces'                 => $single_post_count,
			'discussion_spaces'                  => $discussion_count,
			'link_spaces'                        => $link_count,
			'recent_community_posts_content_30d' => $recent_activity['recent_posts'] + $recent_activity['recent_content'],
		];

		// Add KPI tracking data.
		$kpi_data = $this->get_kpi_tracking_data();
		if ( ! empty( $kpi_data ) ) {
			$stats_data['plugin_data']['suredash']['kpi_records'] = $kpi_data;
		}

		return apply_filters( 'suredash_bsf_analytics_data', $stats_data );
	}

	/**
	 * Get recent activity counts for last 30 days.
	 *
	 * @since 1.6.0
	 * @return array<string, int> Array containing recent_posts and recent_content counts.
	 */
	private function get_recent_activity_counts(): array {
		global $wpdb;
		$thirty_days_ago = gmdate( 'Y-m-d H:i:s', strtotime( '-30 days' ) );

		// Get community posts count from last 30 days.
		$recent_posts = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(ID) FROM {$wpdb->posts}
				WHERE post_type = %s
				AND post_status = 'publish'
				AND post_date >= %s",
				SUREDASHBOARD_FEED_POST_TYPE,
				$thirty_days_ago
			)
		);

		// Get community content count from last 30 days.
		$recent_content = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(ID) FROM {$wpdb->posts}
				WHERE post_type = %s
				AND post_status = 'publish'
				AND post_date >= %s",
				SUREDASHBOARD_SUB_CONTENT_POST_TYPE,
				$thirty_days_ago
			)
		);

		return [
			'recent_posts'   => absint( $recent_posts ),
			'recent_content' => absint( $recent_content ),
		];
	}

	/**
	 * Get KPI tracking data for the last 2 days (excluding today).
	 *
	 * @since 1.6.1
	 * @return array<string, array<string, array<string, int>>> KPI data organized by date.
	 */
	private function get_kpi_tracking_data(): array {
		$kpi_data = [];
		$today    = current_time( 'Y-m-d' );

		// Get data for yesterday and day before yesterday.
		for ( $i = 1; $i <= 2; $i++ ) {
			$date = gmdate( 'Y-m-d', strtotime( $today . ' -' . $i . ' days' ) ); // @phpstan-ignore-line

			$kpi_data[ $date ] = [
				'numeric_values' => [
					'community_posts'   => $this->get_daily_community_posts_count( $date ),
					'community_content' => $this->get_daily_community_content_count( $date ),
					'comments'          => $this->get_daily_comments_count( $date ),
				],
			];
		}

		return $kpi_data;
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
}
