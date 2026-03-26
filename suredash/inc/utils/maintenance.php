<?php
/**
 * Maintenance.
 *
 * @package SureDash
 * @since 1.0.0
 */

namespace SureDashboard\Inc\Utils;

use SureDashboard\Inc\Traits\Get_Instance;

defined( 'ABSPATH' ) || exit;

/**
 * Update Compatibility
 *
 * @package SureDash
 */
class Maintenance {
	use Get_Instance;

	/**
	 *  Constructor
	 */
	public function __construct() {
		if ( is_admin() ) {
			add_action( 'admin_init', self::class . '::init' );
		} else {
			add_action( 'init', self::class . '::init' );
		}
	}

	/**
	 * Init
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function init(): void {
		do_action( 'suredash_update_before' );

		// Get auto saved version number.
		$saved_version = get_option( 'suredash_saved_version', '' );

		// Update auto saved version number.
		if ( ! $saved_version ) {
			update_option( 'suredash_saved_version', SUREDASHBOARD_VER );
			return;
		}

		// If equals then return.
		if ( version_compare( strval( $saved_version ), SUREDASHBOARD_VER, '=' ) ) {
			return;
		}

		self::manage_backward();

		// Update auto saved version number.
		update_option( 'suredash_saved_version', SUREDASHBOARD_VER );

		do_action( 'suredash_update_after' );

		// Finally flush rewrite rules.
		delete_option( 'rewrite_rules' );
	}

	/**
	 * Manage backward compatibility.
	 *
	 * @since 1.3.0
	 * @return void
	 */
	public static function manage_backward(): void {
		$saved_version = strval( get_option( 'suredash_saved_version', false ) );

		// If the saved version is already set then manage some backward compatibility.
		if ( ! $saved_version ) {
			return;
		}

		// Case 1: Color palette default setting compatibility.
		Backwards::version_1_3_0( $saved_version );

		// Case 2: Like and share separate options compatibility.
		Backwards::version_1_4_0( $saved_version );

		// Case 3: Preserve excerpt HTML compatibility.
		Backwards::version_1_5_0( $saved_version );

		// Case 4: Forcefully disable preserve excerpt HTML compatibility -- due to hotfix needed.
		Backwards::version_1_5_1( $saved_version );
	}
}
