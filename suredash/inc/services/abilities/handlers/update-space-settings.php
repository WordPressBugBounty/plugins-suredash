<?php
/**
 * Update Space Settings Ability.
 *
 * @package SureDash
 * @since 1.6.3
 */

namespace SureDashboard\Inc\Services\Abilities\Handlers;

use SureDashboard\Inc\Services\Abilities\Ability;

defined( 'ABSPATH' ) || exit;

/**
 * Update_Space_Settings class.
 *
 * @since 1.6.3
 */
class Update_Space_Settings extends Ability {
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
		return 'update-space-settings';
	}

	/**
	 * Get the human-readable name.
	 *
	 * @since 1.6.3
	 *
	 * @return string
	 */
	public function get_name(): string {
		return __( 'Update Space Settings', 'suredash' );
	}

	/**
	 * Get the ability description.
	 *
	 * @since 1.6.3
	 *
	 * @return string
	 */
	public function get_description(): string {
		return __( 'Updates settings for a specific space. Only provided fields are changed — existing settings are preserved. Supports title, status, description, icon, privacy, visibility, comments, like/share buttons, layout, and member posting permissions.', 'suredash' );
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
			'content_id'            => [
				'type'        => 'integer',
				'required'    => true,
				'description' => __( 'The ID of the space to update.', 'suredash' ),
			],
			'post_title'            => [
				'type'        => 'string',
				'required'    => false,
				'description' => __( 'New title for the space.', 'suredash' ),
			],
			'space_description'     => [
				'type'        => 'string',
				'required'    => false,
				'description' => __( 'UI label: "Space Description". Short description shown in home page grid view.', 'suredash' ),
			],
			'item_emoji'            => [
				'type'        => 'string',
				'required'    => false,
				'description' => __( 'Icon name for the space.', 'suredash' ),
			],
			'allow_members_to_post' => [
				'type'        => 'boolean',
				'required'    => false,
				'description' => __( 'UI label: "Allow Members to Post". Whether members can create posts in this space. Only applies to posts_discussion spaces.', 'suredash' ),
			],
			'private_forum'         => [
				'type'        => 'boolean',
				'required'    => false,
				'description' => __( 'UI label: "Make Discussions Private". Makes all discussions private between admins and the topic creator. Only applies to posts_discussion spaces with allow_members_to_post enabled.', 'suredash' ),
			],
			'hidden_space'          => [
				'type'        => 'boolean',
				'required'    => false,
				'description' => __( 'UI label: "Make the Space Hidden". Hides space from frontend sidebar navigation.', 'suredash' ),
			],
			'hide_from_search'      => [
				'type'        => 'boolean',
				'required'    => false,
				'description' => __( 'UI label: "Hide from Search Results". Hides this hidden space from search block results. Only applies when hidden_space is true.', 'suredash' ),
			],
			'comments'              => [
				'type'        => 'boolean',
				'required'    => false,
				'description' => __( 'UI label: "Allow Comments". Enable or disable comments on this space.', 'suredash' ),
			],
			'show_like_button'      => [
				'type'        => 'boolean',
				'required'    => false,
				'description' => __( 'UI label: "Enable Like Button". Shows like button on posts/lessons in this space.', 'suredash' ),
			],
			'show_share_button'     => [
				'type'        => 'boolean',
				'required'    => false,
				'description' => __( 'UI label: "Enable Share Button". Shows share button on posts/lessons in this space.', 'suredash' ),
			],
			'default_list_view'     => [
				'type'        => 'boolean',
				'required'    => false,
				'description' => __( 'UI label: "Default to List View". Shows posts in list view by default. Only applies to posts_discussion spaces.', 'suredash' ),
			],
			'post_content_type'     => [
				'type'        => 'string',
				'required'    => false,
				'description' => __( 'UI label: "Content Preview". Controls how post content is previewed. Only applies to posts_discussion spaces.', 'suredash' ),
				'enum'        => [ 'full', 'excerpt' ],
			],
			'layout'                => [
				'type'        => 'string',
				'required'    => false,
				'description' => __( 'UI label: "Container Width". Controls the width of the space container. Not applicable to course spaces.', 'suredash' ),
				'enum'        => [ 'global', 'normal', 'narrow', 'full_width' ],
			],
			'content_layout_style'  => [
				'type'        => 'string',
				'required'    => false,
				'description' => __( 'UI label: "Content Layout Style". Controls how content is displayed. Only applies to collection, resource_library, and events spaces.', 'suredash' ),
				'enum'        => [ 'list', 'stacked', 'grid' ],
			],
			'space_status'          => [
				'type'        => 'string',
				'required'    => false,
				'description' => __( 'New status for the space.', 'suredash' ),
				'enum'        => [ 'publish', 'draft' ],
			],
			'single_post_id'        => [
				'type'        => 'integer',
				'required'    => false,
				'description' => __( 'WordPress post/page ID to display in a single_post space.', 'suredash' ),
			],
			'post_render_type'      => [
				'type'        => 'string',
				'required'    => false,
				'description' => __( 'Render type for single_post spaces. Use "wordpress" to render the linked post/page.', 'suredash' ),
				'enum'        => [ 'blank', 'wordpress' ],
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
		return 'Only provided fields are updated — omitted fields stay unchanged. Use get-space-meta first to see current values. Use content_id (the space post ID) from list-spaces. Use space_status to change publish/draft state.';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param array<string, mixed> $params Validated parameters.
	 */
	public function execute( array $params ): array {
		$post_id = absint( $params['content_id'] ?? 0 );

		if ( ! $post_id ) {
			return [
				'success' => false,
				'data'    => [ 'message' => __( 'Content ID is required.', 'suredash' ) ],
			];
		}

		$post = get_post( $post_id );
		if ( ! $post || $post->post_type !== SUREDASHBOARD_POST_TYPE ) {
			return [
				'success' => false,
				'data'    => [ 'message' => __( 'Space not found.', 'suredash' ) ],
			];
		}

		// Premium space types require SureDash Pro.
		$pro_check = $this->require_pro_for_space( $post_id );
		if ( is_wp_error( $pro_check ) ) {
			return $this->get_pro_required_error( (string) sd_get_post_meta( $post_id, 'integration', true ) );
		}

		$updated_fields = [];

		// Update post title.
		if ( isset( $params['post_title'] ) ) {
			$title     = sanitize_text_field( $params['post_title'] );
			$post_name = sanitize_title( $title );
			wp_update_post(
				[
					'ID'         => $post_id,
					'post_title' => $title,
					'post_name'  => $post_name,
				]
			);
			$updated_fields[] = 'post_title';
		}

		// Update space status.
		if ( isset( $params['space_status'] ) && in_array( $params['space_status'], [ 'publish', 'draft' ], true ) ) {
			wp_update_post(
				[
					'ID'          => $post_id,
					'post_status' => sanitize_text_field( $params['space_status'] ),
				]
			);
			$updated_fields[] = 'space_status';
		}

		// Meta fields that map directly to post meta.
		$meta_fields = [
			'space_description',
			'item_emoji',
			'allow_members_to_post',
			'private_forum',
			'hidden_space',
			'hide_from_search',
			'show_like_button',
			'show_share_button',
			'default_list_view',
			'post_content_type',
			'layout',
			'content_layout_style',
		];

		foreach ( $meta_fields as $field ) {
			if ( isset( $params[ $field ] ) ) {
				sd_update_post_meta( $post_id, $field, $params[ $field ] );
				$updated_fields[] = $field;
			}
		}

		// Comments toggle — also updates WP comment_status.
		if ( isset( $params['comments'] ) ) {
			$comments_enabled = (bool) $params['comments'];
			update_post_meta( $post_id, 'comments', $comments_enabled );
			wp_update_post(
				[
					'ID'             => $post_id,
					'comment_status' => $comments_enabled ? 'open' : 'closed',
				]
			);
			$updated_fields[] = 'comments';
		}

		// Single post space fields.
		if ( isset( $params['single_post_id'] ) ) {
			sd_update_post_meta( $post_id, 'single_post_id', absint( $params['single_post_id'] ) );
			$updated_fields[] = 'single_post_id';
		}

		if ( isset( $params['post_render_type'] ) ) {
			sd_update_post_meta( $post_id, 'post_render_type', sanitize_text_field( $params['post_render_type'] ) );
			$updated_fields[] = 'post_render_type';
		}

		return [
			'success' => true,
			'data'    => [
				'message'        => __( 'Settings updated successfully.', 'suredash' ),
				'updated_fields' => $updated_fields,
			],
		];
	}
}
