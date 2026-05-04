<?php
/**
 * WP-CLI command to manage SureDash AI abilities.
 *
 * @package SureDash
 * @since 1.8.1
 */

namespace SureDashboard\Inc\Services\CLI;

use SureDashboard\Inc\Modules\MCP\Module as MCP_Module;
use WP_CLI;

defined( 'ABSPATH' ) || exit;

/**
 * Manage SureDash AI abilities from the command line.
 *
 * Writes go through `MCP_Module::save_settings()` so the same individual
 * options that ability permission callbacks read are updated — the dashboard
 * UI reads from those same options, so changes are reflected on next page load.
 *
 * @since 1.8.1
 */
class Abilities_Command {
	/**
	 * Enable SureDash abilities for AI agents.
	 *
	 * Turns on the master "Enable Abilities" toggle. By default only read
	 * abilities become available — pass --with-edit and/or --with-delete
	 * to also enable the destructive ability groups. Existing values for
	 * flags you don't pass are preserved.
	 *
	 * ## OPTIONS
	 *
	 * [--with-edit]
	 * : Also enable edit abilities (create, update, reorder, settings).
	 *
	 * [--with-delete]
	 * : Also enable delete abilities (irreversible removals).
	 *
	 * ## EXAMPLES
	 *
	 *     # Enable read-only abilities.
	 *     $ wp suredash abilities enable
	 *
	 *     # Enable read + edit abilities.
	 *     $ wp suredash abilities enable --with-edit
	 *
	 *     # Enable everything.
	 *     $ wp suredash abilities enable --with-edit --with-delete
	 *
	 * @when after_wp_load
	 *
	 * @param array<int, string>         $args       Positional arguments (unused).
	 * @param array<string, string|bool> $assoc_args Associative flags.
	 * @return void
	 */
	public function enable( array $args, array $assoc_args ): void {
		unset( $args );

		$with_edit   = (bool) WP_CLI\Utils\get_flag_value( $assoc_args, 'with-edit', false );
		$with_delete = (bool) WP_CLI\Utils\get_flag_value( $assoc_args, 'with-delete', false );

		// Read current state so unspecified flags retain their existing value.
		$settings = MCP_Module::get_settings();

		$settings['suredash_abilities_api'] = true;

		if ( $with_edit ) {
			$settings['suredash_abilities_api_edit'] = true;
		}

		if ( $with_delete ) {
			$settings['suredash_abilities_api_delete'] = true;
		}

		MCP_Module::save_settings( $settings );

		WP_CLI::log( sprintf( '  Enable Abilities:        %s', $this->yes_no( (bool) $settings['suredash_abilities_api'] ) ) );
		WP_CLI::log( sprintf( '  Enable Edit Abilities:   %s', $this->yes_no( (bool) $settings['suredash_abilities_api_edit'] ) ) );
		WP_CLI::log( sprintf( '  Enable Delete Abilities: %s', $this->yes_no( (bool) $settings['suredash_abilities_api_delete'] ) ) );

		WP_CLI::success( 'SureDash abilities enabled.' );
	}

	/**
	 * Format a boolean as a human-readable yes/no for log output.
	 *
	 * @param bool $value Boolean to format.
	 * @return string
	 */
	private function yes_no( bool $value ): string {
		return $value ? 'yes' : 'no';
	}
}
