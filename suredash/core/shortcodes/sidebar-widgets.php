<?php
/**
 * Portal Sidebar Widgets Shortcode Initialize.
 *
 * @package SureDash
 * @since 1.6.0
 */

namespace SureDashboard\Core\Shortcodes;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use SureDashboard\Inc\Traits\Get_Instance;
use SureDashboard\Inc\Traits\Shortcode;

/**
 * Class Sidebar_Widgets Shortcode.
 */
class Sidebar_Widgets {
	use Shortcode;
	use Get_Instance;

	/**
	 * Constructor
	 */
	public function __construct() {
		// Sidebar is now rendered directly in template via shortcode.
		// No need to inject via filters.
	}

	/**
	 * Register shortcode event.
	 *
	 * @return void
	 */
	public function register_shortcode_event(): void {
		$this->add_shortcode( 'sidebar_widgets' );
	}

	/**
	 * Display portal sidebar widgets.
	 *
	 * @param array<mixed> $atts Array of attributes.
	 * @since 1.6.0
	 * @return string|false
	 */
	public function render_sidebar_widgets( $atts ) {
		$atts = shortcode_atts( [], $atts );

		ob_start();
		suredash_get_template_part( 'parts/sidebar-widgets' );
		return ob_get_clean();
	}
}
