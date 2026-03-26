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

if ( ! is_user_logged_in() ) {
	return;
}

$elements = ! empty( $attributes['style']['elements'] ) ? $attributes['style']['elements'] : []; // Extended color options support.

$block_atts = [
	'draweropenverposition'   => $attributes['draweropenverposition'] ?? 'top',
	'draweropenhorposition'   => is_rtl() ? 'left' : 'right',
	'drawerhorpositionoffset' => $attributes['drawerhorpositionoffset'] ?? '',
	'drawerverpositionoffset' => $attributes['drawerverpositionoffset'] ?? '',
	'iconcolor'               => ! empty( $elements['iconcolor']['color']['stroke'] ) ? $elements['iconcolor']['color']['stroke'] : '',
];

add_filter(
	'suredash_notification_attributes',
	static function ( $atts ) use ( $block_atts ) {
		return $block_atts;
	}
);

?>

<div <?php echo do_shortcode( get_block_wrapper_attributes( [ 'class' => 'portal-content' ] ) ); ?>>
	<?php echo do_shortcode( '[portal_notification]' ); ?>
</div>
