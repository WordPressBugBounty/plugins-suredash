<?php
/**
 * Shortcode
 *
 * @package SureDash
 * @since 1.0.0
 */

namespace SureDashboard\Inc\Traits;

defined( 'ABSPATH' ) || exit;

/**
 * Trait Shortcode.
 *
 * @since 1.0.0
 */
trait Shortcode {
	/**
	 * Shortcode prefix
	 *
	 * @var string
	 */
	public $prefix = 'portal';

	/**
	 * Add shortcode.
	 *
	 * @param string $shortcode Shortcode name.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function add_shortcode( $shortcode ): void {
		$callback = [ $this, 'render_' . $shortcode ];
		add_shortcode( $this->prefix . '_' . $shortcode, $callback ); // @phpstan-ignore-line
	}
}
