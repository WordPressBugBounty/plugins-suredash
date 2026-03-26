<?php
/**
 * Single column register centered pattern.
 *
 * @package SureDash
 * @since 1.4.0
 */

defined( 'ABSPATH' ) || exit;

return [
	'title'      => __( 'Register Centered Layout', 'suredash' ),
	'categories' => [ 'suredash_auth' ],
	'blockTypes' => [ 'suredash/register' ],
	'content'    => suredash_register_single_column_centered_pattern(),
];
