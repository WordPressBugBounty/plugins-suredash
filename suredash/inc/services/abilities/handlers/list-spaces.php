<?php
/**
 * List Spaces Ability.
 *
 * @package SureDash
 * @since 1.6.3
 */

namespace SureDashboard\Inc\Services\Abilities\Handlers;

use SureDashboard\Core\Routers\Backend as BackendRoute;
use SureDashboard\Inc\Services\Abilities\Ability;

defined( 'ABSPATH' ) || exit;

/**
 * List_Spaces class.
 *
 * @since 1.6.3
 */
class List_Spaces extends Ability {
	/**
	 * Get the unique identifier for this ability.
	 *
	 * @since 1.6.3
	 *
	 * @return string
	 */
	public function get_id(): string {
		return 'list-spaces';
	}

	/**
	 * Get the human-readable name.
	 *
	 * @since 1.6.3
	 *
	 * @return string
	 */
	public function get_name(): string {
		return __( 'List Spaces', 'suredash' );
	}

	/**
	 * Get the ability description.
	 *
	 * @since 1.6.3
	 *
	 * @return string
	 */
	public function get_description(): string {
		return __( 'Lists portal spaces with optional search and filtering. Returns space ID, title, type, and status. Use category_id to filter by group — get group term_ids from list-groups. Use content_type to filter by space type. Results are paginated via per_page and page parameters.', 'suredash' );
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
			'search'       => [
				'type'        => 'string',
				'required'    => false,
				'default'     => '',
				'description' => __( 'Search query to filter spaces by title.', 'suredash' ),
			],
			'per_page'     => [
				'type'        => 'integer',
				'required'    => false,
				'default'     => 20,
				'description' => __( 'Number of spaces to return per page.', 'suredash' ),
			],
			'page'         => [
				'type'        => 'integer',
				'required'    => false,
				'default'     => 1,
				'description' => __( 'Page number for pagination.', 'suredash' ),
			],
			'category_id'  => [
				'type'        => 'integer',
				'required'    => false,
				'default'     => 0,
				'description' => __( 'Group term_id to filter spaces by. Use list-groups to get available group IDs. 0 returns all spaces.', 'suredash' ),
			],
			'content_type' => [
				'type'        => 'string',
				'required'    => false,
				'default'     => '',
				'enum'        => [ '', 'posts_discussion', 'course', 'resource_library', 'collection', 'events' ],
				'description' => __( 'Filter by space type. Empty string returns all types.', 'suredash' ),
			],
			'status'       => [
				'type'        => 'string',
				'required'    => false,
				'default'     => 'publish',
				'enum'        => [ 'publish', 'draft', 'any' ],
				'description' => __( 'Filter by post status. Use "any" to return all statuses.', 'suredash' ),
			],
		];
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_returns(): array {
		return [
			'type'        => 'array',
			'description' => __( 'Array of space objects with titles, IDs, types, and metadata.', 'suredash' ),
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
		return 'Returns paginated spaces. Use category_id from list-groups to filter by group. Chain with get-space-meta for full space configuration. Use content_type to narrow results (e.g. "posts_discussion", "course").';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param array<string, mixed> $params Validated parameters.
	 */
	public function execute( array $params ): array {
		$status = $params['status'] ?? 'publish';

		// Map 'any' to comma-separated statuses for the query model.
		$post_status = $status === 'any' ? 'publish,draft' : $status;

		$this->setup_post_data(
			[
				'q'            => $params['search'] ?? '',
				'post_type'    => SUREDASHBOARD_POST_TYPE,
				'per_page'     => $params['per_page'] ?? 20,
				'page'         => $params['page'] ?? 1,
				'category_id'  => $params['category_id'] ?? 0,
				'taxonomy'     => SUREDASHBOARD_TAXONOMY,
				'content_type' => $params['content_type'] ?? '',
				'post_status'  => $post_status,
			]
		);

		$request = $this->build_request();
		$result  = $this->call_json_handler(
			[ BackendRoute::get_instance(), 'get_posts_list' ],
			$request
		);

		$this->cleanup_post_data( [ 'q', 'post_type', 'per_page', 'page', 'category_id', 'taxonomy', 'content_type', 'post_status' ] );

		if ( empty( $result['success'] ) || empty( $result['data'] ) ) {
			return $result;
		}

		$spaces = [];
		foreach ( $result['data'] as $group ) {
			if ( empty( $group['options'] ) || ! is_array( $group['options'] ) ) {
				continue;
			}
			foreach ( $group['options'] as $option ) {
				$spaces[] = [
					'id'    => $option['value'] ?? 0,
					'title' => $option['label'] ?? '',
					'type'  => $option['content_type'] ?? '',
				];
			}
		}

		return [
			'success' => true,
			'data'    => [
				'spaces' => $spaces,
				'total'  => count( $spaces ),
			],
		];
	}
}
