<?php
/**
 * List Community Posts Ability.
 *
 * @package SureDash
 * @since 1.6.3
 */

namespace SureDashboard\Inc\Services\Abilities\Handlers;

use SureDashboard\Core\Routers\Backend as BackendRoute;
use SureDashboard\Inc\Services\Abilities\Ability;

defined( 'ABSPATH' ) || exit;

/**
 * List_Posts class.
 *
 * @since 1.6.3
 */
class List_Posts extends Ability {
	/**
	 * Get the unique identifier for this ability.
	 *
	 * @since 1.6.3
	 *
	 * @return string
	 */
	public function get_id(): string {
		return 'list-community-posts';
	}

	/**
	 * Get the human-readable name.
	 *
	 * @since 1.6.3
	 *
	 * @return string
	 */
	public function get_name(): string {
		return __( 'List Community Posts', 'suredash' );
	}

	/**
	 * Get the ability description.
	 *
	 * @since 1.6.3
	 *
	 * @return string
	 */
	public function get_description(): string {
		return __( 'Lists community/discussion posts with optional filtering. Returns post ID, title, author, date, status, and engagement data. Filter by space_id (discussion space post ID) or forum_term_id (taxonomy term ID). Use list-spaces with content_type "posts_discussion" to find discussion space IDs.', 'suredash' );
	}

	/**
	 * Get the ability category.
	 *
	 * @since 1.6.3
	 *
	 * @return string
	 */
	public function get_category(): string {
		return 'community';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_parameters(): array {
		return [
			'space_id'      => [
				'type'        => 'integer',
				'required'    => false,
				'default'     => 0,
				'description' => __( 'Discussion space post ID to list posts from. The forum term is resolved automatically. Use list-spaces to find space IDs.', 'suredash' ),
			],
			'forum_term_id' => [
				'type'        => 'integer',
				'required'    => false,
				'default'     => 0,
				'description' => __( 'Community-forum taxonomy term ID. Use this only if you already know the term ID. If space_id is provided, this is ignored.', 'suredash' ),
			],
		];
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_returns(): array {
		return [
			'type'        => 'array',
			'description' => __( 'Array of community post objects.', 'suredash' ),
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
		return 'Filter by space_id from list-spaces or forum_term_id if already known. Returns posts with engagement data. Use post IDs with edit-post, delete-post, content-action, or create-comment.';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param array<string, mixed> $params Validated parameters.
	 */
	public function execute( array $params ): array {
		$category_id = 0;

		// Resolve space_id to its forum term ID.
		$space_id = absint( $params['space_id'] ?? 0 );
		if ( $space_id ) {
			$category_id = absint( sd_get_post_meta( $space_id, 'feed_group_id', true ) );

			if ( ! $category_id ) {
				return [
					'success' => false,
					'data'    => [ 'message' => __( 'No discussion forum found for this space. Ensure the space is a posts_discussion type.', 'suredash' ) ],
				];
			}
		} elseif ( ! empty( $params['forum_term_id'] ) ) {
			$category_id = absint( $params['forum_term_id'] );
		}

		$this->setup_post_data(
			[
				'category_id' => $category_id,
			]
		);

		$request = $this->build_request();
		$result  = $this->call_json_handler(
			[ BackendRoute::get_instance(), 'get_community_posts_list' ],
			$request
		);

		$this->cleanup_post_data( [ 'category_id' ] );

		return $result;
	}
}
