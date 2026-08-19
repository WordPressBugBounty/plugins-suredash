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
		return __( 'Lists portal spaces with optional search and filtering. Returns the ID, title and "type" of each space (its integration, e.g. "posts_discussion", "course", "events", "resource_library", "collection", "link", "single_post"), plus "total" (the full match count across every page) and "total_pages". Ungrouped spaces are included. Use category_id to filter by group — get group term_ids from list-groups. Use content_type to filter by space type. Results are paginated via per_page and page parameters.', 'suredash' );
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
				'enum'        => [ '', 'posts_discussion', 'course', 'resource_library', 'collection', 'events', 'link', 'single_post' ],
				'description' => __( 'Filter by space type, matched against the space integration and returned as "type" on each result. Empty string returns all types.', 'suredash' ),
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
			'type'        => 'object',
			'description' => __( 'Object containing spaces (array of space objects for the requested page, each with id, title and type, where type is the space integration), total (full match count across all pages), page, per_page, and total_pages.', 'suredash' ),
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
		return 'Returns paginated spaces. To answer how many spaces exist, call this with no filters and read "total" — it counts every match including spaces that belong to no group. Never answer a space count by adding up space_count from list-groups. Use category_id from list-groups to filter by group. Chain with get-space-meta for full space configuration. Use content_type to narrow results (e.g. "posts_discussion", "course").';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param array<string, mixed> $params Validated parameters.
	 */
	public function execute( array $params ): array {
		$status       = $params['status'] ?? 'publish';
		$per_page     = max( 1, absint( $params['per_page'] ?? 20 ) );
		$page         = max( 1, absint( $params['page'] ?? 1 ) );
		$content_type = sanitize_text_field( (string) ( $params['content_type'] ?? '' ) );

		// Map 'any' to comma-separated statuses for the query model.
		$post_status = $status === 'any' ? 'publish,draft' : $status;

		// per_page 0 tells get_posts_list to drop its LIMIT so we receive every
		// matching space. The route has no offset support, so paginating there
		// would silently discard everything past the first page and leave the
		// count capped at the page size. We page the full set below instead.
		$this->setup_post_data(
			[
				'q'           => $params['search'] ?? '',
				'post_type'   => SUREDASHBOARD_POST_TYPE,
				'per_page'    => 0,
				'category_id' => $params['category_id'] ?? 0,
				'taxonomy'    => SUREDASHBOARD_TAXONOMY,
				'post_status' => $post_status,
			]
		);

		$request = $this->build_request();
		$result  = $this->call_json_handler(
			[ BackendRoute::get_instance(), 'get_posts_list' ],
			$request
		);

		$this->cleanup_post_data( [ 'q', 'post_type', 'per_page', 'category_id', 'taxonomy', 'post_status' ] );

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
					'type'  => '',
				];
			}
		}

		// The route filters content_type against a meta key only sub-content
		// carries, so asking it to narrow spaces returns nothing at all. Filter
		// here instead, on the integration each space actually stores. Typing
		// every match (not just the page) is required to count them correctly.
		if ( $content_type !== '' ) {
			$spaces = array_values(
				array_filter(
					$this->add_space_types( $spaces ),
					static function ( $space ) use ( $content_type ) {
						return $space['type'] === $content_type;
					}
				)
			);
		}

		// total is the portal-wide match count, not the size of this page, so
		// "how many spaces are there" is answerable from a single call.
		$total = count( $spaces );

		return [
			'success' => true,
			'data'    => [
				'spaces'      => $this->add_space_types( array_slice( $spaces, ( $page - 1 ) * $per_page, $per_page ) ),
				'total'       => $total,
				'page'        => $page,
				'per_page'    => $per_page,
				'total_pages' => (int) ceil( $total / $per_page ),
			],
		];
	}

	/**
	 * Fill in each space's integration type.
	 *
	 * The route only attaches content_type for sub-content items, and a space
	 * does not carry that meta at all — its kind lives in "integration". So the
	 * type came back empty for every space, and callers had to make a
	 * get-space-meta call per row just to tell a course from a discussion.
	 *
	 * Runs on the current page only, and primes the meta cache in one query
	 * first, so a large portal does not pay a lookup per space.
	 *
	 * @since 1.11.1
	 *
	 * @param array<int, array<string, mixed>> $spaces Spaces for the requested page.
	 * @return array<int, array<string, mixed>> The same spaces with "type" filled in.
	 */
	private function add_space_types( array $spaces ): array {
		$space_ids = array_values(
			array_filter(
				array_map(
					static function ( $space ) {
						return absint( $space['id'] ?? 0 );
					},
					$spaces
				)
			)
		);

		if ( empty( $space_ids ) ) {
			return $spaces;
		}

		update_meta_cache( 'post', $space_ids );

		foreach ( $spaces as $index => $space ) {
			$spaces[ $index ]['type'] = (string) sd_get_post_meta( absint( $space['id'] ?? 0 ), 'integration', true );
		}

		return $spaces;
	}
}
