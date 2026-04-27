<?php
/**
 * Get Member Stats Ability.
 *
 * @package SureDash
 * @since 1.6.3
 */

namespace SureDashboard\Inc\Services\Abilities\Handlers;

use SureDashboard\Core\Routers\Backend as BackendRoute;
use SureDashboard\Inc\Services\Abilities\Ability;

defined( 'ABSPATH' ) || exit;

/**
 * Get_Member_Stats class.
 *
 * @since 1.6.3
 */
class Get_Member_Stats extends Ability {
	/**
	 * Get the unique identifier for this ability.
	 *
	 * @since 1.6.3
	 *
	 * @return string
	 */
	public function get_id(): string {
		return 'get-member-stats';
	}

	/**
	 * Get the human-readable name.
	 *
	 * @since 1.6.3
	 *
	 * @return string
	 */
	public function get_name(): string {
		return __( 'Get Member Stats', 'suredash' );
	}

	/**
	 * Get the ability description.
	 *
	 * @since 1.6.3
	 *
	 * @return string
	 */
	public function get_description(): string {
		return __( 'Retrieves member engagement analytics for a date range. Returns signup trends and member activity data.', 'suredash' );
	}

	/**
	 * Get the ability category.
	 *
	 * @since 1.6.3
	 *
	 * @return string
	 */
	public function get_category(): string {
		return 'analytics';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_parameters(): array {
		return [
			'start_date' => [
				'type'        => 'string',
				'required'    => false,
				'description' => __( 'Start date for the analytics range (Y-m-d format). Defaults to 30 days ago.', 'suredash' ),
			],
			'end_date'   => [
				'type'        => 'string',
				'required'    => false,
				'description' => __( 'End date for the analytics range (Y-m-d format). Defaults to today.', 'suredash' ),
			],
		];
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_returns(): array {
		return [
			'type'        => 'object',
			'description' => __( 'Member statistics and chart data for the given date range.', 'suredash' ),
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
		return 'Signup trends for a custom date range. Defaults to last 30 days. Use get-dashboard-stats for a broader community overview.';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param array<string, mixed> $params Validated parameters.
	 */
	public function execute( array $params ): array {
		$request = $this->build_request();

		if ( ! empty( $params['start_date'] ) ) {
			$request->set_param( 'start_date', sanitize_text_field( $params['start_date'] ) );
		}

		if ( ! empty( $params['end_date'] ) ) {
			$request->set_param( 'end_date', sanitize_text_field( $params['end_date'] ) );
		}

		return $this->call_json_handler(
			[ BackendRoute::get_instance(), 'get_member_stats' ],
			$request
		);
	}
}
