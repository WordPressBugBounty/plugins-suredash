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
	$current_post_id = get_the_ID();
	$space_id        = $current_post_id ? absint( sd_get_space_id_by_post( $current_post_id ) ) : 0;
	$default_url     = home_url( '/' . suredash_get_community_slug() . '/' );
	$space_link      = $space_id ? get_permalink( $space_id ) . '#portal-post-' . $current_post_id : $default_url;
	$space_title     = $space_id ? get_the_title( $space_id ) : '';

	// Priority 1: Use ?ref= param (passed from quick-view expand) — this is the actual parent page.
	// Priority 2: Use HTTP_REFERER (direct navigation, no quick-view).
	// Priority 3: Fall back to space permalink.
	$ref_param     = ! empty( $_GET['ref'] ) ? esc_url_raw( rawurldecode( sanitize_text_field( wp_unslash( $_GET['ref'] ) ) ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$referer       = $ref_param ? $ref_param : ( ! empty( $_SERVER['HTTP_REFERER'] ) ? esc_url_raw( $_SERVER['HTTP_REFERER'] ) : '' );
	$referer_clean = $referer ? strtok( $referer, '?' ) : ''; // Strip query params.
	$is_same_site  = $referer_clean && strpos( $referer_clean, home_url() ) === 0;
	$is_self       = $is_same_site && $current_post_id && strpos( $referer_clean, (string) $current_post_id ) !== false;
	$use_referer   = $is_same_site && ! $is_self;
	$back_link     = $use_referer ? $referer_clean . '#portal-post-' . $current_post_id : $space_link;

	// Resolve back link title from referer or space.
	$back_title = '';
	if ( $use_referer ) {
		$slug = basename( rtrim( $referer_clean, '/' ) );
		if ( $slug === 'feeds' ) {
			$back_title = __( 'Feeds', 'suredash' );
		} else {
			$ref_post   = get_page_by_path( $slug, OBJECT, 'portal' );
			$back_title = ! empty( $ref_post->post_title ) ? $ref_post->post_title : '';
		}
	}
	if ( empty( $back_title ) ) {
		$back_title = $space_title;
	}
	?>
	<a href="<?php echo esc_url( $back_link ); ?>" <?php echo do_shortcode( get_block_wrapper_attributes( [ 'class' => 'portal-content portal-sub-item-link' ] ) ); ?>>
		<?php
			Helper::get_library_icon( 'ChevronLeft', true );

		if ( ! empty( $back_title ) ) {
			Labels::get_label( 'back_to_cpt', true );
			echo esc_html( $back_title );
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
