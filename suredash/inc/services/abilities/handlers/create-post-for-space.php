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
				'description' => __( 'WordPress post ID of the parent space (the space is stored as a portal post). Use list-spaces to find space IDs.', 'suredash' ),
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
				'description' => __( 'Optional. Type of the parent space. Only used as a fallback: the space\'s own integration is read from the space and wins, so you do not need to send this.', 'suredash' ),
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
		$space_id = absint( $params['space_id'] );

		// Target guard — the router only skips the space_id meta when the
		// space is invalid, so a bad ID would still create an orphaned item.
		$target_error = $this->get_post_target_error( $space_id, SUREDASHBOARD_POST_TYPE, __( 'Space', 'suredash' ) );
		if ( $target_error !== null ) {
			return $target_error;
		}

		// Discussion spaces take community posts, not sub-content items.
		$integration = (string) sd_get_post_meta( $space_id, 'integration', true );
		if ( $integration === 'posts_discussion' ) {
			return $this->fail(
				sprintf(
					/* translators: %d: space post ID. */
					__( 'Space ID %d is a discussion space ("posts_discussion"). Use the create-post ability to create discussion posts. create-post-for-space is only for course, resource library, and collection spaces.', 'suredash' ),
					$space_id
				)
			);
		}

		// The space's real integration decides the payload, not the agent's
		// space_type hint. Without this the router never writes content_type,
		// which later makes update-content-settings reject every field that
		// only applies to a lesson, resource or event.
		$post_data = [
			'post_title'  => sanitize_text_field( $params['post_title'] ),
			'post_status' => $params['post_status'] ?? 'publish',
			'space_id'    => $space_id,
			'space_type'  => $integration !== '' ? $integration : (string) ( $params['space_type'] ?? '' ),
		];

		// Course lessons bind to the course the same way the dashboard binds
		// them, which is also what makes the router set content_type "lesson".
		if ( $integration === 'course' ) {
			$post_data['belong_to_course'] = $space_id;
			$post_data['context']          = 'course_lesson';
		}

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
