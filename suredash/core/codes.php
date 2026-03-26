<?php
/**
 * Portals Codes Initialize.
 *
 * @package SureDash
 */

namespace SureDashboard\Core;

use SureDashboard\Inc\Traits\Get_Instance;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class Codes.
 */
class Codes {
	use Get_Instance;

	/**
	 * Constructor
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->initialize_hooks();
	}

	/**
	 * Init Hooks.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function initialize_hooks(): void {
		$this->register_all_shortcodes();
	}

	/**
	 * Register Shortcodes routes.
	 *
	 * @since 1.0.0
	 */
	public function register_all_shortcodes(): void {
		$codes_namespace = 'SureDashboard\Core\Shortcodes\\';

		$controllers = [
			$codes_namespace . 'Navigation',
			$codes_namespace . 'Notification',
			$codes_namespace . 'EndpointNavigation',
			$codes_namespace . 'Search',
			$codes_namespace . 'Menu',
			$codes_namespace . 'ResponsiveNavigation',
			$codes_namespace . 'HomeContent',
			$codes_namespace . 'SingleContent',
			$codes_namespace . 'SingleEndpointContent',
			$codes_namespace . 'User_Profile',
			$codes_namespace . 'SingleComments',
			$codes_namespace . 'ArchiveContent',
			$codes_namespace . 'Content_Header',
			$codes_namespace . 'Sidebar_Widgets',
		];

		foreach ( $controllers as $controller ) {
			$instance = $controller::get_instance();
			if ( method_exists( $instance, 'register_shortcode_event' ) ) {
				$instance->register_shortcode_event();
			}
		}
	}
}
