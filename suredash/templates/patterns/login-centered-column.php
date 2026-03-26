<?php
/**
 * Single column login centered pattern.
 *
 * @package SureDash
 * @since 1.4.0
 */

defined( 'ABSPATH' ) || exit;

return [
	'title'      => __( 'Login Centered Layout', 'suredash' ),
	'categories' => [ 'suredash_auth' ],
	'blockTypes' => [ 'suredash/login' ],
	'content'    => suredash_login_single_column_centered_pattern(),
];
