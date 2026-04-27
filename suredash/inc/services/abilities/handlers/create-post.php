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
				'description' => __( 'Space ID to create the post in. The post will be assigned to the forum category linked to this space.', 'suredash' ),
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
		return 'IMPORTANT: Use this tool (not create-post-for-space) whenever the user asks to create a post in a discussion space. Call list-spaces first to find the space_id. Content supports HTML. Only use create-post-for-space for course lessons, resource library files, or collection items — never for discussion posts.';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param array<string, mixed> $params Validated parameters.
	 */
	public function execute( array $params ): array {
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
				'formData' => wp_json_encode( $form_data ),
			]
		);

		$request = $this->build_request();
		$result  = $this->call_json_handler(
			[ MiscRoute::get_instance(), 'submit_post' ],
			$request
		);

		$this->cleanup_post_data( [ 'formData' ] );

		return $result;
	}
}
