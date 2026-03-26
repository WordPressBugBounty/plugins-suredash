<?php
/**
 * Portals Menu Shortcode Initialize.
 *
 * @package SureDash
 */

namespace SureDashboard\Core\Shortcodes;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use SureDashboard\Inc\Traits\Get_Instance;
use SureDashboard\Inc\Traits\Shortcode;

/**
 * Class Menu Shortcode.
 */
class Menu {
	use Shortcode;
	use Get_Instance;

	/**
	 * Register_shortcode_event.
	 *
	 * @return void
	 */
	public function register_shortcode_event(): void {
		$this->add_shortcode( 'menu' );
	}

	/**
	 * Display menu.
	 *
	 * @param array<mixed> $atts Array of attributes.
	 * @since 1.0.0
	 *
	 * @return string|false
	 */
	public function render_menu( $atts ) {
		$atts = shortcode_atts(
			[],
			$atts
		);

		if ( ! has_nav_menu( 'portal_menu' ) ) {
			return false;
		}

		ob_start();
		?>
			<div class="sd-header-menu-wrap portal-content">
				<?php
					wp_nav_menu(
						[
							'theme_location'  => 'portal_menu',
							'menu_class'      => 'pfd-menu sd-no-space',
							'container'       => 'nav',
							'container_class' => 'sd-menu-container',
							'depth'           => 1,
						]
					);
				?>
			</div>
		<?php

		return ob_get_clean();
	}
}
