<?php
/**
 * Get Dashboard Stats Ability.
 *
 * @package SureDash
 * @since 1.6.3
 */

namespace SureDashboard\Inc\Services\Abilities\Handlers;

use SureDashboard\Core\Routers\Backend as BackendRoute;
use SureDashboard\Inc\Services\Abilities\Ability;

defined( 'ABSPATH' ) || exit;

/**
 * Get_Dashboard_Stats class.
 *
 * @since 1.6.3
 */
class Get_Dashboard_Stats extends Ability {
	/**
	 * Get the unique identifier for this ability.
	 *
	 * @since 1.6.3
	 *
	 * @return string
	 */
	public function get_id(): string {
		return 'get-dashboard-stats';
	}

	/**
	 * Get the human-readable name.
	 *
	 * @since 1.6.3
	 *
	 * @return string
	 */
	public function get_name(): string {
		return __( 'Get Dashboard Stats', 'suredash' );
	}

	/**
	 * Get the ability description.
	 *
	 * @since 1.6.3
	 *
	 * @return string
	 */
	public function get_description(): string {
		return __( 'Retrieves dashboard overview statistics including member growth charts, post activity, comment trends, top contributors, and engagement overview.', 'suredash' );
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
		return [];
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_returns(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'dashboard-data'      => [
					'type'        => 'object',
					'description' => __( 'Member growth chart data.', 'suredash' ),
				],
				'posts-chart-data'    => [
					'type'        => 'object',
					'description' => __( 'Post activity chart data.', 'suredash' ),
				],
				'comments-chart-data' => [
					'type'        => 'object',
					'description' => __( 'Comment trends chart data.', 'suredash' ),
				],
				'top_contributors'    => [
					'type'        => 'array',
					'description' => __( 'Top contributing users.', 'suredash' ),
				],
				'engagement_overview' => [
					'type'        => 'object',
					'description' => __( 'Overall engagement metrics.', 'suredash' ),
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
		return 'Returns community health overview with charts and engagement data. Use get-member-stats for detailed signup trends over a custom date range.';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param array<string, mixed> $params Validated parameters.
	 */
	public function execute( array $params ): array {
		$request = $this->build_request();
		return $this->call_json_handler(
			[ BackendRoute::get_instance(), 'get_dashboard_data' ],
			$request
		);
	}
}
