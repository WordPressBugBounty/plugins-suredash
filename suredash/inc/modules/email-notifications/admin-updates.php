<?php
/**
 * Admin Updates User Preference Filter
 *
 * @package SureDash
 * @since 1.5.0
 */

namespace SureDashboard\Inc\Modules\EmailNotifications;

use SureDashboard\Inc\Traits\Get_Instance;

defined( 'ABSPATH' ) || exit;

/**
 * Admin Updates User Preference Filter
 *
 * Provides utility functions to filter users based on admin email preferences.
 *
 * @since 1.5.0
 */
class Admin_Updates {
	use Get_Instance;

	/**
	 * Filter user IDs based on admin email notification preferences.
	 *
	 * @since 1.5.0
	 * @param array<int> $user_ids Array of user IDs to filter.
	 * @return array<int> Filtered array of user IDs who want to receive admin emails.
	 */
	public static function filter_admin_email_recipients( array $user_ids ): array {
		if ( empty( $user_ids ) ) {
			return [];
		}

		$filtered_users = [];

		foreach ( $user_ids as $user_id ) {
			if ( self::should_receive_admin_email( $user_id ) ) {
				$filtered_users[] = $user_id;
			}
		}

		return $filtered_users;
	}

	/**
	 * Check if user should receive admin email notifications.
	 *
	 * @since 1.5.0
	 * @param int $user_id The user ID.
	 * @return bool
	 */
	private static function should_receive_admin_email( $user_id ): bool {
		// Check global email notification setting.
		$global_enabled = sd_get_user_meta( $user_id, 'enable_all_email_notifications', true );
		if ( $global_enabled === '' || $global_enabled === '1' ) {
			return true;
		}

		// Check admin email notification setting.
		$admin_email_enabled = sd_get_user_meta( $user_id, 'enable_admin_email', true );

		// Default to true if key doesn't exist (empty string) or is explicitly set to '1'.
		return $admin_email_enabled !== '0';
	}
}
