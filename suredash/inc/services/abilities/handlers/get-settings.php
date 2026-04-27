<?php
/**
 * Get Settings Ability.
 *
 * @package SureDash
 * @since 1.6.3
 */

namespace SureDashboard\Inc\Services\Abilities\Handlers;

use SureDashboard\Inc\Services\Abilities\Ability;
use SureDashboard\Inc\Utils\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Get_Settings class.
 *
 * @since 1.6.3
 */
class Get_Settings extends Ability {
	/**
	 * Get the unique identifier for this ability.
	 *
	 * @since 1.6.3
	 *
	 * @return string
	 */
	public function get_id(): string {
		return 'get-settings';
	}

	/**
	 * Get the human-readable name.
	 *
	 * @since 1.6.3
	 *
	 * @return string
	 */
	public function get_name(): string {
		return __( 'Get Settings', 'suredash' );
	}

	/**
	 * Get the ability description.
	 *
	 * @since 1.6.3
	 *
	 * @return string
	 */
	public function get_description(): string {
		return __( 'Retrieves current global portal settings including branding, layout, features, and access control configuration.', 'suredash' );
	}

	/**
	 * Get the ability category.
	 *
	 * @since 1.6.3
	 *
	 * @return string
	 */
	public function get_category(): string {
		return 'settings';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_parameters(): array {
		return [];
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_returns(): array {
		return [
			'type'        => 'object',
			'description' => __( 'Object containing all current portal settings.', 'suredash' ),
		];
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_annotations(): array {
		return [
			'readOnlyHint'    => true,
			'destructiveHint' => false,
			'idempotentHint'  => true,
		];
	}

	/**
	 * Get usage instructions for AI agents.
	 *
	 * @since 1.6.3
	 *
	 * @return string
	 */
	public function get_instructions(): string {
		return 'Returns all portal settings as key-value pairs. Use save-settings to update specific keys — only provided keys are changed.';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param array<string, mixed> $params Validated parameters.
	 */
	public function execute( array $params ): array {
		$settings = Settings::get_suredash_settings();

		if ( is_array( $settings ) ) {
			// Remove sensitive keys from AI/MCP-facing response.
			$sensitive_keys = [
				'google_token_id',
				'google_token_secret',
				'facebook_token_id',
				'facebook_token_secret',
				'recaptcha_site_key_v2',
				'recaptcha_secret_key_v2',
				'recaptcha_site_key_v3',
				'recaptcha_secret_key_v3',
				'turnstile_site_key',
				'turnstile_secret_key',
				'giphy_api_key',
			];

			foreach ( $sensitive_keys as $key ) {
				unset( $settings[ $key ] );
			}
		}

		return [
			'success' => true,
			'data'    => is_array( $settings ) ? $settings : [],
		];
	}
}
