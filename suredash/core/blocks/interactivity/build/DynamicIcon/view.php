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

use SureDashboard\Inc\Utils\Helper;

// Get attributes with defaults.
$attributes = $attributes ?? [];
$block      = $block ?? null;

if ( ! $block ) {
	return;
}

$open_in_new_tab = $block->context['openInNewTab'] ?? false;
$stack_icon_text = $block->context['stackIconText'] ?? false;

$text = ! empty( $attributes['label'] ) ? trim( $attributes['label'] ) : '';

$service     = $attributes['service'] ?? 'Icon';
$url         = $attributes['url'] ?? false;
$text        = $text ? $text : $service;
$rel         = $attributes['rel'] ?? '';
$show_labels = array_key_exists( 'showLabels', $block->context ) ? $block->context['showLabels'] : false;

// Don't render a link if there is no URL set.
if ( ! $url ) {
	return '';
}

$wrapper_attributes = get_block_wrapper_attributes(
	[
		'class' => 'wp-social-link wp-social-link-' . $service . suredash_dynamic_icon_get_color_classes( $block->context ),
		'style' => suredash_dynamic_icon_get_color_styles( $block->context ),
	]
);

$icon_link  = '<li ' . $wrapper_attributes . '>';
$icon_link .= '<a href="' . esc_url( $url ) . '" class="wp-block-suredash-dynamic-icon-anchor ' . esc_attr( $stack_icon_text ? 'sd-flex-col' : '' ) . '">';
$icon_link .= Helper::get_library_icon( $service, false, 'inherit' );
$icon_link .= '<span class="wp-block-suredash-dynamic-icon-label' . ( $show_labels ? '' : ' screen-reader-text' ) . '">' . esc_html( $text ) . '</span>';
$icon_link .= '</a></li>';

$processor = new \WP_HTML_Tag_Processor( $icon_link );
$processor->next_tag( 'a' ); // @phpstan-ignore-line

if ( $open_in_new_tab ) {
	$processor->set_attribute( 'rel', trim( $rel . ' noopener nofollow' ) );
	$processor->set_attribute( 'target', '_blank' );
} elseif ( $rel !== '' ) {
	$processor->set_attribute( 'rel', trim( $rel ) );
}

echo do_shortcode( $processor->get_updated_html() );
