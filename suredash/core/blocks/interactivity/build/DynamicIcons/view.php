<?php
/**
 * PHP file to use when rendering the DynamicIcons block type on the server to show on the front end.
 *
 * The following variables are exposed to the file:
 *     $attributes (array): The block attributes.
 *     $content (string): The block default content.
 *     $block (WP_Block): The block instance.
 *
 * @see https://github.com/WordPress/gutenberg/blob/trunk/docs/reference-guides/block-api/block-metadata.md#render
 * @package suredash
 * @since 1.5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$content = $content ?? '';

// Get attributes with defaults.
$attributes = $attributes ?? [];

// The <ul> lives in saved post content, so existing sites cannot be fixed by
// editing the block pattern. Inject the accessible name server-side instead,
// which reaches every site on the next page load.
$processor = new \WP_HTML_Tag_Processor( $content );
if ( $processor->next_tag( [ 'tag_name' => 'ul' ] ) ) {
	$processor->set_attribute( 'aria-label', __( 'Main', 'suredash' ) );
	$content = $processor->get_updated_html();
}

echo do_shortcode( $content );
