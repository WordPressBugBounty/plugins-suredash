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

?>
<div <?php echo do_shortcode( get_block_wrapper_attributes( [ 'class' => 'portal-content' ] ) ); ?>>
	<?php
		printf(
			'<style class="suredash-navigation-block-css">
				.wp-block-suredash-navigation .portal-aside-group-body {
					margin-bottom:%1$s;
					padding-bottom: unset;
				}
				.wp-block-suredash-navigation .portal-aside-group-link {
					padding-top:%2$s;
					padding-bottom:%2$s;
				}
				.wp-block-suredash-navigation .portal-aside-group-header {
					margin-bottom:%3$s;
				}
				.wp-block-suredash-navigation ul a.active {
					background: %5$s;
				}
				.wp-block-suredash-navigation ul a.active, .wp-block-suredash-navigation ul a.active * {
					color: %4$s;
				}
				.wp-block-suredash-navigation .portal-aside-group-header {
					background: %7$s;
				}
				.wp-block-suredash-navigation .portal-aside-group-header .portal-aside-group-title {
					color: %6$s;
				}
			</style>',
			esc_attr( ! empty( $attributes['spacegroupsgap'] ) ? suredash_get_default_value_with_unit( $attributes['spacegroupsgap'] ) : '' ),
			esc_attr( ! empty( $attributes['spacesgap'] ) ? suredash_get_default_value_with_unit( $attributes['spacesgap'] ) : '' ),
			esc_attr( ! empty( $attributes['spacegrouptitlefirstspacegap'] ) ? suredash_get_default_value_with_unit( $attributes['spacegrouptitlefirstspacegap'] ) : '' ),
			esc_attr( ! empty( $elements['spaceactivetext']['color']['color'] ) ? $elements['spaceactivetext']['color']['color'] : '' ),
			esc_attr( ! empty( $elements['spaceactivebackground']['color']['background'] ) ? $elements['spaceactivebackground']['color']['background'] : '' ),
			esc_attr( ! empty( $elements['spacegrouptext']['color']['color'] ) ? $elements['spacegrouptext']['color']['color'] : '' ),
			esc_attr( ! empty( $elements['spacegroupbackground']['color']['background'] ) ? $elements['spacegroupbackground']['color']['background'] : '' ),
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
