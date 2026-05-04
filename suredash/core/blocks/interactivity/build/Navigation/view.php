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

$elements = ! empty( $attributes['style']['elements'] ) ? $attributes['style']['elements'] : []; // Extended color options support.

/**
 * Helper function to convert typography object to CSS string
 *
 * @param array $typography Typography attributes array.
 * @return string CSS string with typography properties.
 */
if ( ! function_exists( 'suredash_get_typography_css' ) ) {
	function suredash_get_typography_css( $typography ) {
		if ( empty( $typography ) || ! is_array( $typography ) ) {
			return '';
		}

		$css = '';

		if ( ! empty( $typography['fontSize'] ) ) {
			$css .= 'font-size: ' . esc_attr( $typography['fontSize'] ) . ';';
		}

		if ( ! empty( $typography['fontWeight'] ) ) {
			$css .= 'font-weight: ' . esc_attr( $typography['fontWeight'] ) . ';';
		}

		if ( ! empty( $typography['lineHeight'] ) ) {
			$css .= 'line-height: ' . esc_attr( $typography['lineHeight'] ) . ';';
		}

		return $css;
	}
}

// Extract typography attributes.
$space_group_typography = ! empty( $attributes['spaceGroupTypography'] ) ? $attributes['spaceGroupTypography'] : [];
$space_typography       = ! empty( $attributes['spaceTypography'] ) ? $attributes['spaceTypography'] : [];

// Generate CSS from typography attributes.
$space_group_typo_css = suredash_get_typography_css( $space_group_typography );
$space_typo_css       = suredash_get_typography_css( $space_typography );

?>
<div <?php echo do_shortcode( get_block_wrapper_attributes( [ 'class' => 'portal-content' ] ) ); ?>>
	<?php
		printf(
			'<style class="suredash-navigation-block-css">
				.wp-block-suredash-navigation .portal-aside-group-body {
					margin-bottom:%1$s;
					padding-bottom: unset;
				}
				.portal-aside-group-list a.portal-aside-group-link {
					padding-top:%2$s;
					padding-bottom:%2$s;
					%8$s
				}
				.portal-aside-group-list a.portal-aside-group-link .portal-aside-item-title {
					%8$s
				}
				.portal-aside-group-header {
					margin-bottom:%3$s;
					background: %7$s;
				}
				.wp-block-suredash-navigation ul a.active {
					background: %5$s;
				}
				.wp-block-suredash-navigation ul a.active, .wp-block-suredash-navigation ul a.active * {
					color: %4$s;
				}
				.portal-aside-group .portal-aside-group-header .portal-aside-group-title {
					color: %6$s;
					%9$s
				}
			</style>',
			esc_attr( ! empty( $attributes['spacegroupsgap'] ) ? suredash_get_default_value_with_unit( $attributes['spacegroupsgap'] ) : '' ),
			esc_attr( ! empty( $attributes['spacesgap'] ) ? suredash_get_default_value_with_unit( $attributes['spacesgap'] ) : '' ),
			esc_attr( ! empty( $attributes['spacegrouptitlefirstspacegap'] ) ? suredash_get_default_value_with_unit( $attributes['spacegrouptitlefirstspacegap'] ) : '' ),
			esc_attr( ! empty( $elements['spaceactivetext']['color']['color'] ) ? $elements['spaceactivetext']['color']['color'] : '' ),
			esc_attr( ! empty( $elements['spaceactivebackground']['color']['background'] ) ? $elements['spaceactivebackground']['color']['background'] : '' ),
			esc_attr( ! empty( $elements['spacegrouptext']['color']['color'] ) ? $elements['spacegrouptext']['color']['color'] : '' ),
			esc_attr( ! empty( $elements['spacegroupbackground']['color']['background'] ) ? $elements['spacegroupbackground']['color']['background'] : '' ),
			wp_strip_all_tags( $space_typo_css ),
			wp_strip_all_tags( $space_group_typo_css ),
		);

		$content  = '';
		$endpoint = suredash_content_post();
		if ( $endpoint ) {
			$content = do_shortcode( '[portal_endpoint_navigation endpoint="' . $endpoint . '"]' );
		} else {
			$content = do_shortcode( '[portal_navigation show_only_navigation=true]' );
		}

		if ( empty( $content ) ) {
			$content = do_shortcode( '[portal_navigation show_only_navigation=true]' );
		}

		echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		?>
</div>
