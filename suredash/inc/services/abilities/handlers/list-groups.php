<?php
/**
 * List Groups Ability.
 *
 * @package SureDash
 * @since 1.6.3
 */

namespace SureDashboard\Inc\Services\Abilities\Handlers;

use SureDashboard\Core\Models\Controller;
use SureDashboard\Inc\Services\Abilities\Ability;

defined( 'ABSPATH' ) || exit;

/**
 * List_Groups class.
 *
 * @since 1.6.3
 */
class List_Groups extends Ability {
	/**
	 * Get the unique identifier for this ability.
	 *
	 * @since 1.6.3
	 *
	 * @return string
	 */
	public function get_id(): string {
		return 'list-groups';
	}

	/**
	 * Get the human-readable name.
	 *
	 * @since 1.6.3
	 *
	 * @return string
	 */
	public function get_name(): string {
		return __( 'List Groups', 'suredash' );
	}

	/**
	 * Get the ability description.
	 *
	 * @since 1.6.3
	 *
	 * @return string
	 */
	public function get_description(): string {
		return __( 'Lists all space groups (sidebar categories) in the portal. Returns each group\'s term_id, name, description, display order, space count, and the ordered list of space IDs within it. Groups are returned sorted by their display order. Spaces that belong to no group are reported as an extra "Uncategorized" entry with term_id 0, matching the portal dashboard.', 'suredash' );
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
		return [];
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_returns(): array {
		return [
			'type'        => 'object',
			'description' => __( 'Object with success flag and data containing groups (array of group objects, plus an "Uncategorized" entry with term_id 0 when ungrouped spaces exist), total (count of real groups, excluding that entry), and uncategorized_spaces (count of spaces in no group).', 'suredash' ),
			'properties'  => [
				'groups' => [
					'type'        => 'array',
					'description' => __( 'Array of group objects.', 'suredash' ),
					'items'       => [
						'type'       => 'object',
						'properties' => [
							'term_id'     => [
								'type'        => 'integer',
								'description' => __( 'Group taxonomy term ID.', 'suredash' ),
							],
							'name'        => [
								'type'        => 'string',
								'description' => __( 'Group display name.', 'suredash' ),
							],
							'description' => [
								'type'        => 'string',
								'description' => __( 'Group description.', 'suredash' ),
							],
							'order'       => [
								'type'        => 'integer',
								'description' => __( 'Display position (lower = higher in sidebar).', 'suredash' ),
							],
							'space_count' => [
								'type'        => 'integer',
								'description' => __( 'Number of spaces in this group.', 'suredash' ),
							],
							'space_ids'   => [
								'type'        => 'array',
								'description' => __( 'Ordered array of space post IDs in this group.', 'suredash' ),
							],
							'hide_label'  => [
								'type'        => 'boolean',
								'description' => __( 'Whether the group label is hidden in navigation.', 'suredash' ),
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
	public function get_annotations(): array {
		return [
			'readOnlyHint'    => true,
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
		return 'Call this first to discover group IDs and their space_ids arrays. Use term_id values with create-space (group_id), list-spaces (category_id), reorder-groups, and reorder-spaces-in-group. term_id 0 is the synthetic "Uncategorized" bucket for spaces with no group — read it, but never pass 0 to a write ability. "total" is the number of real groups and excludes that bucket; "uncategorized_spaces" is how many spaces sit outside every group. For a portal-wide space count call list-spaces and read its "total" instead of adding up space_count across groups.';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param array<string, mixed> $params Validated parameters.
	 */
	public function execute( array $params ): array {
		$terms = get_terms(
			[
				'taxonomy'   => SUREDASHBOARD_TAXONOMY,
				'hide_empty' => false,
				'orderby'    => 'meta_value_num',
				'meta_key'   => 'group_tax_position', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'order'      => 'ASC',
			]
		);

		if ( is_wp_error( $terms ) ) {
			return [
				'success' => false,
				'data'    => [ 'message' => $terms->get_error_message() ],
			];
		}

		$groups = [];

		foreach ( $terms as $term ) {
			$order      = (int) get_term_meta( $term->term_id, 'group_tax_position', true );
			$link_order = get_term_meta( $term->term_id, '_link_order', true );
			$hide_label = (bool) get_term_meta( $term->term_id, 'hide_label', true );

			$space_ids = [];
			if ( ! empty( $link_order ) ) {
				$space_ids = array_values( array_unique( array_map( 'absint', array_filter( explode( ',', $link_order ) ) ) ) );
			}

			$groups[] = [
				'term_id'     => $term->term_id,
				'name'        => $term->name,
				'description' => $term->description,
				'slug'        => $term->slug,
				'order'       => $order,
				'space_count' => (int) $term->count,
				'space_ids'   => $space_ids,
				'hide_label'  => $hide_label,
			];
		}

		// total counts real taxonomy terms only. The Uncategorized bucket is a
		// placeholder for ungrouped spaces, not a group an admin created, so
		// folding it into total would overstate how many groups the portal has.
		$total = count( $groups );

		$uncategorized = $this->get_uncategorized_group();

		if ( ! empty( $uncategorized ) ) {
			$groups[] = $uncategorized;
		}

		return [
			'success' => true,
			'data'    => [
				'groups'               => $groups,
				'total'                => $total,
				'uncategorized_spaces' => $uncategorized['space_count'] ?? 0,
			],
		];
	}

	/**
	 * Build the synthetic "Uncategorized" group for spaces with no group.
	 *
	 * The portal dashboard shows ungrouped spaces under a placeholder group
	 * with term_id 0 (see admin/menu.php). Abilities used to skip them
	 * entirely, so any caller that walked groups or summed space_count missed
	 * every ungrouped space. This mirrors the dashboard so the totals agree.
	 *
	 * @since 1.9.4
	 * @return array<string, mixed> Empty array when every space belongs to a group.
	 */
	private function get_uncategorized_group(): array {
		$items = Controller::get_query_uncategorized_items( 'Backend_Feeds' );

		if ( empty( $items ) ) {
			return [];
		}

		$space_ids = [];

		foreach ( $items as $item ) {
			// Skip auto-drafts — WordPress creates these placeholders whenever
			// the new space screen is opened and they are not real spaces.
			if ( ( $item['post_status'] ?? '' ) === 'auto-draft' ) {
				continue;
			}

			$space_ids[] = absint( $item['ID'] );
		}

		if ( empty( $space_ids ) ) {
			return [];
		}

		return [
			'term_id'     => 0,
			'name'        => __( 'Uncategorized', 'suredash' ),
			'description' => __( 'Spaces that are not assigned to any group.', 'suredash' ),
			'slug'        => 'uncategorized',
			'order'       => 999,
			'space_count' => count( $space_ids ),
			'space_ids'   => $space_ids,
			'hide_label'  => false,
		];
	}
}
