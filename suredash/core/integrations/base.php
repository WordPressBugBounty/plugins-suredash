<?php
/**
 * This is the base class for all integrations which can be extended by other integrations.
 *
 * @package SureDash
 * @since 1.0.0
 */

namespace SureDashboard\Core\Integrations;

use SureDashboard\Inc\Interfaces\Integration;

defined( 'ABSPATH' ) || exit;

/**
 * Base class for all integrations.
 *
 * @since 1.0.0
 */
abstract class Base implements Integration {
	/**
	 * The name of the integration.
	 *
	 * @var string
	 */
	protected $name;

	/**
	 * The slug of the integration.
	 *
	 * @var string
	 */
	protected $slug;

	/**
	 * The description of the integration.
	 *
	 * @var string
	 */
	protected $description;

	/**
	 * The is_active status of the integration.
	 *
	 * @var bool
	 */
	protected $is_active;

	/**
	 * Constructor.
	 *
	 * @param string $name The name of the integration.
	 * @param string $slug The slug of the integration.
	 * @param string $description The description of the integration.
	 * @param bool   $is_active The is_active of the integration.
	 */
	public function __construct( $name, $slug, $description, $is_active ) {
		$this->name        = $name;
		$this->slug        = $slug;
		$this->description = $description;
		$this->is_active   = $is_active;
	}

	/**
	 * Get the name of the integration.
	 *
	 * @return string
	 */
	public function get_name() {
		return $this->name;
	}

	/**
	 * Get the slug of the integration.
	 *
	 * @return string
	 */
	public function get_slug() {
		return $this->slug;
	}

	/**
	 * Get the description of the integration.
	 *
	 * @return string
	 */
	public function get_description() {
		return $this->description;
	}

	/**
	 * Get the integration active status.
	 *
	 * @return bool
	 */
	public function is_active() {
		return $this->is_active;
	}
}
