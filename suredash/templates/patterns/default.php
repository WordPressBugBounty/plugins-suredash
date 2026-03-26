<?php
/**
 * Classic portal block pattern.
 *
 * @package Suredash
 * @since 0.0.6
 */

defined( 'ABSPATH' ) || exit;

return [
	'title'      => __( 'Classic Layout', 'suredash' ),
	'categories' => [ 'suredash_portal' ],
	'blockTypes' => [ 'suredash/portal' ],
	'content'    => file_get_contents( SUREDASHBOARD_DIR . 'templates/parts/portal.html' ),
];
