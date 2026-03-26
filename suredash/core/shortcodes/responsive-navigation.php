<?php
/**
 * Portals Docs ResponsiveNavigation Shortcode Initialize.
 *
 * @package SureDash
 */

namespace SureDashboard\Core\Shortcodes;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use SureDashboard\Inc\Traits\Get_Instance;
use SureDashboard\Inc\Traits\Shortcode;
use SureDashboard\Inc\Utils\Helper;

/**
 * Class ResponsiveNavigation Shortcode.
 */
class ResponsiveNavigation {
	use Shortcode;
	use Get_Instance;

	/**
	 * Set status for aside navigation markup loaded.
	 *
	 * @var bool
	 */
	private $aside_navigation_markup_loaded = false;

	/**
	 * Register_shortcode_event.
	 *
	 * @return void
	 */
	public function register_shortcode_event(): void {
		$this->add_shortcode( 'responsive_navigation' );
	}

	/**
	 * Display docs menu.
	 *
	 * @param array<mixed> $atts Array of attributes.
	 * @since 1.0.0
	 * @return string|false
	 */
	public function render_responsive_navigation( $atts ) {
		$atts = shortcode_atts(
			[],
			$atts
		);

		ob_start();

		?>
			<div class="portal-hide-on-desktop">
				<button class="sd-portal-navigation-toggle sd-px-12 sd-py-8 pfd-svg-icon portal-button button-ghost">
					<?php Helper::get_library_icon( 'List', true, 'md' ); ?>
				</button>
				<label class="sd-portal-navigation-close pfd-svg-icon portal-hide" tabindex="0">
					<?php Helper::get_library_icon( 'X', true ); ?>
				</label>
			</div>
		<?php

		add_action( 'wp_footer', [ $this, 'process_global_portal_query' ] );

		return ob_get_clean();
	}

	/**
	 * Get the global docs query.
	 *
	 * @since 1.0.0
	 */
	public function process_global_portal_query(): void {
		if ( $this->aside_navigation_markup_loaded ) {
			return;
		}

		?>
			<div class="portal-bg-overlay"></div>
			<!-- Have markup from navigation using JS. -->
			<div class="portal-aside-list-wrapper portal-footer-resp-nav sd-custom-scroll portal-content wp-block-suredash-navigation" aria-hidden="true"></div>
		<?php

		$this->aside_navigation_markup_loaded = true;
	}
}
