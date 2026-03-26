<?php
/**
 * PHP file to use when rendering the block type on the server to show on the front end.
 *
 * The following variables are exposed to the file:
 *     $attributes (array): The block attributes.
 *     $content (string): The block default content.
 *     $block (WP_Block): The block instance.
 *
 * @see https://github.com/WordPress/gutenberg/blob/trunk/docs/reference-guides/block-api/block-metadata.md#render
 * @package suredash
 * @since 0.0.6
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use SureDashboard\Core\Shortcodes\Search;
use SureDashboard\Inc\Utils\Helper;

$responsive_only_icon  = isset( $attributes['responsiveonlyicon'] ) && $attributes['responsiveonlyicon'] ? true : false;
$wrapper_default_class = $responsive_only_icon ? 'portal-content search-icon-responsive' : 'portal-content';

if ( ! suredash_content_post() ) {
	$placeholder = '';

	if ( method_exists( Search::get_instance(), 'search_placeholder' ) ) {
		$placeholder = Search::get_instance()->search_placeholder();
	}

	?>
		<div <?php echo do_shortcode( get_block_wrapper_attributes( [ 'class' => $wrapper_default_class ] ) ); ?>>
			<?php
				printf(
					'<style class="suredash-search-block-css">
						.wp-block-suredash-search .portal-search-input {
							border-radius:%1$s !important;
						}
					</style>',
					esc_attr( ! empty( $attributes['inputborderradius'] ) ? $attributes['inputborderradius'] : '' )
				);
				echo do_shortcode( $placeholder );
			if ( $responsive_only_icon ) {
				printf(
					'<span class="portal-header-search-trigger only-search-icon-wrap"> %1$s </span>',
					do_shortcode( Helper::get_library_icon( 'Search', false, 'sm' ) )
				);
			}
			?>
		</div>
	<?php
}
