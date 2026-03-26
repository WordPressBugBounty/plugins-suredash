<?php
/**
 * The template for displaying content area view.
 *
 * @see     https://developer.wordpress.org/themes/basics/template-hierarchy/
 * @package SureDash\Templates
 * @version 1.0.0
 */

defined( 'ABSPATH' ) || exit;

$content_type    = $content_type ?? 'welcome';
$endpoint        = $endpoint ?? 'default';
$container_class = $content_type === 'welcome' ? 'portal-entry-container' : 'portal-entry-container portal-post-content';

// Note: This template is not used in block-based themes.
// The Content block (core/blocks/interactivity/src/Content/view.php) is used instead.

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
			$container_class,
			$content_type === 'post' ? '[portal_single_content]' :
			sprintf(
				'
					[portal_content_header]
					[portal_home_content type="%1$s"]
				',
				$endpoint
			)
		)
	)
);
