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
			'content_id'                  => [
				'type'        => 'integer',
				'required'    => true,
				'description' => __( 'The ID of the sub-content post to update (lesson, event, resource, or collection item).', 'suredash' ),
			],
			'post_title'                  => [
				'type'        => 'string',
				'required'    => false,
				'description' => __( 'New title for the content item.', 'suredash' ),
			],
			'post_status'                 => [
				'type'        => 'string',
				'required'    => false,
				'description' => __( 'New status for the content item.', 'suredash' ),
				'enum'        => [ 'publish', 'draft' ],
			],
			'lesson_duration'             => [
				'type'        => 'string',
				'required'    => false,
				'description' => __( 'Lesson duration in minutes. Only for lessons.', 'suredash' ),
			],
			'resource_type'               => [
				'type'        => 'string',
				'required'    => false,
				'description' => __( 'Resource type: "upload" or "external". Only for resources.', 'suredash' ),
				'enum'        => [ 'upload', 'external' ],
			],
			'attachment_id'               => [
				'type'        => 'integer',
				'required'    => false,
				'description' => __( 'WordPress media attachment ID. Only for resources with type "upload".', 'suredash' ),
			],
			'external_url'                => [
				'type'        => 'string',
				'required'    => false,
				'description' => __( 'External URL. Only for resources with type "external".', 'suredash' ),
			],
			'event_date'                  => [
				'type'        => 'string',
				'required'    => false,
				'description' => __( 'Event date in YYYY-MM-DD format. Only for events.', 'suredash' ),
			],
			'event_start_time'            => [
				'type'        => 'string',
				'required'    => false,
				'description' => __( 'Event start time in HH:MM format (24-hour). Only for events.', 'suredash' ),
			],
			'event_timezone'              => [
				'type'        => 'string',
				'required'    => false,
				'description' => __( 'Timezone identifier (e.g. "America/New_York"). Only for events.', 'suredash' ),
			],
			'rsvp_link'                   => [
				'type'        => 'string',
				'required'    => false,
				'description' => __( 'RSVP link URL. Only for events.', 'suredash' ),
			],
			'event_joining_link'          => [
				'type'        => 'string',
				'required'    => false,
				'description' => __( 'Live event joining link URL. Only for events.', 'suredash' ),
			],
			'recorded_video_link'         => [
				'type'        => 'string',
				'required'    => false,
				'description' => __( 'Recorded video link URL. Only for events.', 'suredash' ),
			],

			// Quiz fields. Only for quiz content items (content_type = "quiz").
			'quiz_has_passing'            => [
				'type'        => 'boolean',
				'required'    => false,
				'description' => __( 'Master toggle for the quiz scoring/pass behavior. When false, the quiz collects answers without a pass mark or progression gate.', 'suredash' ),
			],
			'quiz_pass_mark'              => [
				'type'        => 'integer',
				'required'    => false,
				'minimum'     => 0,
				'maximum'     => 100,
				'description' => __( 'Pass mark as a percentage (0-100). Only meaningful when quiz_has_passing is true.', 'suredash' ),
			],
			'quiz_enforce_pass'           => [
				'type'        => 'boolean',
				'required'    => false,
				'description' => __( 'When true, the next lesson is locked until the learner passes this quiz. Requires quiz_has_passing = true.', 'suredash' ),
			],
			'quiz_hide_answers_on_result' => [
				'type'        => 'boolean',
				'required'    => false,
				'description' => __( 'When true, the learner does not see which options were correct after submitting — only their score.', 'suredash' ),
			],
			'quiz_questions'              => [
				'type'        => 'array',
				'required'    => false,
				'description' => __( 'Full list of quiz questions. Replaces existing questions in full. Each question may have an attachment ID for an image and short help text.', 'suredash' ),
				'items'       => [
					'type'       => 'object',
					'properties' => [
						'id'       => [
							'type'        => 'string',
							'description' => __( 'Stable question ID. Generate as "q_xxx" if creating new. Reuse existing IDs when editing.', 'suredash' ),
						],
						'type'     => [
							'type'        => 'string',
							'enum'        => [ 'single', 'multiple' ],
							'description' => __( '"single" for one-correct-answer, "multiple" for multi-select.', 'suredash' ),
						],
						'text'     => [
							'type'        => 'string',
							'description' => __( 'The question prompt shown to learners.', 'suredash' ),
						],
						'helptext' => [
							'type'        => 'string',
							'description' => __( 'Optional secondary text shown under the question prompt.', 'suredash' ),
						],
						'image_id' => [
							'type'        => 'integer',
							'description' => __( 'Optional WordPress attachment ID for an image shown with the question.', 'suredash' ),
						],
						'options'  => [
							'type'        => 'array',
							'description' => __( 'Answer options. At least one option must have is_correct = true.', 'suredash' ),
							'items'       => [
								'type'       => 'object',
								'properties' => [
									'id'         => [
										'type'        => 'string',
										'description' => __( 'Stable option ID, e.g. "opt_xxx".', 'suredash' ),
									],
									'text'       => [
										'type'        => 'string',
										'description' => __( 'Option label.', 'suredash' ),
									],
									'is_correct' => [
										'type'        => 'boolean',
										'description' => __( 'Whether this option is a correct answer.', 'suredash' ),
									],
								],
							],
						],
					],
				],
			],
			'event_location_type'         => [
				'type'        => 'string',
				'required'    => false,
				'enum'        => [ 'in_person', 'tbd', 'online', '' ],
				'description' => __( 'Event location type: in_person, tbd, online, or empty. Only for events.', 'suredash' ),
			],
			'event_location_address'      => [
				'type'        => 'string',
				'required'    => false,
				'description' => __( 'Human-readable address for in-person events. Only used when event_location_type is in_person.', 'suredash' ),
			],
			'event_end_date'              => [
				'type'        => 'string',
				'required'    => false,
				'description' => __( 'Event end date in YYYY-MM-DD format. Only for events.', 'suredash' ),
			],
			'event_end_time'              => [
				'type'        => 'string',
				'required'    => false,
				'description' => __( 'Event end time in HH:MM format (24-hour). Only for events.', 'suredash' ),
			],
			'event_host'                  => [
				'type'        => 'integer',
				'required'    => false,
				'description' => __( 'WP user ID of the portal manager hosting the event. Only for events.', 'suredash' ),
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
			'event_end_date',
			'event_start_time',
			'event_end_time',
			'event_timezone',
			'event_host',
			'rsvp_link',
			'event_joining_link',
			'recorded_video_link',
			'quiz_has_passing',
			'quiz_pass_mark',
			'quiz_enforce_pass',
			'quiz_hide_answers_on_result',
			'quiz_questions',
			'event_location_type',
			'event_location_address',
		];

		foreach ( $allowed_fields as $field ) {
			if ( isset( $params[ $field ] ) ) {
				$post_data[ $field ] = $params[ $field ];
			}
		}

		// save_content_meta_fields() expects quiz_questions as either an array or a JSON
		// string. When invoked through the Abilities API the value already arrives as a
		// PHP array; setup_post_data() flattens scalars, so re-encode arrays as JSON to
		// preserve nesting through $_POST.
		if ( isset( $post_data['quiz_questions'] ) && is_array( $post_data['quiz_questions'] ) ) {
			$post_data['quiz_questions'] = wp_json_encode( $post_data['quiz_questions'] );
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
