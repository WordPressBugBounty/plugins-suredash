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

use SureDashboard\Inc\Utils\Helper;
use SureDashboard\Inc\Utils\Labels;

$attributes      = $attributes ?? [];
$resp_aside_menu = boolval( $attributes['responsivesidenavigation'] ) ? true : false;
$endpoint        = suredash_content_post();

if ( $resp_aside_menu && ! $endpoint ) {
	echo do_shortcode( '[portal_responsive_navigation]' );
}

if ( $endpoint ) {
	echo do_shortcode( '[portal_responsive_navigation]' );
	echo do_shortcode( '[portal_single_endpoint_content get_only_header="true" endpoint="' . $endpoint . '"]' );
} elseif ( suredash_cpt() ) {
	$is_referer  = ! empty( $_SERVER['HTTP_REFERER'] ) ? true : false;
	$default_url = home_url( '/' . suredash_get_community_slug() . '/' );
	$back_link   = $is_referer ? esc_url_raw( $_SERVER['HTTP_REFERER'] ) : $default_url;

	// Remove query parameters from the back link if they exist & redirect to the default URL, case: Quick View new tab opened post getting simply_content URL.
	if ( strpos( $back_link, '?' ) !== false ) {
		$back_link = $default_url;
	}
	?>
	<a href="<?php echo esc_url( $back_link ); ?>" <?php echo do_shortcode( get_block_wrapper_attributes( [ 'class' => 'portal-content portal-sub-item-link' ] ) ); ?>>
		<?php
			Helper::get_library_icon( 'ChevronLeft', true );
			$ref_post = null;

		if ( $is_referer ) {
			$slug     = basename( rtrim( $back_link, '/' ) );
			$ref_post = get_page_by_path( $slug, OBJECT, 'portal' );
		}

		if ( $is_referer && ! empty( $ref_post->post_title ) ) {
			Labels::get_label( 'back_to_cpt', true );
			echo esc_html( $ref_post->post_title );
		} else {
			Labels::get_label( 'back_to_portal', true );
		}
		?>
	</a>
	<?php
} else {
	$title_placeholder = Labels::get_label( 'welcome_text' ) . ' ' . suredash_get_user_display_name();
	$atts              = apply_filters(
		'suredash_title_block_set',
		[
			'emoji' => '',
			'title' => $title_placeholder,
		]
	);
	?>
	<h1 <?php echo do_shortcode( get_block_wrapper_attributes( [ 'class' => 'portal-content sd-flex sd-gap-8 sd-items-center' ] ) ); ?>>
		<?php
		$space_id = get_the_ID();
		do_action( 'suredash_before_title_block', $space_id );

		if ( ! empty( $atts['emoji'] ) ) {
			echo do_shortcode( apply_filters( 'suredash_aside_navigation_space_icon_' . $space_id, Helper::get_library_icon( $atts['emoji'], false, 'md' ), $space_id ) );
		}

		if ( ! empty( $atts['title'] ) ) {
			echo wp_kses_post( $atts['title'] );
		} else {
			echo wp_kses_post( $title_placeholder );
		}

		do_action( 'suredash_after_title_block', $space_id );
		?>
	</h1>
	<?php
}
