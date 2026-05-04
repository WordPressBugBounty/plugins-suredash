<?php
/**
 * MCP Module.
 *
 * Manages MCP (Model Context Protocol) integration for SureDash — settings,
 * ability gating, and MCP Adapter server registration.
 *
 * @package SureDash
 * @since 1.7.3
 */

namespace SureDashboard\Inc\Modules\MCP;

use SureDashboard\Inc\Traits\Get_Instance;
use SureDashboard\Inc\Utils\Helper;
use SureDashboard\Inc\Utils\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Module class.
 *
 * @since 1.7.3
 */
class Module {
	use Get_Instance;

	/**
	 * Constructor.
	 *
	 * @since 1.7.3
	 */
	public function __construct() {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );

		// Register MCP server with MCP Adapter plugin when enabled.
		if ( self::mcp_adapter_enabled() ) {
			add_action( 'mcp_adapter_init', [ $this, 'register_mcp_server' ] );
		}
	}

	/**
	 * Check if MCP Adapter is available and the MCP server toggle is enabled.
	 *
	 * @since 1.7.3
	 * @return bool
	 */
	public static function mcp_adapter_enabled(): bool {
		return function_exists( 'wp_register_ability' )
			&& class_exists( 'WP\MCP\Plugin' )
			&& (bool) Helper::get_option( 'suredash_mcp_server', false );
	}

	/**
	 * Get MCP Adapter plugin installation status.
	 *
	 * @since 1.7.3
	 * @return string 'active', 'installed', or 'not_installed'.
	 */
	public static function get_adapter_status(): string {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$plugin_file = 'mcp-adapter/mcp-adapter.php';

		if ( ! file_exists( WP_PLUGIN_DIR . '/' . $plugin_file ) ) {
			return 'not_installed';
		}

		return is_plugin_active( $plugin_file ) ? 'active' : 'installed';
	}

	/**
	 * Get current MCP settings.
	 *
	 * Reads from the unified `portal_settings` array — same source the rest of
	 * the dashboard reads through `Helper::get_option()`, so there's nowhere
	 * for the values to drift.
	 *
	 * @since 1.7.3
	 * @return array<string, bool>
	 */
	public static function get_settings(): array {
		return [
			'suredash_abilities_api'        => (bool) Helper::get_option( 'suredash_abilities_api', false ),
			'suredash_abilities_api_edit'   => (bool) Helper::get_option( 'suredash_abilities_api_edit', false ),
			'suredash_abilities_api_delete' => (bool) Helper::get_option( 'suredash_abilities_api_delete', false ),
			'suredash_mcp_server'           => (bool) Helper::get_option( 'suredash_mcp_server', false ),
		];
	}

	/**
	 * Save MCP settings into the unified `portal_settings` array.
	 *
	 * Writes all four MCP keys in a single DB round-trip.
	 *
	 * @since 1.7.3
	 * @param array<string, mixed> $settings Settings to save.
	 * @return void
	 */
	public static function save_settings( array $settings ): void {
		$portal_settings = Settings::get_settings();
		if ( ! is_array( $portal_settings ) ) {
			$portal_settings = [];
		}

		$portal_settings['suredash_abilities_api']        = ! empty( $settings['suredash_abilities_api'] );
		$portal_settings['suredash_abilities_api_edit']   = ! empty( $settings['suredash_abilities_api_edit'] );
		$portal_settings['suredash_abilities_api_delete'] = ! empty( $settings['suredash_abilities_api_delete'] );
		$portal_settings['suredash_mcp_server']           = ! empty( $settings['suredash_mcp_server'] );

		update_option( SUREDASHBOARD_SETTINGS, $portal_settings );

		// Drop the static cache so subsequent Helper::get_option() reads see fresh data.
		Settings::$dashboard_options = [];
	}

	/**
	 * Register REST routes for MCP settings.
	 *
	 * @since 1.7.3
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			'suredash/v1',
			'/mcp-settings',
			[
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_settings_endpoint' ],
					'permission_callback' => [ $this, 'admin_permission_check' ],
				],
				[
					'methods'             => \WP_REST_Server::EDITABLE,
					'callback'            => [ $this, 'save_settings_endpoint' ],
					'permission_callback' => [ $this, 'admin_permission_check' ],
				],
			]
		);
	}

	/**
	 * Permission check — require manage_options.
	 *
	 * @since 1.7.3
	 * @return bool
	 */
	public function admin_permission_check(): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * GET endpoint — return MCP settings.
	 *
	 * @since 1.7.3
	 * @return \WP_REST_Response
	 */
	public function get_settings_endpoint(): \WP_REST_Response {
		return new \WP_REST_Response(
			[
				'success' => true,
				'data'    => self::get_settings(),
			],
			200
		);
	}

	/**
	 * POST endpoint — save MCP settings.
	 *
	 * @since 1.7.3
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function save_settings_endpoint( \WP_REST_Request $request ): \WP_REST_Response {
		$settings = $request->get_json_params();

		if ( ! is_array( $settings ) ) {
			return new \WP_REST_Response(
				[
					'success' => false,
					'message' => __( 'Invalid settings data.', 'suredash' ),
				],
				400
			);
		}

		self::save_settings( $settings );

		return new \WP_REST_Response(
			[
				'success' => true,
				'message' => __( 'MCP settings saved successfully.', 'suredash' ),
				'data'    => self::get_settings(),
			],
			200
		);
	}

	/**
	 * Register MCP server with the MCP Adapter plugin.
	 *
	 * Hooked to 'mcp_adapter_init'. Collects all suredash/* abilities
	 * and creates an HTTP-based MCP server endpoint.
	 *
	 * @since 1.7.3
	 * @param object $adapter The MCP Adapter instance.
	 * @return void
	 */
	public function register_mcp_server( $adapter ): void {
		if ( ! function_exists( 'wp_get_abilities' ) ) {
			return;
		}

		$abilities = wp_get_abilities();
		$tools     = [];

		foreach ( $abilities as $ability ) {
			if ( str_starts_with( $ability->get_name(), 'suredash/' ) ) {
				$tools[] = $ability->get_name();
			}
		}

		// Also include Pro abilities if available.
		foreach ( $abilities as $ability ) {
			if ( str_starts_with( $ability->get_name(), 'suredash-pro/' ) ) {
				$tools[] = $ability->get_name();
			}
		}

		if ( empty( $tools ) ) {
			return;
		}

		$transport_class = class_exists( '\WP\MCP\Transport\HttpTransport' )
			? 'WP\MCP\Transport\HttpTransport'
			: 'WP\MCP\Transport\Http\RestTransport';

		// @phpstan-ignore-next-line — $adapter is provided by MCP Adapter plugin at runtime.
		$adapter->create_server(
			'suredash',
			'suredash/v1',
			'mcp',
			__( 'SureDash MCP Server', 'suredash' ),
			__( 'SureDash MCP Server for community portal management — spaces, groups, posts, users, settings, and analytics.', 'suredash' ),
			defined( 'SUREDASHBOARD_VER' ) ? SUREDASHBOARD_VER : '1.0.0',
			[ $transport_class ],
			'WP\MCP\Infrastructure\ErrorHandling\ErrorLogMcpErrorHandler',
			'WP\MCP\Infrastructure\Observability\NullMcpObservabilityHandler',
			$tools,
			[],
			[]
		);
	}
}
