<?php
/**
 * Two-column registration pattern.
 *
 * @package SureDash
 * @since 1.4.0
 */

defined( 'ABSPATH' ) || exit;

return [
	'title'      => __( 'Two Column Registration Layout', 'suredash' ),
	'categories' => [ 'suredash_auth' ],
	'blockTypes' => [ 'suredash/register' ],
	'content'    => suredash_register_two_columns_pattern(),
];
