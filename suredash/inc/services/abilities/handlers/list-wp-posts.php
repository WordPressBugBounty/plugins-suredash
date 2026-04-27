<?php
/**
 * List WordPress Posts/Pages Ability.
 *
 * @package SureDash
 * @since 1.6.3
 */

namespace SureDashboard\Inc\Services\Abilities\Handlers;

use SureDashboard\Core\Routers\Backend as BackendRoute;
use SureDashboard\Inc\Services\Abilities\Ability;

defined( 'ABSPATH' ) || exit;

/**
 * List_WP_Posts class.
 *
 * Lists WordPress posts and pages for assigning to single_post spaces.
 *
 * @since 1.6.3
 */
class List_WP_Posts extends Ability {
	/**
	 * Get the unique identifier for this ability.
	 *
	 * @since 1.6.3
	 *
	 * @return string
	 */
	public function get_id(): string {
		return 'list-wp-posts';
	}

	/**
	 * Get the human-readable name.
	 *
	 * @since 1.6.3
	 *
	 * @return string
	 */
	public function get_name(): string {
		return __( 'List WordPress Posts & Pages', 'suredash' );
	}

	/**
	 * Get the ability description.
	 *
	 * @since 1.6.3
	 *
	 * @return string
	 */
	public function get_description(): string {
		return __( 'Lists WordPress posts and pages that can be assigned to single_post spaces. Use this to find post/page IDs before calling update-space-settings with single_post_id. Supports searching by title and filtering by post type (post or page).', 'suredash' );
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
			'search'    => [
				'type'        => 'string',
				'required'    => false,
				'default'     => '',
				'description' => __( 'Search query to filter by title.', 'suredash' ),
			],
			'post_type' => [
				'type'        => 'string',
				'required'    => false,
				'default'     => 'page',
				'enum'        => [ 'page', 'post' ],
				'description' => __( 'WordPress post type to list. Defaults to "page".', 'suredash' ),
			],
			'per_page'  => [
				'type'        => 'integer',
				'required'    => false,
				'default'     => 20,
				'description' => __( 'Number of results to return.', 'suredash' ),
			],
		];
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_returns(): array {
		return [
			'type'        => 'array',
			'description' => __( 'Array of post/page objects with ID, title, and type.', 'suredash' ),
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
		return 'Lists WordPress posts/pages for assigning to single_post spaces. Use the returned post ID with update-space-settings (single_post_id + post_render_type "wordpress"). Filter by post_type "page" or "post".';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param array<string, mixed> $params Ability parameters.
	 * @return array<string, mixed>
	 */
	public function execute( array $params ): array {
		$post_type = $params['post_type'] ?? 'page';

		$this->setup_post_data(
			[
				'q'           => $params['search'] ?? '',
				'post_type'   => $post_type,
				'per_page'    => $params['per_page'] ?? 20,
				'category_id' => 0,
				'taxonomy'    => '',
				'post_status' => 'publish',
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

		$posts = [];
		foreach ( $result['data'] as $group ) {
			if ( empty( $group['options'] ) || ! is_array( $group['options'] ) ) {
				continue;
			}
			foreach ( $group['options'] as $option ) {
				$posts[] = [
					'id'    => $option['value'] ?? 0,
					'title' => $option['label'] ?? '',
					'type'  => $post_type,
				];
			}
		}

		return [
			'success' => true,
			'data'    => [
				'posts' => $posts,
				'total' => count( $posts ),
			],
		];
	}
}
