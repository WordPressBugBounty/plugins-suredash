<?php
/**
 * Abilities Registry.
 *
 * Central registry for all available abilities. Provides registration,
 * lookup, and discovery functionality.
 *
 * @package SureDash
 * @since 1.6.3
 */

namespace SureDashboard\Inc\Services\Abilities;

use SureDashboard\Inc\Traits\Get_Instance;

defined( 'ABSPATH' ) || exit;

/**
 * Registry class.
 *
 * @since 1.6.3
 */
class Registry {
	use Get_Instance;

	/**
	 * Registered abilities.
	 *
	 * @var array<string, Ability>
	 */
	private $abilities = [];

	/**
	 * Whether built-in abilities have been registered.
	 *
	 * @var bool
	 */
	private $initialized = false;

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'rest_api_init', [ $this, 'maybe_init' ], 5 );

		// WordPress Abilities API integration.
		add_action( 'wp_abilities_api_categories_init', [ $this, 'register_wp_ability_category' ] );
		add_action( 'wp_abilities_api_init', [ $this, 'register_wp_abilities' ] );
	}

	/**
	 * Initialize abilities if not already done.
	 *
	 * @return void
	 */
	public function maybe_init(): void {
		if ( $this->initialized ) {
			return;
		}

		$this->initialized = true;
		$this->register_built_in_abilities();

		/**
		 * Fires after built-in abilities are registered.
		 *
		 * Use this action to register custom abilities from pro addons or third-party plugins.
		 *
		 * @since 1.6.3
		 * @param Registry $registry The abilities registry instance.
		 */
		do_action( 'suredash_register_abilities', $this );
	}

	/**
	 * Register a single ability.
	 *
	 * @param Ability $ability The ability to register.
	 * @return void
	 */
	public function register( Ability $ability ): void {
		$this->abilities[ $ability->get_id() ] = $ability;
	}

	/**
	 * Get an ability by ID.
	 *
	 * @param string $id The ability ID.
	 * @return Ability|null
	 */
	public function get( string $id ): ?Ability {
		return $this->abilities[ $id ] ?? null;
	}

	/**
	 * Get all registered abilities.
	 *
	 * @return array<string, Ability>
	 */
	public function get_all(): array {
		return $this->abilities;
	}

	/**
	 * Check if an ability is registered.
	 *
	 * @param string $id The ability ID.
	 * @return bool
	 */
	public function has( string $id ): bool {
		return isset( $this->abilities[ $id ] );
	}

	/**
	 * Register the SureDash ability category with WordPress Abilities API.
	 *
	 * Hooked to 'wp_abilities_api_categories_init'.
	 *
	 * @return void
	 */
	public function register_wp_ability_category(): void {
		if ( ! function_exists( 'wp_register_ability_category' ) ) {
			return;
		}

		wp_register_ability_category(
			'suredash',
			[
				'label'       => __( 'SureDash', 'suredash' ),
				'description' => __( 'Community portal management abilities — spaces, groups, posts, users, settings, and analytics.', 'suredash' ),
			]
		);
	}

	/**
	 * Register all SureDash abilities with WordPress Abilities API.
	 *
	 * Hooked to 'wp_abilities_api_init'.
	 *
	 * @return void
	 */
	public function register_wp_abilities(): void {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		// Master toggle — don't register any abilities when disabled.
		if ( ! get_option( 'suredash_abilities_api', false ) ) {
			return;
		}

		// Ensure our internal registry is initialized first.
		$this->maybe_init();

		foreach ( $this->abilities as $ability ) {
			// Skip abilities disabled by per-ability gate (edit/delete toggles).
			if ( ! $ability->is_enabled() ) {
				continue;
			}
			$args = [
				'label'               => $ability->get_label(),
				'description'         => $ability->get_wp_description(),
				'category'            => 'suredash',
				'input_schema'        => $ability->get_input_schema(),
				'execute_callback'    => [ $ability, 'handle_execute' ],
				'permission_callback' => [ $ability, 'check_permission' ],
			];

			$meta = [
				'show_in_rest' => true,
				'mcp'          => [
					'public' => true,
					'type'   => 'tool',
				],
			];

			$annotations = $ability->get_annotations();
			if ( ! empty( $annotations ) ) {
				// Map MCP annotation keys to WP Abilities API keys.
				$wp_annotations = [];
				$key_map        = [
					'readOnlyHint'    => 'readonly',
					'destructiveHint' => 'destructive',
					'idempotentHint'  => 'idempotent',
				];

				foreach ( $annotations as $key => $value ) {
					$wp_annotations[ $key_map[ $key ] ?? $key ] = $value;
				}

				$meta['annotations'] = $wp_annotations;
			}

			$args['meta'] = $meta;

			wp_register_ability( $ability->get_wp_ability_name(), $args );
		}
	}

	/**
	 * Register all built-in abilities.
	 *
	 * @return void
	 */
	private function register_built_in_abilities(): void {
		$abilities = [
			// Spaces.
			new Handlers\Create_Space(),
			new Handlers\Delete_Space(),
			new Handlers\Update_Space_Settings(),
			new Handlers\Get_Space_Meta(),
			new Handlers\List_Spaces(),
			new Handlers\Create_Post_For_Space(),
			new Handlers\Update_Content_Settings(),
			new Handlers\List_WP_Posts(),

			// Groups.
			new Handlers\List_Groups(),
			new Handlers\Create_Group(),
			new Handlers\Update_Group(),
			new Handlers\Delete_Group(),
			new Handlers\Reorder_Groups(),
			new Handlers\Reorder_Spaces(),

			// Community Content.
			new Handlers\Content_Action(),
			new Handlers\Create_Post(),
			new Handlers\Edit_Post(),
			new Handlers\Delete_Post(),
			new Handlers\Create_Comment(),
			new Handlers\List_Posts(),
			new Handlers\React_To_Entity(),

			// Users.
			new Handlers\List_Users(),
			new Handlers\Get_User(),

			// Settings.
			new Handlers\Save_Settings(),
			new Handlers\Get_Settings(),

			// Analytics.
			new Handlers\Get_Dashboard_Stats(),
			new Handlers\Get_Member_Stats(),
			new Handlers\Community_Insights(),
		];

		foreach ( $abilities as $ability ) {
			$this->register( $ability );
		}
	}
}
