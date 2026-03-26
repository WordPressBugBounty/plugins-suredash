<?php
/**
 * All Ajax related actions.
 *
 * @package SureDash
 * @since 1.0.0
 */

namespace SureDashboard\Inc\Traits;

defined( 'ABSPATH' ) || exit;

/**
 * Trait Ajax.
 *
 * @since 1.0.0
 */
trait Ajax {
	/**
	 * Ajax prefix
	 *
	 * @var string
	 */
	public $ajax_prefix = 'portal';

	/**
	 * Errors
	 *
	 * @access private
	 * @var array<string, string> Errors strings.
	 * @since 1.0.0
	 */
	public $errors = [];

	/**
	 * Ajax events
	 * This array should be defined in the class constructor.
	 * It will be used to create ajax events.
	 * It should be an array of strings or array of arrays of strings.
	 * Each string will be used to create an ajax event.
	 *
	 * @since 1.0.0
	 * @var array<int, mixed>
	 */
	public $ajax_events = [];

	/**
	 * Ajax events for non logged in users
	 * This array should be defined in the class constructor
	 * It will be used to create ajax events for non logged in users.
	 * It should be an array of strings or array of arrays of strings.
	 * Each string will be used to create an ajax event.
	 *
	 * @since 1.0.0
	 * @var array<int, mixed>
	 */
	public $ajax_nopriv_events = [];

	/**
	 * Initiate ajax events
	 * This function should be called from the class constructor
	 * It will add action to initiate ajax events.
	 * Further create a static function for each ajax event.
	 * Each function should be named as the ajax event.
	 * Each function should be public and static.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function initiate_ajax_events(): void {
		foreach ( $this->ajax_events as $event ) {
			if ( is_array( $event ) ) {
				foreach ( $event as $sub_event ) {
					$this->add_ajax_event( $sub_event );
				}
			}
			if ( is_string( $event ) ) {
				$this->add_ajax_event( $event );
			}
		}

		foreach ( $this->ajax_nopriv_events as $event ) {
			if ( is_array( $event ) ) {
				foreach ( $event as $sub_event ) {
					$this->add_ajax_event( $sub_event );
					$this->add_ajax_event( $sub_event, true );
				}
			}
			if ( is_string( $event ) ) {
				$this->add_ajax_event( $event );
				$this->add_ajax_event( $event, true );
			}
		}

		$this->set_ajax_event_errors();
	}

	/**
	 * Add ajax event
	 * This function should be called from the initiate_ajax_events() function.
	 * It will add action to initiate ajax event.
	 * It will create a function for the ajax event.
	 * The function should be named as the ajax event.
	 * The function should be public and static.
	 *
	 * @param string $event   Event name.
	 * @param bool   $nopriv  Whether the event is for non logged in users.
	 * @since 1.0.0
	 * @return void
	 */
	public function add_ajax_event( $event, $nopriv = false ): void {
		$nopriv_prefix = $nopriv ? 'nopriv_' : '';
		add_action( 'wp_ajax_' . $nopriv_prefix . $this->ajax_prefix . '_' . $event, [ $this, $event ] ); // @phpstan-ignore-line
	}

	/**
	 * Creates ajax nonces
	 * This function should be called from the class constructor
	 * It will create nonces for each ajax event.
	 * It should be called when array of nonce needs to be created, to further localize the script.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function create_ajax_nonces(): void {
		$nonces = [];

		if ( ! empty( $this->ajax_events ) ) {
			foreach ( $this->ajax_events as $event ) {
				$nonces[ $event . '_nonce' ] = wp_create_nonce( $this->ajax_prefix . '_' . $event );
			}
		}

		if ( ! empty( $this->ajax_nopriv_events ) ) {
			foreach ( $this->ajax_nopriv_events as $event ) {
				$nonces[ $event . '_nonce' ] = wp_create_nonce( $this->ajax_prefix . '_' . $event );
			}
		}

		$filter_handle = is_admin() ? '_localized_admin_data' : '_localized_frontend_data';

		// Add the nonces to the localized array.
		add_filter(
			$this->ajax_prefix . $filter_handle,
			static function ( $localized_data ) use ( $nonces ) {
				return array_merge( $localized_data, $nonces );
			}
		);
	}

	/**
	 * Creates an array of default ajax action related error messages.
	 * This function will be called automatically when the initiate_ajax_events() function is called.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function set_ajax_event_errors(): void {
		$this->errors = [
			'permission'        => __( 'Action not allowed. Please ensure your role includes Portal Manager access.', 'suredash' ),
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
	 * @since 1.0.0
	 */
	public function get_ajax_event_error( $type ) {
		if ( ! isset( $this->errors[ $type ] ) ) {
			$type = 'default';
		}

		return $this->errors[ $type ];
	}
}
