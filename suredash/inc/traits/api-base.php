<?php
/**
 * Trait.
 *
 * @package SureDash
 * @since 1.0.0
 */

namespace SureDashboard\Inc\Traits;

defined( 'ABSPATH' ) || exit;

/**
 * Trait Get_Instance.
 *
 * @since 1.0.0
 */
trait API_Base {
	/**
	 * Endpoint namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'portal/v1';

	/**
	 * Constructor
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
	}

	/**
	 * Register API routes.
	 *
	 * @return string
	 */
	public function get_api_namespace() {
		return $this->namespace;
	}
}
