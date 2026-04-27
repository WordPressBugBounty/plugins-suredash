<?php
/**
 * Get Space Meta Ability.
 *
 * @package SureDash
 * @since 1.6.3
 */

namespace SureDashboard\Inc\Services\Abilities\Handlers;

use SureDashboard\Core\Routers\Backend as BackendRoute;
use SureDashboard\Inc\Services\Abilities\Ability;

defined( 'ABSPATH' ) || exit;

/**
 * Get_Space_Meta class.
 *
 * @since 1.6.3
 */
class Get_Space_Meta extends Ability {
	/**
	 * Get the unique identifier for this ability.
	 *
	 * @since 1.6.3
	 *
	 * @return string
	 */
	public function get_id(): string {
		return 'get-space-meta';
	}

	/**
	 * Get the human-readable name.
	 *
	 * @since 1.6.3
	 *
	 * @return string
	 */
	public function get_name(): string {
		return __( 'Get Space Metadata', 'suredash' );
	}

	/**
	 * Get the ability description.
	 *
	 * @since 1.6.3
	 *
	 * @return string
	 */
	public function get_description(): string {
		return __( 'Retrieves metadata for a specific space including its type, status, permissions, and display settings. Returns a filtered set of configuration fields relevant for understanding and updating the space.', 'suredash' );
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
			'post_id' => [
				'type'        => 'integer',
				'required'    => true,
				'description' => __( 'The ID of the space to get metadata for.', 'suredash' ),
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
				'post_id'     => [
					'type'        => 'integer',
					'description' => __( 'Space ID.', 'suredash' ),
				],
				'integration' => [
					'type'        => 'string',
					'description' => __( 'Space type/integration.', 'suredash' ),
				],
				'post_status' => [
					'type'        => 'string',
					'description' => __( 'Current status.', 'suredash' ),
				],
			],
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
		return 'Returns space configuration. Check current values before calling update-space-settings. Use list-spaces to find space IDs.';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param array<string, mixed> $params Validated parameters.
	 */
	public function execute( array $params ): array {
		$this->setup_post_data(
			[
				'post_id' => absint( $params['post_id'] ),
			]
		);

		$request = $this->build_request();
		$result  = $this->call_json_handler(
			[ BackendRoute::get_instance(), 'get_post_meta' ],
			$request
		);

		$this->cleanup_post_data( [ 'post_id' ] );

		if ( empty( $result['success'] ) || empty( $result['data'] ) ) {
			return $result;
		}

		$meta     = $result['data'];
		$filtered = [
			'post_id'               => $meta['post_id'] ?? 0,
			'title'                 => get_the_title( $meta['post_id'] ?? 0 ),
			'integration'           => $meta['integration'] ?? '',
			'post_status'           => $meta['post_status'] ?? '',
			'permalink'             => $meta['permalink'] ?? '',
			'space_description'     => $meta['space_description'] ?? '',
			'item_emoji'            => $meta['item_emoji'] ?? '',
			'allow_members_to_post' => $meta['allow_members_to_post'] ?? false,
			'private_forum'         => $meta['private_forum'] ?? false,
			'hidden_space'          => $meta['hidden_space'] ?? false,
			'hide_from_search'      => $meta['hide_from_search'] ?? false,
			'comments'              => $meta['comments'] ?? false,
			'show_like_button'      => $meta['show_like_button'] ?? false,
			'show_share_button'     => $meta['show_share_button'] ?? false,
			'default_list_view'     => $meta['default_list_view'] ?? false,
			'post_content_type'     => $meta['post_content_type'] ?? 'full',
			'layout'                => $meta['layout'] ?? 'global',
			'content_layout_style'  => $meta['content_layout_style'] ?? 'list',
			'is_restricted'         => $meta['is_restricted'] ?? false,
		];

		if ( isset( $meta['feed_group_id'] ) ) {
			$filtered['feed_group_id'] = $meta['feed_group_id'];
		}

		return [
			'success' => true,
			'data'    => $filtered,
		];
	}
}
