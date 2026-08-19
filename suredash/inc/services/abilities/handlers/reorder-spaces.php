<?php
/**
 * Reorder Spaces In Group Ability.
 *
 * @package SureDash
 * @since 1.6.3
 */

namespace SureDashboard\Inc\Services\Abilities\Handlers;

use SureDashboard\Core\Routers\Backend as BackendRoute;
use SureDashboard\Inc\Services\Abilities\Ability;
use SureDashboard\Inc\Utils\Helper;

defined( 'ABSPATH' ) || exit;

/**
 * Reorder_Spaces class.
 *
 * @since 1.6.3
 */
class Reorder_Spaces extends Ability {
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
		return 'reorder-spaces-in-group';
	}

	/**
	 * Get the human-readable name.
	 *
	 * @since 1.6.3
	 *
	 * @return string
	 */
	public function get_name(): string {
		return __( 'Reorder Spaces In Group', 'suredash' );
	}

	/**
	 * Get the ability description.
	 *
	 * @since 1.6.3
	 *
	 * @return string
	 */
	public function get_description(): string {
		return __( 'Changes the display order of spaces within a specific group. Provide the group term_id and an ordered array of space post IDs. The array order determines the display order — first item appears at the top. You must include ALL space IDs in the group, not just the ones being moved. Use list-spaces to get current space IDs for a group. Example: {"term_id": 5, "space_ids": [42, 18, 7, 33]}', 'suredash' );
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
			'term_id'   => [
				'type'        => 'integer',
				'required'    => true,
				'minimum'     => 1,
				'description' => __( 'ID of the group containing the spaces to reorder.', 'suredash' ),
			],
			'space_ids' => [
				'type'        => 'array',
				'required'    => true,
				'description' => __( 'Ordered array of space IDs representing the new display order. Must include ALL spaces in the group.', 'suredash' ),
				'items'       => [
					'type'    => 'integer',
					'minimum' => 1,
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
			'required'   => [ 'term_id', 'space_ids' ],
			'properties' => [
				'term_id'   => [
					'type'        => 'integer',
					'description' => __( 'ID of the group (taxonomy term) containing the spaces to reorder.', 'suredash' ),
				],
				'space_ids' => [
					'type'        => 'array',
					'description' => __( 'Ordered array of space post IDs. The array position determines the display order (index 0 = first). Must include ALL space IDs in the group.', 'suredash' ),
					'items'       => [
						'type'        => 'integer',
						'description' => __( 'Space post ID.', 'suredash' ),
					],
					'examples'    => [
						[ 42, 18, 7, 33 ],
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
		return 'Must include ALL space IDs in the group, not just moved ones. Use list-groups to get current space_ids for a group. Array order determines display order — first item appears at top.';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param array<string, mixed> $params Validated parameters.
	 */
	public function execute( array $params ): array {
		$term_id      = absint( $params['term_id'] );
		$target_error = $this->get_term_target_error( $term_id, SUREDASHBOARD_TAXONOMY, __( 'Group', 'suredash' ) );
		if ( $target_error !== null ) {
			return $target_error;
		}

		// Stale IDs left behind by deleted spaces still show up in list-groups,
		// so drop anything that is not a real space from both sides rather than
		// blocking a reorder over an ID the agent could not have known was gone.
		$is_space  = static function ( $space_id ) {
			return get_post_type( $space_id ) === SUREDASHBOARD_POST_TYPE;
		};
		$space_ids = array_values( array_unique( array_filter( array_map( 'absint', $params['space_ids'] ), $is_space ) ) );

		// Completeness guard — the stored order is replaced in full, so a
		// partial payload silently drops the omitted spaces from the group's
		// ordering. Item shape is enforced by the schema.
		$current_ids = array_values(
			array_unique( array_filter( array_map( 'absint', Helper::get_items_order_sequence( $term_id ) ), $is_space ) )
		);

		$id_list_errors = $this->get_id_set_errors(
			$space_ids,
			$current_ids,
			__( 'spaces in this group', 'suredash' ),
			'list-groups'
		);

		if ( ! empty( $id_list_errors ) ) {
			return $this->fail(
				sprintf(
					/* translators: %d: group term ID. */
					__( 'Nothing reordered: space_ids must contain exactly the full set of spaces in group %d.', 'suredash' ),
					$term_id
				),
				$id_list_errors
			);
		}

		$this->setup_post_data(
			[
				'list_term_id'        => $term_id,
				'items_ordering_data' => $this->encode_json_for_router( $space_ids ),
			]
		);

		$request = $this->build_request();
		$result  = $this->call_json_handler(
			[ BackendRoute::get_instance(), 'update_item_order_by_group' ],
			$request
		);

		$this->cleanup_post_data( [ 'list_term_id', 'items_ordering_data' ] );

		// Echo the resulting order so agents can self-verify without a
		// follow-up list-groups call.
		if ( ! empty( $result['success'] ) ) {
			$result['final_order'] = $space_ids;
		}

		return $result;
	}
}
