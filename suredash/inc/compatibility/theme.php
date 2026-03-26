<?php
/**
 * Theme.
 *
 * @package SureDash
 * @since 0.0.1
 */

namespace SureDashboard\Inc\Compatibility;

use SureDashboard\Inc\Traits\Get_Instance;

defined( 'ABSPATH' ) || exit;

/**
 * Have compatibility with active theme.
 *
 * @since 0.0.1
 */
class Theme {
	use Get_Instance;

	/**
	 * Active theme.
	 *
	 * @var string
	 */
	private $active_theme = '';

	/**
	 * Constructor
	 *
	 * @since 0.0.1
	 */
	public function __construct() {
		add_action( 'wp', [ $this, 'load_theme_compatibility' ] );
	}

	/**
	 * Get Current Theme.
	 *
	 * @return string
	 * @since 0.0.1
	 */
	public function get_current_theme(): string {
		$theme = wp_get_theme();

		if ( ! empty( $this->active_theme ) ) {
			return $this->active_theme;
		}

		if ( isset( $theme->parent_theme ) && ( $theme->parent_theme !== '' && $theme->parent_theme !== null ) ) {
			$this->active_theme = $theme->parent_theme;
		} else {
			$this->active_theme = $theme->name;
		}

		return $this->active_theme;
	}

	/**
	 * Load Theme Compatibility.
	 *
	 * @return void
	 * @since 0.0.1
	 */
	public function load_theme_compatibility(): void {
		$theme = $this->get_current_theme();
		switch ( $theme ) {
			case 'Astra':
				$this->astra();
				break;
			case 'Kadence':
				$this->kadence();
				break;
			default:
				$this->default_theme();
				break;
		}
	}

	/**
	 * Default Theme Compatibility.
	 *
	 * @return void
	 * @since 0.0.1
	 */
	public function default_theme(): void {
		// Default theme compatibility.
	}

	/**
	 * Astra Theme Compatibility.
	 *
	 * @return void
	 * @since 0.0.1
	 */
	public function astra(): void {
		if ( suredash_frontend() ) {
			// Disable scroll to top for portal view.
			add_filter( 'astra_get_option_scroll-to-top-enable', '__return_false' );

			// Use 'astra_block_based_legacy_setup' filter with return false after Astra 4.11.2, to fix the legacy setup issue.
			add_filter( 'astra_block_based_legacy_setup', '__return_false' );
		}
	}

	/**
	 * Kadence Theme Compatibility.
	 *
	 * @return void
	 * @since 1.2.1
	 */
	public function kadence(): void {
		if ( suredash_frontend() ) {
			wp_add_inline_style( 'portal-global', trim( apply_filters( 'kadence_dynamic_css', '' ) ) );
		}
	}
}
