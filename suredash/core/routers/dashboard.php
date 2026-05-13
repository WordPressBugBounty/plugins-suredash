<?php
/**
 * Dashboard Router.
 *
 * Handles REST endpoints specific to the admin dashboard UI
 * (Learn tab dismissal, etc.).
 *
 * @package SureDash
 * @since 1.8.2
 */

namespace SureDashboard\Core\Routers;

use SureDashboard\Inc\Modules\Learn\Learn;
use SureDashboard\Inc\Traits\Get_Instance;
use SureDashboard\Inc\Traits\Rest_Errors;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class Dashboard Router.
 *
 * @since 1.8.2
 */
class Dashboard {
	use Get_Instance;
	use Rest_Errors;

	/**
	 * Dismiss or restore the Learn tab for the current user.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @since 1.8.2
	 * @return void
	 */
	public function dismiss_learn( $request ): void {
		$nonce = (string) $request->get_header( 'X-WP-Nonce' );
		if ( ! wp_verify_nonce( sanitize_text_field( $nonce ), 'wp_rest' ) ) {
			wp_send_json_error( [ 'message' => $this->get_rest_event_error( 'nonce' ) ] );
		}

		$dismissed = ! empty( $request->get_param( 'dismissed' ) );
		$user_id   = get_current_user_id();

		update_user_meta( $user_id, Learn::DISMISSED_META_KEY, $dismissed );

		wp_send_json_success(
			[
				'dismissed' => $dismissed,
			]
		);
	}
}
