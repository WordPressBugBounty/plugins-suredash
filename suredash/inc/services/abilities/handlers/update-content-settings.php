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
				'format'      => 'uri',
				'description' => __( 'External URL. Only for resources with type "external".', 'suredash' ),
			],
			'event_date'                  => [
				'type'        => 'string',
				'required'    => false,
				'format'      => 'date',
				'description' => __( 'Event date in YYYY-MM-DD format. Only for events.', 'suredash' ),
			],
			'event_start_time'            => [
				'type'        => 'string',
				'required'    => false,
				'format'      => 'time',
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
				'format'      => 'uri',
				'description' => __( 'RSVP link URL. Only for events.', 'suredash' ),
			],
			'event_joining_link'          => [
				'type'        => 'string',
				'required'    => false,
				'format'      => 'uri',
				'description' => __( 'Live event joining link URL. Only for events.', 'suredash' ),
			],
			'recorded_video_link'         => [
				'type'        => 'string',
				'required'    => false,
				'format'      => 'uri',
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
					'required'   => [ 'text', 'options' ],
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
							'description' => __( 'Answer options. Every option must state is_correct (true or false), and at least one option per question must be true.', 'suredash' ),
							'items'       => [
								'type'       => 'object',
								'required'   => [ 'text', 'is_correct' ],
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
				'format'      => 'date',
				'description' => __( 'Event end date in YYYY-MM-DD format. Only for events.', 'suredash' ),
			],
			'event_end_time'              => [
				'type'        => 'string',
				'required'    => false,
				'format'      => 'time',
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
		return 'Updates an existing sub-content item (lesson, event, resource, or collection item). Only provided fields are changed. Use list-course-content or list-space-content to find content IDs. Provide only the fields relevant to the content type — event fields for events, resource fields for resources, etc. When sending quiz_questions: every question MUST mark its correct answer(s) via is_correct: true — exactly one for "single" type questions. Any question lacking a correct option is rejected and nothing is saved, whether or not the quiz is scored. Verify the returned quiz_questions_summary shows the expected correct_count per question.';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param array<string, mixed> $params Validated parameters.
	 */
	public function execute( array $params ): array {
		$content_id = absint( $params['content_id'] ?? 0 );

		if ( ! $content_id ) {
			return $this->fail( __( 'Content ID is required.', 'suredash' ) );
		}

		// Target guard — agents mix up space/post/content IDs, so verify the
		// target is an actual sub-content item before any field is written.
		$target_error = $this->get_post_target_error( $content_id, SUREDASHBOARD_SUB_CONTENT_POST_TYPE, __( 'Content', 'suredash' ) );
		if ( $target_error !== null ) {
			return $target_error;
		}

		$content_type = (string) sd_get_post_meta( $content_id, 'content_type', true );

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

		// Field/type match — the router saves any meta it is handed, so sending
		// event fields to a lesson would silently attach junk meta. Fail loudly
		// instead. post_title/post_status are universal and stay unchecked.
		$field_type_map = [
			'event'    => [ 'event_date', 'event_end_date', 'event_start_time', 'event_end_time', 'event_timezone', 'event_host', 'rsvp_link', 'event_joining_link', 'recorded_video_link', 'event_location_type', 'event_location_address' ],
			'quiz'     => [ 'quiz_has_passing', 'quiz_pass_mark', 'quiz_enforce_pass', 'quiz_hide_answers_on_result', 'quiz_questions' ],
			'resource' => [ 'resource_type', 'attachment_id', 'external_url' ],
			'lesson'   => [ 'lesson_duration' ],
		];

		// Content items created before content_type was recorded, and collection
		// items which never carry one, have nothing to match against. Skipping
		// the check there keeps legacy content editable instead of locking it.
		$mismatched_fields = [];
		if ( isset( $field_type_map[ $content_type ] ) ) {
			foreach ( $field_type_map as $type => $fields ) {
				if ( $type === $content_type ) {
					continue;
				}
				foreach ( $fields as $field ) {
					if ( isset( $post_data[ $field ] ) ) {
						$mismatched_fields[] = sprintf(
							/* translators: 1: field name, 2: content type the field belongs to. */
							__( '%1$s (only valid for "%2$s" content)', 'suredash' ),
							$field,
							$type
						);
					}
				}
			}
		}

		if ( ! empty( $mismatched_fields ) ) {
			return $this->fail(
				sprintf(
					/* translators: 1: content ID, 2: content type of the target. */
					__( 'Nothing saved: the request contains fields that do not apply to content ID %1$d, which has content type "%2$s". Remove the fields below or target the correct content item.', 'suredash' ),
					$content_id,
					$content_type
				),
				$mismatched_fields
			);
		}

		// Event cross-field rules: reject malformed dates/times and an end
		// datetime before the start (using stored meta for whichever side the
		// request does not provide).
		if ( $content_type === 'event' ) {
			$event_error = $this->get_event_schedule_error( $content_id, $post_data );
			if ( $event_error !== null ) {
				return $event_error;
			}
		}

		// Quiz cross-field rule: enforcing a pass mark that does not exist is a
		// contradiction the router would accept silently. The 0-100 range is
		// enforced by the schema's minimum/maximum.
		if ( isset( $post_data['quiz_enforce_pass'] ) && filter_var( $post_data['quiz_enforce_pass'], FILTER_VALIDATE_BOOLEAN ) ) {
			$effective_has_passing = isset( $params['quiz_has_passing'] )
				? filter_var( $params['quiz_has_passing'], FILTER_VALIDATE_BOOLEAN )
				: filter_var( get_post_meta( $content_id, 'quiz_has_passing', true ), FILTER_VALIDATE_BOOLEAN );

			if ( ! $effective_has_passing ) {
				return $this->fail( __( 'Nothing saved: quiz_enforce_pass = true contradicts quiz_has_passing = false. There is no pass mark to enforce. Set quiz_has_passing to true in the same request, or drop quiz_enforce_pass.', 'suredash' ) );
			}
		}

		// The schema now guarantees every option states is_correct; what it
		// cannot express is the answer key itself, so check that here: at least
		// one correct option per question, exactly one for "single" questions.
		if ( isset( $post_data['quiz_questions'] ) && is_array( $post_data['quiz_questions'] ) ) {
			$answer_problems = $this->get_quiz_answer_errors( $post_data['quiz_questions'] );
			if ( ! empty( $answer_problems ) ) {
				return $this->fail(
					__( 'Quiz not saved: every quiz question needs a correct answer. Fix the questions below and resend the full quiz_questions list.', 'suredash' ),
					$answer_problems
				);
			}
		}

		// save_content_meta_fields() expects quiz_questions as either an array or a JSON
		// string. When invoked through the Abilities API the value already arrives as a
		// PHP array; setup_post_data() flattens scalars, so re-encode arrays as JSON to
		// preserve nesting through $_POST.
		if ( isset( $post_data['quiz_questions'] ) && is_array( $post_data['quiz_questions'] ) ) {
			$post_data['quiz_questions'] = $this->encode_json_for_router( $post_data['quiz_questions'] );
		}

		$this->setup_post_data( $post_data );

		$request = $this->build_request();
		$result  = BackendRoute::get_instance()->update_content_settings( $request );

		$this->cleanup_post_data( array_keys( $post_data ) );

		if ( ! is_array( $result ) ) {
			return $this->fail( __( 'Unexpected response.', 'suredash' ) );
		}

		if ( empty( $result['success'] ) ) {
			return $result;
		}

		// Echo what was forwarded so agents can self-verify the save.
		$result['updated_fields'] = array_values( array_diff( array_keys( $post_data ), [ 'content_id' ] ) );

		// Echo the answer key back, read from storage rather than from the
		// request, so the summary reflects what was actually saved.
		if ( isset( $params['quiz_questions'] ) ) {
			$stored_questions                 = sd_get_post_meta( $content_id, 'quiz_questions', true );
			$result['quiz_questions_summary'] = $this->summarize_quiz_questions( is_array( $stored_questions ) ? $stored_questions : [] );
		}

		return $result;
	}

	/**
	 * Check the event start/end ordering, request values winning over stored ones.
	 *
	 * Date and time formats are already enforced by the schema; what is left is
	 * the comparison, and it has to resolve whichever side the request omits
	 * from stored meta so a request that only moves the end date still cannot
	 * place it before the stored start.
	 *
	 * @since 1.10.2
	 *
	 * @param int                  $content_id Event content post ID.
	 * @param array<string, mixed> $post_data  Fields about to be forwarded.
	 * @return array<string, mixed>|null Hard-fail response array, or null when the schedule is valid.
	 */
	private function get_event_schedule_error( int $content_id, array $post_data ): ?array {
		$schedule_fields = [ 'event_date', 'event_end_date', 'event_start_time', 'event_end_time' ];

		if ( empty( array_intersect( $schedule_fields, array_keys( $post_data ) ) ) ) {
			return null;
		}

		$resolved = [];
		foreach ( $schedule_fields as $field ) {
			$resolved[ $field ] = isset( $post_data[ $field ] )
				? (string) $post_data[ $field ]
				: (string) sd_get_post_meta( $content_id, $field, true );
		}

		$order_error = $this->get_schedule_order_error(
			$resolved['event_date'],
			$resolved['event_start_time'],
			$resolved['event_end_date'],
			$resolved['event_end_time']
		);

		if ( $order_error === null ) {
			return null;
		}

		/* translators: %s: description of the scheduling problem. */
		return $this->fail( sprintf( __( 'Nothing saved: %s', 'suredash' ), $order_error ) );
	}
}
