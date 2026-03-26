<?php
/**
 * The template for displaying portal header.
 *
 * @see     https://developer.wordpress.org/themes/basics/template-hierarchy/
 * @package SureDash\Templates
 * @version 1.0.0
 */

defined( 'ABSPATH' ) || exit;

echo do_shortcode( render_block( [ 'blockName' => 'suredash/identity' ] ) );
echo do_shortcode( render_block( [ 'blockName' => 'suredash/search' ] ) );
