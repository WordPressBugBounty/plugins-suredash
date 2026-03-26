<?php
/**
 * All REST related actions.
 *
 * @package SureDash
 * @since 0.0.2
 */

namespace SureDashboard\Inc\Traits;

defined( 'ABSPATH' ) || exit;

/**
 * Trait Ajax.
 *
 * @since 0.0.2
 */
trait Rest_Errors {
	/**
	 * Errors
	 *
	 * @access private
	 * @var array<string, string> Errors strings.
	 * @since 0.0.2
	 */
	public $errors = [];

	/**
	 * Creates an array of default ajax action related error messages.
	 *
	 * @since 0.0.2
	 * @return void
	 */
	public function set_rest_event_errors(): void {
		$this->errors = [
			'permission'        => __( 'Sorry, you are not allowed to do this operation.', 'suredash' ),
			'nonce'             => __( 'Nonce validation failed', 'suredash' ),
			'default'           => __( 'Sorry, something went wrong.', 'suredash' ),
			'missing_key'       => __( 'Oops, the required key is missing.', 'suredash' ),
			'invalid_post_type' => __( 'The current post\'s post type is not of this plugin.', 'suredash' ),
			'success'           => __( 'Data saved successfully.', 'suredash' ),
		];
	}

	/**
	 * Get error message.
	 *
	 * @param string $type Message type.
	 * @return string
	 * @since 0.0.2
	 */
	public function get_rest_event_error( $type ) {

		if ( empty( $this->errors ) ) {
			$this->set_rest_event_errors();
		}

		if ( ! isset( $this->errors[ $type ] ) ) {
			$type = 'default';
		}

		return $this->errors[ $type ];
	}
}
