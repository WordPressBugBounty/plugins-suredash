<?php
/**
 * SureCart Integration for SureDash
 *
 * This integration handles the redirection of SureCart customer area to SureDash spaces
 * when a Customer Area page is selected in any single page/post space.
 *
 * @package SureDash
 * @since 1.4.0
 */

namespace SureDashboard\Core\Integrations;

use SureDashboard\Inc\Traits\Get_Instance;
use SureDashboard\Inc\Utils\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * SureCart Integration class.
 *
 * @since 1.4.0
 */
class SureCart extends Base {
	use Get_Instance;

	/**
	 * Constructor.
	 *
	 * @since 1.4.0
	 */
	public function __construct() {
		$this->name        = 'SureCart';
		$this->slug        = 'surecart';
		$this->description = __( 'Integrates SureCart customer area with SureDash spaces.', 'suredash' );
		$this->is_active   = defined( 'SURECART_PLUGIN_FILE' ) && SURECART_PLUGIN_FILE;

		parent::__construct( $this->name, $this->slug, $this->description, $this->is_active );

		if ( ! $this->is_active ) {
			return;
		}

		$this->initialize_hooks();
	}

	/**
	 * Override the SureCart dashboard page ID.
	 *
	 * @param mixed  $pre_option The value to return instead of the option value.
	 * @param string $option     Option name.
	 * @param mixed  $default    The fallback value to return if the option does not exist.
	 * @return mixed
	 * @since 1.4.0
	 */
	public function override_dashboard_page_id( $pre_option, $option, $default ) {
		// Get the global setting for SureCart customer dashboard space.
		$settings          = Settings::get_suredash_settings();
		$selected_space_id = isset( $settings['surecart_customer_dashboard_space'] ) ? absint( $settings['surecart_customer_dashboard_space'] ) : 0;

		// If "None" is selected (value = 0), don't override - use default SureCart behavior.
		if ( empty( $selected_space_id ) ) {
			return $pre_option;
		}

		// Verify the selected space exists and is published.
		$space_post = get_post( $selected_space_id );
		if ( $space_post && $space_post->post_status === 'publish' ) {
			// Return the selected space ID instead of the original page ID.
			return $selected_space_id;
		}

		// If selected space doesn't exist, use default SureCart behavior.
		return $pre_option;
	}

	/**
	 * Initialize hooks.
	 *
	 * Override the SureCart dashboard page ID based on global settings.
	 *
	 * @since 1.4.0
	 * @return void
	 */
	private function initialize_hooks(): void {
		add_filter( 'pre_option_surecart_dashboard_page_id', [ $this, 'override_dashboard_page_id' ], 10, 3 );
	}
}
