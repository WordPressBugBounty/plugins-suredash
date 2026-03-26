<?php
/**
 * Portals CPTs Initialize.
 *
 * @package SureDash
 */

namespace SureDashboard\Core;

use SureDashboard\Inc\Traits\Get_Instance;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class CPTs.
 */
class CPTs {
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
		$this->register_all_cpt();
	}

	/**
	 * Register CPT routes.
	 *
	 * @since 1.0.0
	 */
	public function register_all_cpt(): void {
		$codes_namespace = 'SureDashboard\Core\CPT\\';

		$controllers = [
			$codes_namespace . 'Portal',
			$codes_namespace . 'Posts',
			$codes_namespace . 'Content',
		];

		foreach ( $controllers as $controller ) {
			$instance = $controller::get_instance();
			if ( method_exists( $instance, 'create_cpt_n_taxonomy' ) ) {
				$instance->create_cpt_n_taxonomy();
			}
		}
	}
}
