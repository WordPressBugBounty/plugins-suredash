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
				'description' => __( 'Array of objects with "term_id" (integer) and "order" (integer) specifying the new display order for each group. Must include ALL groups.', 'suredash' ),
				'items'       => [
					'type'       => 'object',
					'required'   => [ 'term_id', 'order' ],
					'properties' => [
						'term_id' => [
							'type'        => 'integer',
							'minimum'     => 1,
							'description' => __( 'Group taxonomy term ID.', 'suredash' ),
						],
						'order'   => [
							'type'        => 'integer',
							'minimum'     => 0,
							'description' => __( 'Display position (0-based, lower = higher in sidebar).', 'suredash' ),
						],
					],
				],
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
			return $this->fail( __( 'ordering_data must be an array.', 'suredash' ) );
		}

		// Completeness guard — the stored order is replaced in full, so a partial
		// payload leaves the omitted groups with stale positions and silently
		// scrambles the sidebar. Item shape is enforced by the schema.
		$all_group_ids = get_terms(
			[
				'taxonomy'   => SUREDASHBOARD_TAXONOMY,
				'hide_empty' => false,
				'fields'     => 'ids',
			]
		);

		if ( is_wp_error( $all_group_ids ) ) {
			return $this->fail( $all_group_ids->get_error_message() );
		}

		$payload_ids = array_map(
			static function ( $item ) {
				return absint( $item['term_id'] );
			},
			$ordering_data
		);

		$id_list_errors = $this->get_id_set_errors(
			$payload_ids,
			array_map( 'absint', $all_group_ids ),
			__( 'groups', 'suredash' ),
			'list-groups'
		);

		if ( ! empty( $id_list_errors ) ) {
			return $this->fail(
				__( 'Nothing reordered: ordering_data must contain exactly the full set of groups.', 'suredash' ),
				$id_list_errors
			);
		}

		$this->setup_post_data(
			[
				'taxonomy_ordering_data' => $this->encode_json_for_router( $ordering_data ),
			]
		);

		$request = $this->build_request();
		$result  = $this->call_json_handler(
			[ BackendRoute::get_instance(), 'update_group_order' ],
			$request
		);

		$this->cleanup_post_data( [ 'taxonomy_ordering_data' ] );

		// Echo the resulting order (term IDs sorted by their new position) so
		// agents can self-verify without a follow-up list-groups call.
		if ( ! empty( $result['success'] ) ) {
			usort(
				$ordering_data,
				static function ( $a, $b ) {
					return (int) $a['order'] <=> (int) $b['order'];
				}
			);
			$result['final_order'] = array_map(
				static function ( $item ) {
					return absint( $item['term_id'] );
				},
				$ordering_data
			);
		}

		return $result;
	}
}
