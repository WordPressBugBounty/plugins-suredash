<?php
/**
 * Admin notices.
 *
 * @package SureDash
 * @since 0.0.2
 */

namespace SureDashboard\Admin;

use SureDashboard\Inc\Traits\Get_Instance;
use SureDashboard\Inc\Utils\Helper;

/**
 * Notices
 *
 * @since 0.0.2
 */
class Notices {
	use Get_Instance;

	/**
	 * Constructor
	 *
	 * @since 0.0.2
	 */
	public function __construct() {
		add_action( 'admin_init', [ $this, 'minimum_pro_version_requirement' ] );
		add_action( 'admin_init', [ $this, 'alpha_to_stable_migrator_notice' ], 1 );
		add_action( 'admin_footer', [ $this, 'show_suredash_nps_notice' ], 999 );
	}

	/**
	 * Check if the current screen is the admin screen to display the notice.
	 *
	 * @return bool
	 */
	public function should_notice_be_visible(): bool {
		if ( ! current_user_can( 'activate_plugins' ) || ! current_user_can( 'install_plugins' ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Display admin notice if premium incompatible version is activated.
	 *
	 * @since 0.0.2
	 */
	public function minimum_pro_version_requirement(): void {
		if ( ! $this->should_notice_be_visible() ) {
			return;
		}

		if ( ! defined( 'SUREDASH_PRO_VER' ) ) {
			return;
		}

		if ( version_compare( SUREDASH_PRO_VER, SUREDASH_PRO_MINIMUM_VER, '<' ) && class_exists( 'BSF_Admin_Notices' ) ) {
			/* translators: %s: html tags */
			$notice_message = sprintf( __( 'The %1$s %2$s %3$s plugin requires %1$s %4$s %3$s plugin to be updated to version %5$s or higher!', 'suredash' ), '<strong>', 'SureDash', '</strong>', SUREDASH_PRO_PRODUCT, SUREDASH_PRO_MINIMUM_VER );

			if ( class_exists( 'BSF_Admin_Notices' ) ) {
				\BSF_Admin_Notices::add_notice(
					[
						'id'                         => 'suredash-free-version-requirement-notice',
						'type'                       => 'warning',
						/* translators: %s: html tags */
						'message'                    => sprintf(
							'<div class="notice-content" style="margin: 0;">
								<h3 id="suredash-version-notice-title" class="sr-only" style="margin-top: 0;">%2$s</h3>
								%1$s
							</div>',
							$notice_message,
							__( 'Plugin Version Update Required', 'suredash' )
						),
						'repeat-notice-after'        => false,
						'priority'                   => 18,
						'display-with-other-notices' => true,
						'is_dismissible'             => false,
					]
				);
			}
		}
	}

	/**
	 * Display admin notice for alpha to stable migrator.
	 *
	 * To-Do: Remove this notice after 1.0.0 few releases.
	 *
	 * @since 1.0.0
	 */
	public function alpha_to_stable_migrator_notice(): void {
		if ( ! $this->should_notice_be_visible() ) {
			return;
		}

		$db_version  = get_option( 'suredash_saved_version', SUREDASHBOARD_VER );
		$show_notice = Helper::get_option( 'show_alpha_to_stable_migrator_notice', false );

		if ( version_compare( $db_version, '1.0.0', '<' ) && ! $show_notice ) {
			$show_notice = true;
			Helper::update_option( 'show_alpha_to_stable_migrator_notice', true );
		}

		if ( $show_notice && class_exists( 'BSF_Admin_Notices' ) ) {
			/* translators: %1$s: html tags, %2$s: plugin name, %3$s: html tags, %4$s: link to document, %5$s: html tags */
			$notice_message = sprintf( __( 'The %1$s %2$s %3$s plugin has been updated to a stable version. Please follow %4$s this document %5$s to migrate your settings. Ignore if already migrated.', 'suredash' ), '<strong>', 'SureDash', '</strong>', '<a href="https://suredash.com/docs/how-to-migrate-from-alpha-to-final-version/" target="_blank" title="How To Migrate From Alpha To Final Version">', '</a>' );

			if ( class_exists( 'BSF_Admin_Notices' ) ) {
				\BSF_Admin_Notices::add_notice(
					[
						'id'                         => 'suredash-alpha-to-stable-migrator-notice',
						'type'                       => 'warning',
						/* translators: %s: html tags */
						'message'                    => sprintf(
							'<div class="notice-content" style="margin: 0;">
								%1$s
							</div>',
							$notice_message
						),
						'repeat-notice-after'        => false,
						'priority'                   => 18,
						'display-with-other-notices' => true,
						'is_dismissible'             => true,
					]
				);
			}
		}
	}

	/**
	 * Render SureDash NPS Survey notice.
	 *
	 * @return void
	 * @since 1.4.0
	 */
	public function show_suredash_nps_notice(): void {
		if ( class_exists( 'Nps_Survey' ) ) {
			\Nps_Survey::show_nps_notice(
				'nps-survey-suredash',
				[
					'show_if'          => Helper::has_more_than_four_spaces(),
					'dismiss_timespan' => 2 * WEEK_IN_SECONDS,
					'display_after'    => 0,
					'plugin_slug'      => 'suredash',
					'message'          => [

						// Step 1 - Rating input.
						'logo'                  => esc_url( SUREDASHBOARD_URL . 'assets/icons/icon.svg' ),
						'plugin_name'           => __( 'SureDash', 'suredash' ),
						'nps_rating_title'      => __( 'Quick Question!', 'suredash' ),
						'nps_rating_message'    => __( 'How would you rate your SureDash experience? Love it or not, your feedback helps us make community building better.', 'suredash' ),
						'rating_min_label'      => __( 'Very unlikely!', 'suredash' ),
						'rating_max_label'      => __( 'Very likely!', 'suredash' ),

						// Step 2A - (rating 8-10).
						'feedback_title'        => __( 'Thanks for your amazing feedback! 😍', 'suredash' ),
						'feedback_content'      => __( 'Glad you’re enjoying SureDash! Thanks for growing with us. Got ideas? We’d love to hear them.', 'suredash' ),

						// Step 2B - (rating 0-7).
						'plugin_rating_title'   => __( 'Thank you for sharing your feedback 🙏', 'suredash' ),
						'plugin_rating_content' => __( 'We truly value your input. Tell us what’s missing or what could be better — we’re here to improve your SureDash experience.', 'suredash' ),
					],
					'allow_review'     => false,
					'show_overlay'     => false,
					'show_on_screens'  => [ 'toplevel_page_portal' ],
				]
			);
		}
	}
}
