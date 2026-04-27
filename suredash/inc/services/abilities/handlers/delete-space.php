<?php
/**
 * Delete Space Ability.
 *
 * @package SureDash
 * @since 1.6.3
 */

namespace SureDashboard\Inc\Services\Abilities\Handlers;

use SureDashboard\Core\Routers\Backend as BackendRoute;
use SureDashboard\Inc\Services\Abilities\Ability;

defined( 'ABSPATH' ) || exit;

/**
 * Delete_Space class.
 *
 * @since 1.6.3
 */
class Delete_Space extends Ability {
	/**
	 * Option gate key for ability permission control.
	 *
	 * @since 1.7.3
	 * @var string
	 */
	protected string $gated = 'suredash_abilities_api_delete';

	/**
	 * Get the unique identifier for this ability.
	 *
	 * @since 1.6.3
	 *
	 * @return string
	 */
	public function get_id(): string {
		return 'delete-space';
	}

	/**
	 * Get the human-readable name.
	 *
	 * @since 1.6.3
	 *
	 * @return string
	 */
	public function get_name(): string {
		return __( 'Delete Space', 'suredash' );
	}

	/**
	 * Get the ability description.
	 *
	 * @since 1.6.3
	 *
	 * @return string
	 */
	public function get_description(): string {
		return __( 'Permanently deletes a portal space and all its associated content (posts, lessons, comments). This action cannot be undone.', 'suredash' );
	}

	/**
	 * Get the ability category.
	 *
	 * @since 1.6.3
	 *
	 * @return string
	 */
	public function get_category(): string {
		return 'spaces';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_parameters(): array {
		return [
			'post_id' => [
				'type'        => 'integer',
				'required'    => true,
				'description' => __( 'The ID of the space to delete.', 'suredash' ),
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
			'destructiveHint' => true,
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
		return 'DESTRUCTIVE: Permanently deletes the space and ALL its content (posts, lessons, comments). Cannot be undone. Use list-spaces to verify the space_id before deleting. Consider using update-space-settings to set status to "draft" instead.';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param array<string, mixed> $params Validated parameters.
	 */
	public function execute( array $params ): array {
		$this->setup_post_data(
			[
				'post_id' => absint( $params['post_id'] ),
			]
		);

		$request = $this->build_request();
		$result  = $this->call_json_handler(
			[ BackendRoute::get_instance(), 'delete_space' ],
			$request
		);

		$this->cleanup_post_data( [ 'post_id' ] );

		return $result;
	}
}
