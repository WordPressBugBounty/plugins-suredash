<?php
/**
 * Abstract Ability Base Class.
 *
 * Provides the schema and execution interface for AI-consumable abilities.
 *
 * @package SureDash
 * @since 1.6.3
 */

namespace SureDashboard\Inc\Services\Abilities;

use SureDashboard\Inc\Utils\Helper;

defined( 'ABSPATH' ) || exit;

/**
 * Abstract Ability class.
 *
 * Each ability wraps an existing SureDash operation with a self-documenting
 * schema that AI agents can discover and invoke.
 *
 * @since 1.6.3
 */
abstract class Ability {
	/**
	 * Premium space integration types that require SureDash Pro.
	 *
	 * @since 1.7.3
	 */
	public const PRO_INTEGRATIONS = [ 'course', 'resource_library', 'collection', 'events' ];

	/**
	 * Option gate key.
	 *
	 * When non-empty, the ability is disabled if the option is explicitly false/0.
	 * Abilities default to enabled — they are only gated off when an admin
	 * disables them via the MCP settings page.
	 *
	 * @since 1.7.3
	 * @var string
	 */
	protected string $gated = '';

	/**
	 * Get the unique identifier for this ability.
	 *
	 * @return string Kebab-case ID (e.g., 'create-space').
	 */
	abstract public function get_id(): string;

	/**
	 * Get the human-readable name.
	 *
	 * @return string
	 */
	abstract public function get_name(): string;

	/**
	 * Get a detailed description of what this ability does.
	 *
	 * @return string
	 */
	abstract public function get_description(): string;

	/**
	 * Get the category this ability belongs to.
	 *
	 * @return string One of: spaces, groups, community, users, settings, analytics.
	 */
	abstract public function get_category(): string;

	/**
	 * Get the parameter schema for this ability.
	 *
	 * @return array<string, array<string, mixed>> Associative array of parameter definitions.
	 */
	abstract public function get_parameters(): array;

	/**
	 * Execute the ability with the given parameters.
	 *
	 * @param array<string, mixed> $params Validated parameters.
	 * @return array<string, mixed> Result array with 'success' and 'data' keys.
	 */
	abstract public function execute( array $params ): array;

	/**
	 * Get the permission level required.
	 *
	 * @return string Default 'admin'.
	 */
	public function get_permission(): string {
		return 'admin';
	}

	/**
	 * Check if this ability is enabled based on its gate option.
	 *
	 * If the ability has a $gated option key set, it checks that option.
	 * Abilities default to enabled — only gated off when admin explicitly
	 * disables them via the MCP settings.
	 *
	 * @since 1.7.3
	 * @return bool
	 */
	public function is_enabled(): bool {
		if ( ! empty( $this->gated ) && ! Helper::get_option( $this->gated, true ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Get the return value schema.
	 *
	 * @return array<string, mixed>
	 */
	public function get_returns(): array {
		return [];
	}

	/**
	 * Get MCP tool annotations describing behavioral characteristics.
	 *
	 * Keys use MCP protocol naming: readOnlyHint, destructiveHint, idempotentHint.
	 * These are passed through to the WP Abilities API meta.annotations and
	 * exposed directly by the MCP adapter.
	 *
	 * @return array<string, bool>
	 */
	public function get_annotations(): array {
		return [];
	}

	/**
	 * Get plain-text instructions for AI agents.
	 *
	 * Provides workflow guidance: what to call before/after, caveats,
	 * cross-references to related abilities.
	 *
	 * @return string
	 */
	public function get_instructions(): string {
		return '';
	}

	/**
	 * Get the label for WordPress Abilities API.
	 *
	 * Alias for get_name() to match WP Abilities API convention.
	 *
	 * @return string
	 */
	public function get_label(): string {
		return $this->get_name();
	}

	/**
	 * Get the namespaced ability name for WordPress Abilities API.
	 *
	 * Format: suredash/{ability-id}
	 *
	 * @return string
	 */
	public function get_wp_ability_name(): string {
		return 'suredash/' . $this->get_id();
	}

	/**
	 * Get the description for WordPress Abilities API registration.
	 *
	 * Concatenates the base description with instructions (if any) so AI
	 * agents see workflow guidance in the tool description.
	 *
	 * @return string
	 */
	public function get_wp_description(): string {
		$description  = $this->get_description();
		$instructions = $this->get_instructions();

		if ( ! empty( $instructions ) ) {
			$description .= ' ' . $instructions;
		}

		return $description;
	}

	/**
	 * Convert the parameter schema to JSON Schema format for WP Abilities API.
	 *
	 * @return array<string, mixed> JSON Schema compatible input schema.
	 */
	public function get_input_schema(): array {
		$parameters = $this->get_parameters();

		if ( empty( $parameters ) ) {
			return [
				'type'                 => 'object',
				'properties'           => new \stdClass(),
				'additionalProperties' => false,
				'default'              => [],
			];
		}

		$properties = [];
		$required   = [];

		foreach ( $parameters as $key => $definition ) {
			$property = [
				'description' => $definition['description'] ?? '',
			];

			// Map our types to JSON Schema types.
			$type = $definition['type'] ?? 'string';
			switch ( $type ) {
				case 'integer':
					$property['type'] = 'integer';
					break;
				case 'boolean':
					$property['type'] = 'boolean';
					break;
				case 'array':
					$property['type'] = 'array';
					break;
				case 'object':
					$property['type'] = 'object';
					break;
				default:
					$property['type'] = 'string';
					break;
			}

			if ( isset( $definition['enum'] ) ) {
				$property['enum'] = $definition['enum'];
			}

			if ( isset( $definition['default'] ) ) {
				$property['default'] = $definition['default'];
			}

			$properties[ $key ] = $property;

			if ( ! empty( $definition['required'] ) ) {
				$required[] = $key;
			}
		}

		$schema = [
			'type'                 => 'object',
			'properties'           => $properties,
			'additionalProperties' => false,
			'default'              => [],
		];

		if ( ! empty( $required ) ) {
			$schema['required'] = $required;
		}

		return $schema;
	}

	/**
	 * Execute callback for WordPress Abilities API.
	 *
	 * Called by wp_register_ability's execute_callback. Receives the input
	 * array from the WP Abilities API, validates, applies defaults, and
	 * delegates to the concrete execute() method.
	 *
	 * @param array<string, mixed> $input Input from WP Abilities API.
	 * @return array<string, mixed> Result array.
	 */
	public function handle_execute( array $input ): array {
		$params = $this->apply_defaults( $input );

		$validation = $this->validate( $params );

		if ( is_wp_error( $validation ) ) {
			return [
				'success' => false,
				'message' => $validation->get_error_message(),
				'errors'  => $validation->get_error_data()['errors'] ?? [],
			];
		}

		return $this->execute( $params );
	}

	/**
	 * Permission callback for WordPress Abilities API.
	 *
	 * Checks: master toggle → per-ability gate → user capability.
	 *
	 * @since 1.7.3
	 * @return bool Whether the current user can execute this ability.
	 */
	public function check_permission(): bool {
		// Master toggle — all abilities off when disabled.
		if ( ! Helper::get_option( 'suredash_abilities_api', false ) ) {
			return false;
		}

		// Per-ability gate (edit/delete toggles).
		if ( ! $this->is_enabled() ) {
			return false;
		}

		if ( function_exists( 'suredash_is_user_manager' ) && suredash_is_user_manager() ) {
			return true;
		}

		return current_user_can( 'manage_options' );
	}

	/**
	 * Validate parameters against the schema.
	 *
	 * @param array<string, mixed> $params Parameters to validate.
	 * @return true|\WP_Error True if valid, WP_Error with details if not.
	 */
	public function validate( array $params ) {
		$schema = $this->get_parameters();
		$errors = [];

		foreach ( $schema as $key => $definition ) {
			$required = $definition['required'] ?? false;
			$type     = $definition['type'] ?? 'string';

			// Check required.
			if ( $required && ! isset( $params[ $key ] ) ) {
				$errors[] = sprintf(
					/* translators: %s: parameter name */
					__( 'Missing required parameter: %s', 'suredash' ),
					$key
				);
				continue;
			}

			if ( ! isset( $params[ $key ] ) ) {
				continue;
			}

			$value = $params[ $key ];

			// Type checking.
			if ( ! $this->check_type( $value, $type ) ) {
				$errors[] = sprintf(
					/* translators: 1: parameter name, 2: expected type, 3: actual type */
					__( 'Parameter "%1$s" must be of type %2$s, got %3$s', 'suredash' ),
					$key,
					$type,
					gettype( $value )
				);
			}

			// Enum checking.
			if ( ! empty( $definition['enum'] ) && ! in_array( $value, $definition['enum'], true ) ) {
				$errors[] = sprintf(
					/* translators: 1: parameter name, 2: allowed values */
					__( 'Parameter "%1$s" must be one of: %2$s', 'suredash' ),
					$key,
					implode( ', ', $definition['enum'] )
				);
			}
		}

		if ( ! empty( $errors ) ) {
			return new \WP_Error(
				'invalid_params',
				__( 'Parameter validation failed', 'suredash' ),
				[
					'status' => 400,
					'errors' => $errors,
				]
			);
		}

		return true;
	}

	/**
	 * Apply defaults to parameters.
	 *
	 * @param array<string, mixed> $params Raw parameters.
	 * @return array<string, mixed> Parameters with defaults applied.
	 */
	public function apply_defaults( array $params ): array {
		$schema = $this->get_parameters();

		foreach ( $schema as $key => $definition ) {
			if ( ! isset( $params[ $key ] ) && isset( $definition['default'] ) ) {
				$params[ $key ] = $definition['default'];
			}
		}

		return $params;
	}

	/**
	 * Check if an integration type requires SureDash Pro.
	 *
	 * @since 1.7.3
	 *
	 * @param string $integration The integration/space type slug.
	 * @return bool
	 */
	protected function is_pro_integration( string $integration ): bool {
		return in_array( $integration, self::PRO_INTEGRATIONS, true );
	}

	/**
	 * Check if a space has a premium integration and Pro is not active.
	 *
	 * Returns a WP_Error if the space requires Pro but it's not available,
	 * or true if the check passes (either not premium, or Pro is active).
	 *
	 * @since 1.7.3
	 *
	 * @param int $space_id The space post ID.
	 * @return true|\WP_Error True if allowed, WP_Error if Pro is required but not active.
	 */
	protected function require_pro_for_space( int $space_id ) {
		$integration = sd_get_post_meta( $space_id, 'integration', true );

		if ( ! $this->is_pro_integration( (string) $integration ) ) {
			return true;
		}

		if ( function_exists( 'suredash_is_pro_active' ) && suredash_is_pro_active() ) {
			return true;
		}

		return new \WP_Error(
			'pro_required',
			sprintf(
				/* translators: %s: integration type name */
				__( 'The "%s" space type requires SureDash Pro to be active.', 'suredash' ),
				$integration
			),
			[ 'status' => 403 ]
		);
	}

	/**
	 * Get a standardized error response for Pro-required operations.
	 *
	 * @since 1.7.3
	 *
	 * @param string $integration The integration type that requires Pro.
	 * @return array<string, mixed>
	 */
	protected function get_pro_required_error( string $integration ): array {
		return [
			'success' => false,
			'data'    => [
				'message' => sprintf(
					/* translators: %s: integration type name */
					__( 'The "%s" space type requires SureDash Pro to be active.', 'suredash' ),
					$integration
				),
			],
		];
	}

	/**
	 * Check if a value matches the expected type.
	 *
	 * @param mixed  $value The value to check.
	 * @param string $type  Expected type (string, integer, boolean, array, object).
	 * @return bool
	 */
	protected function check_type( $value, string $type ): bool {
		switch ( $type ) {
			case 'string':
				return is_string( $value ) || is_numeric( $value );
			case 'integer':
				return is_int( $value ) || ( is_string( $value ) && ctype_digit( $value ) );
			case 'boolean':
				return is_bool( $value ) || in_array( $value, [ 0, 1, '0', '1', 'true', 'false' ], true );
			case 'array':
				return is_array( $value );
			case 'object':
				return is_array( $value ) || is_object( $value );
			default:
				return true;
		}
	}

	/**
	 * Cast a parameter value to its declared type.
	 *
	 * @param mixed  $value The value to cast.
	 * @param string $type  Target type.
	 * @return mixed
	 */
	protected function cast_type( $value, string $type ) {
		switch ( $type ) {
			case 'integer':
				return intval( $value );
			case 'boolean':
				return filter_var( $value, FILTER_VALIDATE_BOOLEAN );
			case 'string':
				return strval( $value );
			default:
				return $value;
		}
	}

	/**
	 * Call a handler that uses wp_send_json (terminates with die).
	 *
	 * This method captures the JSON output by temporarily overriding
	 * the wp_die behavior to throw an exception instead of exiting.
	 *
	 * @param callable         $callback The handler to call.
	 * @param \WP_REST_Request $request  The request object.
	 * @return array<string, mixed> Decoded JSON response.
	 */
	protected function call_json_handler( callable $callback, \WP_REST_Request $request ): array {
		// Make wp_send_json use wp_die() instead of die().
		$ajax_filter = static function () {
			return true;
		};
		add_filter( 'wp_doing_ajax', $ajax_filter );

		// Override wp_die to throw an exception we can catch.
		$die_handler = static function () {
			return static function (): void {
				throw new \RuntimeException( 'wp_die_intercepted' );
			};
		};
		add_filter( 'wp_die_ajax_handler', $die_handler );

		ob_start();
		try {
			call_user_func( $callback, $request );
		} catch ( \RuntimeException $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
			// Expected - wp_die was called after wp_send_json output.
		}
		$output = ob_get_clean();

		// Clean up filters.
		remove_filter( 'wp_doing_ajax', $ajax_filter );
		remove_filter( 'wp_die_ajax_handler', $die_handler );

		$result = json_decode( (string) $output, true );

		if ( ! is_array( $result ) ) {
			return [
				'success' => false,
				'data'    => [ 'message' => __( 'Unexpected response from handler.', 'suredash' ) ],
			];
		}

		return $result;
	}

	/**
	 * Build a WP_REST_Request with nonce and POST body.
	 *
	 * @param array<string, mixed> $post_data Data to set as POST parameters.
	 * @param string               $method    HTTP method. Default 'POST'.
	 * @return \WP_REST_Request
	 */
	protected function build_request( array $post_data = [], string $method = 'POST' ): \WP_REST_Request {
		$request = new \WP_REST_Request( $method );
		$nonce   = wp_create_nonce( 'wp_rest' );
		$request->set_header( 'X-WP-Nonce', $nonce );

		foreach ( $post_data as $key => $value ) {
			$request->set_param( $key, $value );
		}

		return $request;
	}

	/**
	 * Set up $_POST globals for handlers that read from $_POST directly.
	 *
	 * @param array<string, mixed> $data Data to set in $_POST.
	 * @return void
	 */
	protected function setup_post_data( array $data ): void {
		foreach ( $data as $key => $value ) {
			$_POST[ $key ] = $value; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified in the handler.
		}
	}

	/**
	 * Clean up $_POST globals after handler execution.
	 *
	 * @param array<string> $keys Keys to remove from $_POST.
	 * @return void
	 */
	protected function cleanup_post_data( array $keys ): void {
		foreach ( $keys as $key ) {
			unset( $_POST[ $key ] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		}
	}
}
