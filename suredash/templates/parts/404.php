<?php
/**
 * The template for displaying content area view.
 *
 * @see     https://developer.wordpress.org/themes/basics/template-hierarchy/
 * @package SureDash\Templates
 * @version 1.0.0
 */

defined( 'ABSPATH' ) || exit;

use SureDashboard\Inc\Utils\Labels;

$default_args = [
	'404_heading'    => Labels::get_label( '404_heading' ),
	'not_found_text' => Labels::get_label( 'no_posts_found' ),
	'empty_portal'   => false,
];

if ( ! isset( $args ) ) {
	$args = [];
}

$args = wp_parse_args( $args, $default_args );

$heading       = $args['404_heading'];
$description   = $args['not_found_text'];
$empty_portal  = $args['empty_portal'];
$display_cta   = $args['display_cta'] ?? true;
$image_url     = $empty_portal ? 'assets/images/empty-state.svg' : 'assets/images/404.svg';
$image_classes = 'portal-404-image' . ( ! $empty_portal ? ' sd-max-w-100' : ' sd-max-w-300' ) . ' sd-w-full sd-h-full';

echo do_shortcode(
	apply_filters(
		'suredashboard_404_content_output',
		sprintf(
			'
				<section class="portal-content-area sd-flex sd-justify-center sd-items-center portal-not-found-wrapper">
					%1$s
				</section>
			',
			sprintf(
				'
					<div class="portal-content sd-flex sd-flex-col sd-gap-16 sd-max-w-custom sd-mx-auto sd-mt-30 sd-mb-32 sd-text-center sd-items-center sd-p-custom" style="--sd-max-w-custom: 600px; --sd-p-custom: 40px; margin: 32px auto;">
						<img src="%1$s" alt="%2$s" class="%7$s" />
						<h4 class="portal-item-title sd-no-space"> %3$s </h4>
						%4$s
						<div class="sd-flex sd-gap-8 sd-justify-center">
							%5$s
							%6$s
						</div>
					</div>
				',
				esc_url( SUREDASHBOARD_URL . $image_url ),
				$heading,
				$heading,
				$description,
				sprintf(
					$display_cta ? '<button data-href="%1$s" class="portal-button link-button button-primary sd-w-fit sd-flex sd-self-center">%2$s</button>' : '',
					esc_url( home_url( '/' . suredash_get_community_slug() ) ),
					Labels::get_label( 'back_to_home' )
				),
				! is_user_logged_in() ? sprintf(
					'
						<a href="%1$s" class="portal-button button-secondary sd-w-fit sd-flex sd-self-center">%2$s</a>
					',
					esc_url( suredash_get_login_page_url() ),
					__( 'Login', 'suredash' )
				) : '',
				$image_classes,
			)
		)
	)
);
