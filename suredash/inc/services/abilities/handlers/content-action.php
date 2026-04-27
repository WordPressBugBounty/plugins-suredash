<?php
/**
 * Content Action Ability.
 *
 * @package SureDash
 * @since 1.6.3
 */

namespace SureDashboard\Inc\Services\Abilities\Handlers;

use SureDashboard\Core\Routers\Backend as BackendRoute;
use SureDashboard\Inc\Services\Abilities\Ability;

defined( 'ABSPATH' ) || exit;

/**
 * Content_Action class.
 *
 * @since 1.6.3
 */
class Content_Action extends Ability {
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
		return 'content-action';
	}

	/**
	 * Get the human-readable name.
	 *
	 * @since 1.6.3
	 *
	 * @return string
	 */
	public function get_name(): string {
		return __( 'Content Action', 'suredash' );
	}

	/**
	 * Get the ability description.
	 *
	 * @since 1.6.3
	 *
	 * @return string
	 */
	public function get_description(): string {
		return __( 'Performs a status action on a community post — publish, draft, delete, or duplicate. The delete action is permanent. Use "draft" to unpublish without deleting.', 'suredash' );
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
			'action'  => [
				'type'        => 'string',
				'required'    => true,
				'description' => __( 'The action to perform on the content.', 'suredash' ),
				'enum'        => [ 'publish', 'draft', 'delete', 'duplicate' ],
			],
			'post_id' => [
				'type'        => 'integer',
				'required'    => true,
				'description' => __( 'ID of the community post to act on.', 'suredash' ),
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
		return 'Performs status changes on community posts. "publish" and "draft" are safe and reversible. "delete" is DESTRUCTIVE and permanent. "duplicate" creates a copy. Use list-community-posts to verify the post_id. For space-level status changes, use update-space-settings instead.';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param array<string, mixed> $params Validated parameters.
	 */
	public function execute( array $params ): array {
		$this->setup_post_data(
			[
				'action'    => sanitize_text_field( $params['action'] ),
				'post_id'   => absint( $params['post_id'] ),
				'post_type' => SUREDASHBOARD_FEED_POST_TYPE,
			]
		);

		$request = $this->build_request();
		$result  = $this->call_json_handler(
			[ BackendRoute::get_instance(), 'content_action' ],
			$request
		);

		$this->cleanup_post_data( [ 'action', 'post_id', 'post_type' ] );

		if ( empty( $result['success'] ) || empty( $result['data'] ) ) {
			return $result;
		}

		$data     = $result['data'];
		$response = [
			'message' => $data['message'] ?? '',
			'post_id' => absint( $params['post_id'] ),
			'action'  => $params['action'],
		];

		// Include duplicated post ID when action is 'duplicate'.
		if ( $params['action'] === 'duplicate' && ! empty( $data['post']['id'] ) ) {
			$response['duplicated_post_id'] = $data['post']['id'];
		}

		return [
			'success' => true,
			'data'    => $response,
		];
	}
}
