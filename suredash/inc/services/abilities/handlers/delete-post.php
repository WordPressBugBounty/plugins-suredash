<?php
/**
 * Delete Community Post Ability.
 *
 * @package SureDash
 * @since 1.6.3
 */

namespace SureDashboard\Inc\Services\Abilities\Handlers;

use SureDashboard\Core\Routers\Misc as MiscRoute;
use SureDashboard\Inc\Services\Abilities\Ability;

defined( 'ABSPATH' ) || exit;

/**
 * Delete_Post class.
 *
 * @since 1.6.3
 */
class Delete_Post extends Ability {
	/**
	 * Option gate key for ability permission control.
	 *
	 * @since 1.7.3
	 * @var string
	 */
	protected string $gated = 'suredash_abilities_api_delete';

	/**
	 * Get the unique identifier for this ability.
	 *
	 * @since 1.6.3
	 *
	 * @return string
	 */
	public function get_id(): string {
		return 'delete-post';
	}

	/**
	 * Get the human-readable name.
	 *
	 * @since 1.6.3
	 *
	 * @return string
	 */
	public function get_name(): string {
		return __( 'Delete Community Post', 'suredash' );
	}

	/**
	 * Get the ability description.
	 *
	 * @since 1.6.3
	 *
	 * @return string
	 */
	public function get_description(): string {
		return __( 'Permanently deletes a community/discussion post and all its comments. This action cannot be undone. Use content-action with "draft" to unpublish instead.', 'suredash' );
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
			'post_id' => [
				'type'        => 'integer',
				'required'    => true,
				'description' => __( 'ID of the community post to delete.', 'suredash' ),
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
			'destructiveHint' => true,
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
		return 'DESTRUCTIVE: Permanently deletes the post and all comments. Cannot be undone. Use content-action with "draft" to unpublish instead. Use list-community-posts to verify post_id before deleting.';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param array<string, mixed> $params Validated parameters.
	 */
	public function execute( array $params ): array {
		$post_id = absint( $params['post_id'] );

		// Target guard — the router rejects non-community post types too, but
		// with a generic "Invalid Post."; fail here naming the actual type, and
		// capture the title before it is gone so the success response can echo it.
		$target_error = $this->get_post_target_error( $post_id, SUREDASHBOARD_FEED_POST_TYPE, __( 'Post', 'suredash' ) );
		if ( $target_error !== null ) {
			return $target_error;
		}

		$post_title = get_the_title( $post_id );

		$request = $this->build_request();
		$request->set_param( 'id', $post_id );
		$request->set_url_params( [ 'id' => $post_id ] );

		$result = $this->call_json_handler(
			[ MiscRoute::get_instance(), 'delete_post' ],
			$request
		);

		if ( ! empty( $result['success'] ) && is_array( $result['data'] ?? null ) ) {
			$result['data']['deleted_id']    = $post_id;
			$result['data']['deleted_title'] = $post_title;
		}

		return $result;
	}
}
