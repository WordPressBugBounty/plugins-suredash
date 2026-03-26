<?php
/**
 * The template for displaying portal footer.
 *
 * @see     https://developer.wordpress.org/themes/basics/template-hierarchy/
 * @package SureDash\Templates
 * @version 1.0.0
 */

defined( 'ABSPATH' ) || exit;

echo do_shortcode(
	apply_filters(
		'suredash_footer_brand_output',
		sprintf(
			'
				<a target="_blank" href="%2$s" class="portal-branding portal-content">
					%1$s
				</a>
			',
			__( 'Powered by', 'suredash' ) . ' SureDash',
			'https://suredash.com/'
		)
	)
);
