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
use SureDashboard\Inc\Utils\Labels;

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
		$search_within_setting = Helper::get_option( 'searchPostTypes' );
		$search_source         = SUREDASHBOARD_POST_TYPE;
		if ( ! empty( $search_within_setting ) ) {
			foreach ( $search_within_setting as $post_type_data ) {
				$search_source .= is_object( $post_type_data ) && ! empty( $post_type_data->value ) ? ',' . $post_type_data->value : '';
			}
		}

		$defaults = [
			'placeholder'  => Labels::get_label( 'search_placeholder' ),
			'source'       => $search_source,
			'page'         => '',
			'support_link' => false,
			'recent_items' => apply_filters( 'portal_search_recent_items_default_option', true ),
		];

		$atts = shortcode_atts( $defaults, $atts );

		$type = SUREDASHBOARD_POST_TYPE;

		ob_start();

		?>
			<div class="portal-search-backdrop"></div>
			<div class="portal-search-modal-container" role="dialog" aria-modal="true" aria-label="<?php echo esc_attr__( 'Search', 'suredash' ); ?>">
				<div id="search-wrapper" class="wrapper portal-content" data-number="30" >
					<!-- search input box -->
					<div class="portal-search-container">
						<input itemprop="query-input" type="search" data-object-type="<?php echo esc_attr( $type ); ?>" id="search-input" class="portal-search-input" placeholder="<?php echo esc_attr( $atts['placeholder'] ); ?>"
						data-remote-url="<?php echo esc_url( site_url() ); ?>">
						<label id="pf_docs-button-holder" class="pf-svg-search pfd-svg-icon pf-docs-search-button" for="search-input">
							<?php Helper::get_library_icon( 'Search', true ); ?>
						</label>
						<span class="portal-search-shortcut">/</span>
					</div>

					<!-- results count -->
					<div class="portal-search-results-wrap portal-content pf-docs-hide">
						<div id="search-results" class="portal-search-results">
							<p class="no-search-results-text pf-docs-hide">
								<?php
									Labels::get_label( 'no_results_found', true );
								?>
							</p>

							<p class="portal-helper pf-docs-hide">
								<?php esc_html_e( 'Search must be at least 3 characters.', 'suredash' ); ?>
							</p>

							<p class="portal-error-results-wrap"></p>
						</div>

						<?php
						if ( apply_filters( 'portal_search_recent_items_default_option', true ) ) {
							?>
							<!-- recent docs results -->
							<div id="pfd-search-recent-docs" class="pfd-search-recent-docs">
								<?php
								$recent_items = Helper::get_recent_searched_items();
								if ( ! empty( $recent_items ) && is_array( $recent_items ) ) {
									?>
									<div class="pfd-search-recent-docs-container">
										<ul class="pf-docs-result-list sd-no-space">
											<li class="list-heading"><?php esc_html_e( 'Recently viewed', 'suredash' ); ?></li>
											<?php
											foreach ( $recent_items as $space_id ) {
												$space_id = intval( $space_id );
												$space    = sd_get_post( $space_id );
												if ( ! $space_id || ! $space || ! isset( $space->ID ) ) {
													continue;
												}
												?>
												<li id="portal_search_item-<?php echo esc_attr( (string) $space->ID ); ?>" class="pfd-search-item">
													<a href="<?php echo esc_url( (string) get_the_permalink( $space->ID ) ); ?>" class="portal-search-item-link">
														<?php Helper::get_library_icon( 'History', true ); ?>
														<div class="portal-search-item-title-wrap">
															<span class="portal-search-result-title"><?php echo esc_html( $space->post_title ?? '' ); ?></span>
														</div>
													</a>
													<button class="pfd-svg-icon pfd-search-recent-item-remove portal-button button-ghost" data-id="<?php echo esc_attr( $space->ID ); ?>">
														<?php Helper::get_library_icon( 'Trash', true ); ?>
													</button>
												</li>
												<?php
											}
											?>
										</ul>
									</div>
										<?php
								}

								if ( is_user_logged_in() ) {
									$portal_user_menu_links = Helper::get_option( 'profile_links' );
									?>
									<div class="pfd-search-categories-container">
										<ul class="pf-docs-result-list sd-no-space">
											<li class="list-heading"><?php esc_html_e( 'Quick Links', 'suredash' ); ?></li>
											<?php
											foreach ( $portal_user_menu_links as $index => $link_data ) {
												if ( ! is_array( $link_data ) ) {
													continue;
												}
												?>
												<li id="portal_search_item-<?php echo esc_attr( $index ); ?>" class="pfd-search-item">
													<?php $link = suredash_dynamic_content_support( $link_data['link'] ); ?>
													<a href="<?php echo esc_url( $link ); ?>" class="portal-search-item-link">
													<?php Helper::get_library_icon( $link_data['icon'], true, 'xs' ); ?>
														<div class="portal-search-item-title-wrap">
															<span class="portal-search-result-title"><?php echo esc_html( $link_data['title'] ); ?></span>
														</div>
													</a>
												</li>
											<?php } ?>
										</ul>
									</div>
								<?php } ?>
							</div>
							<?php
						}
						?>

						<!-- search loading skeleton -->
						<div id="search-loading" class="pfd-search-loading pf-docs-hide">
							<?php echo do_shortcode( (string) Helper::get_skeleton( 'search' ) ); ?>
						</div>

						<!-- append searched results. -->
						<ul itemprop="target" id="result-list" class="pf-docs-result-list sd-no-space"></ul>
						<!-- append html input results. -->
					</div>
				</div>
			</div>
		<?php

		return ob_get_clean();
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
