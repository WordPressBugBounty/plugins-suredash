<?php
/**
 * Update Content Settings Ability.
 *
 * @package SureDash
 * @since 1.6.3
 */

namespace SureDashboard\Inc\Services\Abilities\Handlers;

use SureDashboard\Core\Routers\Backend as BackendRoute;
use SureDashboard\Inc\Services\Abilities\Ability;

defined( 'ABSPATH' ) || exit;

/**
 * Update_Content_Settings class.
 *
 * @since 1.6.3
 */
class Update_Content_Settings extends Ability {
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
		return 'update-content-settings';
	}

	/**
	 * Get the human-readable name.
	 *
	 * @since 1.6.3
	 *
	 * @return string
	 */
	public function get_name(): string {
		return __( 'Update Content Settings', 'suredash' );
	}

	/**
	 * Get the ability description.
	 *
	 * @since 1.6.3
	 *
	 * @return string
	 */
	public function get_description(): string {
		return __( 'Updates title, status, and meta fields on an existing sub-content item (lesson, event, resource, or collection item). Only provided fields are changed — existing values are preserved.', 'suredash' );
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
			'content_id'          => [
				'type'        => 'integer',
				'required'    => true,
				'description' => __( 'The ID of the sub-content post to update (lesson, event, resource, or collection item).', 'suredash' ),
			],
			'post_title'          => [
				'type'        => 'string',
				'required'    => false,
				'description' => __( 'New title for the content item.', 'suredash' ),
			],
			'post_status'         => [
				'type'        => 'string',
				'required'    => false,
				'description' => __( 'New status for the content item.', 'suredash' ),
				'enum'        => [ 'publish', 'draft' ],
			],
			'lesson_duration'     => [
				'type'        => 'string',
				'required'    => false,
				'description' => __( 'Lesson duration in minutes. Only for lessons.', 'suredash' ),
			],
			'resource_type'       => [
				'type'        => 'string',
				'required'    => false,
				'description' => __( 'Resource type: "upload" or "external". Only for resources.', 'suredash' ),
				'enum'        => [ 'upload', 'external' ],
			],
			'attachment_id'       => [
				'type'        => 'integer',
				'required'    => false,
				'description' => __( 'WordPress media attachment ID. Only for resources with type "upload".', 'suredash' ),
			],
			'external_url'        => [
				'type'        => 'string',
				'required'    => false,
				'description' => __( 'External URL. Only for resources with type "external".', 'suredash' ),
			],
			'event_date'          => [
				'type'        => 'string',
				'required'    => false,
				'description' => __( 'Event date in YYYY-MM-DD format. Only for events.', 'suredash' ),
			],
			'event_start_time'    => [
				'type'        => 'string',
				'required'    => false,
				'description' => __( 'Event start time in HH:MM format (24-hour). Only for events.', 'suredash' ),
			],
			'event_duration'      => [
				'type'        => 'string',
				'required'    => false,
				'description' => __( 'Event duration in minutes. Only for events.', 'suredash' ),
			],
			'event_timezone'      => [
				'type'        => 'string',
				'required'    => false,
				'description' => __( 'Timezone identifier (e.g. "America/New_York"). Only for events.', 'suredash' ),
			],
			'rsvp_link'           => [
				'type'        => 'string',
				'required'    => false,
				'description' => __( 'RSVP link URL. Only for events.', 'suredash' ),
			],
			'event_joining_link'  => [
				'type'        => 'string',
				'required'    => false,
				'description' => __( 'Live event joining link URL. Only for events.', 'suredash' ),
			],
			'recorded_video_link' => [
				'type'        => 'string',
				'required'    => false,
				'description' => __( 'Recorded video link URL. Only for events.', 'suredash' ),
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
		return 'Updates an existing sub-content item (lesson, event, resource, or collection item). Only provided fields are changed. Use list-course-content or list-space-content to find content IDs. Provide only the fields relevant to the content type — event fields for events, resource fields for resources, etc.';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param array<string, mixed> $params Validated parameters.
	 */
	public function execute( array $params ): array {
		$content_id = absint( $params['content_id'] ?? 0 );

		if ( ! $content_id ) {
			return [
				'success' => false,
				'data'    => [ 'message' => __( 'Content ID is required.', 'suredash' ) ],
			];
		}

		// Build POST data from provided params only.
		$post_data = [ 'content_id' => $content_id ];

		$allowed_fields = [
			'post_title',
			'post_status',
			'lesson_duration',
			'resource_type',
			'attachment_id',
			'external_url',
			'event_date',
			'event_start_time',
			'event_duration',
			'event_timezone',
			'rsvp_link',
			'event_joining_link',
			'recorded_video_link',
		];

		foreach ( $allowed_fields as $field ) {
			if ( isset( $params[ $field ] ) ) {
				$post_data[ $field ] = $params[ $field ];
			}
		}

		$this->setup_post_data( $post_data );

		$request = $this->build_request();
		$result  = BackendRoute::get_instance()->update_content_settings( $request );

		$this->cleanup_post_data( array_keys( $post_data ) );

		return is_array( $result ) ? $result : [
			'success' => false,
			'data'    => [ 'message' => __( 'Unexpected response.', 'suredash' ) ],
		];
	}
}
