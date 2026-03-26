<?php
/**
 * The template for displaying content area view.
 *
 * @see     https://developer.wordpress.org/themes/basics/template-hierarchy/
 * @package SureDash\Templates
 * @version 1.0.0
 */

defined( 'ABSPATH' ) || exit;

echo do_shortcode(
	apply_filters(
		'suredashboard_content_output',
		sprintf(
			'
				<section class="portal-content-wrapper portal-wrapper">
					<div class="portal-content-inner-wrap">
						<div class="portal-aside-left portal-sticky-col portal-content"> [portal_navigation] </div>
						<div class="%1$s"> %2$s </div>
					</div>
				</section>
			',
			'portal-entry-container portal-post-content',
			'[portal_single_content]'
		)
	)
);
