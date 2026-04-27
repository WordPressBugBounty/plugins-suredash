<?php
/**
 * Create Group Ability.
 *
 * @package SureDash
 * @since 1.6.3
 */

namespace SureDashboard\Inc\Services\Abilities\Handlers;

use SureDashboard\Core\Routers\Backend as BackendRoute;
use SureDashboard\Inc\Services\Abilities\Ability;

defined( 'ABSPATH' ) || exit;

/**
 * Create_Group class.
 *
 * @since 1.6.3
 */
class Create_Group extends Ability {
	/**
	 * Get the unique identifier for this ability.
	 *
	 * @since 1.6.3
	 *
	 * @return string
	 */
	public function get_id(): string {
		return 'create-group';
	}

	/**
	 * Get the human-readable name.
	 *
	 * @since 1.6.3
	 *
	 * @return string
	 */
	public function get_name(): string {
		return __( 'Create Group', 'suredash' );
	}

	/**
	 * Get the ability description.
	 *
	 * @since 1.6.3
	 *
	 * @return string
	 */
	public function get_description(): string {
		return __( 'Creates a new space group (category) for organizing spaces in the sidebar navigation.', 'suredash' );
	}

	/**
	 * Get the ability category.
	 *
	 * @since 1.6.3
	 *
	 * @return string
	 */
	public function get_category(): string {
		return 'groups';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_parameters(): array {
		return [
			'category_name' => [
				'type'        => 'string',
				'required'    => true,
				'description' => __( 'Name for the new group.', 'suredash' ),
			],
			'hide_label'    => [
				'type'        => 'boolean',
				'required'    => false,
				'default'     => false,
				'description' => __( 'Whether to hide the group label in navigation.', 'suredash' ),
			],
		];
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_returns(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'term_id' => [
					'type'        => 'integer',
					'description' => __( 'ID of the created group.', 'suredash' ),
				],
				'message' => [
					'type'        => 'string',
					'description' => __( 'Success or error message.', 'suredash' ),
				],
			],
		];
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_annotations(): array {
		return [
			'readOnlyHint'    => false,
			'destructiveHint' => false,
			'idempotentHint'  => false,
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
		return 'Creates a sidebar navigation group for organizing spaces. Returns the new term_id. Use reorder-groups afterward to position it. Use the term_id as group_id when creating spaces.';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param array<string, mixed> $params Validated parameters.
	 */
	public function execute( array $params ): array {
		$hide_label = ! empty( $params['hide_label'] ) ? 'true' : 'false';

		$this->setup_post_data(
			[
				'category_name' => sanitize_text_field( $params['category_name'] ),
				'hide_label'    => $hide_label,
			]
		);

		$request = $this->build_request();
		$result  = $this->call_json_handler(
			[ BackendRoute::get_instance(), 'create_space_group' ],
			$request
		);

		$this->cleanup_post_data( [ 'category_name', 'hide_label' ] );

		return $result;
	}
}
