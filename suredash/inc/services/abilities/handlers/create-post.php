<?php
/**
 * Create Community Post Ability.
 *
 * @package SureDash
 * @since 1.6.3
 */

namespace SureDashboard\Inc\Services\Abilities\Handlers;

use SureDashboard\Core\Routers\Misc as MiscRoute;
use SureDashboard\Inc\Services\Abilities\Ability;

defined( 'ABSPATH' ) || exit;

/**
 * Create_Post class.
 *
 * @since 1.6.3
 */
class Create_Post extends Ability {
	/**
	 * Get the unique identifier for this ability.
	 *
	 * @since 1.6.3
	 *
	 * @return string
	 */
	public function get_id(): string {
		return 'create-post';
	}

	/**
	 * Get the human-readable name.
	 *
	 * @since 1.6.3
	 *
	 * @return string
	 */
	public function get_name(): string {
		return __( 'Create Community Post', 'suredash' );
	}

	/**
	 * Get the ability description.
	 *
	 * @since 1.6.3
	 *
	 * @return string
	 */
	public function get_description(): string {
		return __( 'Creates a new community discussion post (community-post post type) inside a discussion space. This is the ONLY correct tool for creating posts in discussion/forum spaces (integration type "posts_discussion"). Do NOT use create-post-for-space for discussion spaces — that creates sub-content items (lessons, resources), not discussion posts.', 'suredash' );
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
			'title'       => [
				'type'        => 'string',
				'required'    => true,
				'description' => __( 'Title of the community post.', 'suredash' ),
			],
			'content'     => [
				'type'        => 'string',
				'required'    => true,
				'description' => __( 'HTML content of the post.', 'suredash' ),
			],
			'category_id' => [
				'type'        => 'integer',
				'required'    => false,
				'default'     => 0,
				'description' => __( 'Forum category (community-forum taxonomy) ID to assign the post to.', 'suredash' ),
			],
			'space_id'    => [
				'type'        => 'integer',
				'required'    => false,
				'default'     => 0,
				'description' => __( 'WordPress post ID of the discussion space to create the post in (the space is stored as a portal post). One of space_id or category_id is required — a post without a space is rejected. Use list-spaces to find space IDs, or ask the user which space the post belongs in.', 'suredash' ),
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
		return 'IMPORTANT: Use this tool (not create-post-for-space) whenever the user asks to create a post in a discussion space. A destination is REQUIRED: pass space_id (preferred) or category_id — calls without one are rejected, so call list-spaces first, and if the user never said where the post should go, ask them. Content supports HTML. Only use create-post-for-space for course lessons, resource library files, or collection items — never for discussion posts.';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param array<string, mixed> $params Validated parameters.
	 */
	public function execute( array $params ): array {
		// A post outside any discussion space exists but surfaces in no feed, so
		// require a destination instead of silently creating an orphan.
		$space_id = absint( $params['space_id'] ?? 0 );
		if ( $space_id === 0 && absint( $params['category_id'] ?? 0 ) === 0 ) {
			return $this->fail( __( 'Posts must be created inside a discussion space. Ask the user which space the post belongs in, or call list-spaces to find it. The post was NOT created.', 'suredash' ) );
		}

		if ( $space_id > 0 ) {
			$target_error = $this->get_post_target_error( $space_id, SUREDASHBOARD_POST_TYPE, __( 'Space', 'suredash' ) );
			if ( $target_error !== null ) {
				return $target_error;
			}

			$integration = (string) sd_get_post_meta( $space_id, 'integration', true );
			if ( $integration !== 'posts_discussion' ) {
				return $this->fail(
					sprintf(
						/* translators: 1: space ID, 2: integration type of the space. */
						__( 'Space ID %1$d is a "%2$s" space, not a discussion space. Discussion posts only belong in posts_discussion spaces. Call list-spaces to find one, or use create-post-for-space for course/resource/collection content.', 'suredash' ),
						$space_id,
						$integration !== '' ? $integration : __( '(none)', 'suredash' )
					)
				);
			}
		}

		$form_data = [
			'custom_post_title'   => sanitize_text_field( $params['title'] ),
			'custom_post_content' => wp_kses_post( $params['content'] ),
		];

		if ( ! empty( $params['category_id'] ) ) {
			$form_data['custom_post_tax_id'] = absint( $params['category_id'] );
		}

		if ( ! empty( $params['space_id'] ) ) {
			$form_data['custom_post_space_selection'] = absint( $params['space_id'] );
		}

		$this->setup_post_data(
			[
				'formData' => $this->encode_json_for_router( $form_data ),
			]
		);

		// The router's success payload is only a message, so the new post ID
		// comes from its action hook and lets agents chain follow-up calls.
		$request  = $this->build_request();
		$captured = $this->capture_id_from_action(
			'suredash_after_post_submit',
			function () use ( $request ) {
				return $this->call_json_handler(
					[ MiscRoute::get_instance(), 'submit_post' ],
					$request
				);
			}
		);

		$result = $captured['result'];

		$this->cleanup_post_data( [ 'formData' ] );

		if ( ! empty( $result['success'] ) && $captured['id'] ) {
			$result['post_id'] = $captured['id'];
		}

		return $result;
	}
}
