<?php
/**
 * Delete Group Ability.
 *
 * @package SureDash
 * @since 1.6.3
 */

namespace SureDashboard\Inc\Services\Abilities\Handlers;

use SureDashboard\Core\Routers\Backend as BackendRoute;
use SureDashboard\Inc\Services\Abilities\Ability;

defined( 'ABSPATH' ) || exit;

/**
 * Delete_Group class.
 *
 * @since 1.6.3
 */
class Delete_Group extends Ability {
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
		return 'delete-group';
	}

	/**
	 * Get the human-readable name.
	 *
	 * @since 1.6.3
	 *
	 * @return string
	 */
	public function get_name(): string {
		return __( 'Delete Group', 'suredash' );
	}

	/**
	 * Get the ability description.
	 *
	 * @since 1.6.3
	 *
	 * @return string
	 */
	public function get_description(): string {
		return __( 'Deletes a space group (sidebar category). Spaces in the group become uncategorized but are NOT deleted. Only the grouping is removed.', 'suredash' );
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
			'term_id' => [
				'type'        => 'integer',
				'required'    => true,
				'description' => __( 'ID of the group to delete.', 'suredash' ),
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
		return 'DESTRUCTIVE: Deletes the group permanently. Spaces inside become uncategorized but are not deleted. Use list-groups to verify the term_id and check which spaces are inside before deleting.';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param array<string, mixed> $params Validated parameters.
	 */
	public function execute( array $params ): array {
		$term_id = absint( $params['term_id'] );

		// Target guard — the router hands any ID to wp_delete_term(), so a term
		// from another taxonomy would fail with a generic message (or, in the
		// worst case, delete the wrong object). Verify it is a portal space
		// group and capture its name before it is gone.
		$target_error = $this->get_term_target_error( $term_id, SUREDASHBOARD_TAXONOMY, __( 'Group', 'suredash' ) );
		if ( $target_error !== null ) {
			return $target_error;
		}

		$term       = get_term( $term_id, SUREDASHBOARD_TAXONOMY );
		$group_name = $term instanceof \WP_Term ? $term->name : '';

		$this->setup_post_data(
			[
				'term_id' => $term_id,
			]
		);

		$request = $this->build_request();
		$result  = $this->call_json_handler(
			[ BackendRoute::get_instance(), 'delete_space_group' ],
			$request
		);

		$this->cleanup_post_data( [ 'term_id' ] );

		if ( ! empty( $result['success'] ) && is_array( $result['data'] ?? null ) ) {
			$result['data']['deleted_id']   = $term_id;
			$result['data']['deleted_name'] = $group_name;
		}

		return $result;
	}
}
