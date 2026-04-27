<?php
/**
 * React to Entity Ability (Like/Unlike).
 *
 * @package SureDash
 * @since 1.6.3
 */

namespace SureDashboard\Inc\Services\Abilities\Handlers;

use SureDashboard\Core\Routers\Misc as MiscRoute;
use SureDashboard\Inc\Services\Abilities\Ability;

defined( 'ABSPATH' ) || exit;

/**
 * React_To_Entity class.
 *
 * Toggles a like reaction on a post or comment.
 *
 * @since 1.6.3
 */
class React_To_Entity extends Ability {
	/**
	 * Get the unique identifier for this ability.
	 *
	 * @since 1.6.3
	 *
	 * @return string
	 */
	public function get_id(): string {
		return 'react-to-entity';
	}

	/**
	 * Get the human-readable name.
	 *
	 * @since 1.6.3
	 *
	 * @return string
	 */
	public function get_name(): string {
		return __( 'React to Post or Comment', 'suredash' );
	}

	/**
	 * Get the ability description.
	 *
	 * @since 1.6.3
	 *
	 * @return string
	 */
	public function get_description(): string {
		return __( 'Toggles a like reaction on a community post or comment. If the current user has not liked it, this adds a like. If already liked, it removes the like (unlike).', 'suredash' );
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
			'entity_id' => [
				'type'        => 'integer',
				'required'    => true,
				'description' => __( 'The ID of the post or comment to react to.', 'suredash' ),
			],
			'entity'    => [
				'type'        => 'string',
				'required'    => false,
				'default'     => 'post',
				'enum'        => [ 'post', 'comment' ],
				'description' => __( 'Entity type — "post" for community posts, "comment" for comments.', 'suredash' ),
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
				'like_status' => [
					'type'        => 'string',
					'description' => __( '"liked" or "unliked" indicating the new state.', 'suredash' ),
				],
				'like_count'  => [
					'type'        => 'integer',
					'description' => __( 'Total number of likes after the action.', 'suredash' ),
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
		return 'Toggles like on a post or comment — calling twice reverts the action. Use list-community-posts to find post IDs. Set entity to "comment" for comment likes. Returns the new like_status and like_count.';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param array<string, mixed> $params Ability parameters.
	 * @return array<string, mixed>
	 */
	public function execute( array $params ): array {
		$this->setup_post_data(
			[
				'entity_id' => absint( $params['entity_id'] ),
				'entity'    => $params['entity'] ?? 'post',
			]
		);

		$request = $this->build_request();
		$result  = $this->call_json_handler(
			[ MiscRoute::get_instance(), 'entity_reaction' ],
			$request
		);

		$this->cleanup_post_data( [ 'entity_id', 'entity' ] );

		return $result;
	}
}
