<?php
/**
 * Two-column login pattern.
 *
 * @package SureDash
 * @since 1.4.0
 */

defined( 'ABSPATH' ) || exit;

return [
	'title'      => __( 'Two Column Login Layout', 'suredash' ),
	'categories' => [ 'suredash_auth' ],
	'blockTypes' => [ 'suredash/login' ],
	'content'    => suredash_login_two_columns_pattern(),
];
