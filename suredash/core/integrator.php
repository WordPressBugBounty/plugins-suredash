<?php
/**
 * Portals Integrator Initialize.
 *
 * @package SureDash
 */

namespace SureDashboard\Core;

use SureDashboard\Inc\Traits\Get_Instance;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class Integrator.
 *
 * @since 1.0.0
 */
class Integrator {
	use Get_Instance;

	/**
	 * List of all integrations.
	 *
	 * @var array<mixed>
	 * @since 1.0.0
	 */
	public $all_integrations = [];

	/**
	 * List of active integrations.
	 *
	 * @var array<mixed>
	 * @since 1.0.0
	 */
	public $active_integrations = [];

	/**
	 * List of inactive integrations.
	 *
	 * @var array<mixed>
	 * @since 1.0.0
	 */
	public $inactive_integrations = [];

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
		$this->register_all_integrations();
	}

	/**
	 * Register Integrations routes.
	 *
	 * @since 1.0.0
	 */
	public function register_all_integrations(): void {
		$codes_namespace = 'SureDashboard\Core\Integrations\\';

		$controllers = [
			$codes_namespace . 'Feeds',
			$codes_namespace . 'SinglePost',
			$codes_namespace . 'SureMembers',
			$codes_namespace . 'SureCart',
		];

		foreach ( $controllers as $controller ) {
			$instance        = $controller::get_instance();
			$controller_slug = method_exists( $instance, 'get_slug' ) ? $instance->get_slug() : '';

			if ( method_exists( $instance, 'is_active' ) && $instance->is_active() ) {
				$this->active_integrations[ $controller_slug ] = $instance;
			} else {
				$this->inactive_integrations[ $controller_slug ] = $instance;
			}
		}

		$this->all_integrations = array_merge( $this->active_integrations, $this->inactive_integrations );
	}

	/**
	 * Get all integrations.
	 *
	 * @since 1.0.0
	 * @return array<mixed>
	 */
	public function get_all_integrations() {
		return $this->all_integrations;
	}

	/**
	 * Get active integrations.
	 *
	 * @since 1.0.0
	 * @return array<mixed>
	 */
	public function get_active_integrations() {
		return $this->active_integrations;
	}

	/**
	 * Get inactive integrations.
	 *
	 * @since 1.0.0
	 * @return array<mixed>
	 */
	public function get_inactive_integrations() {
		return $this->inactive_integrations;
	}

	/**
	 * Get integration by slug.
	 *
	 * @param string $slug Integration slug.
	 * @since 1.0.0
	 * @return object|bool
	 */
	public function get_integration_by_slug( $slug ) {
		foreach ( $this->all_integrations as $integration_slug => $integration ) {
			if ( $slug === $integration_slug ) {
				return $integration;
			}
		}

		return false;
	}
}
