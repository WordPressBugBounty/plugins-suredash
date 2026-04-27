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
		return 'Creates a new space in draft status by default. Always call list-groups first and ask the user which group to place the space in before creating. Returns the new space_id. Follow up with update-space-settings to configure further, then use content-action or set space_status to "publish" to make it live.';
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

		$form_data = [
			'item_title'            => $params['title'],
			'integration'           => $integration,
			'space_description'     => $params['space_description'] ?? '',
			'item_emoji'            => $params['item_emoji'] ?? 'Link',
			'allow_members_to_post' => $params['allow_members_to_post'] ?? false,
			'hidden_space'          => $params['hidden_space'] ?? false,
			'space_status'          => $params['space_status'] ?? 'draft',
		];

		$group_id = intval( $params['group_id'] ?? 0 );

		if ( $group_id > 0 ) {
			$form_data['category'] = $group_id;
		} else {
			// Create or use uncategorized group.
			$form_data['category'] = $this->get_or_create_default_group();
		}

		$this->setup_post_data(
			[
				'formData' => wp_json_encode( $form_data ),
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

		$data = $result['data'];

		return [
			'success' => true,
			'data'    => [
				'space_id'  => $data['space_id'] ?? 0,
				'message'   => $data['message'] ?? '',
				'status'    => $data['meta']['post_status'] ?? 'draft',
				'permalink' => $data['meta']['permalink'] ?? '',
			],
		];
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
