<?php
/**
 * Update Group Ability.
 *
 * @package SureDash
 * @since 1.6.3
 */

namespace SureDashboard\Inc\Services\Abilities\Handlers;

use SureDashboard\Core\Routers\Backend as BackendRoute;
use SureDashboard\Inc\Services\Abilities\Ability;

defined( 'ABSPATH' ) || exit;

/**
 * Update_Group class.
 *
 * @since 1.6.3
 */
class Update_Group extends Ability {
	/**
	 * Option gate key for ability permission control.
	 *
	 * @since 1.7.3
	 * @var string
	 */
	protected string $gated = 'suredash_abilities_api_edit';

	/**
	 * Get the unique identifier for this ability.
	 *
	 * @since 1.6.3
	 *
	 * @return string
	 */
	public function get_id(): string {
		return 'update-group';
	}

	/**
	 * Get the human-readable name.
	 *
	 * @since 1.6.3
	 *
	 * @return string
	 */
	public function get_name(): string {
		return __( 'Update Group', 'suredash' );
	}

	/**
	 * Get the ability description.
	 *
	 * @since 1.6.3
	 *
	 * @return string
	 */
	public function get_description(): string {
		return __( 'Updates an existing space group name and/or settings. Only provided fields are changed.', 'suredash' );
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
			'term_id'       => [
				'type'        => 'integer',
				'required'    => true,
				'description' => __( 'ID of the group to update.', 'suredash' ),
			],
			'category_name' => [
				'type'        => 'string',
				'required'    => false,
				'description' => __( 'New name for the group.', 'suredash' ),
			],
			'hide_label'    => [
				'type'        => 'boolean',
				'required'    => false,
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
		return 'Only provided fields are updated. Use list-groups to find group term_ids.';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param array<string, mixed> $params Validated parameters.
	 */
	public function execute( array $params ): array {
		$post_data = [
			'term_id' => absint( $params['term_id'] ),
		];

		if ( isset( $params['category_name'] ) ) {
			$post_data['category_name'] = sanitize_text_field( $params['category_name'] );
		}

		if ( isset( $params['hide_label'] ) ) {
			$post_data['hide_label'] = $params['hide_label'] ? 'true' : 'false';
		}

		$this->setup_post_data( $post_data );

		$request = $this->build_request();
		$result  = $this->call_json_handler(
			[ BackendRoute::get_instance(), 'update_space_group' ],
			$request
		);

		$this->cleanup_post_data( array_keys( $post_data ) );

		return $result;
	}
}
