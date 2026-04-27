<?php
/**
 * Create Comment Ability.
 *
 * @package SureDash
 * @since 1.6.3
 */

namespace SureDashboard\Inc\Services\Abilities\Handlers;

use SureDashboard\Core\Routers\Misc as MiscRoute;
use SureDashboard\Inc\Services\Abilities\Ability;

defined( 'ABSPATH' ) || exit;

/**
 * Create_Comment class.
 *
 * @since 1.6.3
 */
class Create_Comment extends Ability {
	/**
	 * Get the unique identifier for this ability.
	 *
	 * @since 1.6.3
	 *
	 * @return string
	 */
	public function get_id(): string {
		return 'create-comment';
	}

	/**
	 * Get the human-readable name.
	 *
	 * @since 1.6.3
	 *
	 * @return string
	 */
	public function get_name(): string {
		return __( 'Create Comment', 'suredash' );
	}

	/**
	 * Get the ability description.
	 *
	 * @since 1.6.3
	 *
	 * @return string
	 */
	public function get_description(): string {
		return __( 'Adds a comment to an existing community post. Supports replying to other comments via comment_parent.', 'suredash' );
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
			'comment'         => [
				'type'        => 'string',
				'required'    => true,
				'description' => __( 'The comment text/HTML content.', 'suredash' ),
			],
			'comment_post_ID' => [
				'type'        => 'integer',
				'required'    => true,
				'description' => __( 'ID of the post to comment on.', 'suredash' ),
			],
			'comment_parent'  => [
				'type'        => 'integer',
				'required'    => false,
				'default'     => 0,
				'description' => __( 'ID of the parent comment if this is a reply. 0 for top-level comments.', 'suredash' ),
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
		return 'Adds a comment to a community post. Use list-community-posts to find post IDs. Set comment_parent to reply to a specific comment (threaded replies). Content supports HTML.';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param array<string, mixed> $params Validated parameters.
	 */
	public function execute( array $params ): array {
		$this->setup_post_data(
			[
				'comment'         => $params['comment'],
				'comment_post_ID' => absint( $params['comment_post_ID'] ),
				'comment_parent'  => absint( $params['comment_parent'] ?? 0 ),
				'depth'           => 0,
			]
		);

		$request = $this->build_request();
		$result  = $this->call_json_handler(
			[ MiscRoute::get_instance(), 'submit_comment' ],
			$request
		);

		$this->cleanup_post_data( [ 'comment', 'comment_post_ID', 'comment_parent', 'depth' ] );

		if ( empty( $result['success'] ) ) {
			return $result;
		}

		return [
			'success' => true,
			'data'    => [
				'message' => __( 'Comment added successfully.', 'suredash' ),
			],
		];
	}
}
