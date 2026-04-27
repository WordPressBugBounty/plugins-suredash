<?php
/**
 * Reorder Groups Ability.
 *
 * @package SureDash
 * @since 1.6.3
 */

namespace SureDashboard\Inc\Services\Abilities\Handlers;

use SureDashboard\Core\Routers\Backend as BackendRoute;
use SureDashboard\Inc\Services\Abilities\Ability;

defined( 'ABSPATH' ) || exit;

/**
 * Reorder_Groups class.
 *
 * @since 1.6.3
 */
class Reorder_Groups extends Ability {
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
		return 'reorder-groups';
	}

	/**
	 * Get the human-readable name.
	 *
	 * @since 1.6.3
	 *
	 * @return string
	 */
	public function get_name(): string {
		return __( 'Reorder Groups', 'suredash' );
	}

	/**
	 * Get the ability description.
	 *
	 * @since 1.6.3
	 *
	 * @return string
	 */
	public function get_description(): string {
		return __( 'Changes the display order of space groups in the sidebar navigation. Provide an array of objects, each with "term_id" (group ID) and "order" (integer position starting from 0). You must include ALL groups, not just the ones being moved. Example: {"ordering_data": [{"term_id": 5, "order": 0}, {"term_id": 3, "order": 1}, {"term_id": 8, "order": 2}]}', 'suredash' );
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
			'ordering_data' => [
				'type'        => 'array',
				'required'    => true,
				'description' => __( 'Array of objects with "term_id" (integer) and "order" (integer) specifying the new display order for each group.', 'suredash' ),
			],
		];
	}

	/**
	 * Override input schema to document array item structure.
	 *
	 * @return array<string, mixed>
	 */
	public function get_input_schema(): array {
		return [
			'type'       => 'object',
			'required'   => [ 'ordering_data' ],
			'properties' => [
				'ordering_data' => [
					'type'        => 'array',
					'description' => __( 'Array of group ordering objects. Each object specifies a group and its new position. Must include ALL groups.', 'suredash' ),
					'items'       => [
						'type'       => 'object',
						'required'   => [ 'term_id', 'order' ],
						'properties' => [
							'term_id' => [
								'type'        => 'integer',
								'description' => __( 'Group taxonomy term ID.', 'suredash' ),
							],
							'order'   => [
								'type'        => 'integer',
								'description' => __( 'Display position (0-based, lower = higher in sidebar).', 'suredash' ),
							],
						],
					],
					'examples'    => [
						[
							[
								'term_id' => 5,
								'order'   => 0,
							],
							[
								'term_id' => 3,
								'order'   => 1,
							],
							[
								'term_id' => 8,
								'order'   => 2,
							],
						],
					],
				],
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
		return 'Must include ALL groups in the ordering_data array, not just moved ones. Use list-groups first to get all current term_ids and their order. Order values start from 0.';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param array<string, mixed> $params Validated parameters.
	 */
	public function execute( array $params ): array {
		$ordering_data = $params['ordering_data'];

		if ( ! is_array( $ordering_data ) ) {
			return [
				'success' => false,
				'data'    => [ 'message' => __( 'ordering_data must be an array.', 'suredash' ) ],
			];
		}

		$this->setup_post_data(
			[
				'taxonomy_ordering_data' => wp_json_encode( $ordering_data ),
			]
		);

		$request = $this->build_request();
		$result  = $this->call_json_handler(
			[ BackendRoute::get_instance(), 'update_group_order' ],
			$request
		);

		$this->cleanup_post_data( [ 'taxonomy_ordering_data' ] );

		return $result;
	}
}
