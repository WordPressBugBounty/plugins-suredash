<?php
/**
 * Edit Community Post Ability.
 *
 * @package SureDash
 * @since 1.6.3
 */

namespace SureDashboard\Inc\Services\Abilities\Handlers;

use SureDashboard\Core\Routers\Misc as MiscRoute;
use SureDashboard\Inc\Services\Abilities\Ability;

defined( 'ABSPATH' ) || exit;

/**
 * Edit_Post class.
 *
 * @since 1.6.3
 */
class Edit_Post extends Ability {
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
		return 'edit-post';
	}

	/**
	 * Get the human-readable name.
	 *
	 * @since 1.6.3
	 *
	 * @return string
	 */
	public function get_name(): string {
		return __( 'Edit Community Post', 'suredash' );
	}

	/**
	 * Get the ability description.
	 *
	 * @since 1.6.3
	 *
	 * @return string
	 */
	public function get_description(): string {
		return __( 'Updates an existing community/discussion post. Can change the title and/or content. Both fields are currently required — fetch the post first if you only need to update one field.', 'suredash' );
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
			'post_id'      => [
				'type'        => 'integer',
				'required'    => true,
				'description' => __( 'ID of the community post to edit.', 'suredash' ),
			],
			'post_title'   => [
				'type'        => 'string',
				'required'    => true,
				'description' => __( 'Updated title for the post.', 'suredash' ),
			],
			'post_content' => [
				'type'        => 'string',
				'required'    => true,
				'description' => __( 'Updated HTML content for the post.', 'suredash' ),
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
		return 'Updates a community post. Both post_title and post_content are required — use list-community-posts to get current values if you only want to change one field. Use content-action to change post status instead.';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param array<string, mixed> $params Validated parameters.
	 */
	public function execute( array $params ): array {
		$this->setup_post_data(
			[
				'post_id'      => absint( $params['post_id'] ),
				'post_title'   => sanitize_text_field( $params['post_title'] ),
				'post_content' => wp_kses_post( $params['post_content'] ),
			]
		);

		$request = $this->build_request();
		$result  = $this->call_json_handler(
			[ MiscRoute::get_instance(), 'edit_post' ],
			$request
		);

		$this->cleanup_post_data( [ 'post_id', 'post_title', 'post_content' ] );

		return $result;
	}
}
