<?php
/**
 * Portals Search Shortcode Initialize.
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
 * Class Search Shortcode.
 */
class Search {
	use Shortcode;
	use Get_Instance;

	/**
	 * Register_shortcode_event.
	 *
	 * @return void
	 */
	public function register_shortcode_event(): void {
		$this->add_shortcode( 'search' );
	}

	/**
	 * Display Search markup.
	 *
	 * @param array<mixed> $atts Array of attributes.
	 * @since 1.0.0
	 * @return mixed HTML content.
	 */
	public function render_search( $atts ) {
		unset( $atts ); // Modal is JS-driven; no shortcode attributes consumed server-side.
		return '<div class="portal-search-modal-container" data-suredash-search-root="1" aria-hidden="true"></div>';
	}

	/**
	 * Display Search placeholder markup.
	 *
	 * @return mixed HTML content.
	 * @since 0.0.1
	 */
	public function search_placeholder() {
		ob_start();
		?>
			<!-- search input box -->
			<div id="portal-placeholder-search-wrap" class="portal-search-container portal-header-search-trigger portal-content">
				<input itemprop="query-input" type="search" id="placeholder-search-input" class="portal-search-input" placeholder="<?php echo esc_attr__( 'Search', 'suredash' ); ?>"/>
				<label id="pf_docs-button-holder" class="pf-svg-search pfd-svg-icon pf-docs-search-button" for="placeholder-search-input">
					<?php Helper::get_library_icon( 'Search', true ); ?>
				</label>
				<span class="portal-search-shortcut">/</span>
			</div>
		<?php
		return ob_get_clean();
	}
}
