<?php
/**
 * Create Post For Space Ability.
 *
 * @package SureDash
 * @since 1.6.3
 */

namespace SureDashboard\Inc\Services\Abilities\Handlers;

use SureDashboard\Core\Routers\Backend as BackendRoute;
use SureDashboard\Inc\Services\Abilities\Ability;

defined( 'ABSPATH' ) || exit;

/**
 * Create_Post_For_Space class.
 *
 * @since 1.6.3
 */
class Create_Post_For_Space extends Ability {
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
		return 'create-post-for-space';
	}

	/**
	 * Get the human-readable name.
	 *
	 * @since 1.6.3
	 *
	 * @return string
	 */
	public function get_name(): string {
		return __( 'Create Post For Space', 'suredash' );
	}

	/**
	 * Get the ability description.
	 *
	 * @since 1.6.3
	 *
	 * @return string
	 */
	public function get_description(): string {
		return __( 'Creates a sub-content item (community-content post type) inside a course, resource library, or collection space. Use ONLY for courses (lessons), resource libraries (files), and collections — NOT for discussion spaces. For discussion/forum posts, use create-post instead.', 'suredash' );
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
			'post_title'     => [
				'type'        => 'string',
				'required'    => true,
				'description' => __( 'Title of the content item to create.', 'suredash' ),
			],
			'space_id'       => [
				'type'        => 'integer',
				'required'    => true,
				'description' => __( 'ID of the parent space.', 'suredash' ),
			],
			'post_status'    => [
				'type'        => 'string',
				'required'    => false,
				'default'     => 'publish',
				'description' => __( 'Status of the new post.', 'suredash' ),
				'enum'        => [ 'publish', 'draft' ],
			],
			'space_type'     => [
				'type'        => 'string',
				'required'    => false,
				'default'     => '',
				'description' => __( 'Type of the parent space for context.', 'suredash' ),
			],
			'forum_category' => [
				'type'        => 'integer',
				'required'    => false,
				'default'     => 0,
				'description' => __( 'Forum category ID if creating a discussion post.', 'suredash' ),
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
				'post_id' => [
					'type'        => 'integer',
					'description' => __( 'ID of the created content item.', 'suredash' ),
				],
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
		return 'Creates sub-content (lessons, resources, collection items) inside non-discussion spaces. NEVER use this for discussion spaces — use create-post instead. Call get-space-meta first to check the space integration type. Only use this when integration is "course", "resource_library", or "collection".';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param array<string, mixed> $params Validated parameters.
	 */
	public function execute( array $params ): array {
		$post_data = [
			'post_title'  => sanitize_text_field( $params['post_title'] ),
			'post_status' => $params['post_status'] ?? 'publish',
			'space_id'    => absint( $params['space_id'] ),
			'space_type'  => $params['space_type'] ?? '',
		];

		if ( ! empty( $params['forum_category'] ) ) {
			$post_data['forum_category'] = absint( $params['forum_category'] );
		}

		$this->setup_post_data( $post_data );

		$request = $this->build_request();

		// create_post_for_space returns array directly (no wp_send_json).
		$result = BackendRoute::get_instance()->create_post_for_space( $request );

		$this->cleanup_post_data( array_keys( $post_data ) );

		return is_array( $result ) ? $result : [
			'success' => false,
			'data'    => [ 'message' => __( 'Unexpected response.', 'suredash' ) ],
		];
	}
}
