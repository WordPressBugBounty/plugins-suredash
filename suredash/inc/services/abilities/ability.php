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
	 * Get deprecated parameter aliases for this ability.
	 *
	 * Maps a canonical parameter name to the deprecated alias names that are
	 * also accepted on input. Aliases are exposed in the input schema (so the
	 * Abilities API does not reject them) and normalized to the canonical key
	 * before validation. This lets abilities standardize on a single argument
	 * name (e.g. `space_id`) without breaking callers that still send an older
	 * name such as `post_id` or `content_id`.
	 *
	 * @since 1.9.3
	 * @return array<string, array<int, string>> Map of canonical name => list of alias names.
	 */
	public function get_aliases(): array {
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
	 * Format: suredash/{ability-id}. The result is forced to lowercase so it
	 * always satisfies the WP Abilities API contract (which requires
	 * lowercase, non-empty strings) regardless of how a subclass formats its
	 * `get_id()` output.
	 *
	 * @return lowercase-string&non-falsy-string
	 */
	public function get_wp_ability_name(): string {
		return strtolower( 'suredash/' . $this->get_id() );
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

			// Pass the constraints validate() enforces through to the advertised
			// schema, so an agent can see the rule instead of only discovering it
			// from the rejection message.
			foreach ( [ 'minimum', 'maximum', 'format', 'items' ] as $keyword ) {
				if ( isset( $definition[ $keyword ] ) ) {
					$property[ $keyword ] = $definition[ $keyword ];
				}
			}

			$properties[ $key ] = $property;

			if ( ! empty( $definition['required'] ) ) {
				$required[] = $key;
			}
		}

		// Expose deprecated aliases as optional properties so the Abilities API
		// schema layer (additionalProperties: false) accepts them. A canonical
		// key that has aliases is dropped from the JSON Schema `required` list —
		// an alias may satisfy it — and the requirement is enforced instead by
		// validate() after normalize_aliases() runs.
		foreach ( $this->get_aliases() as $canonical => $alias_names ) {
			if ( ! isset( $properties[ $canonical ] ) ) {
				continue;
			}

			$required = array_values( array_diff( $required, [ $canonical ] ) );

			foreach ( $alias_names as $alias ) {
				$alias_property                = $properties[ $canonical ];
				$alias_property['description'] = sprintf(
					/* translators: %s: canonical parameter name */
					__( 'Deprecated alias for "%s"; accepted for backward compatibility. Prefer the canonical name.', 'suredash' ),
					$canonical
				);
				$properties[ $alias ] = $alias_property;
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
		$input  = $this->normalize_aliases( $input );
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

			/* translators: %s: parameter name. */
			$label  = sprintf( __( 'Parameter "%s"', 'suredash' ), $key );
			$errors = array_merge( $errors, $this->get_constraint_errors( $label, $value, $definition ) );

			// Array item checking — the shape of each entry in an array param.
			if ( is_array( $value ) && ! empty( $definition['items'] ) ) {
				$errors = array_merge( $errors, $this->get_item_errors( $key, $value, $definition['items'] ) );
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
	 * Normalize deprecated parameter aliases to their canonical keys.
	 *
	 * For each canonical => aliases mapping from get_aliases(): when the
	 * canonical key is absent (or empty) but an alias is provided, the alias
	 * value is copied to the canonical key. Alias keys are always removed from
	 * the returned array so the canonical name is the single source of truth
	 * downstream. The canonical value wins if both are sent.
	 *
	 * @since 1.9.3
	 *
	 * @param array<string, mixed> $input Raw input parameters.
	 * @return array<string, mixed> Parameters keyed by canonical names.
	 */
	public function normalize_aliases( array $input ): array {
		foreach ( $this->get_aliases() as $canonical => $alias_names ) {
			$has_canonical = isset( $input[ $canonical ] ) && $input[ $canonical ] !== '';

			foreach ( $alias_names as $alias ) {
				if ( ! isset( $input[ $alias ] ) ) {
					continue;
				}

				if ( ! $has_canonical ) {
					$input[ $canonical ] = $input[ $alias ];
					$has_canonical       = true;
				}

				unset( $input[ $alias ] );
			}
		}

		return $input;
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
	 * Validate the declarative constraints on a single value.
	 *
	 * Schemas across the abilities already advertise minimum, maximum and
	 * value shapes in their descriptions, but validate() only ever enforced
	 * required/type/enum — so handlers re-checked ranges and date/time/URL
	 * formats by hand. Declaring the keyword is now enough.
	 *
	 * Supported keywords: minimum, maximum, and format ("date", "time", "uri").
	 *
	 * @since 1.10.2
	 *
	 * @param string               $label      Human-readable name for the value, used in messages.
	 * @param mixed                $value      Provided value.
	 * @param array<string, mixed> $definition Schema definition for the value.
	 * @return array<int, string> Problems found, empty when valid.
	 */
	protected function get_constraint_errors( string $label, $value, array $definition ): array {
		$errors = [];

		if ( is_numeric( $value ) ) {
			if ( isset( $definition['minimum'] ) && (float) $value < (float) $definition['minimum'] ) {
				$errors[] = sprintf(
					/* translators: 1: value label, 2: minimum allowed, 3: provided value. */
					__( '%1$s must be %2$s or higher, got %3$s.', 'suredash' ),
					$label,
					(string) $definition['minimum'],
					(string) $value
				);
			}

			if ( isset( $definition['maximum'] ) && (float) $value > (float) $definition['maximum'] ) {
				$errors[] = sprintf(
					/* translators: 1: value label, 2: maximum allowed, 3: provided value. */
					__( '%1$s must be %2$s or lower, got %3$s.', 'suredash' ),
					$label,
					(string) $definition['maximum'],
					(string) $value
				);
			}
		}

		$format = (string) ( $definition['format'] ?? '' );

		// Empty strings clear a field rather than set a malformed one, so they
		// are left to the handler's own required/emptiness rules.
		if ( $format === '' || ! is_string( $value ) || $value === '' ) {
			return $errors;
		}

		if ( ! $this->matches_format( $value, $format ) ) {
			$errors[] = sprintf(
				/* translators: 1: value label, 2: expected shape, 3: provided value. */
				__( '%1$s must be %2$s, got "%3$s".', 'suredash' ),
				$label,
				$this->get_format_hint( $format ),
				$value
			);
		}

		return $errors;
	}

	/**
	 * Validate the shape of every entry in an array parameter.
	 *
	 * Recurses through nested "items" definitions, so a schema can require a
	 * key several levels down (quiz_questions → options → is_correct) and have
	 * it actually enforced instead of only described.
	 *
	 * @since 1.10.2
	 *
	 * @param string               $label  Human-readable name for the array, used in messages.
	 * @param array<int, mixed>    $values Provided array.
	 * @param array<string, mixed> $items  The schema's "items" definition.
	 * @return array<int, string> Problems found, empty when valid.
	 */
	protected function get_item_errors( string $label, array $values, array $items ): array {
		$errors        = [];
		$item_type     = (string) ( $items['type'] ?? 'string' );
		$required_keys = is_array( $items['required'] ?? null ) ? $items['required'] : [];
		$properties    = is_array( $items['properties'] ?? null ) ? $items['properties'] : [];

		foreach ( array_values( $values ) as $index => $item ) {
			/* translators: 1: array label, 2: item position. */
			$item_label = sprintf( __( '%1$s item %2$d', 'suredash' ), $label, $index + 1 );

			if ( $item_type !== 'object' ) {
				if ( ! $this->check_type( $item, $item_type ) ) {
					$errors[] = sprintf(
						/* translators: 1: item label, 2: expected type, 3: provided value. */
						__( '%1$s must be of type %2$s, got "%3$s".', 'suredash' ),
						$item_label,
						$item_type,
						is_scalar( $item ) ? (string) $item : gettype( $item )
					);
					continue;
				}

				$errors = array_merge( $errors, $this->get_constraint_errors( $item_label, $item, $items ) );
				continue;
			}

			if ( ! is_array( $item ) ) {
				$errors[] = sprintf(
					/* translators: %s: item label. */
					__( '%s must be an object.', 'suredash' ),
					$item_label
				);
				continue;
			}

			foreach ( $required_keys as $required_key ) {
				if ( ! array_key_exists( $required_key, $item ) ) {
					$errors[] = sprintf(
						/* translators: 1: item label, 2: missing key name. */
						__( '%1$s is missing the required "%2$s" key.', 'suredash' ),
						$item_label,
						$required_key
					);
				}
			}

			foreach ( $properties as $property => $definition ) {
				if ( ! isset( $item[ $property ] ) || ! is_array( $definition ) ) {
					continue;
				}

				/* translators: 1: item label, 2: property name. */
				$property_label = sprintf( __( '%1$s "%2$s"', 'suredash' ), $item_label, $property );
				$property_type  = (string) ( $definition['type'] ?? 'string' );

				if ( ! $this->check_type( $item[ $property ], $property_type ) ) {
					$errors[] = sprintf(
						/* translators: 1: property label, 2: expected type. */
						__( '%1$s must be of type %2$s.', 'suredash' ),
						$property_label,
						$property_type
					);
					continue;
				}

				$errors = array_merge( $errors, $this->get_constraint_errors( $property_label, $item[ $property ], $definition ) );

				if ( is_array( $item[ $property ] ) && ! empty( $definition['items'] ) ) {
					$errors = array_merge( $errors, $this->get_item_errors( $property_label, $item[ $property ], $definition['items'] ) );
				}
			}
		}

		return $errors;
	}

	/**
	 * Check a value against a supported schema "format".
	 *
	 * @since 1.10.2
	 *
	 * @param string $value  Value to check.
	 * @param string $format One of: date, time, uri.
	 * @return bool True when the format is unknown (nothing to enforce) or the value matches.
	 */
	protected function matches_format( string $value, string $format ): bool {
		switch ( $format ) {
			case 'date':
				return $this->is_valid_date( $value );
			case 'time':
				return $this->is_valid_time( $value );
			case 'uri':
				return $this->is_valid_link_url( $value );
			default:
				return true;
		}
	}

	/**
	 * Describe a format so the error message tells the agent what to send.
	 *
	 * @since 1.10.2
	 *
	 * @param string $format One of: date, time, uri.
	 * @return string
	 */
	protected function get_format_hint( string $format ): string {
		switch ( $format ) {
			case 'date':
				return __( 'a real calendar date in YYYY-MM-DD format (e.g. "2026-07-23")', 'suredash' );
			case 'time':
				return __( 'a time in HH:MM 24-hour format (e.g. "14:30")', 'suredash' );
			case 'uri':
				return __( 'a full http(s) URL (e.g. "https://example.com")', 'suredash' );
			default:
				return $format;
		}
	}

	/**
	 * Check a value is a real calendar date in YYYY-MM-DD format.
	 *
	 * Rejects the natural language dates agents send verbatim ("next Tuesday",
	 * "23 July 2026") and impossible dates like 2026-02-30.
	 *
	 * @since 1.10.2
	 *
	 * @param string $date Value to check.
	 * @return bool
	 */
	protected function is_valid_date( string $date ): bool {
		if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', trim( $date ), $parts ) ) {
			return false;
		}

		return checkdate( (int) $parts[2], (int) $parts[3], (int) $parts[1] );
	}

	/**
	 * Check a value is a HH:MM 24-hour time.
	 *
	 * @since 1.10.2
	 *
	 * @param string $time Value to check.
	 * @return bool
	 */
	protected function is_valid_time( string $time ): bool {
		return (bool) preg_match( '/^([01]\d|2[0-3]):[0-5]\d$/', trim( $time ) );
	}

	/**
	 * Check that an event's end is not before its start.
	 *
	 * The only schedule rule a schema cannot express, so both the create and
	 * update paths share it. A missing start time counts as the start of the
	 * day and a missing end time as the end of it, so a same-day event with
	 * only a start time is never rejected.
	 *
	 * @since 1.10.2
	 *
	 * @param string $start_date Start date (YYYY-MM-DD), empty when unknown.
	 * @param string $start_time Start time (HH:MM), empty when unknown.
	 * @param string $end_date   End date (YYYY-MM-DD), empty to fall back to the start date.
	 * @param string $end_time   End time (HH:MM), empty when unknown.
	 * @return string|null Problem description, or null when the order is valid or unknowable.
	 */
	protected function get_schedule_order_error( string $start_date, string $start_time, string $end_date, string $end_time ): ?string {
		$start_date = trim( $start_date );
		$end_date   = trim( $end_date ) !== '' ? trim( $end_date ) : $start_date;

		if ( $start_date === '' || $end_date === '' ) {
			return null;
		}

		$start_label = trim( $start_date . ' ' . trim( $start_time ) );
		$end_label   = trim( $end_date . ' ' . trim( $end_time ) );
		$start       = strtotime( $start_date . ' ' . ( trim( $start_time ) !== '' ? trim( $start_time ) : '00:00' ) );
		$end         = strtotime( $end_date . ' ' . ( trim( $end_time ) !== '' ? trim( $end_time ) : '23:59' ) );

		if ( $start === false || $end === false || $end >= $start ) {
			return null;
		}

		return sprintf(
			/* translators: 1: event end date and time, 2: event start date and time. */
			__( 'The event would end (%1$s) before it starts (%2$s). Adjust event_end_date/event_end_time or event_date/event_start_time.', 'suredash' ),
			$end_label,
			$start_label
		);
	}

	/**
	 * JSON-encode a payload for a router that expects slashed input.
	 *
	 * Over HTTP, WordPress slashes everything in $_POST, so routers correctly
	 * call wp_unslash() before json_decode(). Abilities bypass HTTP and set
	 * $_POST directly, so the JSON arrived unslashed and wp_unslash() stripped
	 * the escapes out of it: {"t":"say \"hi\""} became {"t":"say "hi""},
	 * which is not valid JSON, so the payload decoded to null and the call
	 * failed with a generic error. Any title or content holding a double quote,
	 * a backslash, or an HTML attribute hit this.
	 *
	 * Slashing here makes the payload identical to what a real request delivers.
	 *
	 * @since 1.11.1
	 *
	 * @param mixed $data Payload to encode.
	 * @return string Slashed JSON, safe to hand to a router that unslashes.
	 */
	protected function encode_json_for_router( $data ): string {
		return wp_slash( (string) wp_json_encode( $data ) );
	}

	/**
	 * Build the standard hard-fail response.
	 *
	 * Every guard returns the same envelope, so agents parse one shape and the
	 * guards themselves stay one line each.
	 *
	 * @since 1.10.2
	 *
	 * @param string             $message Why the call was rejected and what to do next.
	 * @param array<int, string> $errors Optional per-item detail.
	 * @return array<string, mixed>
	 */
	protected function fail( string $message, array $errors = [] ): array {
		$data = [ 'message' => $message ];

		if ( ! empty( $errors ) ) {
			$data['errors'] = $errors;
		}

		return [
			'success' => false,
			'data'    => $data,
		];
	}

	/**
	 * Validate that quiz questions have properly marked correct answers.
	 *
	 * A scored quiz is unusable when a question has no correct option, and a
	 * "single" type question with several correct options contradicts its own
	 * type — both are silent authoring mistakes (typically from AI agents
	 * omitting is_correct), so callers surface these as hard errors when the
	 * quiz is scored and as warnings otherwise.
	 *
	 * @since 1.10.2
	 *
	 * @param array<int, mixed> $questions Quiz questions payload (pre-sanitize shape).
	 * @return array<int, string> Human-readable problems, empty when valid.
	 */
	protected function get_quiz_answer_errors( array $questions ): array {
		$problems = [];

		foreach ( array_values( $questions ) as $index => $question ) {
			if ( ! is_array( $question ) ) {
				continue;
			}

			$options       = is_array( $question['options'] ?? null ) ? $question['options'] : [];
			$correct_count = 0;

			foreach ( $options as $option ) {
				if ( is_array( $option ) && filter_var( $option['is_correct'] ?? false, FILTER_VALIDATE_BOOLEAN ) ) {
					$correct_count++;
				}
			}

			$type  = ( $question['type'] ?? 'single' ) === 'multiple' ? 'multiple' : 'single';
			$label = sanitize_text_field( (string) ( $question['text'] ?? '' ) );
			$label = $label !== '' ? '"' . $label . '"' : __( '(untitled)', 'suredash' );

			if ( $correct_count === 0 ) {
				$problems[] = sprintf(
					/* translators: 1: question number, 2: question text. */
					__( 'Question %1$d %2$s has no option marked is_correct: true. Mark the correct answer(s) and resend.', 'suredash' ),
					$index + 1,
					$label
				);
			} elseif ( $type === 'single' && $correct_count > 1 ) {
				$problems[] = sprintf(
					/* translators: 1: question number, 2: question text, 3: count of options marked correct. */
					__( 'Question %1$d %2$s is type "single" but has %3$d options marked is_correct: true. Mark exactly one, or change the type to "multiple".', 'suredash' ),
					$index + 1,
					$label,
					$correct_count
				);
			}
		}

		return $problems;
	}

	/**
	 * Summarize quiz questions so agents can self-verify the saved answer key.
	 *
	 * @since 1.10.2
	 *
	 * @param array<int, mixed> $questions Sanitized quiz questions.
	 * @return array<int, array<string, mixed>> Per-question text, option and correct counts.
	 */
	protected function summarize_quiz_questions( array $questions ): array {
		$summary = [];

		foreach ( $questions as $question ) {
			if ( ! is_array( $question ) ) {
				continue;
			}

			$options = is_array( $question['options'] ?? null ) ? $question['options'] : [];
			// Same truthiness test as get_quiz_answer_errors(), so the echoed
			// count can never disagree with what validation just accepted.
			$correct = array_filter(
				$options,
				static function ( $option ) {
					return is_array( $option ) && filter_var( $option['is_correct'] ?? false, FILTER_VALIDATE_BOOLEAN );
				}
			);

			$summary[] = [
				'text'          => (string) ( $question['text'] ?? '' ),
				'type'          => (string) ( $question['type'] ?? 'single' ),
				'option_count'  => count( $options ),
				'correct_count' => count( $correct ),
			];
		}

		return $summary;
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
	 * Verify that a post exists and matches the expected post type.
	 *
	 * AI agents routinely pass IDs of the wrong object (a space ID to a post
	 * ability, a lesson ID to delete-space, …). The underlying routers often
	 * reject these with a generic message or, worse, act on the wrong object —
	 * so abilities guard the target up front and name the actual type.
	 *
	 * @since 1.10.2
	 *
	 * @param int    $post_id       Post ID to verify.
	 * @param string $expected_type Expected post type slug.
	 * @param string $label         Human-readable label for the target, e.g. 'Space'.
	 * @return array<string, mixed>|null Hard-fail response array, or null when the target is valid.
	 */
	protected function get_post_target_error( int $post_id, string $expected_type, string $label ): ?array {
		$post = get_post( $post_id );

		if ( ! $post ) {
			return $this->fail(
				sprintf(
					/* translators: 1: target label (e.g. Space), 2: post ID. */
					__( '%1$s ID %2$d does not exist.', 'suredash' ),
					$label,
					$post_id
				)
			);
		}

		if ( $post->post_type !== $expected_type ) {
			return $this->fail(
				sprintf(
					/* translators: 1: target label (e.g. Space), 2: post ID, 3: actual post type, 4: expected post type. */
					__( '%1$s ID %2$d is a "%3$s" post, not "%4$s". Check the ID and use the ability that matches this object type.', 'suredash' ),
					$label,
					$post_id,
					$post->post_type,
					$expected_type
				)
			);
		}

		return null;
	}

	/**
	 * Verify that a term exists and belongs to the expected taxonomy.
	 *
	 * The term counterpart of get_post_target_error(). Routers hand any ID
	 * straight to wp_delete_term()/term meta, so a term from another taxonomy
	 * either fails with a generic message or touches the wrong object.
	 *
	 * @since 1.10.2
	 *
	 * @param int    $term_id           Term ID to verify.
	 * @param string $expected_taxonomy Expected taxonomy slug.
	 * @param string $label             Human-readable label for the target, e.g. 'Group'.
	 * @return array<string, mixed>|null Hard-fail response array, or null when the target is valid.
	 */
	protected function get_term_target_error( int $term_id, string $expected_taxonomy, string $label ): ?array {
		if ( get_term( $term_id, $expected_taxonomy ) instanceof \WP_Term ) {
			return null;
		}

		$other_term = get_term( $term_id );

		if ( $other_term instanceof \WP_Term ) {
			return $this->fail(
				sprintf(
					/* translators: 1: target label (e.g. Group), 2: term ID, 3: actual taxonomy, 4: expected taxonomy. */
					__( '%1$s ID %2$d is a "%3$s" term, not a "%4$s" term. Check the ID with list-groups.', 'suredash' ),
					$label,
					$term_id,
					$other_term->taxonomy,
					$expected_taxonomy
				)
			);
		}

		return $this->fail(
			sprintf(
				/* translators: 1: target label (e.g. Group), 2: term ID. */
				__( '%1$s ID %2$d does not exist. Call list-groups to get valid IDs.', 'suredash' ),
				$label,
				$term_id
			)
		);
	}

	/**
	 * Verify a reorder payload holds exactly the expected set of IDs.
	 *
	 * Reorder operations replace the stored sequence in full, so a partial
	 * payload silently drops the omitted items and an unknown ID silently
	 * corrupts the order. Both reorder abilities share this check.
	 *
	 * @since 1.10.2
	 *
	 * @param array<int, int> $payload_ids  IDs the agent sent.
	 * @param array<int, int> $expected_ids IDs that must all be present.
	 * @param string          $label        Plural noun for the items, e.g. 'groups'.
	 * @param string          $source       Ability to call for the full set, e.g. 'list-groups'.
	 * @return array<int, string> Problems found, empty when the sets match.
	 */
	protected function get_id_set_errors( array $payload_ids, array $expected_ids, string $label, string $source ): array {
		$errors  = [];
		$missing = array_values( array_diff( $expected_ids, $payload_ids ) );
		$unknown = array_values( array_diff( $payload_ids, $expected_ids ) );

		if ( ! empty( $missing ) ) {
			$errors[] = sprintf(
				/* translators: 1: plural item noun, 2: comma-separated IDs, 3: ability name to call. */
				__( 'Payload omits %1$s: %2$s. Include ALL of them. Call %3$s to get the full set.', 'suredash' ),
				$label,
				implode( ', ', $missing ),
				$source
			);
		}

		if ( ! empty( $unknown ) ) {
			$errors[] = sprintf(
				/* translators: 1: comma-separated IDs, 2: plural item noun. */
				__( 'Unknown IDs %1$s are not %2$s.', 'suredash' ),
				implode( ', ', $unknown ),
				$label
			);
		}

		return $errors;
	}

	/**
	 * Run a callback while capturing the ID passed to an action hook.
	 *
	 * Several routers only return rendered HTML or a message, so the ID of the
	 * thing they just created is only available through their action hook.
	 *
	 * @since 1.10.2
	 *
	 * @param string   $hook Action hook whose first argument is the new ID.
	 * @param callable $run  Callback that triggers the hook.
	 * @return array{result: mixed, id: int} The callback's return value and the captured ID (0 when the hook never fired).
	 */
	protected function capture_id_from_action( string $hook, callable $run ): array {
		$captured = 0;

		$capture = static function ( $id ) use ( &$captured ): void {
			$captured = absint( $id );
		};

		add_action( $hook, $capture );
		$result = $run();
		remove_action( $hook, $capture );

		return [
			'result' => $result,
			'id'     => $captured,
		];
	}

	/**
	 * Point a single_post space at a WordPress post or page.
	 *
	 * Three metas decide what a single_post space renders, and the render path
	 * (Single_Post::get_integration_content) reads post_render_type and wp_post,
	 * NOT single_post_id. Writing single_post_id alone leaves post_render_type
	 * at its "blank" default, so the space renders empty while the ability
	 * reports success. Always set the target through this method.
	 *
	 * @since 1.10.2
	 *
	 * @param int $space_id  Space post ID.
	 * @param int $target_id Post/page ID the space should display.
	 * @return void
	 */
	protected function set_single_post_target( int $space_id, int $target_id ): void {
		sd_update_post_meta( $space_id, 'post_render_type', 'wordpress' );
		sd_update_post_meta( $space_id, 'single_post_id', $target_id );
		sd_update_post_meta(
			$space_id,
			'wp_post',
			[
				'value' => $target_id,
				'label' => (string) get_the_title( $target_id ),
			]
		);
	}

	/**
	 * Check that a value is a usable external link destination.
	 *
	 * Sanitizing with esc_url_raw() alone is too permissive here: it turns junk
	 * like "not a url" into "http://not%20a%20url" (non-empty), so an emptiness
	 * check lets invalid input through and a broken link space still saves.
	 * A real destination needs an http/https scheme and a host.
	 *
	 * @since 1.10.2
	 *
	 * @param string $value Raw link URL as provided.
	 * @return bool True when the value is a valid http/https URL.
	 */
	protected function is_valid_link_url( string $value ): bool {
		$parts  = wp_parse_url( trim( $value ) );
		$scheme = strtolower( (string) ( $parts['scheme'] ?? '' ) );
		$host   = (string) ( $parts['host'] ?? '' );

		return in_array( $scheme, [ 'http', 'https' ], true ) && $host !== '';
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
