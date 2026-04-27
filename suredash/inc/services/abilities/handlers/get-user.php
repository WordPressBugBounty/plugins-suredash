<?php
/**
 * Get User Ability.
 *
 * @package SureDash
 * @since 1.6.3
 */

namespace SureDashboard\Inc\Services\Abilities\Handlers;

use SureDashboard\Core\Routers\Backend as BackendRoute;
use SureDashboard\Inc\Services\Abilities\Ability;

defined( 'ABSPATH' ) || exit;

/**
 * Get_User class.
 *
 * @since 1.6.3
 */
class Get_User extends Ability {
	/**
	 * Get the unique identifier for this ability.
	 *
	 * @since 1.6.3
	 *
	 * @return string
	 */
	public function get_id(): string {
		return 'get-user';
	}

	/**
	 * Get the human-readable name.
	 *
	 * @since 1.6.3
	 *
	 * @return string
	 */
	public function get_name(): string {
		return __( 'Get User Details', 'suredash' );
	}

	/**
	 * Get the ability description.
	 *
	 * @since 1.6.3
	 *
	 * @return string
	 */
	public function get_description(): string {
		return __( 'Retrieves detailed information about a specific portal user including profile data, post count, comment count, badges, and activity.', 'suredash' );
	}

	/**
	 * Get the ability category.
	 *
	 * @since 1.6.3
	 *
	 * @return string
	 */
	public function get_category(): string {
		return 'users';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_parameters(): array {
		return [
			'user_id' => [
				'type'        => 'integer',
				'required'    => true,
				'description' => __( 'The WordPress user ID.', 'suredash' ),
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
				'id'              => [
					'type'        => 'integer',
					'description' => __( 'User ID.', 'suredash' ),
				],
				'name'            => [
					'type'        => 'string',
					'description' => __( 'Display name.', 'suredash' ),
				],
				'email'           => [
					'type'        => 'string',
					'description' => __( 'Email address.', 'suredash' ),
				],
				'posts_count'     => [
					'type'        => 'integer',
					'description' => __( 'Number of community posts.', 'suredash' ),
				],
				'comments_count'  => [
					'type'        => 'integer',
					'description' => __( 'Number of comments.', 'suredash' ),
				],
				'user_registered' => [
					'type'        => 'string',
					'description' => __( 'Registration date.', 'suredash' ),
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
		return 'Returns full user profile with badges and activity counts. Use list-users to find user IDs.';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param array<string, mixed> $params Validated parameters.
	 */
	public function execute( array $params ): array {
		$request = $this->build_request( [], 'GET' );
		$request->set_param( 'id', absint( $params['user_id'] ) );
		$request->set_url_params( [ 'id' => absint( $params['user_id'] ) ] );

		// get_user returns WP_REST_Response directly.
		$response = BackendRoute::get_instance()->get_user( $request );

		if ( $response instanceof \WP_REST_Response ) {
			$data = $response->get_data();
		} else {
			$data = [
				'success' => true,
				'data'    => $response,
			];
		}

		$user_data = $data['data'] ?? $data;
		unset( $user_data['avatar_markup'], $user_data['banner_placeholder'] );

		return [
			'success' => $data['success'] ?? true,
			'data'    => $user_data,
		];
	}
}
