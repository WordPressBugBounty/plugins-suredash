<?php
/**
 * Create Space Ability.
 *
 * @package SureDash
 * @since 1.6.3
 */

namespace SureDashboard\Inc\Services\Abilities\Handlers;

use SureDashboard\Core\Routers\Backend as BackendRoute;
use SureDashboard\Inc\Services\Abilities\Ability;

defined( 'ABSPATH' ) || exit;

/**
 * Create_Space class.
 *
 * @since 1.6.3
 */
class Create_Space extends Ability {
	/**
	 * Get the unique identifier for this ability.
	 *
	 * @since 1.6.3
	 *
	 * @return string
	 */
	public function get_id(): string {
		return 'create-space';
	}

	/**
	 * Get the human-readable name.
	 *
	 * @since 1.6.3
	 *
	 * @return string
	 */
	public function get_name(): string {
		return __( 'Create Space', 'suredash' );
	}

	/**
	 * Get the ability description.
	 *
	 * @since 1.6.3
	 *
	 * @return string
	 */
	public function get_description(): string {
		return __( 'Creates a new portal space. Before calling this, you MUST call list-groups to show available groups and ask the user which group to place the space in. Spaces can be discussions, courses, resource libraries, collections, or event spaces.', 'suredash' );
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
			'title'                 => [
				'type'        => 'string',
				'required'    => true,
				'description' => __( 'The name/title of the space.', 'suredash' ),
			],
			'integration'           => [
				'type'        => 'string',
				'required'    => true,
				'description' => __( 'Space type determining the content model.', 'suredash' ),
				'enum'        => [ 'single_post', 'posts_discussion', 'link', 'course', 'resource_library', 'collection', 'events' ],
			],
			'group_id'              => [
				'type'        => 'integer',
				'required'    => true,
				'description' => __( 'Group ID to assign the space to. Call list-groups first and ask the user to choose a group.', 'suredash' ),
			],
			'space_description'     => [
				'type'        => 'string',
				'required'    => false,
				'default'     => '',
				'description' => __( 'Short description of the space.', 'suredash' ),
			],
			'item_emoji'            => [
				'type'        => 'string',
				'required'    => false,
				'default'     => 'Link',
				'description' => __( 'Icon name for the space.', 'suredash' ),
			],
			'allow_members_to_post' => [
				'type'        => 'boolean',
				'required'    => false,
				'default'     => false,
				'description' => __( 'Whether members can create posts in this space.', 'suredash' ),
			],
			'hidden_space'          => [
				'type'        => 'boolean',
				'required'    => false,
				'default'     => false,
				'description' => __( 'Hide space from navigation sidebar.', 'suredash' ),
			],
			'space_status'          => [
				'type'        => 'string',
				'required'    => false,
				'default'     => 'draft',
				'description' => __( 'Post status for the space.', 'suredash' ),
				'enum'        => [ 'publish', 'draft' ],
			],
			'link_url'              => [
				'type'        => 'string',
				'required'    => false,
				'format'      => 'uri',
				'description' => __( 'Destination URL for a link space. REQUIRED when integration is "link" — a link space without a URL is a dead sidebar entry. If the user did not provide the URL, ask them; do not guess.', 'suredash' ),
			],
			'single_post_id'        => [
				'type'        => 'integer',
				'required'    => false,
				'minimum'     => 1,
				'description' => __( 'WordPress post/page ID a single_post space displays. REQUIRED when integration is "single_post". Without a target the space renders blank. Call list-wp-posts to find the ID by title; if the user did not say which post, ask them.', 'suredash' ),
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
				'space_id' => [
					'type'        => 'integer',
					'description' => __( 'ID of the created space.', 'suredash' ),
				],
				'message'  => [
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
		return 'Creates a new space in draft status by default. Always call list-groups first and ask the user which group to place the space in before creating. COMPLETENESS: integration "link" requires link_url, and integration "single_post" requires single_post_id (call list-wp-posts to look the ID up by title). If the user did not provide the target, ask them instead of guessing; incomplete calls are rejected and NO space is created. Returns the new space_id plus the stored link_url or single_post target, which you should verify. Follow up with update-space-settings to configure further, then use content-action or set space_status to "publish" to make it live.';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param array<string, mixed> $params Validated parameters.
	 */
	public function execute( array $params ): array {
		$integration = sanitize_text_field( $params['integration'] );

		// Premium space types require SureDash Pro.
		if ( $this->is_pro_integration( $integration ) ) {
			if ( ! function_exists( 'suredash_is_pro_active' ) || ! suredash_is_pro_active() ) {
				return $this->get_pro_required_error( $integration );
			}
		}

		// Link and single_post spaces are broken without their target, so require
		// it before anything is created. The URL format itself is enforced by
		// the schema's "uri" format; only presence is integration-specific.
		$link_url       = $integration === 'link' ? esc_url_raw( trim( (string) ( $params['link_url'] ?? '' ) ) ) : '';
		$single_post_id = $integration === 'single_post' ? absint( $params['single_post_id'] ?? 0 ) : 0;

		if ( $integration === 'link' && $link_url === '' ) {
			return $this->fail( __( 'Link spaces need link_url. Without it the space is a dead sidebar entry. Ask the user for the destination URL if they did not provide one. The space was NOT created.', 'suredash' ) );
		}

		if ( $integration === 'single_post' ) {
			if ( $single_post_id === 0 ) {
				return $this->fail( __( 'single_post spaces need single_post_id. Without a target the space renders blank. Call list-wp-posts to find the post/page ID, or ask the user which post the space should display. The space was NOT created.', 'suredash' ) );
			}

			$target = get_post( $single_post_id );
			if ( ! $target || $target->post_status === 'trash' ) {
				return $this->fail(
					sprintf(
						/* translators: %d: the provided post ID. */
						__( 'single_post_id %d does not exist. Call list-wp-posts to find the correct ID. The space was NOT created.', 'suredash' ),
						$single_post_id
					)
				);
			}
		}

		$form_data = [
			'item_title'            => $params['title'],
			'integration'           => $integration,
			'space_description'     => $params['space_description'] ?? '',
			'item_emoji'            => $params['item_emoji'] ?? 'Link',
			'allow_members_to_post' => $params['allow_members_to_post'] ?? false,
			'hidden_space'          => $params['hidden_space'] ?? false,
			'space_status'          => $params['space_status'] ?? 'draft',
		];

		// The router persists every unrecognised formData key as post meta, so
		// the link destination rides along instead of needing a second write.
		if ( $link_url !== '' ) {
			$form_data['link_url'] = $link_url;
		}

		$group_id = intval( $params['group_id'] ?? 0 );

		if ( $group_id > 0 ) {
			$form_data['category'] = $group_id;
		} else {
			// Create or use uncategorized group.
			$form_data['category'] = $this->get_or_create_default_group();
		}

		$this->setup_post_data(
			[
				'formData' => $this->encode_json_for_router( $form_data ),
			]
		);

		$request = $this->build_request();
		$result  = $this->call_json_handler(
			[ BackendRoute::get_instance(), 'create_space' ],
			$request
		);

		$this->cleanup_post_data( [ 'formData' ] );

		if ( empty( $result['success'] ) || empty( $result['data'] ) ) {
			return $result;
		}

		$data     = $result['data'];
		$space_id = absint( $data['space_id'] ?? 0 );

		$response = [
			'success' => true,
			'data'    => [
				'space_id'  => $space_id,
				'message'   => $data['message'] ?? '',
				'status'    => $data['meta']['post_status'] ?? 'draft',
				'permalink' => $data['meta']['permalink'] ?? '',
			],
		];

		// Echo the target back so agents can self-verify it was set.
		if ( $space_id > 0 && $link_url !== '' ) {
			$response['data']['link_url'] = (string) sd_get_post_meta( $space_id, 'link_url', true );
		}

		if ( $space_id > 0 && $single_post_id > 0 ) {
			$this->set_single_post_target( $space_id, $single_post_id );
			$response['data']['single_post'] = [
				'id'    => $single_post_id,
				'title' => get_the_title( $single_post_id ),
			];
		}

		return $response;
	}

	/**
	 * Get or create a default uncategorized group.
	 *
	 * @return int Term ID.
	 */
	private function get_or_create_default_group(): int {
		$terms = get_terms(
			[
				'taxonomy'   => SUREDASHBOARD_TAXONOMY,
				'hide_empty' => false,
				'number'     => 1,
				'orderby'    => 'term_id',
				'order'      => 'ASC',
			]
		);

		if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
			return $terms[0]->term_id;
		}

		$term = wp_insert_term( __( 'General', 'suredash' ), SUREDASHBOARD_TAXONOMY );

		if ( is_wp_error( $term ) ) {
			return 0;
		}

		return $term['term_id'];
	}
}
