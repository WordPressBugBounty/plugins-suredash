<?php
/**
 * The template for displaying content area view.
 *
 * @see     https://developer.wordpress.org/themes/basics/template-hierarchy/
 * @package SureDash\Templates
 * @version 1.0.0
 */

defined( 'ABSPATH' ) || exit;

$endpoint      = $endpoint ?? false;
$endpoint_wrap = 'portal-endpoint-' . $endpoint;

if ( ! $endpoint ) {
	suredash_get_template_part( 'parts', '404' );
	exit;
}

echo do_shortcode(
	apply_filters(
		'suredashboard_content_output',
		sprintf(
			'
				<section class="portal-content-wrapper portal-wrapper %1$s">
					<div class="portal-content-inner-wrap">
						<div class="portal-aside-left portal-sticky-col portal-content"> %2$s </div>
						<div class="portal-entry-container portal-post-content"> %3$s </div>
					</div>
				</section>
			',
			esc_attr( $endpoint ? $endpoint_wrap : '' ), // @phpstan-ignore-line
			'[portal_endpoint_navigation endpoint="' . $endpoint . '"]',
			'[portal_single_endpoint_content endpoint="' . $endpoint . '"]'
		)
	)
);
