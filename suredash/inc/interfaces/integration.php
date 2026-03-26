<?php
/**
 * This is the interface for all integrations.
 *
 * @package SureDash
 * @since 1.0.0
 */

namespace SureDashboard\Inc\Interfaces;

defined( 'ABSPATH' ) || exit;

/**
 * Interface for all integrations.
 *
 * @since 1.0.0
 */
interface Integration {
	/**
	 * Get the name of the integration.
	 *
	 * @return string
	 */
	public function get_name();

	/**
	 * Get the slug of the integration.
	 *
	 * @return string
	 */
	public function get_slug();

	/**
	 * Get the description of the integration.
	 *
	 * @return string
	 */
	public function get_description();

	/**
	 * Get the condition of the integration, if it is active or not.
	 *
	 * @return bool
	 */
	public function is_active();
}
