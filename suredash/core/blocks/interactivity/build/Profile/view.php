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

if ( ! suredash_content_post() ) {
	$attributes = $attributes ?? [];
	$block_atts = [
		'onlyavatar'                   => boolval( $attributes['onlyavatar'] ),
		'menuopenverposition'          => $attributes['menuopenverposition'] ?? 'top',
		'menuopenhorposition'          => is_rtl() ? 'left' : 'right',
		'menuhorpositionoffset'        => $attributes['menuhorpositionoffset'] ?? '',
		'menuverpositionoffset'        => $attributes['menuverpositionoffset'] ?? '',
		'makefixed'                    => boolval( $attributes['makefixed'] ),
		'blockstickyverpositionoffset' => $attributes['blockstickyverpositionoffset'] ?? '',
		'blockmaxwidth'                => $attributes['blockmaxwidth'] ?? '',
		'stickyhorpositionoffset'      => $attributes['stickyhorpositionoffset'] ?? '',
		'stickyverpositionoffset'      => $attributes['stickyverpositionoffset'] ?? '',
	];

	add_filter(
		'suredash_user_profile_attributes',
		static function ( $atts ) use ( $block_atts ) {
			return $block_atts;
		}
	);

	$classes                    = $block_atts['makefixed'] ? 'suredash-profile--fixed portal-content' : 'portal-content';
	$avatar_size                = ! empty( $attributes['avatarsize'] ) ? suredash_get_default_value_with_unit( $attributes['avatarsize'] ) : '40px';
	$sticky_ver_position_offset = ! empty( $block_atts['blockstickyverpositionoffset'] ) ? suredash_get_default_value_with_unit( $block_atts['blockstickyverpositionoffset'] ) : '0';
	$block_max_width            = ! empty( $block_atts['blockmaxwidth'] ) ? suredash_get_default_value_with_unit( $block_atts['blockmaxwidth'] ) : '100%';

	?>
		<div <?php echo do_shortcode( get_block_wrapper_attributes( [ 'class' => $classes ] ) ); ?>>
			<?php
				echo do_shortcode(
					'<style class="suredash-profile-block-css">
						.wp-block-suredash-profile .portal-header-avatar-wrap img:not(.portal-custom-svg-icon img) {
							max-width:' . esc_attr( $avatar_size ) . ' !important;
							max-height:' . esc_attr( $avatar_size ) . ' !important;
							width:' . esc_attr( $avatar_size ) . ' !important;
							height:' . esc_attr( $avatar_size ) . ' !important;
						}
						.suredash-profile--fixed .portal-user-profiles-wrap {
							bottom: ' . esc_attr( $sticky_ver_position_offset ) . ';
							max-width: ' . esc_attr( $block_max_width ) . ';
							width: 100%;
						}
					</style>'
				);

				echo do_shortcode( '[portal_user_profile]' );
			?>
		</div>
	<?php
}
